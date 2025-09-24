<?php
require_once 'partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Busca estatísticas gerais
$total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$total_instancias = $pdo->query("SELECT COUNT(*) FROM instancias")->fetchColumn();
$total_campanhas = $pdo->query("SELECT COUNT(*) FROM campanhas WHERE status = 'Enviando'")->fetchColumn();
?>

<h1 class="text-3xl font-bold text-white mb-6">Visão Geral do Sistema</h1>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-card-fundo p-6 rounded-lg"><h2 class="text-gray-400">Total de Clientes</h2><p class="text-3xl font-bold"><?= $total_clientes ?></p></div>
    <div class="bg-card-fundo p-6 rounded-lg"><h2 class="text-gray-400">Total de Instâncias</h2><p class="text-3xl font-bold"><?= $total_instancias ?></p></div>
    <div class="bg-card-fundo p-6 rounded-lg"><h2 class="text-gray-400">Campanhas Ativas</h2><p class="text-3xl font-bold"><?= $total_campanhas ?></p></div>
</div>

<?php require_once 'partials/footer.php'; ?>