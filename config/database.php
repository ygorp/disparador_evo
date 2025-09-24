<?php

// --- Configurações do Banco de Dados ---
// Altere para as suas credenciais
$db_host = 'localhost';         // Geralmente 'localhost' ou o IP do seu servidor de banco de dados
$db_name = 'disparador'; // O nome do banco de dados que você criou
$db_user = 'root';              // Seu usuário do banco de dados
$db_pass = 'P@checo7292';                  // Sua senha do banco de dados
$db_charset = 'utf8mb4';

// --- DSN (Data Source Name) ---
// Define a string de conexão
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// --- Opções do PDO ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erros
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna os resultados como um array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desabilita a emulação de prepared statements para segurança
];

// --- Criação da Conexão PDO ---
try {
    // Tenta criar uma nova instância do PDO para conectar ao banco
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Se a conexão falhar, exibe uma mensagem de erro e encerra o script
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// A variável $pdo agora está disponível para ser usada em outros scripts que incluírem este arquivo.