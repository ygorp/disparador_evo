<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Inclui o autoload do Composer para carregar o PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';

// Importa as classes do PHPMailer para o escopo global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = $_POST['action'] ?? '';

if ($action === 'request_reset') {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Por favor, insira um e-mail válido.";
        header('Location: ' . BASE_URL . 'views/client/esqueci_senha.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nome FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Gera um token seguro e único
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = (new DateTime())->add(new DateInterval('PT1H'))->format('Y-m-d H:i:s');

            // Salva o token HASHED e a data de expiração no banco
            $stmt_update = $pdo->prepare("UPDATE clientes SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $stmt_update->execute([$token_hash, $expires_at, $user['id']]);

            // --- LÓGICA DE ENVIO DE E-MAIL COM PHPMailer ---
            $reset_link = BASE_URL . 'views/client/resetar_senha.php?token=' . $token;
            
            $mail = new PHPMailer(true);
            try {
                // Configurações do servidor SMTP
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Ou PHPMailer::ENCRYPTION_SMTPS
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';

                // Remetente e Destinatário
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($user['email'], $user['nome']);

                // Conteúdo do E-mail
                $mail->isHTML(true);
                $mail->Subject = 'Redefinicao de Senha - Discador.net';
                $mail->Body    = "Olá, " . htmlspecialchars($user['nome']) . "!<br><br>Recebemos uma solicitação para redefinir sua senha. Clique no link abaixo para criar uma nova senha:<br><br><a href='" . $reset_link . "'>Redefinir Minha Senha</a><br><br>Se você não solicitou isso, por favor, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe Discador.net";
                $mail->AltBody = "Olá, " . htmlspecialchars($user['nome']) . "!\n\nPara redefinir sua senha, copie e cole o seguinte link no seu navegador:\n" . $reset_link . "\n\nSe você não solicitou isso, por favor, ignore este e-mail.";

                $mail->send();
                
            } catch (Exception $e) {
                // Em caso de erro no envio do e-mail, guarda o erro, mas não informa ao usuário por segurança
                error_log("Erro no envio de e-mail: {$mail->ErrorInfo}");
            }
        }
    } catch (PDOException $e) {
        error_log("Erro de banco de dados: " . $e->getMessage());
    }
    
    // Por segurança, sempre mostramos a mesma mensagem, quer o e-mail exista ou não.
    $_SESSION['success_message'] = "Se um e-mail correspondente for encontrado em nosso sistema, um link de recuperação será enviado.";
    header('Location: ' . BASE_URL . 'views/client/esqueci_senha.php');
    exit;
}

// Futuramente, a lógica para de fato resetar a senha viria aqui, em outro 'elseif' para a ação 'perform_reset'
?>