<?php
/**
 * Webhook ASAAS para Produção
 * Versão robusta com validações de segurança e logs detalhados
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/asaas_config.php';

// Configurar headers de resposta
header('Content-Type: application/json; charset=utf-8');

// Log de entrada com timestamp detalhado
$timestamp = date('Y-m-d H:i:s');
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

error_log("=== ASAAS WEBHOOK [" . ASAAS_ENVIRONMENT . "] RECEBIDO [$timestamp] ===");
error_log("IP: $remote_ip | User-Agent: $user_agent");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

// 1. VALIDAÇÕES DE SEGURANÇA

// Apenas aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("WEBHOOK ERROR: Method not allowed - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Verifica se é um teste de conectividade
if (isset($_GET['test'])) {
    error_log("WEBHOOK TEST: Conectividade verificada");
    http_response_code(200);
    echo json_encode(['status' => 'webhook_accessible', 'environment' => ASAAS_ENVIRONMENT, 'timestamp' => $timestamp]);
    exit;
}

// Lê o corpo da requisição
$input = file_get_contents('php://input');

if (empty($input)) {
    error_log("WEBHOOK ERROR: Corpo da requisição vazio");
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

error_log("WEBHOOK BODY: " . $input);

// Decodifica o JSON
$webhookData = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("WEBHOOK ERROR: JSON inválido - " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// 2. VALIDAÇÃO DE TOKEN (se configurado)
$receivedToken = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Em produção, sempre validar o token
if (ASAAS_ENVIRONMENT === 'production' && defined('ASAAS_WEBHOOK_TOKEN') && ASAAS_WEBHOOK_TOKEN) {
    if (empty($receivedToken)) {
        error_log("WEBHOOK ERROR: Token não fornecido em produção");
        http_response_code(401);
        echo json_encode(['error' => 'Token required']);
        exit;
    }
    
    // Remove prefixo Bearer se existir
    $receivedToken = str_replace('Bearer ', '', $receivedToken);
    
    if (!hash_equals(ASAAS_WEBHOOK_TOKEN, $receivedToken)) {
        error_log("WEBHOOK ERROR: Token inválido - Recebido: " . substr($receivedToken, 0, 10) . "...");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    error_log("WEBHOOK SUCCESS: Token validado");
}

// 3. VALIDAÇÃO DE IP (opcional - descomente se quiser restringir IPs)
/*
$allowed_ips = [
    '18.229.220.146',  // IP do ASAAS (exemplo)
    '18.231.194.64',   // IP do ASAAS (exemplo)
    // Adicione outros IPs conhecidos do ASAAS
];

if (!in_array($remote_ip, $allowed_ips) && ASAAS_ENVIRONMENT === 'production') {
    error_log("WEBHOOK ERROR: IP não autorizado - $remote_ip");
    http_response_code(403);
    echo json_encode(['error' => 'IP not allowed']);
    exit;
}
*/

// 4. RATE LIMITING BÁSICO (previne spam)
$rate_limit_file = sys_get_temp_dir() . '/asaas_webhook_' . md5($remote_ip);
$current_time = time();

if (file_exists($rate_limit_file)) {
    $last_request = (int)file_get_contents($rate_limit_file);
    if (($current_time - $last_request) < 1) { // Máximo 1 request por segundo por IP
        error_log("WEBHOOK ERROR: Rate limit excedido - $remote_ip");
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
}

file_put_contents($rate_limit_file, $current_time);

// 5. VALIDAÇÃO DOS DADOS DO WEBHOOK
$required_fields = ['event'];
foreach ($required_fields as $field) {
    if (!isset($webhookData[$field])) {
        error_log("WEBHOOK ERROR: Campo obrigatório ausente - $field");
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// 6. REGISTRA O WEBHOOK NO BANCO (para auditoria)
try {
    $stmt = $pdo->prepare("
        INSERT INTO webhook_logs (provider, event_type, payload, processed_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([
        'asaas',
        $webhookData['event'] ?? 'unknown',
        $input
    ]);
    
    $webhook_log_id = $pdo->lastInsertId();
    error_log("WEBHOOK LOG: Salvo com ID $webhook_log_id");
    
} catch (Exception $e) {
    error_log("WEBHOOK WARNING: Erro ao salvar log - " . $e->getMessage());
    // Não falha o webhook por causa do log
}

// 7. PROCESSA O WEBHOOK
try {
    error_log("WEBHOOK PROCESSING: Iniciando processamento do evento " . $webhookData['event']);
    
    $result = AsaasAPI::processWebhook($webhookData);
    
    if ($result['success']) {
        error_log("WEBHOOK SUCCESS: " . $result['message']);
        
        // Resposta de sucesso
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => $result['message'],
            'environment' => ASAAS_ENVIRONMENT,
            'timestamp' => $timestamp,
            'log_id' => $webhook_log_id ?? null
        ]);
        
    } else {
        error_log("WEBHOOK ERROR: Falha no processamento - " . $result['message']);
        
        // Resposta de erro (mas HTTP 200 para não reenviar)
        http_response_code(200);
        echo json_encode([
            'status' => 'error',
            'message' => $result['message'],
            'environment' => ASAAS_ENVIRONMENT,
            'timestamp' => $timestamp
        ]);
    }
    
} catch (Exception $e) {
    error_log("WEBHOOK CRITICAL ERROR: " . $e->getMessage());
    error_log("WEBHOOK STACK TRACE: " . $e->getTraceAsString());
    
    // Em caso de erro crítico, retorna 500 para o ASAAS tentar novamente
    http_response_code(500);
    echo json_encode([
        'status' => 'critical_error',
        'message' => 'Internal server error',
        'environment' => ASAAS_ENVIRONMENT,
        'timestamp' => $timestamp
    ]);
}

// 8. LIMPEZA
// Remove arquivos de rate limiting antigos (mais de 1 hora)
$temp_dir = sys_get_temp_dir();
$files = glob($temp_dir . '/asaas_webhook_*');
foreach ($files as $file) {
    if (filemtime($file) < (time() - 3600)) {
        unlink($file);
    }
}

error_log("=== ASAAS WEBHOOK FINALIZADO [$timestamp] ===");
?>