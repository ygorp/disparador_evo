<?php
require_once 'partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Busca todos os planos para exibir na tabela
$planos = $pdo->query("SELECT * FROM planos ORDER BY tipo, preco, creditos_disparo")->fetchAll(PDO::FETCH_ASSOC);
?>

<div x-data="{ 
    isModalOpen: false, 
    isEditMode: false,
    planData: { 
        id: null, 
        nome: '', 
        descricao: '', 
        tipo: 'disparo', 
        preco: '0.00', 
        creditos: 0, 
        duracao_dias: 0, 
        ativo: 1 
    } 
}">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Gerenciamento de Planos</h1>
        <button @click="isModalOpen = true; isEditMode = false; planData = { id: null, nome: '', descricao: '', tipo: 'disparo', preco: '0.00', creditos: 0, duracao_dias: 0, ativo: 1 }" class="bg-verde-sucesso text-white font-bold px-4 py-2 rounded-md hover:bg-green-600 transition">+ Novo Plano</button>
    </div>

    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-4">Nome</th>
                        <th class="p-4">Tipo</th>
                        <th class="p-4">Preço/Custo</th>
                        <th class="p-4">Créditos/Dias</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($planos)): ?>
                        <tr><td colspan="6" class="text-center p-6 text-gray-400">Nenhum plano cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($planos as $plano): ?>
                        <tr class="border-b border-gray-700 hover:bg-fundo-principal">
                            <td class="p-4 text-white font-medium"><?= htmlspecialchars($plano['nome']) ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $plano['tipo'] == 'disparo' ? 'bg-roxo-principal text-white' : 'bg-blue-500 text-white' ?>">
                                    <?= htmlspecialchars(ucfirst($plano['tipo'])) ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-300">R$ <?= number_format($plano['preco'], 2, ',', '.') ?></td>
                            <td class="p-4 text-gray-300"><?= $plano['tipo'] == 'disparo' ? number_format($plano['creditos_disparo'], 0, ',', '.') . ' créditos' : $plano['duracao_dias'] . ' dias' ?></td>
                            <td class="p-4"><?= $plano['ativo'] ? '<span class="text-green-400">Ativo</span>' : '<span class="text-red-400">Inativo</span>' ?></td>
                            <td class="p-4">
                                <button @click="isModalOpen = true; isEditMode = true; planData = JSON.parse('<?= htmlspecialchars(json_encode($plano), ENT_QUOTES) ?>')" class="text-sm text-yellow-400 hover:underline">Editar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-lg">
            <h2 class="text-2xl font-bold text-white mb-6" x-text="isEditMode ? 'Editar Plano' : 'Criar Novo Plano'"></h2>
            <form action="../../src/controllers/admin_controller.php" method="POST">
                <input type="hidden" name="action" :value="isEditMode ? 'update_plan' : 'create_plan'">
                <input type="hidden" name="id" x-model="planData.id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label for="nome" class="block text-sm font-medium text-gray-300">Nome do Plano</label>
                        <input type="text" name="nome" x-model="planData.nome" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md" required>
                    </div>
                    <div class="col-span-2">
                        <label for="descricao" class="block text-sm font-medium text-gray-300">Descrição</label>
                        <textarea name="descricao" x-model="planData.descricao" rows="2" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md"></textarea>
                    </div>
                    <div>
                        <label for="tipo" class="block text-sm font-medium text-gray-300">Tipo de Plano</label>
                        <select name="tipo" x-model="planData.tipo" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md">
                            <option value="disparo">Disparo (Pacote de Créditos)</option>
                            <option value="maturacao">Maturação (Plano por Dias)</option>
                        </select>
                    </div>
                     <div>
                        <label for="preco" class="block text-sm font-medium text-gray-300">Preço (R$)</label>
                        <input type="number" step="0.01" name="preco" x-model="planData.preco" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md" required>
                    </div>

                    <div x-show="planData.tipo === 'disparo'">
                        <label for="creditos" class="block text-sm font-medium text-gray-300">Quantidade de Créditos</label>
                        <input type="number" step="1" name="creditos" x-model="planData.creditos" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md">
                    </div>

                    <div x-show="planData.tipo === 'maturacao'">
                        <label for="duracao_dias" class="block text-sm font-medium text-gray-300">Duração (dias)</label>
                        <input type="number" step="1" name="duracao_dias" x-model="planData.duracao_dias" class="w-full mt-1 p-2 bg-fundo-principal text-white border-gray-600 border rounded-md">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-700">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-700 transition">Cancelar</button>
                    <button type="submit" class="bg-roxo-principal text-white font-bold px-4 py-2 rounded-md hover:bg-purple-600 transition">Salvar Plano</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once 'partials/footer.php'; ?>