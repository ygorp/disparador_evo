<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Verificação de login
if (!isset($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];

// Função helper para chamadas API
function callEvolutionAPI($method, $endpoint, $apiKey, $payload = null) {
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Content-Type: application/json'];
    if ($apiKey) {
        $headers[] = 'apikey: ' . $apiKey;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (strtoupper($method) === 'POST' && $payload) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } elseif (strtoupper($method) === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $http_code, 'body' => $response];
}

try {
    if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // =============== INICIAR NOVA MATURAÇÃO ===============
        $instance_id = $_POST['instance_id'];
        $plano_id = $_POST['plano_id'];

        $pdo->beginTransaction();

        // Busca dados da instância e plano
        $stmt = $pdo->prepare("
            SELECT i.numero_telefone, i.nome_instancia, p.preco, p.duracao_dias, c.saldo_creditos_maturacao 
            FROM instancias i 
            JOIN planos p ON p.id = ? 
            JOIN clientes c ON c.id = ? 
            WHERE i.id = ? AND i.cliente_id = ? AND i.status = 'Conectado'
        ");
        $stmt->execute([$plano_id, $cliente_id, $instance_id, $cliente_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new Exception("Instância não encontrada ou não conectada.");
        }

        if (empty($data['numero_telefone'])) {
            throw new Exception("Número de telefone não encontrado. Reconecte a instância.");
        }

        // Verifica saldo
        if ((float)$data['saldo_creditos_maturacao'] < (float)$data['preco']) {
            throw new Exception("Saldo insuficiente. Você tem " . number_format($data['saldo_creditos_maturacao'], 2, ',', '.') . " créditos e precisa de " . number_format($data['preco'], 2, ',', '.') . " créditos.");
        }

        // Verifica se já não está em maturação
        $stmt_check = $pdo->prepare("SELECT id FROM instancias WHERE id = ? AND (data_fim_maturacao IS NOT NULL OR maturacao_restante_secs > 0)");
        $stmt_check->execute([$instance_id]);
        if ($stmt_check->fetch()) {
            throw new Exception("Esta instância já está em processo de maturação.");
        }

        // Adiciona ao grupo na API
        $endpoint = EVOLUTION_API_BASE_URL . '/group/updateParticipant/' . GROUP_ADMIN_INSTANCE_NAME . '?groupJid=' . MATURATION_GROUP_ID;
        $payload = ['action' => 'add', 'participants' => [$data['numero_telefone'] . '@s.whatsapp.net']];
        $result = callEvolutionAPI('POST', $endpoint, EVOLUTION_API_GLOBAL_KEY, $payload);

        if (!in_array($result['code'], [200, 201, 409])) {
            throw new Exception("Falha ao adicionar ao grupo de maturação.");
        }

        // Debita créditos
        $novo_saldo = (float)$data['saldo_creditos_maturacao'] - (float)$data['preco'];
        $stmt_debito = $pdo->prepare("UPDATE clientes SET saldo_creditos_maturacao = ? WHERE id = ?");
        $stmt_debito->execute([$novo_saldo, $cliente_id]);

        // Atualiza instância com plano e data fim
        $data_fim = (new DateTime())->add(new DateInterval("P{$data['duracao_dias']}D"))->format('Y-m-d H:i:s');
        $stmt_update = $pdo->prepare("UPDATE instancias SET plano_maturacao_id = ?, data_fim_maturacao = ?, maturacao_restante_secs = NULL WHERE id = ?");
        $stmt_update->execute([$plano_id, $data_fim, $instance_id]);

        // Registra transação
        $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (cliente_id, plano_id, tipo_transacao, valor, status_pagamento) VALUES (?, ?, ?, ?, ?)");
        $stmt_transacao->execute([$cliente_id, $plano_id, 'compra_maturacao', -(float)$data['preco'], 'Pago']);

        $pdo->commit();
        $_SESSION['success_message'] = "Maturação iniciada com sucesso para a instância '" . $data['nome_instancia'] . "'!";

    } elseif ($action === 'pause' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // =============== PAUSAR MATURAÇÃO ===============
        $instance_id = $_GET['instance_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT numero_telefone, data_fim_maturacao FROM instancias WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$instance_id, $cliente_id]);
        $instancia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($instancia && $instancia['data_fim_maturacao']) {
            // Calcula segundos restantes
            $data_fim = new DateTime($instancia['data_fim_maturacao']);
            $agora = new DateTime();
            $segundos_restantes = null;
            
            if ($data_fim > $agora) {
                $segundos_restantes = $data_fim->getTimestamp() - $agora->getTimestamp();
            }

            // Remove do grupo (opcional)
            if (!empty($instancia['numero_telefone'])) {
                $endpoint = EVOLUTION_API_BASE_URL . '/group/updateParticipant/' . GROUP_ADMIN_INSTANCE_NAME . '?groupJid=' . MATURATION_GROUP_ID;
                $payload = ['action' => 'remove', 'participants' => [$instancia['numero_telefone'] . '@s.whatsapp.net']];
                callEvolutionAPI('POST', $endpoint, EVOLUTION_API_GLOBAL_KEY, $payload);
            }

            // Salva tempo restante e limpa data fim
            $stmt_update = $pdo->prepare("UPDATE instancias SET data_fim_maturacao = NULL, maturacao_restante_secs = ? WHERE id = ?");
            $stmt_update->execute([$segundos_restantes, $instance_id]);

            $_SESSION['success_message'] = "Maturação pausada com sucesso.";
        }

        $pdo->commit();

    } elseif ($action === 'resume' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // =============== RETOMAR MATURAÇÃO ===============
        $instance_id = $_GET['instance_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT numero_telefone, maturacao_restante_secs FROM instancias WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$instance_id, $cliente_id]);
        $instancia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($instancia && $instancia['maturacao_restante_secs'] > 0) {
            // Adiciona de volta ao grupo
            if (!empty($instancia['numero_telefone'])) {
                $endpoint = EVOLUTION_API_BASE_URL . '/group/updateParticipant/' . GROUP_ADMIN_INSTANCE_NAME . '?groupJid=' . MATURATION_GROUP_ID;
                $payload = ['action' => 'add', 'participants' => [$instancia['numero_telefone'] . '@s.whatsapp.net']];
                callEvolutionAPI('POST', $endpoint, EVOLUTION_API_GLOBAL_KEY, $payload);
            }

            // Calcula nova data fim baseada no tempo restante
            $data_fim = (new DateTime())->add(new DateInterval("PT{$instancia['maturacao_restante_secs']}S"))->format('Y-m-d H:i:s');
            
            $stmt_update = $pdo->prepare("UPDATE instancias SET data_fim_maturacao = ?, maturacao_restante_secs = NULL WHERE id = ?");
            $stmt_update->execute([$data_fim, $instance_id]);

            $_SESSION['success_message'] = "Maturação retomada com sucesso.";
        }

        $pdo->commit();

    } elseif ($action === 'stop' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // =============== FINALIZAR MATURAÇÃO ===============
        $instance_id = $_GET['instance_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT numero_telefone FROM instancias WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$instance_id, $cliente_id]);
        $instancia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($instancia) {
            // Remove do grupo
            if (!empty($instancia['numero_telefone'])) {
                $endpoint = EVOLUTION_API_BASE_URL . '/group/updateParticipant/' . GROUP_ADMIN_INSTANCE_NAME . '?groupJid=' . MATURATION_GROUP_ID;
                $payload = ['action' => 'remove', 'participants' => [$instancia['numero_telefone'] . '@s.whatsapp.net']];
                callEvolutionAPI('POST', $endpoint, EVOLUTION_API_GLOBAL_KEY, $payload);
            }

            // Limpa dados de maturação
            $stmt_update = $pdo->prepare("UPDATE instancias SET data_fim_maturacao = NULL, maturacao_restante_secs = NULL WHERE id = ?");
            $stmt_update->execute([$instance_id]);

            $_SESSION['success_message'] = "Maturação finalizada com sucesso.";
        }

        $pdo->commit();

    } elseif ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // =============== CANCELAR MATURAÇÃO PAUSADA ===============
        $instance_id = $_GET['instance_id'];

        $pdo->beginTransaction();

        $stmt_update = $pdo->prepare("UPDATE instancias SET maturacao_restante_secs = NULL, plano_maturacao_id = NULL WHERE id = ? AND cliente_id = ?");
        $stmt_update->execute([$instance_id, $cliente_id]);

        $_SESSION['success_message'] = "Maturação cancelada com sucesso.";
        $pdo->commit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Erro: " . $e->getMessage();
}

header('Location: ' . BASE_URL . 'views/client/maturacao.php');
exit;