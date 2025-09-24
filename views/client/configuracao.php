<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/database.php';

$cliente_id = $_SESSION['user_id'];

// Busca dados do cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<div class="max-w-4xl mx-auto" x-data="{ 
    activeTab: 'perfil',
    isPasswordChanging: false,
    showCurrentPassword: false,
    showNewPassword: false,
    showConfirmPassword: false
}">

    <h1 class="text-3xl font-bold text-white mb-6">Configurações da Conta</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="bg-green-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="bg-red-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Abas de Navegação -->
    <div class="bg-card-fundo rounded-lg shadow-lg mb-6">
        <div class="flex border-b border-gray-600">
            <button @click="activeTab = 'perfil'" 
                    :class="activeTab === 'perfil' ? 'text-roxo-principal border-roxo-principal' : 'text-gray-400 border-transparent hover:text-white'"
                    class="px-6 py-4 border-b-2 font-medium transition">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>Perfil</span>
                </div>
            </button>
            
            <button @click="activeTab = 'seguranca'" 
                    :class="activeTab === 'seguranca' ? 'text-roxo-principal border-roxo-principal' : 'text-gray-400 border-transparent hover:text-white'"
                    class="px-6 py-4 border-b-2 font-medium transition">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Segurança</span>
                </div>
            </button>
            
            <button @click="activeTab = 'notificacoes'" 
                    :class="activeTab === 'notificacoes' ? 'text-roxo-principal border-roxo-principal' : 'text-gray-400 border-transparent hover:text-white'"
                    class="px-6 py-4 border-b-2 font-medium transition">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zm-2-14l-6 6v10h10v-6l6-6h-10z"></path>
                    </svg>
                    <span>Notificações</span>
                </div>
            </button>
        </div>
    </div>

    <!-- Conteúdo das Abas -->
    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        
        <!-- Aba Perfil -->
        <div x-show="activeTab === 'perfil'" x-transition>
            <h2 class="text-xl font-semibold text-white mb-6">Informações do Perfil</h2>
            
            <form action="../../src/controllers/config_controller.php" method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nome" class="block text-gray-300 text-sm font-medium mb-2">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($cliente['nome']) ?>"
                               class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal"
                               required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>"
                               class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal"
                               required>
                        <p class="text-xs text-gray-500 mt-1">Usado para login e recebimento de notificações</p>
                    </div>
                    
                    <div>
                        <label for="cpf" class="block text-gray-300 text-sm font-medium mb-2">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($cliente['cpf']) ?>"
                               class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal"
                               readonly>
                        <p class="text-xs text-gray-500 mt-1">CPF não pode ser alterado após cadastro</p>
                    </div>
                    
                    <div>
                        <label for="telefone" class="block text-gray-300 text-sm font-medium mb-2">Telefone</label>
                        <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone']) ?>"
                               class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal"
                               placeholder="(11) 99999-9999">
                    </div>
                </div>
                
                <!-- Informações da Conta -->
                <div class="mt-8 pt-6 border-t border-gray-600">
                    <h3 class="text-lg font-semibold text-white mb-4">Informações da Conta</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-fundo-principal p-4 rounded-lg">
                            <div class="text-sm text-gray-400">Data de Cadastro</div>
                            <div class="text-lg font-semibold text-white">
                                <?= (new DateTime($cliente['data_cadastro']))->format('d/m/Y') ?>
                            </div>
                        </div>
                        <div class="bg-fundo-principal p-4 rounded-lg">
                            <div class="text-sm text-gray-400">Créditos Disparo</div>
                            <div class="text-lg font-semibold text-green-400">
                                <?= number_format($cliente['saldo_creditos_disparo'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="bg-fundo-principal p-4 rounded-lg">
                            <div class="text-sm text-gray-400">Créditos Maturação</div>
                            <div class="text-lg font-semibold text-blue-400">
                                <?= number_format($cliente['saldo_creditos_maturacao'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8">
                    <button type="submit" class="bg-azul-acento text-white font-bold py-3 px-6 rounded-md hover:bg-blue-600 transition">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>

        <!-- Aba Segurança -->
        <div x-show="activeTab === 'seguranca'" x-transition>
            <h2 class="text-xl font-semibold text-white mb-6">Segurança da Conta</h2>
            
            <!-- Alterar Senha -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Alterar Senha</h3>
                    <button @click="isPasswordChanging = !isPasswordChanging"
                            class="text-azul-acento hover:text-blue-400 text-sm font-medium">
                        <span x-show="!isPasswordChanging">Alterar Senha</span>
                        <span x-show="isPasswordChanging">Cancelar</span>
                    </button>
                </div>
                
                <div x-show="isPasswordChanging" x-transition class="space-y-4">
                    <form action="../../src/controllers/config_controller.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="relative">
                                <label class="block text-gray-300 text-sm font-medium mb-2">Senha Atual</label>
                                <input :type="showCurrentPassword ? 'text' : 'password'" name="current_password"
                                       class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal pr-10"
                                       required>
                                <button type="button" @click="showCurrentPassword = !showCurrentPassword"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center mt-8">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              :d="showCurrentPassword ? 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21' : 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="relative">
                                <label class="block text-gray-300 text-sm font-medium mb-2">Nova Senha</label>
                                <input :type="showNewPassword ? 'text' : 'password'" name="new_password"
                                       class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal pr-10"
                                       required minlength="6">
                                <button type="button" @click="showNewPassword = !showNewPassword"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center mt-8">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              :d="showNewPassword ? 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21' : 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="relative">
                                <label class="block text-gray-300 text-sm font-medium mb-2">Confirmar Nova Senha</label>
                                <input :type="showConfirmPassword ? 'text' : 'password'" name="confirm_password"
                                       class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal pr-10"
                                       required minlength="6">
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center mt-8">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              :d="showConfirmPassword ? 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21' : 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="bg-azul-acento text-white font-bold py-2 px-6 rounded-md hover:bg-blue-600 transition">
                                Alterar Senha
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Dicas de Segurança -->
                <div class="mt-6 p-4 bg-fundo-principal rounded-lg">
                    <h4 class="text-white font-medium mb-2">Dicas de Segurança:</h4>
                    <ul class="text-gray-300 text-sm space-y-1">
                        <li>• Use uma senha forte com pelo menos 8 caracteres</li>
                        <li>• Combine letras maiúsculas, minúsculas, números e símbolos</li>
                        <li>• Não compartilhe sua senha com outras pessoas</li>
                        <li>• Altere sua senha regularmente</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Aba Notificações -->
        <div x-show="activeTab === 'notificacoes'" x-transition>
            <h2 class="text-xl font-semibold text-white mb-6">Preferências de Notificação</h2>
            
            <form action="../../src/controllers/config_controller.php" method="POST">
                <input type="hidden" name="action" value="update_notifications">
                
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-4">Notificações por E-mail</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_payments" value="1" 
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Pagamentos e transações financeiras</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_campaigns" value="1"
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Status de campanhas de disparo</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_instances" value="1"
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Conexão e desconexão de instâncias</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_maturation" value="1"
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Conclusão de processos de maturação</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-4">Relatórios</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="weekly_report" value="1"
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Relatório semanal de atividades</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="monthly_report" value="1"
                                       class="rounded border-gray-600 text-roxo-principal focus:ring-roxo-principal focus:ring-offset-0 bg-fundo-principal">
                                <span class="ml-3 text-gray-300">Relatório mensal de gastos</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8">
                    <button type="submit" class="bg-azul-acento text-white font-bold py-3 px-6 rounded-md hover:bg-blue-600 transition">
                        Salvar Preferências
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Zona de Perigo -->
    <div class="mt-8 bg-red-900 bg-opacity-20 border border-red-500 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-red-400 mb-4">Zona de Perigo</h2>
        <p class="text-gray-300 mb-4">Ações irreversíveis que afetam permanentemente sua conta.</p>
        
        <button class="bg-red-600 text-white font-bold py-2 px-4 rounded-md hover:bg-red-700 transition"
                onclick="alert('Funcionalidade em desenvolvimento. Entre em contato com o suporte para excluir sua conta.')">
            Excluir Conta Permanentemente
        </button>
    </div>

</div>

<script>
// Máscara para telefone
document.addEventListener('DOMContentLoaded', function() {
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
});
</script>

<?php require_once '../partials/footer.php'; ?>