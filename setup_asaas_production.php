<?php
/**
 * Script de configuração do ASAAS para PRODUÇÃO
 * Execute este arquivo para configurar o ambiente de produção
 * 
 * IMPORTANTE: Execute apenas após ter:
 * 1. Sua API Key de produção do ASAAS
 * 2. Um domínio real configurado (não localhost/ngrok)
 * 3. SSL válido no seu domínio
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/asaas_config.php';

echo "=== CONFIGURAÇÃO DO ASAAS PARA PRODUÇÃO ===\n\n";

// Verificar se está em produção
if (ASAAS_ENVIRONMENT !== 'production') {
    echo "❌ ERRO: O ambiente não está configurado para produção!\n";
    echo "Altere ASAAS_ENVIRONMENT para 'production' em config/asaas_config.php\n\n";
    exit(1);
}

// Verificar se a API Key de produção foi configurada
if (ASAAS_API_KEY_PRODUCTION === 'SUA_CHAVE_DE_PRODUCAO_AQUI') {
    echo "❌ ERRO: API Key de produção não configurada!\n";
    echo "Configure ASAAS_API_KEY_PRODUCTION em config/asaas_config.php\n\n";
    exit(1);
}

// Verificar se a URL base está usando HTTPS
if (!str_starts_with(BASE_URL, 'https://')) {
    echo "❌ ERRO: URL base deve usar HTTPS em produção!\n";
    echo "URL atual: " . BASE_URL . "\n";
    echo "Configure BASE_URL com https:// em config/config.php\n\n";
    exit(1);
}

echo "✅ Verificações iniciais passou!\n";
echo "🔧 Iniciando configuração...\n\n";

$asaas = new AsaasAPI();

// 1. Testar conexão com a API de produção
echo "1. Testando conexão com API de produção...\n";
$accountResult = $asaas->makeRequestPublic('GET', '/myAccount');

if (!$accountResult['success']) {
    echo "❌ Erro ao conectar com ASAAS produção:\n";
    echo "Código HTTP: " . $accountResult['http_code'] . "\n";
    echo "Resposta: " . json_encode($accountResult['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    if ($accountResult['http_code'] == 401) {
        echo "🔑 ERRO DE AUTENTICAÇÃO:\n";
        echo "Verifique se sua API Key de produção está correta\n";
    }
    exit(1);
}

echo "✅ Conectado ao ASAAS produção com sucesso!\n";
$account = $accountResult['data'];
echo "📋 Conta: " . $account['name'] . " (" . $account['email'] . ")\n\n";

// 2. Configurar webhook de produção
echo "2. Configurando webhook de produção...\n";

$webhookUrl = ASAAS_WEBHOOK_URL;
echo "🌐 URL do Webhook: $webhookUrl\n";

// Verificar se a URL do webhook é acessível
echo "🔍 Testando acessibilidade do webhook...\n";
$webhookTest = @file_get_contents($webhookUrl . '?test=1');
if ($webhookTest === false) {
    echo "⚠️  AVISO: Não foi possível acessar a URL do webhook.\n";
    echo "Certifique-se de que $webhookUrl está acessível publicamente.\n\n";
} else {
    echo "✅ Webhook acessível!\n\n";
}

// Remover webhooks antigos
echo "3. Limpando webhooks existentes...\n";
$result = $asaas->makeRequestPublic('GET', '/webhooks');

if ($result['success']) {
    $existingWebhooks = $result['data']['data'] ?? [];
    echo "📋 Webhooks existentes: " . count($existingWebhooks) . "\n";
    
    foreach ($existingWebhooks as $webhook) {
        echo "🗑️  Removendo: " . $webhook['name'] . "\n";
        $deleteResult = $asaas->makeRequestPublic('DELETE', '/webhooks/' . $webhook['id']);
        if (!$deleteResult['success']) {
            echo "⚠️  Aviso: Não foi possível remover webhook " . $webhook['id'] . "\n";
        }
    }
}

// Criar novo webhook de produção
echo "\n4. Criando webhook de produção...\n";

$webhookData = [
    'name' => 'Discador.net Production Webhook',
    'url' => $webhookUrl,
    'enabled' => true,
    'sendType' => ASAAS_WEBHOOK_SEND_TYPE, // SEQUENTIALLY ou NON_SEQUENTIALLY
    'apiVersion' => ASAAS_WEBHOOK_API_VERSION, // Versão da API
    'events' => [
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED', 
        'PAYMENT_OVERDUE',
        'PAYMENT_DELETED',
        'PAYMENT_CREATED'
    ],
    'authToken' => ASAAS_WEBHOOK_TOKEN
];

$createResult = $asaas->makeRequestPublic('POST', '/webhooks', $webhookData);

if ($createResult['success']) {
    echo "✅ Webhook de produção configurado com sucesso!\n";
    echo "🆔 ID do Webhook: " . $createResult['data']['id'] . "\n";
    
    // Salvar configuração no banco
    try {
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes_sistema (chave, valor, descricao) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()
        ");
        
        $stmt->execute([
            'asaas_webhook_configured', 
            '1', 
            'Webhook de produção configurado',
            '1'
        ]);
        
        $stmt->execute([
            'asaas_webhook_id', 
            $createResult['data']['id'], 
            'ID do webhook de produção',
            $createResult['data']['id']
        ]);
        
        $stmt->execute([
            'asaas_environment', 
            'production', 
            'Ambiente ASAAS atual',
            'production'
        ]);
        
        echo "✅ Configurações salvas no banco de dados!\n\n";
        
    } catch (Exception $e) {
        echo "⚠️  Aviso: Erro ao salvar configurações: " . $e->getMessage() . "\n\n";
    }
    
} else {
    echo "❌ Erro ao configurar webhook:\n";
    echo "Código HTTP: " . $createResult['http_code'] . "\n";
    
    if (isset($createResult['data']['errors'])) {
        foreach ($createResult['data']['errors'] as $error) {
            echo "- " . $error['description'] . "\n";
        }
    }
    
    echo "Resposta completa: " . $createResult['raw_response'] . "\n\n";
    exit(1);
}

// 5. Testar webhook
echo "5. Testando webhook...\n";
echo "🧪 Criando cobrança de teste para validar webhook...\n";

// Criar cliente de teste
$testCustomer = [
    'name' => 'Cliente Teste Webhook',
    'email' => 'teste.webhook@' . parse_url(BASE_URL, PHP_URL_HOST),
    'cpfCnpj' => '11144477735', // CPF válido para testes
    'externalReference' => 'webhook_test_' . time()
];

$customerResult = $asaas->createOrUpdateCustomer($testCustomer);

if ($customerResult['success']) {
    $customerId = $customerResult['data']['id'];
    
    // Criar cobrança de teste
    $testPayment = [
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => 0.01, // 1 centavo para teste
        'dueDate' => date('Y-m-d', strtotime('+1 day')),
        'description' => 'Teste de webhook - produção',
        'externalReference' => 'webhook_test_' . time()
    ];
    
    $paymentResult = $asaas->createPayment($testPayment);
    
    if ($paymentResult['success']) {
        echo "✅ Cobrança de teste criada: " . $paymentResult['data']['id'] . "\n";
        echo "💰 Valor: R$ 0,01 via PIX\n";
        echo "📅 Vencimento: " . $paymentResult['data']['dueDate'] . "\n\n";
    } else {
        echo "⚠️  Aviso: Não foi possível criar cobrança de teste\n\n";
    }
} else {
    echo "⚠️  Aviso: Não foi possível criar cliente de teste\n\n";
}

// 6. Verificar configurações de segurança
echo "6. Verificações de segurança...\n";

// Verificar permissões do arquivo de webhook
$webhookFile = __DIR__ . '/src/webhooks/asaas_webhook.php';
if (file_exists($webhookFile)) {
    $perms = fileperms($webhookFile);
    echo "🔒 Permissões do webhook: " . decoct($perms & 0777) . "\n";
    
    if (($perms & 0777) > 0644) {
        echo "⚠️  AVISO: Permissões muito abertas no arquivo webhook\n";
        echo "Recomendação: chmod 644 $webhookFile\n";
    }
} else {
    echo "❌ Arquivo de webhook não encontrado: $webhookFile\n";
}

// Verificar se logs estão habilitados
if (ini_get('log_errors')) {
    echo "✅ Logs de erro habilitados\n";
} else {
    echo "⚠️  AVISO: Logs de erro desabilitados\n";
}

echo "\n=== CONFIGURAÇÃO CONCLUÍDA ===\n\n";

echo "📋 RESUMO:\n";
echo "✅ Ambiente: PRODUÇÃO\n";
echo "✅ API Key: Configurada\n";
echo "✅ Webhook: " . (isset($createResult) && $createResult['success'] ? "Configurado" : "Com problemas") . "\n";
echo "✅ HTTPS: Ativo\n";
echo "✅ Banco: Atualizado\n\n";

echo "🚨 IMPORTANTE - PRÓXIMOS PASSOS:\n";
echo "1. Faça um teste real de compra com valor baixo (ex: R$ 1,00)\n";
echo "2. Monitore os logs para verificar se o webhook está funcionando\n";
echo "3. Verifique se os créditos são adicionados automaticamente após pagamento\n";
echo "4. Configure monitoramento de pagamentos pendentes\n";
echo "5. Implemente notificações por email para pagamentos\n\n";

echo "📊 MONITORAMENTO:\n";
echo "- Logs do sistema: tail -f " . ini_get('error_log') . "\n";
echo "- Webhook logs: SELECT * FROM webhook_logs ORDER BY processed_at DESC LIMIT 10;\n";
echo "- Transações: SELECT * FROM transacoes WHERE status_pagamento = 'Pendente';\n\n";

if (isset($paymentResult) && $paymentResult['success']) {
    echo "🧪 COBRANÇA DE TESTE CRIADA:\n";
    echo "- ID: " . $paymentResult['data']['id'] . "\n";
    echo "- Status: " . $paymentResult['data']['status'] . "\n";
    echo "- Use esta cobrança para testar o webhook\n\n";
}

echo "✨ Sistema ASAAS configurado para produção com sucesso!\n";
echo "🎉 Seus clientes agora podem fazer pagamentos reais via PIX e Boleto\n\n";
?>