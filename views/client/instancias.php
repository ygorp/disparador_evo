<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$evolutionApiUrl = EVOLUTION_API_BASE_URL;
$evolutionApiKey = EVOLUTION_API_GLOBAL_KEY;
$cliente_id = $_SESSION['user_id'];
$instancias = [];

try {
    // Rotina de sincronização automática
    $stmt_sync = $pdo->prepare("SELECT id, instance_name_api, status FROM instancias WHERE cliente_id = ?");
    $stmt_sync->execute([$cliente_id]);
    $instancias_para_sincronizar = $stmt_sync->fetchAll();

    foreach ($instancias_para_sincronizar as $instancia_local) {
        $endpoint = $evolutionApiUrl . '/instance/connectionState/' . $instancia_local['instance_name_api'];
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evolutionApiKey]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $api_data = json_decode($response, true);
            if (isset($api_data['instance']['state'])) {
                $status_api = ($api_data['instance']['state'] === 'open') ? 'Conectado' : 'Desconectado';
                if ($status_api !== $instancia_local['status']) {
                    $stmt_update = $pdo->prepare("UPDATE instancias SET status = ? WHERE id = ?");
                    $stmt_update->execute([$status_api, $instancia_local['id']]);
                }
            }
        }
        usleep(100000);
    }

    // Busca final dos dados já sincronizados
    $stmt_final = $pdo->prepare("SELECT * FROM instancias WHERE cliente_id = ? ORDER BY data_criacao DESC");
    $stmt_final->execute([$cliente_id]);
    $instancias = $stmt_final->fetchAll();

    // Busca estatísticas
    $total_instancias = count($instancias);
    $conectadas = 0; $desconectadas = 0;
    foreach ($instancias as $instancia) {
        if ($instancia['status'] === 'Conectado') $conectadas++;
        elseif ($instancia['status'] === 'Desconectado') $desconectadas++;
    }

} catch (PDOException $e) {
    die("Erro ao carregar instâncias: " . $e->getMessage());
}
?>

