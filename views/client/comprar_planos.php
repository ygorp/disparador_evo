<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/database.php';

// Busca os planos de DISPARO
$planos_disparo = [];
try {
    $stmt_disparo = $pdo->prepare("SELECT * FROM planos WHERE tipo = 'disparo' AND ativo = TRUE ORDER BY preco");
    $stmt_disparo->execute();
    $planos_disparo = $stmt_disparo->fetchAll();
} catch (PDOException $e) { /* Tratar erro */ }

// Busca os planos de MATURAÇÃO
$planos_maturacao = [];
try {
    $stmt_maturacao = $pdo->prepare("SELECT * FROM planos WHERE tipo = 'maturacao' AND ativo = TRUE ORDER BY preco");
    $stmt_maturacao->execute();
    $planos_maturacao = $stmt_maturacao->fetchAll();
} catch (PDOException $e) { /* Tratar erro */ }

// Busca saldo atual do cliente
$cliente_id = $_SESSION['user_id'];
$saldos = [];
try {
    $stmt_saldo = $pdo->prepare("SELECT saldo_creditos_disparo, saldo_creditos_maturacao FROM clientes WHERE id = ?");
    $stmt_saldo->execute([$cliente_id]);
    $saldos = $stmt_saldo->fetch();
} catch (PDOException $e) { 
    $saldos = ['saldo_creditos_disparo' => 0, 'saldo_creditos_maturacao' => 0];
}
?>

