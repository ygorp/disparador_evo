<?php
// === CONFIGURAÇÕES DO ASAAS ===
// IMPORTANTE: Configure suas credenciais reais do ASAAS

// Ambiente de desenvolvimento (sandbox)
define('ASAAS_API_KEY_SANDBOX', '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjliYTVhMDM3LWNmODYtNDg1Ny1hMTU4LTc0ZjhiNzk1YjE4YTo6JGFhY2hfMTAzZmM5NTYtMzJjYi00YzQ1LWJkY2EtYzNkYzY4MTUwODAx');
define('ASAAS_BASE_URL_SANDBOX', 'https://sandbox.asaas.com/api/v3');

// Ambiente de produção - CONFIGURE SUA API KEY REAL AQUI
define('ASAAS_API_KEY_PRODUCTION', '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OmE0ZWExMzZiLTJhZTAtNDg0Yi05ZGFmLThiZWQxMzRhN2ViMzo6JGFhY2hfZDI4NWQ2NjItMmI2MS00ZjRhLWJhM2QtYWVjYmQ0ZDgzZGQ4');
define('ASAAS_BASE_URL_PRODUCTION', 'https://www.asaas.com/api/v3');

// Configuração atual (altere para 'production' quando for para produção)
// IMPORTANTE: Altere para 'production' antes de executar setup_asaas_production.php
define('ASAAS_ENVIRONMENT', 'production'); // 'sandbox' ou 'production'

// URLs dinâmicas baseadas no ambiente
if (ASAAS_ENVIRONMENT === 'production') {
    define('ASAAS_API_KEY', ASAAS_API_KEY_PRODUCTION);
    define('ASAAS_BASE_URL', ASAAS_BASE_URL_PRODUCTION);
} else {
    define('ASAAS_API_KEY', ASAAS_API_KEY_SANDBOX);
    define('ASAAS_BASE_URL', ASAAS_BASE_URL_SANDBOX);
}

// Webhook URLs
define('ASAAS_WEBHOOK_URL', BASE_URL . 'src/webhooks/asaas_webhook.php');

// Configurações de cobrança
define('ASAAS_WEBHOOK_TOKEN', 'webhook_token_secreto_' . hash('sha256', BASE_URL . 'disparador2025')); // Token único para validar webhooks
define('ASAAS_DUE_DATE_DAYS', 7); // Vencimento padrão em dias
define('ASAAS_FINE_PERCENTAGE', 2); // Multa por atraso (%)
define('ASAAS_INTEREST_PERCENTAGE', 1); // Juros por mês de atraso (%)

// Configurações do cliente
define('ASAAS_NOTIFICATION_DISABLED', false); // Notificações por email/SMS
define('ASAAS_POSTAL_SERVICE', false); // Envio de boleto por correio

// Configurações específicas do webhook
define('ASAAS_WEBHOOK_SEND_TYPE', 'SEQUENTIALLY'); // Tipo de envio: SEQUENTIALLY ou NON_SEQUENTIALLY
define('ASAAS_WEBHOOK_API_VERSION', 3); // Versão da API

// === CLASSE PARA INTEGRAÇÃO COM ASAAS - VERSÃO MELHORADA ===
class AsaasAPI {
    private $apiKey;
    private $baseUrl;
    private $environment;
    
    public function __construct() {
        $this->apiKey = ASAAS_API_KEY;
        $this->baseUrl = ASAAS_BASE_URL;
        $this->environment = ASAAS_ENVIRONMENT;
    }
    