<div x-data="{
    isCreateModalOpen: false,
    isQrCodeModalOpen: false,
    qrCodeBase64: '',
    instanceNameForQr: '',
    isLoadingQrCode: false,
    statusInterval: null,

    startStatusChecker(instanceApiName) {
        if (this.statusInterval) clearInterval(this.statusInterval);
        this.statusInterval = setInterval(() => {
            fetch(`../../src/controllers/instancia_controller.php?action=check_status&instance_name=${instanceApiName}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status === 'Conectado') {
                        clearInterval(this.statusInterval);
                        this.isQrCodeModalOpen = false;
                        window.location.reload();
                    }
                });
        }, 3000);
    },

    stopStatusChecker() {
        if (this.statusInterval) clearInterval(this.statusInterval);
        this.statusInterval = null;
    },

    getQrCode(instanceApiName, instanceFriendlyName) {
        this.isQrCodeModalOpen = true;
        this.isLoadingQrCode = true;
        this.instanceNameForQr = instanceFriendlyName;
        this.qrCodeBase64 = '';
        fetch(`../../src/controllers/instancia_controller.php?action=get_qrcode&instance_name=${instanceApiName}`)
            .then(response => response.json())
            .then(data => {
                this.isLoadingQrCode = false;
                if (data.success && data.qrcode) {
                    this.qrCodeBase64 = data.qrcode;
                    this.startStatusChecker(instanceApiName);
                } else {
                    alert('Não foi possível obter o QR Code. A instância pode já estar conectada ou ocorreu um erro.');
                    this.isQrCodeModalOpen = false;
                }
            })
            .catch(error => {
                this.isLoadingQrCode = false;
                this.isQrCodeModalOpen = false;
                alert('Ocorreu um erro de comunicação.');
            });
    }
}">

    <h1 class="text-3xl font-bold text-white mb-2">Gerenciar Instâncias</h1>
    <p class="text-gray-400 mb-8">Crie, conecte e gerencie suas instâncias do WhatsApp.</p>

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

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-roxo-principal">
            <h2 class="text-gray-400 text-sm font-medium">Total de Instâncias</h2>
            <p class="text-3xl font-bold text-white"><?= $total_instancias ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-verde-sucesso">
            <h2 class="text-gray-400 text-sm font-medium">Conectadas</h2>
            <p class="text-3xl font-bold text-white"><?= $conectadas ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-vermelho-erro">
            <h2 class="text-gray-400 text-sm font-medium">Desconectadas</h2>
            <p class="text-3xl font-bold text-white"><?= $desconectadas ?></p>
        </div>
    </div>

    <!-- Tabela de Instâncias -->
    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">Minhas Instâncias</h2>
            <button @click="isCreateModalOpen = true" class="bg-verde-sucesso text-white font-bold px-4 py-2 rounded-md hover:bg-green-600 transition">
                + Nova Instância
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-4">Nome da Instância</th>
                        <th class="p-4">Número</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Proxy</th>
                        <th class="p-4">Data de Criação</th>
                        <th class="p-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instancias)): ?>
                        <tr>
                            <td colspan="6" class="text-center p-8 text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <p class="text-lg font-medium mb-2">Nenhuma instância encontrada</p>
                                    <p class="text-sm text-gray-500 mb-4">Crie sua primeira instância para começar a usar o WhatsApp Business</p>
                                    <button @click="isCreateModalOpen = true" class="bg-roxo-principal text-white px-4 py-2 rounded-md hover:bg-purple-600 transition">
                                        Criar Primeira Instância
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instancias as $instancia): ?>
                            <tr class="border-b border-gray-700 hover:bg-fundo-principal">
                                <td class="p-4 font-medium text-white"><?= htmlspecialchars($instancia['nome_instancia']) ?></td>
                                <td class="p-4 text-gray-300">
                                    <?= $instancia['numero_telefone'] ? htmlspecialchars($instancia['numero_telefone']) : '<span class="text-gray-500 italic">Não conectado</span>' ?>
                                </td>
                                <td class="p-4">
                                    <?php
                                        $status_class = 'bg-vermelho-erro';
                                        if ($instancia['status'] === 'Conectado') $status_class = 'bg-verde-sucesso';
                                        elseif ($instancia['status'] === 'Desconectado') $status_class = 'bg-vermelho-erro';
                                        else $status_class = 'bg-amarelo-atencao';
                                    ?>
                                    <span class="px-3 py-1 text-sm rounded-full text-white <?= $status_class ?>">
                                        <?= htmlspecialchars($instancia['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <?php if ($instancia['proxy_ativo']): ?>
                                        <span class="px-3 py-1 text-xs rounded-full text-white bg-green-500">Ativo</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-xs rounded-full text-gray-200 bg-gray-600">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-gray-300">
                                    <?= (new DateTime($instancia['data_criacao']))->format('d/m/Y H:i') ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($instancia['status'] === 'Conectado'): ?>
                                            <a href="../../src/controllers/instancia_controller.php?action=disconnect&instance_name=<?= $instancia['instance_name_api'] ?>" 
                                               onclick="return confirm('Deseja desconectar esta instância?');" 
                                               class="bg-yellow-600 text-white px-3 py-1 rounded-md text-sm hover:bg-yellow-700">
                                                Desconectar
                                            </a>
                                        <?php else: ?>
                                            <button @click="getQrCode('<?= $instancia['instance_name_api'] ?>', '<?= htmlspecialchars($instancia['nome_instancia']) ?>')" 
                                                    class="bg-roxo-principal text-white px-3 py-1 rounded-md text-sm hover:bg-purple-600">
                                                Conectar
                                            </button>
                                        <?php endif; ?>
                                        <a href="../../src/controllers/instancia_controller.php?action=delete&instance_id=<?= $instancia['id'] ?>&instance_name=<?= $instancia['instance_name_api'] ?>" 
                                           onclick="return confirm('ATENÇÃO: Esta ação é irreversível! Deseja apagar esta instância?');" 
                                           class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">
                                            Excluir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Criação de Instância -->
    <div x-show="isCreateModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isCreateModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-white mb-4">Criar Nova Instância</h2>
            <form action="../../src/controllers/instancia_controller.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="mb-6">
                    <label for="nome_instancia" class="block text-gray-300 text-sm font-medium mb-2">Nome da Instância</label>
                    <input type="text" id="nome_instancia" name="nome_instancia" 
                           class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" 
                           placeholder="Ex: WhatsApp Vendas" required>
                    <p class="text-xs text-gray-500 mt-1">Escolha um nome descritivo para identificar esta instância.</p>
                </div>
                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" @click="isCreateModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-roxo-principal text-white font-bold hover:bg-purple-600 transition">Criar Instância</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de QR Code -->
    <div x-show="isQrCodeModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isQrCodeModalOpen = false; stopStatusChecker()" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md text-center">
            <h2 class="text-2xl font-bold text-white mb-2">Conectar Instância</h2>
            <p class="text-roxo-principal font-semibold mb-4" x-text="instanceNameForQr"></p>
            <p class="text-gray-400 mb-6">Abra o WhatsApp no seu celular e escaneie o código abaixo.</p>
            <div id="qrcode-container" class="bg-white p-4 rounded-md inline-block min-w-[200px] min-h-[200px] flex items-center justify-center">
                <span x-show="isLoadingQrCode">Carregando QR Code...</span>
                <img x-show="!isLoadingQrCode && qrCodeBase64" :src="qrCodeBase64" alt="QR Code">
            </div>
            <div class="mt-8">
                <button type="button" @click="isQrCodeModalOpen = false; stopStatusChecker()" class="px-6 py-2 rounded-md text-gray-300 hover:bg-gray-600 transition">Fechar</button>
            </div>
        </div>
    </div>

</div>

<?php require_once '../partials/footer.php'; ?>