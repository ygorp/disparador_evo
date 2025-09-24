<?php
// Inicia a sessão. Essencial para armazenar informações do usuário após o login.
session_start();

// Inclui o nosso arquivo de conexão com o banco de dados.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// --- Verificação Inicial ---
// Verifica se o método de requisição é POST, ou seja, se o formulário foi enviado.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Se não for POST, redireciona de volta para a página de login.
    header('Location: ../../views/login.php');
    exit;
}

// --- Obtenção dos Dados do Formulário ---
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

// Validação simples para ver se os campos não estão vazios.
if (empty($email) || empty($senha)) {
    $_SESSION['login_error'] = 'Por favor, preencha o e-mail e a senha.';
    header('Location: ../../views/login.php');
    exit;
}

// --- Lógica de Autenticação ---
try {
    // Prepara a consulta SQL para buscar um cliente pelo e-mail.
    // Usar 'prepare' é uma prática de segurança fundamental para evitar injeção de SQL.
    $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM clientes WHERE email = ?");
    
    // Executa a consulta, passando o e-mail do formulário como parâmetro.
    $stmt->execute([$email]);
    
    // Busca o resultado da consulta. Se nenhum usuário for encontrado, o resultado será 'false'.
    $cliente = $stmt->fetch();

    // Verifica se um cliente foi encontrado E se a senha digitada corresponde à hash salva no banco.
    if ($cliente && password_verify($senha, $cliente['senha'])) {
        // Se a senha estiver correta, a autenticação foi bem-sucedida.
        
        // Limpa qualquer erro de login anterior.
        unset($_SESSION['login_error']);
        
        // Armazena informações importantes do usuário na sessão.
        $_SESSION['user_id'] = $cliente['id'];
        $_SESSION['user_name'] = $cliente['nome'];
        $_SESSION['logged_in'] = true;
        
        // Redireciona o usuário para o painel principal do cliente.
        // Criaremos este arquivo no próximo passo.
        header('Location: ../../views/client/dashboard.php');
        exit;
    }

    // 2. Se não for cliente, tenta fazer login como ADMINISTRADOR
    $stmt = $pdo->prepare("SELECT id, nome, senha FROM administradores WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        // Login de ADMINISTRADOR bem-sucedido
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_role'] = 'admin'; // Define o papel do usuário
        $_SESSION['logged_in'] = true;

        header('Location: ' . BASE_URL . 'views/admin/dashboard.php'); // Redireciona para o painel do admin
        exit;
    } else {
        // Se o cliente não foi encontrado ou a senha está incorreta.
        $_SESSION['login_error'] = 'E-mail ou senha inválidos.';
        header('Location: ../../views/login.php');
        exit;
    }

} catch (\PDOException $e) {
    // Em caso de um erro com o banco de dados.
    $_SESSION['login_error'] = 'Ocorreu um erro no servidor. Tente novamente mais tarde.';
    // Para depuração: error_log("Erro no login: " . $e->getMessage());
    header('Location: ../../views/login.php');
    exit;
}