    /**
     * Executa uma requisição para a API do ASAAS com retry automático
     */
    private function makeRequest($method, $endpoint, $data = null, $retries = 3) {
        $url = $this->baseUrl . $endpoint;
        
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            // ASAAS usa 'access_token' no header
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'access_token: ' . $this->apiKey,
                'User-Agent: Discador.net/' . ($this->environment === 'production' ? 'Production' : 'Sandbox')
            ]);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            } elseif ($method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }
            
            // Configurações SSL para produção
            if ($this->environment === 'production') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Log para debug
            error_log("ASAAS API [$this->environment] Attempt $attempt: $method $url - HTTP $httpCode");
            
            if ($error && $attempt < $retries) {
                error_log("ASAAS API Error (attempt $attempt): $error - Retrying...");
                sleep(1); // Espera 1 segundo antes de tentar novamente
                continue;
            }
            
            if ($error) {
                error_log("ASAAS API Final Error: $error");
            }
            
            $decodedResponse = json_decode($response, true);
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'data' => $decodedResponse,
                'raw_response' => $response,
                'error' => $error,
                'attempt' => $attempt
            ];
        }
    }
    
    /**
     * Método público para fazer requisições
     */
    public function makeRequestPublic($method, $endpoint, $data = null) {
        return $this->makeRequest($method, $endpoint, $data);
    }
    
    /**
     * Cria ou atualiza um cliente no ASAAS
     */
    public function createOrUpdateCustomer($customerData) {
        // Verifica se já existe cliente com este email
        $existing = $this->getCustomerByEmail($customerData['email']);
        
        if ($existing['success'] && !empty($existing['data']['data'])) {
            // Cliente já existe, atualiza
            $customerId = $existing['data']['data'][0]['id'];
            return $this->makeRequest('PUT', "/customers/{$customerId}", $customerData);
        } else {
            // Cliente não existe, cria novo
            return $this->makeRequest('POST', '/customers', $customerData);
        }
    }
    
    /**
     * Busca cliente por email
     */
    public function getCustomerByEmail($email) {
        return $this->makeRequest('GET', "/customers?email=" . urlencode($email));
    }
    
    /**
     * Cria uma cobrança no ASAAS
     */
    public function createPayment($paymentData) {
        return $this->makeRequest('POST', '/payments', $paymentData);
    }
    
    /**
     * Busca uma cobrança específica
     */
    public function getPayment($paymentId) {
        return $this->makeRequest('GET', "/payments/{$paymentId}");
    }
    
    /**
     * Lista cobranças com filtros
     */
    public function listPayments($filters = []) {
        $queryString = http_build_query($filters);
        $endpoint = '/payments' . ($queryString ? '?' . $queryString : '');
        return $this->makeRequest('GET', $endpoint);
    }
    
    /**
     * Cancela uma cobrança
     */
    public function cancelPayment($paymentId) {
        return $this->makeRequest('DELETE', "/payments/{$paymentId}");
    }
    
    /**
     * Gera link de pagamento PIX
     */
    public function getPixQrCode($paymentId) {
        return $this->makeRequest('GET', "/payments/{$paymentId}/pixQrCode");
    }
    
    /**
     * Webhook - processa notificação do ASAAS
     */
    public static function processWebhook($webhookData) {
        global $pdo;
        
        // Log do webhook recebido
        error_log("ASAAS Webhook [" . ASAAS_ENVIRONMENT . "] recebido: " . json_encode($webhookData));
        
        $event = $webhookData['event'] ?? '';
        $payment = $webhookData['payment'] ?? [];
        
        if (empty($payment['id'])) {
            return ['success' => false, 'message' => 'Payment ID não encontrado'];
        }
        
        // Processa diferentes tipos de evento
        switch ($event) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                return self::handlePaymentConfirmed($payment);
            
            case 'PAYMENT_OVERDUE':
                return self::handlePaymentOverdue($payment);
            
            case 'PAYMENT_DELETED':
                return self::handlePaymentDeleted($payment);
            
            case 'PAYMENT_CREATED':
                // Log apenas, não precisa processar
                error_log("Pagamento criado: " . $payment['id']);
                return ['success' => true, 'message' => 'Payment created logged'];
            
            default:
                error_log("Evento não tratado: " . $event);
                return ['success' => true, 'message' => 'Evento ignorado'];
        }
    }
    
    /**
     * Processa pagamento confirmado
     */
    private static function handlePaymentConfirmed($payment) {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Busca a transação pelo payment ID
            $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE asaas_payment_id = ?");
            $stmt->execute([$payment['id']]);
            $transacao = $stmt->fetch();
            
            if (!$transacao) {
                // Tenta buscar por external_reference como fallback
                $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE external_reference = ?");
                $stmt->execute([$payment['externalReference'] ?? '']);
                $transacao = $stmt->fetch();
            }
            
            if (!$transacao) {
                throw new Exception("Transação não encontrada para payment ID: " . $payment['id']);
            }
            
            // Verifica se já foi processado para evitar duplicação
            if ($transacao['status_pagamento'] === 'Pago') {
                error_log("Pagamento já processado anteriormente: " . $payment['id']);
                return ['success' => true, 'message' => 'Pagamento já processado'];
            }
            
            // Atualiza status da transação
            $stmt_update = $pdo->prepare("UPDATE transacoes SET status_pagamento = 'Pago', asaas_payment_id = ?, data_pagamento = NOW() WHERE id = ?");
            $stmt_update->execute([$payment['id'], $transacao['id']]);
            
            // Adiciona créditos ao cliente
            if ($transacao['tipo_transacao'] === 'recarga_disparo') {
                $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo + ? WHERE id = ?");
                $stmt_credito->execute([$transacao['creditos_quantidade'], $transacao['cliente_id']]);
            } elseif ($transacao['tipo_transacao'] === 'recarga_maturacao') {
                $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_maturacao = saldo_creditos_maturacao + ? WHERE id = ?");
                $stmt_credito->execute([$transacao['creditos_quantidade'], $transacao['cliente_id']]);
            }
            
            // Salva detalhes do pagamento
            $stmt_details = $pdo->prepare("
                INSERT INTO pagamentos_detalhes (transacao_id, asaas_payment_id, status_anterior, status_atual, valor_pago, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_details->execute([
                $transacao['id'],
                $payment['id'],
                $transacao['status_pagamento'],
                'Pago',
                $payment['value'] ?? $transacao['valor'],
                'Pagamento confirmado via webhook - ' . ASAAS_ENVIRONMENT
            ]);
            
            // Cria notificação para o cliente
            if (defined('BASE_URL')) {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notificacoes (cliente_id, tipo, titulo, mensagem, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt_notif->execute([
                    $transacao['cliente_id'],
                    'payment_confirmed',
                    'Pagamento Confirmado',
                    'Seu pagamento de R$ ' . number_format($transacao['valor'], 2, ',', '.') . ' foi confirmado e os créditos foram adicionados à sua conta.'
                ]);
            }
            
            $pdo->commit();
            
            error_log("Pagamento confirmado com sucesso [" . ASAAS_ENVIRONMENT . "]: " . $payment['id']);
            return ['success' => true, 'message' => 'Pagamento processado'];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao processar pagamento [" . ASAAS_ENVIRONMENT . "]: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Processa pagamento em atraso
     */
    private static function handlePaymentOverdue($payment) {
        global $pdo;
        
        $stmt = $pdo->prepare("UPDATE transacoes SET status_pagamento = 'Vencido' WHERE asaas_payment_id = ?");
        $stmt->execute([$payment['id']]);
        
        error_log("Pagamento vencido [" . ASAAS_ENVIRONMENT . "]: " . $payment['id']);
        return ['success' => true, 'message' => 'Pagamento marcado como vencido'];
    }
    
    /**
     * Processa pagamento cancelado
     */
    private static function handlePaymentDeleted($payment) {
        global $pdo;
        
        $stmt = $pdo->prepare("UPDATE transacoes SET status_pagamento = 'Cancelado' WHERE asaas_payment_id = ?");
        $stmt->execute([$payment['id']]);
        
        error_log("Pagamento cancelado [" . ASAAS_ENVIRONMENT . "]: " . $payment['id']);
        return ['success' => true, 'message' => 'Pagamento cancelado'];
    }
    
    /**
     * Verifica se o ambiente está em produção
     */
    public function isProduction() {
        return $this->environment === 'production';
    }
    
    /**
     * Retorna informações do ambiente
     */
    public function getEnvironmentInfo() {
        return [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'webhook_url' => ASAAS_WEBHOOK_URL
        ];
    }
}
?>