<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';


// Validação de segurança
if (!isset($_SESSION['logged_in'])) {
    exit('Acesso negado.');
}

// Roteador de Ações
$action = $_REQUEST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];

// --- AÇÃO 1: CRIAR UMA NOVA CAMPANHA ---
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- NOVOS CAMPOS DO FORMULÁRIO ---
    $nome_campanha = $_POST['nome_campanha'] ?? null;
    $mensagem_template = $_POST['mensagem'] ?? null;
    $lista_contatos_file = $_FILES['lista_contatos'] ?? null;
    $data_agendamento = $_POST['data_agendamento'] ?? null;
    
    // Arrays para múltiplas instâncias e suas porcentagens
    $instancias_ids = $_POST['instancias'] ?? [];
    $porcentagens = $_POST['porcentagens'] ?? [];

    // Valores de Delay
    $delay_min = $_POST['delay_min'] ?? 5;
    $delay_max = $_POST['delay_max'] ?? 15;


    // --- VALIDAÇÃO DOS DADOS ---
    if (!$nome_campanha || !$mensagem_template || empty($instancias_ids) || !$lista_contatos_file || $lista_contatos_file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Por favor, preencha todos os campos obrigatórios e envie uma lista de contatos válida.";
        header('Location: ' . BASE_URL . 'views/client/disparos.php');
        exit;
    }
    
    // Mapear porcentagens para as instâncias selecionadas
    $instancias_selecionadas_com_porcentagem = [];
    $total_porcentagem = 0;
    foreach($instancias_ids as $index => $id){
        $percent = filter_var($porcentagens[$index], FILTER_VALIDATE_INT);
        if ($percent > 0) {
            $instancias_selecionadas_com_porcentagem[$id] = $percent;
            $total_porcentagem += $percent;
        }
    }

    if ($total_porcentagem !== 100) {
        $_SESSION['error_message'] = "A soma das porcentagens das instâncias selecionadas deve ser exatamente 100%.";
        header('Location: ' . BASE_URL . 'views/client/disparos.php');
        exit;
    }


    // --- PROCESSAMENTO DE UPLOADS E LEITURA DE CSV ---
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $csv_filename = uniqid('lista_', true) . '.csv';
    $csv_path = $upload_dir . $csv_filename;

    if (!move_uploaded_file($lista_contatos_file['tmp_name'], $csv_path)) {
        $_SESSION['error_message'] = "Falha ao fazer upload da lista de contatos.";
        header('Location: ' . BASE_URL . 'views/client/disparos.php');
        exit;
    }
    
    $midia_file = $_FILES['midia'] ?? null;
    $midia_path_db = null;
    if ($midia_file && $midia_file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($midia_file['name'], PATHINFO_EXTENSION);
        $midia_filename = uniqid('midia_', true) . '.' . $ext;
        $midia_path_server = $upload_dir . $midia_filename;
        if (move_uploaded_file($midia_file['tmp_name'], $midia_path_server)) {
            $midia_path_db = $midia_filename;
        }
    }

    function remove_utf8_bom($text) {
        $bom = pack('H*','EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }
    $contatos = [];
    if (($handle = fopen($csv_path, "r")) !== FALSE) {
        $primeira_linha_str = fgets($handle);
        $primeira_linha_limpa = remove_utf8_bom($primeira_linha_str);
        $delimitador = (strpos($primeira_linha_limpa, ';') !== false) ? ';' : ',';
        $cabecalho = str_getcsv($primeira_linha_limpa, $delimitador);
        $cabecalho_limpo = array_map(function($h) { return trim(preg_replace('/[^\x20-\x7E]/u', '', $h)); }, $cabecalho);
        $numero_idx = array_search('numero', array_map('strtolower', $cabecalho_limpo));

        if ($numero_idx === false) {
            $_SESSION['error_message'] = "A planilha CSV precisa ter uma coluna chamada 'numero'.";
            fclose($handle); unlink($csv_path);
            header('Location: ' . BASE_URL . 'views/client/disparos.php');
            exit;
        }
        
        while (($data = fgetcsv($handle, 1000, $delimitador)) !== FALSE) {
            if(isset($data[$numero_idx]) && !empty($data[$numero_idx])) {
                $contatos[] = $data;
            }
        }
        fclose($handle);
    }
    
    $total_contatos = count($contatos);

    if ($total_contatos == 0) {
        $_SESSION['error_message'] = "Nenhum contato válido encontrado na planilha.";
        unlink($csv_path);
        header('Location: ' . BASE_URL . 'views/client/disparos.php');
        exit;
    }

    // --- VERIFICAÇÃO DE CRÉDITO ---
    $stmt_saldo = $pdo->prepare("SELECT saldo_creditos_disparo FROM clientes WHERE id = ?");
    $stmt_saldo->execute([$cliente_id]);
    $saldo_disparo = $stmt_saldo->fetchColumn();

    if ($saldo_disparo < $total_contatos) {
        $_SESSION['error_message'] = "Saldo de créditos insuficiente. Você tem {$saldo_disparo} e sua lista precisa de {$total_contatos} créditos.";
        unlink($csv_path);
        if ($midia_path_db) unlink($upload_dir . $midia_path_db);
        header('Location: ' . BASE_URL . 'views/client/disparos.php');
        exit;
    }
    
    // --- LÓGICA DE DISTRIBUIÇÃO DAS MENSAGENS ---
    $distribuicao_final = [];
    $total_atribuido = 0;
    foreach ($instancias_selecionadas_com_porcentagem as $id => $percent) {
        $quantidade = floor(($percent / 100) * $total_contatos);
        for ($i = 0; $i < $quantidade; $i++) {
            $distribuicao_final[] = $id;
        }
        $total_atribuido += $quantidade;
    }

    // Distribui o restante (se houver sobras de arredondamento)
    $i = 0;
    while($total_atribuido < $total_contatos) {
        $instancia_id_aleatoria = array_keys($instancias_selecionadas_com_porcentagem)[$i % count($instancias_selecionadas_com_porcentagem)];
        $distribuicao_final[] = $instancia_id_aleatoria;
        $total_atribuido++;
        $i++;
    }
    shuffle($distribuicao_final); // Embaralha para revezamento

    
    // --- INSERÇÃO NO BANCO DE DADOS ---
    try {
        $status_inicial = !empty($data_agendamento) ? 'Agendada' : 'Enviando';
        $data_agendamento_db = !empty($data_agendamento) ? $data_agendamento : null;
        
        $pdo->beginTransaction();
        
        // Salva a campanha com as novas informações de delay
        $stmt_campanha = $pdo->prepare("INSERT INTO campanhas (cliente_id, nome_campanha, mensagem, caminho_midia, status, data_agendamento, delay_min, delay_max) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_campanha->execute([$cliente_id, $nome_campanha, $mensagem_template, $midia_path_db, $status_inicial, $data_agendamento_db, $delay_min, $delay_max]);
        $campanha_id = $pdo->lastInsertId();

        // Prepara para inserir na fila, agora com a instancia_id
        $stmt_fila = $pdo->prepare("INSERT INTO fila_envio (campanha_id, instancia_id, numero_destino, mensagem_personalizada, status) VALUES (?, ?, ?, ?, 'Pendente')");
        
        foreach ($contatos as $index => $contato_data) {
            $mensagem_personalizada = $mensagem_template;
            foreach ($cabecalho_limpo as $col_index => $coluna_nome) {
                $placeholder = '{{' . $coluna_nome . '}}';
                $valor = $contato_data[$col_index] ?? '';
                $mensagem_personalizada = str_ireplace($placeholder, $valor, $mensagem_personalizada);
            }
            $numero_destino = $contato_data[$numero_idx];
            $instancia_id_atribuida = $distribuicao_final[$index]; // Pega a instância da lista de distribuição
            
            // Insere na fila com a instância já definida
            $stmt_fila->execute([$campanha_id, $instancia_id_atribuida, $numero_destino, $mensagem_personalizada]);
        }
        
        // Debita os créditos
        $stmt_debito = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo - ? WHERE id = ?");
        $stmt_debito->execute([$total_contatos, $cliente_id]);
        
        // Registra a transação
        $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (cliente_id, tipo_transacao, valor, status_pagamento) VALUES (?, ?, ?, ?)");
        $stmt_transacao->execute([$cliente_id, 'compra_disparo', - (float)$total_contatos, 'Pago']);
        
        $pdo->commit();
        $_SESSION['success_message'] = "Campanha '{$nome_campanha}' criada e enfileirada com sucesso!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Ocorreu um erro ao criar a campanha: " . $e->getMessage();
    }
    
    unlink($csv_path);
    header('Location: ' . BASE_URL . 'views/client/disparos.php');
    exit;

// --- AÇÃO 2: ATUALIZAR O STATUS DE UMA CAMPANHA (PAUSAR/CONTINUAR) ---
} elseif ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $campanha_id = $_GET['id'] ?? null;
    $novo_status = $_GET['status'] ?? null;

    // Valida se o novo status é um dos permitidos para evitar injeção de dados
    $status_permitidos = ['Enviando', 'Pausada'];
    if ($campanha_id && $novo_status && in_array($novo_status, $status_permitidos)) {
        try {
            // Atualiza o status da campanha, garantindo que ela pertence ao cliente logado
            $stmt = $pdo->prepare("UPDATE campanhas SET status = ? WHERE id = ? AND cliente_id = ?");
            $stmt->execute([$novo_status, $campanha_id, $cliente_id]);
            $_SESSION['success_message'] = "Status da campanha atualizado para '{$novo_status}'!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erro ao atualizar status da campanha.";
        }
    }
    header('Location: ' . BASE_URL . 'views/client/disparos.php');
    exit;

} elseif ($action === 'delete_campaign' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $campanha_id = $_GET['id'] ?? null;
    $cliente_id = $_SESSION['user_id'];

    if ($campanha_id) {
        try {
            // Para garantir a integridade, usamos uma transação
            $pdo->beginTransaction();

            // Primeiro, deleta todas as mensagens da fila de envio associadas a esta campanha
            $stmt_fila = $pdo->prepare("DELETE FROM fila_envio WHERE campanha_id = ?");
            $stmt_fila->execute([$campanha_id]);

            // Depois, deleta a campanha principal, garantindo que ela pertence ao cliente logado
            $stmt_campanha = $pdo->prepare("DELETE FROM campanhas WHERE id = ? AND cliente_id = ?");
            $stmt_campanha->execute([$campanha_id, $cliente_id]);

            $pdo->commit();
            $_SESSION['success_message'] = "Campanha excluída com sucesso.";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Erro ao excluir a campanha.";
        }
    }
    header('Location: ' . BASE_URL . 'views/client/disparos.php');
    exit;
} else {
    // Se nenhuma ação válida for encontrada, redireciona para o painel principal
    header('Location: ' . BASE_URL . 'views/client/dashboard.php');
    exit;
}
?>