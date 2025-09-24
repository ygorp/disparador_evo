<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/database.php';

$cliente_id = $_SESSION['user_id'];

// Lógica de Filtro e Paginação
$data_inicial = $_GET['data_inicial'] ?? '';
$data_final = $_GET['data_final'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$limit = 20; // Aumentado para 20 itens por página

// Constrói a query SQL com base nos filtros
$sql = "SELECT t.*, p.nome as nome_plano 
        FROM transacoes t 
        LEFT JOIN planos p ON t.plano_id = p.id 
        WHERE t.cliente_id = ?";
$params = [$cliente_id];

if ($data_inicial) {
    $sql .= " AND DATE(t.data_transacao) >= ?";
    $params[] = $data_inicial;
}
if ($data_final) {
    $sql .= " AND DATE(t.data_transacao) <= ?";
    $params[] = $data_final;
}
if ($status_filtro) {
    $sql .= " AND t.status_pagamento = ?";
    $params[] = $status_filtro;
}
$sql .= " ORDER BY t.data_transacao DESC LIMIT $limit";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transacoes = $stmt->fetchAll();
    
    // Busca estatísticas gerais
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transacoes,
            SUM(CASE WHEN status_pagamento = 'Pago' THEN valor ELSE 0 END) as total_pago,
            SUM(CASE WHEN status_pagamento = 'Pendente' THEN valor ELSE 0 END) as total_pendente,
            COUNT(CASE WHEN status_pagamento = 'Pago' THEN 1 END) as transacoes_aprovadas,
            COUNT(CASE WHEN status_pagamento IN ('Cancelado', 'Vencido') THEN 1 END) as transacoes_negadas
        FROM transacoes 
        WHERE cliente_id = ?
    ");
    $stmt_stats->execute([$cliente_id]);
    $stats = $stmt_stats->fetch();
    
} catch (PDOException $e) {
    die("Erro ao buscar transações: " . $e->getMessage());
}

?>

