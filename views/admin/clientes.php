<?php
require_once 'partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Busca todos os clientes para exibir na tabela
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div x-data="{ 
    isAddClientModalOpen: false, 
    isAddCreditsModalOpen: false,
    isEditClientModalOpen: false,
    currentClient: {} 
}">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Gerenciamento de Clientes</h1>
        <button @click="isAddClientModalOpen = true" class="bg-verde-sucesso text-white font-bold px-4 py-2 rounded-md hover:bg-green-600 transition">+ Novo Cliente</button>
    </div>

    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-4">Nome</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">Créditos Disparo</th>
                        <th class="p-4">Créditos Maturação</th>
                        <th class="p-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr><td colspan="5" class="text-center p-6 text-gray-400">Nenhum cliente cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="border-b border-gray-700 hover:bg-fundo-principal">
                            <td class="p-4 text-white font-medium"><?= htmlspecialchars($cliente['nome']) ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($cliente['email']) ?></td>
                            <td class="p-4 text-gray-300"><?= number_format($cliente['saldo_creditos_disparo'], 0, ',', '.') ?></td>
                            <td class="p-4 text-gray-300"><?= number_format($cliente['saldo_creditos_maturacao'], 2, ',', '.') ?></td>
                            <td class="p-4">
                                <button @click="isAddCreditsModalOpen = true; currentClient = <?= htmlspecialchars(json_encode($cliente)) ?>" class="bg-azul-info text-white px-3 py-1 rounded-md text-sm hover:bg-blue-600">
                                    Adicionar Créditos
                                </button>
                                <button @click="isEditClientModalOpen = true; currentClient = <?= htmlspecialchars(json_encode($cliente)) ?>" class="bg-yellow-600 text-white px-3 py-1 rounded-md text-sm hover:bg-yellow-700">Editar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isAddClientModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isAddClientModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-white mb-6">Cadastrar Novo Cliente</h2>
            <form action="../../src/controllers/admin_controller.php" method="POST">
                <input type="hidden" name="action" value="create_client">
                <div class="mb-4">
                    <label for="nome" class="block text-gray-300 text-sm font-medium mb-2">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-6">
                    <label for="senha" class="block text-gray-300 text-sm font-medium mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" @click="isAddClientModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-700 transition">Cancelar</button>
                    <button type="submit" class="bg-roxo-principal text-white font-bold px-4 py-2 rounded-md hover:bg-purple-600 transition">Cadastrar Cliente</button>
                </div>
            </form>
        </div>
    </div>
    
    <div x-show="isAddCreditsModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isAddCreditsModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-white mb-4">Adicionar Créditos para <span x-text="currentClient.nome" class="text-roxo-principal"></span></h2>
            <form action="../../src/controllers/admin_controller.php" method="POST">
                <input type="hidden" name="action" value="add_credits">
                <input type="hidden" name="cliente_id" :value="currentClient.id">
                <div class="mb-4">
                    <label for="creditos_disparo" class="block text-gray-300 text-sm font-medium mb-2">Créditos de Disparo</label>
                    <input type="number" step="1" min="0" id="creditos_disparo" name="creditos_disparo" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md" placeholder="0">
                </div>
                <div class="mb-6">
                    <label for="creditos_maturacao" class="block text-gray-300 text-sm font-medium mb-2">Créditos de Maturação</label>
                    <input type="number" step="0.01" min="0" id="creditos_maturacao" name="creditos_maturacao" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md" placeholder="0.00">
                </div>
                 <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" @click="isAddCreditsModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-700 transition">Cancelar</button>
                    <button type="submit" class="bg-roxo-principal text-white font-bold px-4 py-2 rounded-md hover:bg-purple-600 transition">Adicionar Créditos</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="isEditClientModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isEditClientModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-white mb-6">Editar Cliente</h2>
            <form action="../../src/controllers/admin_controller.php" method="POST">
                <input type="hidden" name="action" value="update_client">
                <input type="hidden" name="id" :value="currentClient.id">
                <div class="mb-4">
                    <label for="edit_nome" class="block text-gray-300 text-sm font-medium mb-2">Nome Completo</label>
                    <input type="text" id="edit_nome" name="nome" x-model="currentClient.nome" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-4">
                    <label for="edit_email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                    <input type="email" id="edit_email" name="email" x-model="currentClient.email" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-6">
                    <label for="edit_senha" class="block text-gray-300 text-sm font-medium mb-2">Nova Senha</label>
                    <input type="password" id="edit_senha" name="senha" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" placeholder="Preencha apenas para alterar">
                    <p class="text-xs text-gray-500 mt-1">Se deixar em branco, a senha atual será mantida.</p>
                </div>
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-700">
                    <button type="button" @click="isEditClientModalOpen = false" class="px-6 py-2 rounded-md text-gray-300 hover:bg-gray-700 transition">Cancelar</button>
                    <button type="submit" class="bg-roxo-principal text-white font-bold px-6 py-2 rounded-md hover:bg-purple-600 transition">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>