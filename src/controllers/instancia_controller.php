<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// VERIFICAÇÃO DE LOGIN
if (!isset($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}

// CONFIGURAÇÕES
$evolutionApiUrl = EVOLUTION_API_BASE_URL;
$evolutionApiKey = EVOLUTION_API_GLOBAL_KEY;
$action = $_REQUEST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];

// FUNÇÃO HELPER PARA CHAMADAS cURL
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

// --- ROTEADOR DE AÇÕES ---
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- LÓGICA PARA CRIAR INSTÂNCIA ---
    
    $nome_instancia_amigavel = $_POST['nome_instancia'];
    $cliente_id = $_SESSION['user_id'];
    $instance_name_api = uniqid($nome_instancia_amigavel . '_', true); // Nome único para API
    $numero_telefone = null;

    $proxy_host = PROXY_HOST;
    $proxy_port = PROXY_PORT;
    $proxy_user = PROXY_USER;
    $proxy_pass = PROXY_PASS;

    $proxy_configurado = (defined('PROXY_HOST') && PROXY_HOST);
    $proxy_ativo_db = $proxy_configurado ? 1 : 0;
    
    $endpoint = $evolutionApiUrl . '/instance/create';
    $payload = [
        'instanceName' => $instance_name_api,
        'qrcode' => true,
        'token' => $instance_name_api,
        'integration' => "WHATSAPP-BAILEYS"
    ];

    // Adiciona proxy se configurado
    if ($proxy_configurado) {
        $payload['proxyHost'] = $proxy_host;
        $payload['proxyPort'] = $proxy_port;
        $payload['proxyProtocol'] = 'http';
        $payload['proxyUsername'] = $proxy_user;
        $payload['proxyPassword'] = $proxy_pass;
    }

    $result = callEvolutionAPI('POST', $endpoint, $evolutionApiKey, $payload);

    if ($result['code'] === 200 || $result['code'] === 201) {
        $api_data = json_decode($result['body'], true);
        if (isset($api_data['instance'])) {
            // Salva no banco SEM plano de maturação
            $stmt = $pdo->prepare("INSERT INTO instancias (cliente_id, nome_instancia, instance_name_api, numero_telefone, status, proxy_ativo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $nome_instancia_amigavel, $instance_name_api, $numero_telefone, 'Desconectado', $proxy_ativo_db]);
            $_SESSION['success_message'] = "Instância '{$nome_instancia_amigavel}' criada com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao criar instância na API.";
        }
    } else {
        $_SESSION['error_message'] = "Falha na comunicação com a API. Código: " . $result['code'];
    }

    header('Location: ' . BASE_URL . 'views/client/instancias.php');
    exit;

} elseif ($action === 'get_qrcode' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // --- LÓGICA PARA BUSCAR QR CODE ---

    header('Content-Type: application/json');
    $response_data = ['success' => false, 'message' => 'Erro desconhecido.'];
    $instance_name = $_GET['instance_name'] ?? '';

    if (empty($instance_name)) {
        $response_data['message'] = 'Nome da instância não fornecido.';
    } else {
        $endpoint = $evolutionApiUrl . '/instance/connect/' . $instance_name;
        $result = callEvolutionAPI('GET', $endpoint, $evolutionApiKey);

        if ($result['code'] === 200 || $result['code'] === 201) {
            $api_data = json_decode($result['body'], true);
            if (isset($api_data['base64'])) {
                $response_data['success'] = true;
                $response_data['message'] = 'QR Code obtido com sucesso.';
                $response_data['qrcode'] = $api_data['base64'];
            } else {
                $response_data['message'] = 'A API conectou, mas não retornou um QR Code. A instância pode já estar conectada.';
            }
        } else {
            $response_data['message'] = 'Falha ao conectar na API. Código: ' . $result['code'];
        }
    }
    echo json_encode($response_data);
    exit;

} elseif ($action === 'check_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    header('Content-Type: application/json');
    $response_data = ['success' => false, 'status' => 'unknown'];
    $instance_name = $_GET['instance_name'] ?? '';

    if (!empty($instance_name)) {
        $endpoint = $evolutionApiUrl . '/instance/connectionState/' . $instance_name;
        $result = callEvolutionAPI('GET', $endpoint, $evolutionApiKey);

        if ($result['code'] === 200) {
            $api_data = json_decode($result['body'], true);
            if (isset($api_data['instance']['state'])) {
                $new_status = ($api_data['instance']['state'] === 'open') ? 'Conectado' : 'Desconectado';
                
                // Atualiza o status no nosso banco
                $stmt = $pdo->prepare("UPDATE instancias SET status = ? WHERE instance_name_api = ?");
                $stmt->execute([$new_status, $instance_name]);
                
                // SE O NOVO STATUS FOR 'CONECTADO', BUSCA E SALVA O NÚMERO
                if ($new_status === 'Conectado') {
                    $fetch_endpoint = $evolutionApiUrl . '/instance/fetchInstances?instanceName=' . $instance_name;
                    $fetch_result = callEvolutionAPI('GET', $fetch_endpoint, $evolutionApiKey);
                    
                    if ($fetch_result['code'] === 200) {
                        $instance_data_api = json_decode($fetch_result['body'], true);
                        $numero_telefone = str_replace('@s.whatsapp.net', '', $instance_data_api[0]['ownerJid'] ?? null);

                        if ($numero_telefone) {
                            $stmt_num = $pdo->prepare("UPDATE instancias SET numero_telefone = ? WHERE instance_name_api = ?");
                            $stmt_num->execute([$numero_telefone, $instance_name]);
                        }
                    }
                }
                
                $response_data['success'] = true;
                $response_data['status'] = $new_status;
            }
        }
    }
    echo json_encode($response_data);
    exit;

} elseif ($action === 'disconnect' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $instance_name = $_GET['instance_name'] ?? '';
    if ($instance_name) {
        $endpoint = $evolutionApiUrl . '/instance/logout/' . $instance_name;
        $result = callEvolutionAPI('DELETE', $endpoint, $evolutionApiKey);
        
        // Atualizar o status no nosso banco para 'Desconectado'
        $stmt = $pdo->prepare("UPDATE instancias SET status = 'Desconectado', numero_telefone = NULL WHERE instance_name_api = ? AND cliente_id = ?");
        $stmt->execute([$instance_name, $cliente_id]);
        
        $_SESSION['success_message'] = "Instância desconectada com sucesso.";
    }
    header('Location: ' . BASE_URL . 'views/client/instancias.php');
    exit;

} elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $instance_name = $_GET['instance_name'] ?? '';
    $instance_id = $_GET['instance_id'] ?? '';
    
    if ($instance_name && $instance_id) {
        try {
            $pdo->beginTransaction();

            // Verifica se a instância está em maturação ativa
            $stmt_check = $pdo->prepare("SELECT data_fim_maturacao, maturacao_restante_secs FROM instancias WHERE id = ? AND cliente_id = ?");
            $stmt_check->execute([$instance_id, $cliente_id]);
            $instancia_data = $stmt_check->fetch();

            if ($instancia_data && ($instancia_data['data_fim_maturacao'] || $instancia_data['maturacao_restante_secs'] > 0)) {
                throw new Exception("Não é possível excluir uma instância que está em processo de maturação. Finalize a maturação primeiro.");
            }

            // 1. Deleta da API Evolution
            $endpoint = $evolutionApiUrl . '/instance/delete/' . $instance_name;
            callEvolutionAPI('DELETE', $endpoint, $evolutionApiKey);

            // 2. Deleta do nosso banco de dados
            $stmt = $pdo->prepare("DELETE FROM instancias WHERE id = ? AND cliente_id = ?");
            $stmt->execute([$instance_id, $cliente_id]);

            $pdo->commit();
            $_SESSION['success_message'] = "Instância excluída com sucesso.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    header('Location: ' . BASE_URL . 'views/client/instancias.php');
    exit;

} else {
    // Se nenhuma ação válida for encontrada, redireciona para a página de instâncias
    header('Location: ' . BASE_URL . 'views/client/instancias.php');
    exit;
}