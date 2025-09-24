<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Proteção: apenas admins podem executar estas ações
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    exit('Acesso não autorizado.');
}

$action = $_POST['action'] ?? '';

if ($action === 'create_client') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha']; // No mundo real, seria bom ter confirmação de senha
    
    // Validações (se email já existe, etc.) - omitidas para brevidade
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, senha) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $email, $senha_hash]);

} elseif ($action === 'add_credits') {
    $cliente_id = $_POST['cliente_id'];
    $creditos_disparo = (float)($_POST['creditos_disparo'] ?: 0);
    $creditos_maturacao = (float)($_POST['creditos_maturacao'] ?: 0);

    $stmt = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo + ?, saldo_creditos_maturacao = saldo_creditos_maturacao + ? WHERE id = ?");
    $stmt->execute([$creditos_disparo, $creditos_maturacao, $cliente_id]);
} elseif ($action === 'create_plan') {
    $stmt = $pdo->prepare("INSERT INTO planos (nome, descricao, tipo, preco, creditos, duracao_dias, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['nome'], $_POST['descricao'], $_POST['tipo'], 
        (float)$_POST['preco'], (int)$_POST['creditos'], (int)$_POST['duracao_dias'], 1
    ]);
    header('Location: ' . BASE_URL . 'views/admin/planos.php');
    exit;

// --- NOVA LÓGICA PARA ATUALIZAR PLANO ---
} elseif ($action === 'update_plan') {
    $stmt = $pdo->prepare("UPDATE planos SET nome=?, descricao=?, tipo=?, preco=?, creditos=?, duracao_dias=? WHERE id = ?");
    $stmt->execute([
        $_POST['nome'], $_POST['descricao'], $_POST['tipo'], 
        (float)$_POST['preco'], (int)$_POST['creditos'], (int)$_POST['duracao_dias'],
        $_POST['id']
    ]);
    header('Location: ' . BASE_URL . 'views/admin/planos.php');
    exit;
} elseif ($action === 'update_client') {
    $cliente_id = $_POST['id'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha']; // Pega a nova senha

    // Monta a query base
    $sql = "UPDATE clientes SET nome = ?, email = ?";
    $params = [$nome, $email];

    // Se uma nova senha foi fornecida, adiciona a atualização de senha à query
    if (!empty($senha)) {
        if (strlen($senha) < 6) {
            $_SESSION['error_message'] = "A nova senha deve ter no mínimo 6 caracteres.";
            header('Location: ' . BASE_URL . 'views/admin/clientes.php');
            exit;
        }
        $sql .= ", senha = ?";
        $params[] = password_hash($senha, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = ?";
    $params[] = $cliente_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $_SESSION['success_message'] = "Cliente atualizado com sucesso!";
}

header('Location: ' . BASE_URL . 'views/admin/clientes.php');
exit;
?>