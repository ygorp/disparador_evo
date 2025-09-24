<?php

require_once 'config/config.php';

// 2. Monta a URL completa para a página de login.
$login_url = BASE_URL . 'views/login.php';

// 3. Envia o cabeçalho de redirecionamento para o navegador.
header('Location: ' . $login_url);
exit;
?>