<div x-data="{ 
    isModalOpen: false, 
    selectedPlan: null,
    selectedType: '',
    paymentMethod: 'PIX',
    isProcessing: false,
    
    openPurchaseModal(plan, type) {
        this.selectedPlan = plan;
        this.selectedType = type;
        this.isModalOpen = true;
    },
    
    submitPurchase() {
        if (!this.selectedPlan || !this.paymentMethod) return;
        
        this.isProcessing = true;
        
        // Submete o formulário
        document.getElementById('purchase-form').submit();
    }
}">

    <h1 class="text-3xl font-bold text-white mb-6">Loja de Planos e Créditos</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="bg-green-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="bg-red-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    if (isset($_GET['payment']) && $_GET['payment'] === 'success') {
        echo '<div class="bg-green-500 text-white p-4 rounded-lg mb-6">✅ Pagamento confirmado! Seus créditos foram adicionados à sua conta.</div>';
    }
    ?>

    <!-- Cards de Saldo Atual -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
        <div class="bg-gradient-to-br from-azul-principal to-azul-acento rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium opacity-90">Saldo Atual - Disparos</h3>
                    <p class="text-3xl font-bold"><?= number_format($saldos['saldo_creditos_disparo'], 0, ',', '.') ?></p>
                    <p class="text-sm opacity-75 mt-1">créditos disponíveis</p>
                </div>
                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium opacity-90">Saldo Atual - Maturação</h3>
                    <p class="text-3xl font-bold"><?= number_format($saldos['saldo_creditos_maturacao'], 2, ',', '.') ?></p>
                    <p class="text-sm opacity-75 mt-1">créditos disponíveis</p>
                </div>
                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Pacotes de Créditos de Disparo -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold text-roxo-principal mb-4 border-b-2 border-roxo-principal pb-2">Pacotes de Créditos de Disparo</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-6">
            <?php foreach ($planos_disparo as $plano): ?>
                <div class="bg-card-fundo rounded-xl p-8 text-center border-2 border-gray-700 hover:border-roxo-principal transition-all duration-300 transform hover:scale-105">
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($plano['nome']) ?></h3>
                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($plano['descricao']) ?></p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="text-5xl font-bold text-white mb-2"><?= number_format($plano['creditos_disparo'], 0, ',', '.') ?></div>
                        <div class="text-gray-400 font-semibold">CRÉDITOS</div>
                    </div>
                    
                    <div class="text-3xl font-bold text-white mb-8">R$ <?= number_format($plano['preco'], 2, ',', '.') ?></div>
                    
                    <button @click="openPurchaseModal(<?= htmlspecialchars(json_encode($plano)) ?>, 'disparo')" 
                            class="w-full bg-azul-acento text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-300 transform hover:scale-105">
                        Comprar Agora
                    </button>
                    
                    <div class="mt-4 text-xs text-gray-500">
                        <?= number_format($plano['preco'] / $plano['creditos_disparo'], 3, ',', '.') ?> por crédito
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Planos de Maturação -->
    <div>
        <h2 class="text-2xl font-semibold text-blue-400 mb-4 border-b-2 border-blue-400 pb-2">Planos de Maturação</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-6">
            <?php foreach ($planos_maturacao as $plano): ?>
                <div class="bg-card-fundo rounded-xl p-8 text-center border-2 border-gray-700 hover:border-blue-400 transition-all duration-300 transform hover:scale-105">
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($plano['nome']) ?></h3>
                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($plano['descricao']) ?></p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="text-5xl font-bold text-blue-400 mb-2"><?= $plano['duracao_dias'] ?></div>
                        <div class="text-gray-400 font-semibold">DIAS</div>
                    </div>
                    
                    <div class="text-3xl font-bold text-white mb-8"><?= number_format($plano['preco'], 2, ',', '.') ?> créditos</div>
                    
                    <button @click="openPurchaseModal(<?= htmlspecialchars(json_encode($plano)) ?>, 'maturacao')" 
                            class="w-full bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-300 transform hover:scale-105">
                        Comprar Créditos
                    </button>
                    
                    <div class="mt-4 text-xs text-gray-500">
                        R$ <?= number_format($plano['preco'], 2, ',', '.') ?> em créditos
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal de Compra -->
    <div x-show="isModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isModalOpen = false" class="bg-card-fundo rounded-xl shadow-2xl p-8 w-full max-w-md mx-4">
            <h2 class="text-2xl font-bold text-white mb-6">Finalizar Compra</h2>
            
            <!-- Resumo do Plano -->
            <div class="bg-fundo-principal p-4 rounded-lg mb-6" x-show="selectedPlan">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-white" x-text="selectedPlan?.nome"></h3>
                    <p class="text-gray-400 text-sm mt-1" x-text="selectedPlan?.descricao"></p>
                    <div class="mt-3">
                        <span class="text-2xl font-bold" :class="selectedType === 'disparo' ? 'text-roxo-principal' : 'text-blue-400'" 
                              x-text="selectedType === 'disparo' ? (selectedPlan?.creditos_disparo + ' créditos') : (selectedPlan?.duracao_dias + ' dias')"></span>
                    </div>
                    <div class="text-xl font-semibold text-white mt-2">
                        R$ <span x-text="selectedPlan?.preco ? parseFloat(selectedPlan.preco).toFixed(2).replace('.', ',') : '0,00'"></span>
                    </div>
                </div>
            </div>

            <!-- Método de Pagamento -->
            <div class="mb-6">
                <label class="block text-gray-300 text-sm font-medium mb-3">Método de Pagamento:</label>
                <div class="space-y-2">
                    <label class="flex items-center p-3 bg-fundo-principal rounded-lg cursor-pointer hover:bg-gray-700">
                        <input type="radio" x-model="paymentMethod" value="PIX" class="text-roxo-principal focus:ring-roxo-principal">
                        <div class="ml-3 flex items-center">
                            <span class="text-white font-medium">PIX</span>
                            <span class="ml-2 bg-green-500 text-white text-xs px-2 py-1 rounded">Instantâneo</span>
                        </div>
                    </label>
                    <label class="flex items-center p-3 bg-fundo-principal rounded-lg cursor-pointer hover:bg-gray-700">
                        <input type="radio" x-model="paymentMethod" value="BOLETO" class="text-roxo-principal focus:ring-roxo-principal">
                        <div class="ml-3 flex items-center">
                            <span class="text-white font-medium">Boleto Bancário</span>
                            <span class="ml-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded">3 dias úteis</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex justify-end space-x-4">
                <button type="button" @click="isModalOpen = false" class="px-6 py-2 rounded-lg text-gray-300 hover:bg-gray-700 transition">
                    Cancelar
                </button>
                <button type="button" @click="submitPurchase()" :disabled="isProcessing" 
                        class="px-6 py-2 rounded-lg font-bold transition"
                        :class="isProcessing ? 'bg-gray-500 cursor-not-allowed' : 'bg-roxo-principal hover:bg-purple-600'"
                        :style="{ color: 'white' }">
                    <span x-show="!isProcessing">Pagar Agora</span>
                    <span x-show="isProcessing">Processando...</span>
                </button>
            </div>

            <!-- Formulário oculto para submissão -->
            <form id="purchase-form" action="../../src/controllers/compra_controller.php" method="POST" style="display: none;">
                <input type="hidden" name="action" value="buy_credits">
                <input type="hidden" name="plano_id" :value="selectedPlan?.id">
                <input type="hidden" name="tipo_credito" x-model="selectedType">
                <input type="hidden" name="payment_method" x-model="paymentMethod">
            </form>
        </div>
    </div>

    <!-- Seção de Informações -->
    <div class="mt-16 bg-card-fundo rounded-xl p-8">
        <h2 class="text-2xl font-semibold text-white mb-6 text-center">Por que Comprar Créditos?</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="bg-roxo-principal p-2 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold">Créditos de Disparo</h3>
                        <p class="text-gray-400 text-sm">Cada crédito permite enviar 1 mensagem. Ideal para campanhas de marketing e comunicação em massa.</p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-3">
                    <div class="bg-green-500 p-2 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold">Pagamento Seguro</h3>
                        <p class="text-gray-400 text-sm">Utilizamos a tecnologia ASAAS para garantir transações seguras via PIX ou Boleto.</p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="bg-blue-500 p-2 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold">Créditos de Maturação</h3>
                        <p class="text-gray-400 text-sm">Aqueça seus números gradualmente para evitar bloqueios e aumentar a taxa de entrega.</p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-3">
                    <div class="bg-yellow-500 p-2 rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold">Ativação Instantânea</h3>
                        <p class="text-gray-400 text-sm">Seus créditos são adicionados automaticamente após a confirmação do pagamento.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Rápido -->
    <div class="mt-12 bg-fundo-principal rounded-xl p-8">
        <h3 class="text-xl font-semibold text-white mb-6">Dúvidas Frequentes</h3>
        <div class="space-y-4 text-sm">
            <div>
                <h4 class="text-white font-medium">Como funciona o PIX?</h4>
                <p class="text-gray-400">O pagamento via PIX é instantâneo. Após o pagamento, seus créditos são adicionados automaticamente em poucos segundos.</p>
            </div>
            <div>
                <h4 class="text-white font-medium">E o Boleto?</h4>
                <p class="text-gray-400">O boleto pode levar até 3 dias úteis para ser confirmado pelos bancos. Após a confirmação, os créditos são adicionados automaticamente.</p>
            </div>
            <div>
                <h4 class="text-white font-medium">Os créditos vencem?</h4>
                <p class="text-gray-400">Não, seus créditos não possuem data de validade. Você pode usá-los quando quiser.</p>
            </div>
        </div>
    </div>

</div>

<?php require_once '../partials/footer.php'; ?>