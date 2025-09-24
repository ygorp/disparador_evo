<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/asaas_config.php';

$transacao_id = $_GET['transacao_id'] ?? null;
$method = $_GET['method'] ?? 'pix';
$cliente_id = $_SESSION['user_id'];

if (!$transacao_id) {
    header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
    exit;
}

try {
    // Busca dados da transação
    $stmt = $pdo->prepare("
        SELECT t.*, p.nome as plano_nome, p.descricao as plano_descricao, c.nome as cliente_nome
        FROM transacoes t
        JOIN planos p ON t.plano_id = p.id
        JOIN clientes c ON t.cliente_id = c.id
        WHERE t.id = ? AND t.cliente_id = ?
    ");
    $stmt->execute([$transacao_id, $cliente_id]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        $_SESSION['error_message'] = "Transação não encontrada";
        header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
        exit;
    }
    
    $asaas = new AsaasAPI();
    $paymentInfo = null;
    $pixData = null;
    
    if ($transacao['asaas_payment_id']) {
        $paymentResult = $asaas->getPayment($transacao['asaas_payment_id']);
        
        if ($paymentResult['success']) {
            $paymentInfo = $paymentResult['data'];
            
            // Se for PIX, busca o QR Code
            if ($method === 'pix' && $paymentInfo['status'] === 'PENDING') {
                $pixResult = $asaas->getPixQrCode($transacao['asaas_payment_id']);
                if ($pixResult['success']) {
                    $pixData = $pixResult['data'];
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao carregar pagamento: " . $e->getMessage());
    $_SESSION['error_message'] = "Erro ao carregar informações do pagamento";
    header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
    exit;
}
?>

<div x-data="{
    copied: false,
    paymentStatus: '<?= $paymentInfo['status'] ?? 'PENDING' ?>',
    checkInterval: null,
    
    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        });
    },
    
    checkPaymentStatus() {
        if (this.paymentStatus === 'RECEIVED' || this.paymentStatus === 'CONFIRMED') {
            return;
        }
        
        fetch('../../src/controllers/check_payment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transacao_id: <?= $transacao_id ?> })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status !== this.paymentStatus) {
                this.paymentStatus = data.status;
                
                if (data.status === 'RECEIVED' || data.status === 'CONFIRMED') {
                    clearInterval(this.checkInterval);
                    // Redireciona após 3 segundos
                    setTimeout(() => {
                        window.location.href = '<?= BASE_URL ?>views/client/comprar_planos.php?payment=success';
                    }, 3000);
                }
            }
        })
        .catch(error => console.error('Erro ao verificar status:', error));
    },
    
    init() {
        // Verifica status a cada 5 segundos
        this.checkInterval = setInterval(() => {
            this.checkPaymentStatus();
        }, 5000);
        
        // Limpa interval quando sair da página
        window.addEventListener('beforeunload', () => {
            if (this.checkInterval) clearInterval(this.checkInterval);
        });
    }
}">

    <div class="max-w-4xl mx-auto">
        
        <!-- Cabeçalho -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Finalizar Pagamento</h1>
            <p class="text-gray-400">Complete sua compra para receber os créditos</p>
        </div>

        <!-- Status do Pagamento -->
        <div class="mb-6" x-show="paymentStatus === 'RECEIVED' || paymentStatus === 'CONFIRMED'">
            <div class="bg-green-500 text-white p-4 rounded-lg text-center">
                <div class="flex items-center justify-center mb-2">
                    <svg class="w-8 h-8 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-xl font-semibold">Pagamento Confirmado!</span>
                </div>
                <p>Seus créditos foram adicionados automaticamente à sua conta.</p>
                <p class="text-sm mt-2">Redirecionando em 3 segundos...</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Informações da Compra -->
            <div class="lg:col-span-1">
                <div class="bg-card-fundo rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Resumo da Compra</h2>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Plano:</span>
                            <span class="text-white font-medium"><?= htmlspecialchars($transacao['plano_nome']) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tipo:</span>
                            <span class="text-white"><?= ucfirst(str_replace('recarga_', '', $transacao['tipo_transacao'])) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Créditos:</span>
                            <span class="text-white font-medium"><?= number_format($transacao['creditos_quantidade'], 0, ',', '.') ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Método:</span>
                            <span class="text-white"><?= $method === 'pix' ? 'PIX' : 'Boleto' ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Vencimento:</span>
                            <span class="text-white"><?= (new DateTime($transacao['data_vencimento']))->format('d/m/Y') ?></span>
                        </div>
                        
                        <hr class="border-gray-600">
                        
                        <div class="flex justify-between text-lg">
                            <span class="text-gray-300 font-semibold">Total:</span>
                            <span class="text-white font-bold">R$ <?= number_format($transacao['valor'], 2, ',', '.') ?></span>
                        </div>
                    </div>
                    
                    <!-- Status da Transação -->
                    <div class="mt-6 p-3 rounded-lg" :class="{
                        'bg-yellow-500 bg-opacity-20 border border-yellow-500': paymentStatus === 'PENDING',
                        'bg-green-500 bg-opacity-20 border border-green-500': paymentStatus === 'RECEIVED' || paymentStatus === 'CONFIRMED',
                        'bg-red-500 bg-opacity-20 border border-red-500': paymentStatus === 'OVERDUE'
                    }">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2" :class="{
                                'bg-yellow-500': paymentStatus === 'PENDING',
                                'bg-green-500': paymentStatus === 'RECEIVED' || paymentStatus === 'CONFIRMED',
                                'bg-red-500': paymentStatus === 'OVERDUE'
                            }"></div>
                            <span class="text-sm font-medium" x-text="{
                                'PENDING': 'Aguardando Pagamento',
                                'RECEIVED': 'Pagamento Recebido',
                                'CONFIRMED': 'Pagamento Confirmado',
                                'OVERDUE': 'Pagamento Vencido'
                            }[paymentStatus] || 'Status Desconhecido'"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Área de Pagamento -->
            <div class="lg:col-span-2">
                
                <?php if ($method === 'pix' && $pixData): ?>
                    <!-- Pagamento PIX -->
                    <div class="bg-card-fundo rounded-lg shadow-lg p-6" x-show="paymentStatus === 'PENDING'">
                        <div class="text-center">
                            <h2 class="text-2xl font-semibold text-white mb-4">Pagamento via PIX</h2>
                            <p class="text-gray-400 mb-6">Escaneie o QR Code com seu aplicativo bancário ou copie e cole o código PIX</p>
                            
                            <!-- QR Code -->
                            <div class="bg-white p-6 rounded-lg inline-block mb-6">
                                <img src="data:image/png;base64,<?= $pixData['encodedImage'] ?>" alt="QR Code PIX" class="w-64 h-64">
                            </div>
                            
                            <!-- Código PIX Copia e Cola -->
                            <div class="mb-6">
                                <label class="block text-gray-300 text-sm font-medium mb-2">Código PIX Copia e Cola:</label>
                                <div class="flex">
                                    <input type="text" readonly value="<?= htmlspecialchars($pixData['payload']) ?>" 
                                           class="flex-1 px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-l-md text-sm font-mono">
                                    <button @click="copyToClipboard('<?= addslashes($pixData['payload']) ?>')" 
                                            class="px-4 py-3 bg-roxo-principal text-white rounded-r-md hover:bg-purple-600 transition">
                                        <span x-show="!copied">Copiar</span>
                                        <span x-show="copied" class="text-green-200">Copiado!</span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Instruções -->
                            <div class="text-left bg-fundo-principal p-4 rounded-lg">
                                <h3 class="text-white font-medium mb-2">Como pagar:</h3>
                                <ol class="text-gray-300 text-sm space-y-1 list-decimal list-inside">
                                    <li>Abra o aplicativo do seu banco</li>
                                    <li>Procure pela opção "PIX" ou "Pagamentos"</li>
                                    <li>Escaneie o QR Code ou cole o código PIX</li>
                                    <li>Confirme o pagamento</li>
                                    <li>Aguarde alguns segundos pela confirmação automática</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($method === 'boleto' && $paymentInfo): ?>
                    <!-- Pagamento Boleto -->
                    <div class="bg-card-fundo rounded-lg shadow-lg p-6" x-show="paymentStatus === 'PENDING'">
                        <div class="text-center">
                            <h2 class="text-2xl font-semibold text-white mb-4">Pagamento via Boleto</h2>
                            <p class="text-gray-400 mb-6">Seu boleto foi gerado com sucesso</p>
                            
                            <div class="space-y-4">
                                <!-- Link do Boleto -->
                                <div>
                                    <a href="<?= $paymentInfo['bankSlipUrl'] ?>" target="_blank" 
                                       class="inline-flex items-center bg-roxo-principal text-white font-bold py-3 px-6 rounded-lg hover:bg-purple-600 transition">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Baixar Boleto
                                    </a>
                                </div>
                                
                                <!-- Código de Barras -->
                                <?php if (isset($paymentInfo['identificationField'])): ?>
                                    <div class="mt-6">
                                        <label class="block text-gray-300 text-sm font-medium mb-2">Código de Barras:</label>
                                        <div class="flex">
                                            <input type="text" readonly value="<?= $paymentInfo['identificationField'] ?>" 
                                                   class="flex-1 px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-l-md text-sm font-mono">
                                            <button @click="copyToClipboard('<?= $paymentInfo['identificationField'] ?>')" 
                                                    class="px-4 py-3 bg-roxo-principal text-white rounded-r-md hover:bg-purple-600 transition">
                                                <span x-show="!copied">Copiar</span>
                                                <span x-show="copied" class="text-green-200">Copiado!</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Instruções -->
                                <div class="text-left bg-fundo-principal p-4 rounded-lg mt-6">
                                    <h3 class="text-white font-medium mb-2">Como pagar:</h3>
                                    <ol class="text-gray-300 text-sm space-y-1 list-decimal list-inside">
                                        <li>Clique em "Baixar Boleto" para salvar o arquivo</li>
                                        <li>Acesse o internet banking do seu banco</li>
                                        <li>Procure pela opção "Pagamentos" ou "Boletos"</li>
                                        <li>Faça o upload do boleto ou digite o código de barras</li>
                                        <li>Confirme o pagamento</li>
                                        <li>O processamento pode levar até 3 dias úteis</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Erro ao carregar informações de pagamento -->
                    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
                        <div class="text-center">
                            <div class="text-red-500 mb-4">
                                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-white mb-2">Erro ao Carregar Pagamento</h2>
                            <p class="text-gray-400 mb-6">Não foi possível carregar as informações de pagamento.</p>
                            <a href="<?= BASE_URL ?>views/client/comprar_planos.php" 
                               class="bg-roxo-principal text-white font-bold py-2 px-4 rounded-md hover:bg-purple-600 transition">
                                Voltar para Comprar Planos
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Ações -->
                <div class="mt-6 flex justify-between items-center">
                    <a href="<?= BASE_URL ?>views/client/comprar_planos.php" 
                       class="text-gray-400 hover:text-white transition">
                        ← Voltar para planos
                    </a>
                    
                    <div class="text-sm text-gray-400">
                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse" x-show="paymentStatus === 'PENDING'"></span>
                        <span x-show="paymentStatus === 'PENDING'">Verificando pagamento automaticamente...</span>
                        <span x-show="paymentStatus === 'RECEIVED' || paymentStatus === 'CONFIRMED'">Pagamento confirmado!</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>