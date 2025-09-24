<?php
// Inicia a sessão para poder manipulá-la
session_start();

// 'unset' remove todas as variáveis da sessão
session_unset();

// 'destroy' destrói a sessão em si
session_destroy();

// Redireciona o usuário de volta para a página de login
header("Location: ../../views/login.php");
exit();
?>