<div class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold text-white mb-6">Extrato Financeiro</h1>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
            <h3 class="text-gray-400 text-sm font-medium">Total de Transações</h3>
            <p class="text-2xl font-bold text-white"><?= $stats['total_transacoes'] ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-green-500">
            <h3 class="text-gray-400 text-sm font-medium">Pagamentos Aprovados</h3>
            <p class="text-2xl font-bold text-white"><?= $stats['transacoes_aprovadas'] ?></p>
            <p class="text-sm text-green-400">R$ <?= number_format($stats['total_pago'], 2, ',', '.') ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
            <h3 class="text-gray-400 text-sm font-medium">Pagamentos Pendentes</h3>
            <p class="text-2xl font-bold text-white">R$ <?= number_format($stats['total_pendente'], 2, ',', '.') ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-red-500">
            <h3 class="text-gray-400 text-sm font-medium">Negados/Vencidos</h3>
            <p class="text-2xl font-bold text-white"><?= $stats['transacoes_negadas'] ?></p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-card-fundo p-6 rounded-lg shadow-lg mb-8">
        <form method="GET" action="extrato.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="data_inicial" class="block text-sm text-gray-400 mb-2">Data Inicial</label>
                <input type="date" name="data_inicial" id="data_inicial" value="<?= htmlspecialchars($data_inicial) ?>" 
                       class="w-full bg-fundo-principal text-white rounded-md p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-roxo-principal">
            </div>
            <div>
                <label for="data_final" class="block text-sm text-gray-400 mb-2">Data Final</label>
                <input type="date" name="data_final" id="data_final" value="<?= htmlspecialchars($data_final) ?>" 
                       class="w-full bg-fundo-principal text-white rounded-md p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-roxo-principal">
            </div>
            <div>
                <label for="status" class="block text-sm text-gray-400 mb-2">Status</label>
                <select name="status" id="status" class="w-full bg-fundo-principal text-white rounded-md p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-roxo-principal">
                    <option value="">Todos os Status</option>
                    <option value="Pago" <?= $status_filtro === 'Pago' ? 'selected' : '' ?>>✅ Pago</option>
                    <option value="Pendente" <?= $status_filtro === 'Pendente' ? 'selected' : '' ?>>⏳ Pendente</option>
                    <option value="Cancelado" <?= $status_filtro === 'Cancelado' ? 'selected' : '' ?>>❌ Cancelado</option>
                    <option value="Vencido" <?= $status_filtro === 'Vencido' ? 'selected' : '' ?>>⏰ Vencido</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-roxo-principal text-white px-6 py-3 rounded-md hover:bg-purple-600 transition font-medium">
                    🔍 Filtrar
                </button>
                <a href="extrato.php" class="bg-gray-600 text-white px-4 py-3 rounded-md hover:bg-gray-700 transition">
                    🔄
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de Transações -->
    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-white">Histórico de Transações</h2>
            <div class="text-sm text-gray-400">
                Mostrando últimas <?= count($transacoes) ?> transações
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-4">Data/Hora</th>
                        <th class="p-4">Descrição</th>
                        <th class="p-4">Método</th>
                        <th class="p-4 text-right">Valor</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transacoes)): ?>
                        <tr>
                            <td colspan="6" class="text-center p-8 text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    <p class="text-lg font-medium mb-2">Nenhuma transação encontrada</p>
                                    <p class="text-sm text-gray-500">Experimente alterar os filtros ou fazer sua primeira compra</p>
                                    <a href="comprar_planos.php" class="mt-4 bg-roxo-principal text-white px-4 py-2 rounded-md hover:bg-purple-600 transition">
                                        Comprar Créditos
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transacoes as $transacao): ?>
                            <tr class="border-b border-gray-700 hover:bg-fundo-principal transition">
                                <td class="p-4">
                                    <div class="text-white font-medium"><?= (new DateTime($transacao['data_transacao']))->format('d/m/Y') ?></div>
                                    <div class="text-gray-400 text-sm"><?= (new DateTime($transacao['data_transacao']))->format('H:i:s') ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="text-white font-medium">
                                        <?php 
                                            $descricao = "Transação";
                                            if ($transacao['tipo_transacao'] === 'recarga_disparo') {
                                                $descricao = "📤 Compra de Créditos de Disparo";
                                            } elseif ($transacao['tipo_transacao'] === 'recarga_maturacao') {
                                                $descricao = "🔥 Compra de Créditos de Maturação";
                                            } elseif ($transacao['tipo_transacao'] === 'compra_maturacao') {
                                                $descricao = "⚡ Uso de Créditos - " . htmlspecialchars($transacao['nome_plano'] ?? 'Maturação');
                                            } elseif ($transacao['tipo_transacao'] === 'compra_disparo') {
                                                $descricao = "📨 Uso de Créditos - Disparo";
                                            }
                                            echo $descricao;
                                        ?>
                                    </div>
                                    <?php if ($transacao['nome_plano']): ?>
                                        <div class="text-gray-400 text-sm"><?= htmlspecialchars($transacao['nome_plano']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($transacao['creditos_quantidade']): ?>
                                        <div class="text-blue-400 text-sm"><?= number_format($transacao['creditos_quantidade'], 0, ',', '.') ?> créditos</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($transacao['metodo_pagamento']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $transacao['metodo_pagamento'] === 'PIX' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                            <?= $transacao['metodo_pagamento'] === 'PIX' ? '⚡ PIX' : '🧾 Boleto' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-sm">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="font-semibold <?= $transacao['valor'] > 0 ? 'text-green-400' : 'text-red-400' ?>">
                                        <?= ($transacao['valor'] > 0 ? '+R$ ' : '-R$ ') . number_format(abs($transacao['valor']), 2, ',', '.') ?>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <?php
                                        $status_info = [
                                            'Pago' => ['class' => 'bg-green-500', 'icon' => '✅', 'text' => 'Pago'],
                                            'Pendente' => ['class' => 'bg-yellow-500', 'icon' => '⏳', 'text' => 'Pendente'],
                                            'Cancelado' => ['class' => 'bg-red-500', 'icon' => '❌', 'text' => 'Cancelado'],
                                            'Vencido' => ['class' => 'bg-red-600', 'icon' => '⏰', 'text' => 'Vencido'],
                                            'Processando' => ['class' => 'bg-blue-500', 'icon' => '🔄', 'text' => 'Processando']
                                        ];
                                        $status = $status_info[$transacao['status_pagamento']] ?? ['class' => 'bg-gray-500', 'icon' => '❓', 'text' => $transacao['status_pagamento']];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white <?= $status['class'] ?>">
                                        <?= $status['icon'] ?> <?= $status['text'] ?>
                                    </span>
                                    
                                    <?php if ($transacao['data_pagamento'] && $transacao['status_pagamento'] === 'Pago'): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Confirmado em <?= (new DateTime($transacao['data_pagamento']))->format('d/m H:i') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if ($transacao['asaas_payment_id']): ?>
                                        <div class="flex flex-col space-y-1">
                                            <span class="text-xs text-gray-500">ID: <?= substr($transacao['asaas_payment_id'], 0, 8) ?>...</span>
                                            <?php if ($transacao['status_pagamento'] === 'Pendente'): ?>
                                                <a href="pagamento.php?transacao_id=<?= $transacao['id'] ?>&method=<?= strtolower($transacao['metodo_pagamento']) ?>" 
                                                   class="text-xs text-roxo-principal hover:underline">
                                                    🔗 Ver Cobrança
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">Sistema</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($transacoes) >= $limit): ?>
            <div class="mt-6 text-center">
                <p class="text-gray-400 text-sm">Mostrando as <?= $limit ?> transações mais recentes</p>
                <p class="text-xs text-gray-500 mt-1">Use os filtros para encontrar transações específicas</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../partials/footer.php'; ?>