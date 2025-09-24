<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/asaas_config.php';

header('Content-Type: application/json');

// Validação de segurança
if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transacao_id = $input['transacao_id'] ?? null;
$cliente_id = $_SESSION['user_id'];

if (!$transacao_id) {
    echo json_encode(['success' => false, 'message' => 'ID da transação não fornecido']);
    exit;
}

try {
    // Busca a transação
    $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE id = ? AND cliente_id = ?");
    $stmt->execute([$transacao_id, $cliente_id]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        echo json_encode(['success' => false, 'message' => 'Transação não encontrada']);
        exit;
    }
    
    if (!$transacao['asaas_payment_id']) {
        echo json_encode(['success' => false, 'message' => 'Payment ID não encontrado']);
        exit;
    }
    
    // Consulta status no ASAAS
    $asaas = new AsaasAPI();
    $result = $asaas->getPayment($transacao['asaas_payment_id']);
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar ASAAS']);
        exit;
    }
    
    $paymentData = $result['data'];
    $currentStatus = $paymentData['status'];
    
    // Se o status mudou, atualiza no banco
    if ($currentStatus !== $transacao['status_pagamento']) {
        $pdo->beginTransaction();
        
        try {
            // Atualiza status da transação
            $stmt_update = $pdo->prepare("UPDATE transacoes SET status_pagamento = ? WHERE id = ?");
            $stmt_update->execute([$currentStatus, $transacao_id]);
            
            // Se foi confirmado, adiciona créditos
            if (in_array($currentStatus, ['RECEIVED', 'CONFIRMED']) && 
                !in_array($transacao['status_pagamento'], ['RECEIVED', 'CONFIRMED'])) {
                
                if ($transacao['tipo_transacao'] === 'recarga_disparo') {
                    $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo + ? WHERE id = ?");
                    $stmt_credito->execute([$transacao['creditos_quantidade'], $cliente_id]);
                } elseif ($transacao['tipo_transacao'] === 'recarga_maturacao') {
                    $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_maturacao = saldo_creditos_maturacao + ? WHERE id = ?");
                    $stmt_credito->execute([$transacao['creditos_quantidade'], $cliente_id]);
                }
                
                // Marca data do pagamento
                $stmt_data = $pdo->prepare("UPDATE transacoes SET data_pagamento = NOW() WHERE id = ?");
                $stmt_data->execute([$transacao_id]);
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    echo json_encode([
        'success' => true,
        'status' => $currentStatus,
        'updated' => $currentStatus !== $transacao['status_pagamento']
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao verificar status do pagamento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>