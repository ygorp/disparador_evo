<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';

// GUARDIÃO DO ADMIN: Protege a página, só permite acesso de administradores
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}
$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Admin - Discador.net</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Configuração de cores do Tailwind CSS
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fundo-principal': '#1a1a2e',
                        'card-fundo': '#2a2a45',
                        'roxo-principal': '#7e57c2',
                        'laranja-acento': '#ff7043',
                        'verde-sucesso': '#22c55e',
                        'vermelho-erro': '#ef4444',
                        'amarelo-atencao': '#f59e0b',
                        'azul-info': '#3b82f6'
                    }
                }
            }
        }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-fundo-principal text-gray-200">
    <div id="app">
        <header class="bg-card-fundo shadow-md">
            <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-3">
                        <img src="../../assets/images/logo.png" alt="Logo Discador.net" class="h-10">
                        <h1 class="text-xl font-bold text-white">PAINEL ADMIN</h1>
                    </div>
                    <nav class="hidden md:flex items-center space-x-6 text-gray-300">
                        <a href="dashboard.php" class="hover:text-white transition duration-200">Dashboard</a>
                        <a href="clientes.php" class="hover:text-white transition duration-200">Clientes</a>
                        <a href="instancias.php" class="hover:text-white transition duration-200">Instâncias</a>
                        <a href="planos.php" class="hover:text-white transition duration-200">Planos</a>
                    </nav>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white font-medium"><?= htmlspecialchars($adminName) ?></span>
                    <a href="<?= BASE_URL ?>src/controllers/logout_controller.php" class="text-sm text-roxo-principal hover:underline" title="Sair">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <main class="container mx-auto p-6">