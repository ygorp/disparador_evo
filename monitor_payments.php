<?php
/**
 * Monitor de Pagamentos ASAAS
 * Script para verificar status de pagamentos pendentes e sincronizar com a API
 * Execute via cron job a cada 5 minutos em produção
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/asaas_config.php';

echo "=== MONITOR DE PAGAMENTOS ASAAS [" . ASAAS_ENVIRONMENT . "] ===\n";
echo "Executado em: " . date('Y-m-d H:i:s') . "\n\n";

$asaas = new AsaasAPI();

try {
    // 1. Busca transações pendentes com mais de 5 minutos
    $stmt = $pdo->prepare("
        SELECT * FROM transacoes 
        WHERE status_pagamento IN ('Pendente', 'Processando') 
        AND asaas_payment_id IS NOT NULL 
        AND data_transacao < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        AND data_transacao > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY data_transacao DESC
        LIMIT 50
    ");
    
    $stmt->execute();
    $transacoes_pendentes = $stmt->fetchAll();
    
    echo "📋 Transações pendentes encontradas: " . count($transacoes_pendentes) . "\n\n";
    
    if (empty($transacoes_pendentes)) {
        echo "✅ Nenhuma transação pendente para verificar.\n";
        exit(0);
    }
    
    $updated_count = 0;
    $confirmed_count = 0;
    $expired_count = 0;
    $error_count = 0;
    
    foreach ($transacoes_pendentes as $transacao) {
        echo "🔍 Verificando pagamento: {$transacao['asaas_payment_id']}\n";
        echo "   Cliente ID: {$transacao['cliente_id']}\n";
        echo "   Valor: R$ " . number_format($transacao['valor'], 2, ',', '.') . "\n";
        echo "   Criado em: {$transacao['data_transacao']}\n";
        
        // Consulta status na API do ASAAS
        $result = $asaas->getPayment($transacao['asaas_payment_id']);
        
        if (!$result['success']) {
            echo "   ❌ Erro ao consultar API: HTTP {$result['http_code']}\n";
            $error_count++;
            
            // Se for 404, o pagamento foi deletado
            if ($result['http_code'] === 404) {
                echo "   🗑️  Pagamento não encontrado na API, marcando como cancelado\n";
                $stmt_update = $pdo->prepare("UPDATE transacoes SET status_pagamento = 'Cancelado' WHERE id = ?");
                $stmt_update->execute([$transacao['id']]);
                $updated_count++;
            }
            continue;
        }
        
        $payment_data = $result['data'];
        $api_status = $payment_data['status'];
        
        echo "   📊 Status na API: $api_status\n";
        
        // Mapeia status da API para nosso sistema
        $novo_status = null;
        switch ($api_status) {
            case 'PENDING':
                $novo_status = 'Pendente';
                break;
            case 'RECEIVED':
            case 'CONFIRMED':
                $novo_status = 'Pago';
                break;
            case 'OVERDUE':
                $novo_status = 'Vencido';
                break;
            case 'REFUNDED':
            case 'CANCELLED':
                $novo_status = 'Cancelado';
                break;
        }
        
        // Se o status mudou, atualiza
        if ($novo_status && $novo_status !== $transacao['status_pagamento']) {
            echo "   🔄 Atualizando status: {$transacao['status_pagamento']} → $novo_status\n";
            
            $pdo->beginTransaction();
            
            try {
                // Atualiza status
                $stmt_update = $pdo->prepare("UPDATE transacoes SET status_pagamento = ? WHERE id = ?");
                $stmt_update->execute([$novo_status, $transacao['id']]);
                
                // Se foi confirmado, adiciona créditos
                if ($novo_status === 'Pago') {
                    echo "   💰 Adicionando créditos ao cliente\n";
                    
                    if ($transacao['tipo_transacao'] === 'recarga_disparo') {
                        $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo + ? WHERE id = ?");
                        $stmt_credito->execute([$transacao['creditos_quantidade'], $transacao['cliente_id']]);
                    } elseif ($transacao['tipo_transacao'] === 'recarga_maturacao') {
                        $stmt_credito = $pdo->prepare("UPDATE clientes SET saldo_creditos_maturacao = saldo_creditos_maturacao + ? WHERE id = ?");
                        $stmt_credito->execute([$transacao['creditos_quantidade'], $transacao['cliente_id']]);
                    }
                    
                    // Marca data do pagamento
                    $stmt_data = $pdo->prepare("UPDATE transacoes SET data_pagamento = NOW() WHERE id = ?");
                    $stmt_data->execute([$transacao['id']]);
                    
                    // Cria notificação
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
                    
                    $confirmed_count++;
                } elseif ($novo_status === 'Vencido') {
                    $expired_count++;
                }
                
                // Salva detalhes
                $stmt_details = $pdo->prepare("
                    INSERT INTO pagamentos_detalhes (transacao_id, asaas_payment_id, status_anterior, status_atual, valor_pago, observacoes) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_details->execute([
                    $transacao['id'],
                    $transacao['asaas_payment_id'],
                    $transacao['status_pagamento'],
                    $novo_status,
                    $payment_data['value'] ?? $transacao['valor'],
                    'Atualizado via monitor - ' . date('Y-m-d H:i:s')
                ]);
                
                $pdo->commit();
                $updated_count++;
                echo "   ✅ Status atualizado com sucesso\n";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "   ❌ Erro ao atualizar: " . $e->getMessage() . "\n";
                $error_count++;
            }
        } else {
            echo "   ✅ Status já atualizado\n";
        }
        
        echo "\n";
        
        // Pequena pausa para não sobrecarregar a API
        usleep(500000); // 0.5 segundos
    }
    
    // 2. Relatório final
    echo "=== RELATÓRIO FINAL ===\n";
    echo "📊 Transações verificadas: " . count($transacoes_pendentes) . "\n";
    echo "🔄 Transações atualizadas: $updated_count\n";
    echo "💰 Pagamentos confirmados: $confirmed_count\n";
    echo "⏰ Pagamentos vencidos: $expired_count\n";
    echo "❌ Erros: $error_count\n";
    
    // 3. Limpeza - remove transações muito antigas com status pendente
    $stmt_cleanup = $pdo->prepare("
        UPDATE transacoes 
        SET status_pagamento = 'Vencido' 
        WHERE status_pagamento = 'Pendente' 
        AND data_transacao < DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND asaas_payment_id IS NOT NULL
    ");
    $stmt_cleanup->execute();
    $cleaned_count = $stmt_cleanup->rowCount();
    
    if ($cleaned_count > 0) {
        echo "🧹 Transações antigas marcadas como vencidas: $cleaned_count\n";
    }
    
    // 4. Verifica webhooks não processados
    $stmt_webhooks = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM webhook_logs 
        WHERE processed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND provider = 'asaas'
    ");
    $stmt_webhooks->execute();
    $webhook_count = $stmt_webhooks->fetchColumn();
    
    echo "📨 Webhooks recebidos na última hora: $webhook_count\n";
    
    echo "\n✨ Monitor finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    error_log("MONITOR PAYMENTS ERROR: " . $e->getMessage());
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Função para criar cron job (apenas exemplo)
if (isset($argv[1]) && $argv[1] === '--install-cron') {
    echo "\n=== INSTALAÇÃO DO CRON JOB ===\n";
    echo "Adicione esta linha ao seu crontab:\n";
    echo "*/5 * * * * /usr/bin/php " . __FILE__ . " >> /var/log/asaas_monitor.log 2>&1\n";
    echo "\nPara editar o crontab: crontab -e\n";
}
?>