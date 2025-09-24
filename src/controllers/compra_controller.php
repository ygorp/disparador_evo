<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/asaas_config.php';

// Validação de segurança
if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    // Busca dados do cliente
    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente = $stmt_cliente->fetch();
    
    if (!$cliente) {
        throw new Exception("Cliente não encontrado");
    }
    
    if ($action === 'buy_credits') {
        // === COMPRA DE CRÉDITOS ===
        
        $plano_id = $_POST['plano_id'];
        $tipo_credito = $_POST['tipo_credito']; // 'disparo' ou 'maturacao'
        $payment_method = $_POST['payment_method']; // 'PIX', 'BOLETO', 'CREDIT_CARD'
        
        // Busca dados do plano
        $stmt_plano = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND tipo = ?");
        $stmt_plano->execute([$plano_id, $tipo_credito]);
        $plano = $stmt_plano->fetch();
        
        if (!$plano) {
            throw new Exception("Plano não encontrado");
        }
        
        // Cria/atualiza cliente no ASAAS
        $asaas = new AsaasAPI();
        
        // Gerar CPF fictício para sandbox (apenas para testes)
        $cpf_fictico = '11144477735'; // CPF válido para sandbox
        
        $customerData = [
            'name' => $cliente['nome'],
            'email' => $cliente['email'],
            'cpfCnpj' => $cliente['cpf'] ?? $cpf_fictico,
            'phone' => $cliente['telefone'] ?? null,
            'externalReference' => 'cliente_' . $cliente_id,
            'notificationDisabled' => ASAAS_NOTIFICATION_DISABLED
        ];
        
        $customerResult = $asaas->createOrUpdateCustomer($customerData);
        
        if (!$customerResult['success']) {
            throw new Exception("Erro ao criar cliente no ASAAS: " . json_encode($customerResult['data']));
        }
        
        $asaasCustomerId = $customerResult['data']['id'];
        
        // Gera external_reference único
        $external_reference = 'creditos_' . $tipo_credito . '_' . $cliente_id . '_' . time();
        
        // Dados da cobrança
        $dueDate = date('Y-m-d', strtotime('+' . ASAAS_DUE_DATE_DAYS . ' days'));
        
        $paymentData = [
            'customer' => $asaasCustomerId,
            'billingType' => $payment_method,
            'value' => (float) $plano['preco'],
            'dueDate' => $dueDate,
            'description' => "Compra de créditos: " . $plano['nome'],
            'externalReference' => $external_reference,
            'fine' => [
                'value' => ASAAS_FINE_PERCENTAGE
            ],
            'interest' => [
                'value' => ASAAS_INTEREST_PERCENTAGE
            ],
            'postalService' => ASAAS_POSTAL_SERVICE
        ];
        
        // Adiciona informações específicas do método de pagamento
        if ($payment_method === 'CREDIT_CARD') {
            // Para cartão de crédito, seria necessário implementar tokenização
            // Por enquanto, vamos focar em PIX e Boleto
            throw new Exception("Pagamento por cartão de crédito será implementado em breve");
        }
        
        $paymentResult = $asaas->createPayment($paymentData);
        
        if (!$paymentResult['success']) {
            throw new Exception("Erro ao criar cobrança no ASAAS: " . json_encode($paymentResult['data']));
        }
        
        $payment = $paymentResult['data'];
        
        // Salva transação no banco
        $stmt_transacao = $pdo->prepare("
            INSERT INTO transacoes (
                cliente_id, plano_id, tipo_transacao, valor, creditos_quantidade,
                status_pagamento, asaas_payment_id, external_reference,
                metodo_pagamento, data_vencimento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $creditos_quantidade = $tipo_credito === 'disparo' ? $plano['creditos_disparo'] : $plano['preco'];
        
        $stmt_transacao->execute([
            $cliente_id,
            $plano_id,
            'recarga_' . $tipo_credito,
            (float) $plano['preco'],
            $creditos_quantidade,
            'Pendente',
            $payment['id'],
            $external_reference,
            $payment_method,
            $dueDate
        ]);
        
        $transacao_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Redireciona para página de pagamento com base no método
        if ($payment_method === 'PIX') {
            $_SESSION['success_message'] = "Cobrança PIX gerada com sucesso!";
            header('Location: ' . BASE_URL . 'views/client/pagamento.php?transacao_id=' . $transacao_id . '&method=pix');
        } else {
            $_SESSION['success_message'] = "Boleto gerado com sucesso!";
            header('Location: ' . BASE_URL . 'views/client/pagamento.php?transacao_id=' . $transacao_id . '&method=boleto');
        }
        exit;
        
    } else {
        throw new Exception("Ação não reconhecida");
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro na compra: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
    exit;
}
?>