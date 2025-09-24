<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../views/login.php');
    exit;
}
$userName = $_SESSION['user_name'] ?? 'Usuário';

// Detecta a página atual para highlight do menu
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Cliente - Discador.net</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fundo-principal': '#1a1a2e',
                        'card-fundo': '#2a2a45',
                        'azul-principal': '#1a2533',
                        'azul-acento': '#30a8f0',
                        'verde-sucesso': '#22c55e',
                        'vermelho-erro': '#ef4444',
                        'amarelo-atencao': '#f59e0b',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-fundo-principal text-gray-200">

    <div id="app">
        <header class="bg-card-fundo shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <!-- Logo e Navegação -->
                    <div class="flex items-center space-x-8">
                        <div class="flex items-center space-x-3">
                            <img src="../../assets/images/logo.png" alt="Logo Discador.net" class="h-10">
                        </div>
                        <nav class="hidden lg:flex items-center space-x-6">
                            <a href="<?php echo BASE_URL; ?>views/client/dashboard.php" class="<?= $current_page === 'dashboard' ? 'text-azul-acento font-semibold border-b-2 border-azul-acento pb-1' : 'text-gray-300 hover:text-white transition duration-200' ?>">
                                Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>views/client/instancias.php" class="<?= $current_page === 'instancias' ? 'text-azul-acento font-semibold border-b-2 border-azul-acento pb-1' : 'text-gray-300 hover:text-white transition duration-200' ?>">
                                Instâncias
                            </a>
                            <a href="<?php echo BASE_URL; ?>views/client/maturacao.php" class="<?= $current_page === 'maturacao' ? 'text-azul-acento font-semibold border-b-2 border-azul-acento pb-1' : 'text-gray-300 hover:text-white transition duration-200' ?>">
                                Maturação
                            </a>
                            <a href="<?php echo BASE_URL; ?>views/client/disparos.php" class="<?= $current_page === 'disparos' ? 'text-green-400 font-semibold border-b-2 border-green-400 pb-1' : 'text-gray-300 hover:text-white transition duration-200' ?>">
                                Disparos
                            </a>
                            <a href="extrato.php" class="<?= $current_page === 'extrato' ? 'text-azul-acento font-semibold border-b-2 border-azul-acento pb-1' : 'text-gray-300 hover:text-white transition duration-200' ?>">
                                Extrato
                            </a>
                        </nav>
                    </div>

                    <!-- Menu do Usuário -->
                    <div class="flex items-center space-x-4">                    
                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center space-x-2 focus:outline-none">
                                <div class="flex items-center space-x-2 bg-fundo-principal px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                                    <div class="w-8 h-8 bg-azul-principal rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-semibold"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                                    </div>
                                    <span class="text-white font-medium hidden md:block"><?= htmlspecialchars(explode(' ', $userName)[0]) ?></span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </button>
                            <div id="user-menu" class="absolute right-0 mt-2 w-56 bg-card-fundo rounded-lg shadow-xl py-2 hidden z-50 border border-gray-600">
                                <div class="px-4 py-3 border-b border-gray-600">
                                    <p class="text-sm font-medium text-white"><?= htmlspecialchars($userName) ?></p>
                                    <p class="text-xs text-gray-400">Cliente Premium</p>
                                </div>
                                <a href="<?php echo BASE_URL; ?>views/client/comprar_planos.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-fundo-principal hover:text-white transition">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                    Comprar Créditos
                                </a>
                                <a href="extrato.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-fundo-principal hover:text-white transition">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Extrato Financeiro
                                </a>
                                <a href="../client/configuracao.php" class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-fundo-principal hover:text-white transition">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Configurações
                                </a>
                                <div class="border-t border-gray-600 my-2"></div>
                                <a href="https://wa.me/552734415852?text=Ol%C3%A1%2C%20gostaria%20de%20ajudar%20sobre%20o%20sistema%20de%20matura%C3%A7%C3%A3o%20e%20disparo." class="flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-fundo-principal hover:text-white transition">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25A9.75 9.75 0 002.25 12 9.75 9.75 0 0012 21.75 9.75 9.75 0 0021.75 12 9.75 9.75 0 0012 2.25z"></path>
                                    </svg>
                                    Suporte
                                </a>
                                <a href="<?php echo BASE_URL; ?>src/controllers/logout_controller.php" class="flex items-center px-4 py-2 text-sm text-red-400 hover:bg-fundo-principal hover:text-red-300 transition">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sair
                                </a>
                            </div>
                        </div>

                        <!-- Menu Mobile Toggle -->
                        <div class="lg:hidden">
                            <button id="mobile-menu-button" class="text-gray-400 hover:text-white focus:outline-none focus:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Menu Mobile -->
                <div id="mobile-menu" class="lg:hidden mt-4 pb-4 border-t border-gray-600 hidden">
                    <div class="flex flex-col space-y-2 pt-4">
                        <a href="<?php echo BASE_URL; ?>views/client/dashboard.php" class="<?= $current_page === 'dashboard' ? 'text-azul-principal font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>views/client/instancias.php" class="<?= $current_page === 'instancias' ? 'text-azul-principal font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Instâncias</a>
                        <a href="<?php echo BASE_URL; ?>views/client/maturacao.php" class="<?= $current_page === 'maturacao' ? 'text-blue-400 font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Maturação</a>
                        <a href="<?php echo BASE_URL; ?>views/client/disparos.php" class="<?= $current_page === 'disparos' ? 'text-green-400 font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Disparos</a>
                        <a href="extrato.php" class="<?= $current_page === 'extrato' ? 'text-azul-principal font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Extrato</a>
                        <a href="tutorial.php" class="<?= $current_page === 'tutorial' ? 'text-azul-principal font-semibold' : 'text-gray-300 hover:text-white' ?> block py-2">Tutorial</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="container mx-auto p-6">