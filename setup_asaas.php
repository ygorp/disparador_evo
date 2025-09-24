<?php
/**
 * Script de configuração inicial do ASAAS - VERSÃO CORRIGIDA
 * Execute este arquivo UMA VEZ para configurar os webhooks automaticamente
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/asaas_config.php';

echo "=== CONFIGURAÇÃO INICIAL DO ASAAS ===\n\n";

// Verificar se já foi configurado
try {
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'asaas_webhook_configured'");
    $stmt->execute();
    $webhook_configured = $stmt->fetchColumn();
    
    if ($webhook_configured == '1') {
        echo "⚠️  AVISO: Webhook do ASAAS já foi configurado anteriormente.\n";
        echo "Deseja reconfigurar? (s/N): ";
        if (php_sapi_name() === 'cli') {
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (strtolower(trim($line)) !== 's') {
                echo "Configuração cancelada.\n";
                exit;
            }
        }
    }
} catch (Exception $e) {
    echo "Erro ao verificar configuração: " . $e->getMessage() . "\n";
}

echo "1. Testando conexão com ASAAS...\n";

$asaas = new AsaasAPI();

// Primeiro teste a conexão
$accountResult = $asaas->makeRequestPublic('GET', '/myAccount');

if (!$accountResult['success']) {
    echo "❌ Erro ao conectar com ASAAS:\n";
    echo "Código HTTP: " . $accountResult['http_code'] . "\n";
    echo "Resposta: " . json_encode($accountResult['data'], JSON_PRETTY_PRINT) . "\n";
    
    if ($accountResult['http_code'] == 401) {
        echo "\n🔑 ERRO DE AUTENTICAÇÃO:\n";
        echo "Verifique se sua API Key do ASAAS está correta em config/asaas_config.php\n";
        echo "API Key atual: " . substr(ASAAS_API_KEY, 0, 20) . "...\n";
    }
    
    exit(1);
}

echo "✅ Conectado ao ASAAS com sucesso!\n";
echo "2. Configurando webhook...\n";

// URL do webhook
$webhookUrl = ASAAS_WEBHOOK_URL;
echo "URL do Webhook: $webhookUrl\n";

// Eventos que queremos monitorar
$events = [
    'PAYMENT_CONFIRMED',
    'PAYMENT_RECEIVED', 
    'PAYMENT_OVERDUE',
    'PAYMENT_DELETED'
];

// Configuração do webhook - SIMPLIFICADA para funcionar com ngrok
$webhookData = [
    'name' => 'Discador.net Webhook',
    'url' => $webhookUrl,
    'enabled' => true,
    'events' => $events
];

echo "3. Removendo webhooks antigos...\n";

// Listar e remover webhooks existentes
$result = $asaas->makeRequestPublic('GET', '/webhooks');

if ($result['success']) {
    $existingWebhooks = $result['data']['data'] ?? [];
    echo "Webhooks existentes: " . count($existingWebhooks) . "\n";
    
    foreach ($existingWebhooks as $webhook) {
        echo "Removendo: " . $webhook['name'] . "\n";
        $deleteResult = $asaas->makeRequestPublic('DELETE', '/webhooks/' . $webhook['id']);
        if (!$deleteResult['success']) {
            echo "⚠️  Aviso: Não foi possível remover webhook " . $webhook['id'] . "\n";
        }
    }
}

echo "4. Criando novo webhook...\n";

// Criar novo webhook
$createResult = $asaas->makeRequestPublic('POST', '/webhooks', $webhookData);

if ($createResult['success']) {
    echo "✅ Webhook configurado com sucesso!\n";
    echo "ID do Webhook: " . $createResult['data']['id'] . "\n";
    
    // Marcar como configurado no banco
    try {
        $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()");
        $stmt->execute([
            'asaas_webhook_configured', 
            '1', 
            'Webhook configurado automaticamente',
            '1'
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()");
        $stmt->execute([
            'asaas_webhook_id', 
            $createResult['data']['id'], 
            'ID do webhook principal do ASAAS',
            $createResult['data']['id']
        ]);
        
        echo "✅ Configuração salva no banco.\n";
    } catch (Exception $e) {
        echo "⚠️  Aviso: Erro ao salvar no banco: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ Erro ao configurar webhook:\n";
    echo "Código HTTP: " . $createResult['http_code'] . "\n";
    
    // Melhor tratamento de erros
    if (isset($createResult['data']) && is_array($createResult['data'])) {
        echo "Detalhes do erro:\n";
        foreach ($createResult['data'] as $key => $value) {
            if (is_array($value)) {
                echo "- $key: " . implode(', ', $value) . "\n";
            } else {
                echo "- $key: $value\n";
            }
        }
    } else {
        echo "Resposta: " . $createResult['raw_response'] . "\n";
    }
    
    if ($createResult['http_code'] == 401) {
        echo "\n🔑 ERRO DE AUTENTICAÇÃO:\n";
        echo "Verifique sua API Key do ASAAS\n";
    }
    
    if ($createResult['http_code'] == 400) {
        echo "\n📋 TENTANDO SOLUÇÃO ALTERNATIVA...\n";
        echo "Vamos configurar sem webhook por enquanto.\n";
        
        // Configurar sem webhook
        try {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()");
            $stmt->execute([
                'asaas_webhook_configured', 
                '1', 
                'Configurado sem webhook (ngrok)',
                '1'
            ]);
            
            echo "✅ Sistema configurado sem webhook.\n";
            echo "💡 Os pagamentos precisarão ser verificados manualmente ou use um domínio real.\n";
            
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n5. Testando configuração da conta...\n";

if ($accountResult['success']) {
    $account = $accountResult['data'];
    echo "✅ Conta ASAAS:\n";
    echo "  - Nome: " . $account['name'] . "\n";
    echo "  - Email: " . $account['email'] . "\n";
    echo "  - Ambiente: " . (ASAAS_ENVIRONMENT === 'sandbox' ? 'SANDBOX' : 'PRODUÇÃO') . "\n";
}

echo "\n=== CONFIGURAÇÃO CONCLUÍDA ===\n";

echo "\n📋 STATUS:\n";
echo "✅ API conectada\n";
echo (isset($createResult) && $createResult['success'] ? "✅" : "⚠️ ") . " Webhook " . (isset($createResult) && $createResult['success'] ? "configurado" : "com problemas") . "\n";
echo "✅ Banco de dados atualizado\n";

echo "\n🚀 PRÓXIMOS PASSOS:\n";
echo "1. Teste uma compra de créditos\n";
echo "2. " . (isset($createResult) && $createResult['success'] ? "Webhook funcionará automaticamente" : "Verifique pagamentos manualmente") . "\n";
echo "3. Monitore os logs PHP para debug\n";

if (ASAAS_ENVIRONMENT === 'sandbox') {
    echo "\n🧪 MODO SANDBOX ATIVO:\n";
    echo "- PIX é simulado automaticamente após alguns segundos\n";
    echo "- Boletos são fictícios\n";
    echo "- Sem cobrança real\n";
}

echo "\nConfiguração finalizada! 🎉\n";
?>