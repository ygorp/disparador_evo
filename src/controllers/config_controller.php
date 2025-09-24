<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Verificação de segurança
if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'update_profile':
            // Atualizar perfil do cliente
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? ''); // Remove formatação
            
            // Validações
            if (empty($nome) || empty($email)) {
                throw new Exception("Nome e e-mail são obrigatórios.");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Formato de e-mail inválido.");
            }
            
            // Verifica se o email já está em uso por outro cliente
            $stmt_check = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $cliente_id]);
            if ($stmt_check->fetch()) {
                throw new Exception("Este e-mail já está sendo usado por outro cliente.");
            }
            
            // Validar telefone se fornecido
            if (!empty($telefone) && (strlen($telefone) < 10 || strlen($telefone) > 11)) {
                throw new Exception("Telefone inválido. Digite apenas números com DDD.");
            }
            
            // Atualiza os dados
            $stmt_update = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ? WHERE id = ?");
            $stmt_update->execute([$nome, $email, $telefone ?: null, $cliente_id]);
            
            // Atualiza o nome na sessão
            $_SESSION['user_name'] = $nome;
            
            $_SESSION['success_message'] = "Perfil atualizado com sucesso!";
            break;
            
        case 'change_password':
            // Alterar senha
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validações
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("Todos os campos de senha são obrigatórios.");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("A nova senha deve ter no mínimo 6 caracteres.");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("A confirmação da nova senha não confere.");
            }
            
            // Busca a senha atual do cliente
            $stmt_senha = $pdo->prepare("SELECT senha FROM clientes WHERE id = ?");
            $stmt_senha->execute([$cliente_id]);
            $cliente = $stmt_senha->fetch();
            
            if (!$cliente || !password_verify($current_password, $cliente['senha'])) {
                throw new Exception("Senha atual incorreta.");
            }
            
            // Atualiza a senha
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update_senha = $pdo->prepare("UPDATE clientes SET senha = ? WHERE id = ?");
            $stmt_update_senha->execute([$new_password_hash, $cliente_id]);
            
            $_SESSION['success_message'] = "Senha alterada com sucesso!";
            break;
            
        case 'update_notifications':
            // Atualizar preferências de notificação
            // Por enquanto, vamos criar uma estrutura básica
            // Em uma implementação futura, você pode criar uma tabela específica para isso
            
            $notify_payments = isset($_POST['notify_payments']) ? 1 : 0;
            $notify_campaigns = isset($_POST['notify_campaigns']) ? 1 : 0;
            $notify_instances = isset($_POST['notify_instances']) ? 1 : 0;
            $notify_maturation = isset($_POST['notify_maturation']) ? 1 : 0;
            $weekly_report = isset($_POST['weekly_report']) ? 1 : 0;
            $monthly_report = isset($_POST['monthly_report']) ? 1 : 0;
            
            // Por enquanto, vamos salvar na sessão ou criar uma tabela específica
            // Aqui você pode implementar a lógica para salvar as preferências
            
            $_SESSION['success_message'] = "Preferências de notificação atualizadas com sucesso!";
            break;
            
        default:
            throw new Exception("Ação não reconhecida.");
    }
    
} catch (Exception $e) {
    error_log("Erro na configuração: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
}

// Redireciona de volta para a página de configurações
header('Location: ' . BASE_URL . 'views/client/configuracoes.php');
exit;
?>