<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

// 1. Coleta e limpa os dados do formulário
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? ''); // Remove formatação
$telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? ''); // Remove formatação
$senha = $_POST['senha'] ?? '';
$senha_confirm = $_POST['senha_confirm'] ?? '';

// 2. Validação dos dados
if (empty($nome) || empty($email) || empty($cpf) || empty($senha) || empty($senha_confirm)) {
    $_SESSION['error_message'] = "Nome, email, CPF e senhas são obrigatórios.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "O formato do e-mail é inválido.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

// Função para validar CPF
function validarCPF($cpf) {
    // CPF deve ter 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

if (!validarCPF($cpf)) {
    $_SESSION['error_message'] = "CPF inválido. Verifique os números digitados.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

if (strlen($senha) < 6) {
    $_SESSION['error_message'] = "A senha deve ter no mínimo 6 caracteres.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

if ($senha !== $senha_confirm) {
    $_SESSION['error_message'] = "As senhas não coincidem.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

// Validar telefone se fornecido
if (!empty($telefone) && (strlen($telefone) < 10 || strlen($telefone) > 11)) {
    $_SESSION['error_message'] = "Telefone inválido. Digite apenas números com DDD.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}

try {
    // 3. Verifica se o e-mail já está cadastrado
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "Este e-mail já está em uso. Tente fazer login ou use outro e-mail.";
        header('Location: ' . BASE_URL . 'views/client/cadastro.php');
        exit;
    }

    // 4. Verifica se o CPF já está cadastrado
    $stmt_cpf = $pdo->prepare("SELECT id FROM clientes WHERE cpf = ?");
    $stmt_cpf->execute([$cpf]);
    if ($stmt_cpf->fetch()) {
        $_SESSION['error_message'] = "Este CPF já está cadastrado no sistema.";
        header('Location: ' . BASE_URL . 'views/client/cadastro.php');
        exit;
    }

    // 5. Criptografa a senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // 6. Insere o novo cliente no banco de dados
    $stmt_insert = $pdo->prepare("INSERT INTO clientes (nome, email, cpf, telefone, senha, data_cadastro) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt_insert->execute([$nome, $email, $cpf, $telefone ?: null, $senha_hash]);

    // 7. Pega o ID do cliente recém-criado
    $cliente_id = $pdo->lastInsertId();

    // 8. Log da atividade
    error_log("Novo cliente cadastrado: ID {$cliente_id}, Email: {$email}");

    // 9. Redireciona para a página de login com mensagem de sucesso
    $_SESSION['success_message'] = "🎉 Cadastro realizado com sucesso! Faça seu login para começar.";
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;

} catch (PDOException $e) {
    // Em caso de erro no banco de dados
    error_log("Erro no cadastro de cliente: " . $e->getMessage());
    $_SESSION['error_message'] = "Ocorreu um erro no servidor. Tente novamente em alguns instantes.";
    header('Location: ' . BASE_URL . 'views/client/cadastro.php');
    exit;
}
?>