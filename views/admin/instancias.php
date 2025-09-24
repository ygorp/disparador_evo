<?php
require_once 'partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Busca todas as instâncias e faz um JOIN com a tabela de clientes para pegar o nome do proprietário
try {
    $stmt = $pdo->query("
        SELECT 
            i.id, 
            i.nome_instancia, 
            i.instance_name_api, 
            i.status, 
            i.data_criacao, 
            c.nome as nome_cliente 
        FROM instancias i
        JOIN clientes c ON i.cliente_id = c.id
        ORDER BY i.data_criacao DESC
    ");
    $instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar instâncias: " . $e->getMessage());
}
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-white">Gerenciamento de Todas as Instâncias</h1>
</div>

<div class="bg-card-fundo rounded-lg shadow-lg p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-gray-400 border-b border-gray-700">
                    <th class="p-4">Nome da Instância</th>
                    <th class="p-4">Proprietário (Cliente)</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Data de Criação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instancias)): ?>
                    <tr><td colspan="4" class="text-center p-6 text-gray-400">Nenhuma instância encontrada no sistema.</td></tr>
                <?php else: ?>
                    <?php foreach ($instancias as $instancia): ?>
                        <tr class="border-b border-gray-700">
                            <td class="p-4 text-white font-medium"><?= htmlspecialchars($instancia['nome_instancia']) ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($instancia['nome_cliente']) ?></td>
                            <td class="p-4">
                                <?php
                                    $status_class = ($instancia['status'] === 'Conectado') ? 'bg-verde-sucesso' : 'bg-vermelho-erro';
                                ?>
                                <span class="px-3 py-1 text-xs rounded-full text-white <?= $status_class ?>">
                                    <?= htmlspecialchars($instancia['status']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-300"><?= (new DateTime($instancia['data_criacao']))->format('d/m/Y H:i') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>