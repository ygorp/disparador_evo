<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/database.php';

$campanha_id = $_GET['id'] ?? null;
$cliente_id = $_SESSION['user_id'];

if (!$campanha_id) {
    // Redireciona se nenhum ID de campanha foi fornecido
    header('Location: disparos.php');
    exit;
}

try {
    // Busca os dados da campanha, garantindo que pertence ao cliente logado
    $stmt_campanha = $pdo->prepare("SELECT * FROM campanhas WHERE id = ? AND cliente_id = ?");
    $stmt_campanha->execute([$campanha_id, $cliente_id]);
    $campanha = $stmt_campanha->fetch();

    if (!$campanha) {
        // Se a campanha não existe ou não pertence ao cliente, redireciona
        header('Location: disparos.php');
        exit;
    }

    // Calcula as estatísticas do relatório
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(id) as total,
            SUM(CASE WHEN status = 'Enviado' THEN 1 ELSE 0 END) as enviados,
            SUM(CASE WHEN status = 'Falhou' THEN 1 ELSE 0 END) as falhas,
            SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes
        FROM fila_envio WHERE campanha_id = ?
    ");
    $stmt_stats->execute([$campanha_id]);
    $stats = $stmt_stats->fetch();

    // Busca a lista detalhada da fila de envio para a tabela
    $stmt_fila = $pdo->prepare("SELECT numero_destino, status, data_envio FROM fila_envio WHERE campanha_id = ? ORDER BY id");
    $stmt_fila->execute([$campanha_id]);
    $fila_envio = $stmt_fila->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar o relatório: " . $e->getMessage());
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Relatório da Campanha</h1>
        <p class="text-2xl text-roxo-principal mt-1"><?= htmlspecialchars($campanha['nome_campanha']) ?></p>
    </div>
    <a href="disparos.php" class="bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
        &larr; Voltar para Campanhas
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Total na Lista</h2><p class="text-3xl font-bold text-white"><?= $stats['total'] ?? 0 ?></p></div>
    <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Enviados com Sucesso</h2><p class="text-3xl font-bold text-verde-sucesso"><?= $stats['enviados'] ?? 0 ?></p></div>
    <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Falhas no Envio</h2><p class="text-3xl font-bold text-vermelho-erro"><?= $stats['falhas'] ?? 0 ?></p></div>
    <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Pendentes</h2><p class="text-3xl font-bold text-yellow-600"><?= $stats['pendentes'] ?? 0 ?></p></div>
</div>

<div class="bg-card-fundo rounded-lg shadow-lg p-6">
    <h2 class="text-xl font-semibold text-white mb-4">Status de Envio por Contato</h2>
    <div class="overflow-y-auto max-h-[60vh]">
        <table class="w-full text-left">
            <thead>
                <tr class="text-gray-400 border-b border-gray-700">
                    <th class="p-4">Número de Destino</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Data do Envio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fila_envio)): ?>
                    <tr><td colspan="3" class="text-center p-6 text-gray-400">Nenhum contato na fila para esta campanha.</td></tr>
                <?php else: ?>
                    <?php foreach ($fila_envio as $item): ?>
                        <tr class="border-b border-gray-700">
                            <td class="p-4 font-medium text-white"><?= htmlspecialchars($item['numero_destino']) ?></td>
                            <td class="p-4">
                                <?php
                                    $status_item = $item['status'];
                                    $color_class_item = 'bg-gray-500';
                                    if ($status_item === 'Enviado') $color_class_item = 'bg-verde-sucesso';
                                    if ($status_item === 'Falhou') $color_class_item = 'bg-vermelho-erro';
                                    if ($status_item === 'Pendente') $color_class_item = 'bg-yellow-600';
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full text-white <?= $color_class_item ?>"><?= htmlspecialchars($status_item) ?></span>
                            </td>
                            <td class="p-4 text-gray-400"><?= $item['data_envio'] ? (new DateTime($item['data_envio']))->format('d/m/Y H:i') : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>