<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$cliente_id = $_SESSION['user_id'];
$campanhas = [];
// Busca as campanhas do cliente para listar na tabela
try {
    $stmt = $pdo->prepare("SELECT * FROM campanhas WHERE cliente_id = ? ORDER BY data_criacao DESC");
    $stmt->execute([$cliente_id]);
    $campanhas = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro ao buscar campanhas: " . $e->getMessage());
}

// Busca instâncias conectadas para o modal de criação
$instancias_conectadas = [];
try {
    $stmt_inst = $pdo->prepare("SELECT id, nome_instancia, instance_name_api FROM instancias WHERE cliente_id = ? AND status = 'Conectado'");
    $stmt_inst->execute([$cliente_id]);
    $instancias_conectadas = $stmt_inst->fetchAll();
} catch (PDOException $e) {
    die("Erro ao buscar instâncias: " . $e->getMessage());
}

// Lógica para os cards
$stats = ['ativas' => 0, 'concluidas' => 0, 'total_enviado' => 0];
foreach($campanhas as $campanha){
    if($campanha['status'] === 'Enviando') $stats['ativas']++;
    if($campanha['status'] === 'Concluída') $stats['concluidas']++;
}

// Busca o saldo de créditos do cliente
$saldo_disparo = 0;
try {
    $stmt_saldo = $pdo->prepare("SELECT saldo_creditos_disparo FROM clientes WHERE id = ?");
    $stmt_saldo->execute([$cliente_id]);
    $saldo_disparo = $stmt_saldo->fetchColumn();
} catch (PDOException $e) { /* Tratar erro */ }

$evolutionApiUrl = EVOLUTION_API_BASE_URL; // PREENCHA COM SUA URL
$evolutionApiKey = EVOLUTION_API_GLOBAL_KEY;       // PREENCHA COM SUA CHAVE

$cliente_id = $_SESSION['user_id'];

try {
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
} catch (PDOException $e) { /* Tratar erro */ }

?>

<div x-data="{ isCreateModalOpen: false}">
    <h1 class="text-3xl font-bold text-white mb-2">Dashboard de Campanhas</h1>
    <p class="text-gray-400 mb-8">Gerencie e acompanhe seus disparos em massa.</p>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="bg-green-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']); // Limpa a mensagem após exibi-la
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="bg-red-500 text-white p-4 rounded-lg mb-6">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']); // Limpa a mensagem após exibi-la
    }
    ?>

    <div class="mb-8 p-6 bg-card-fundo rounded-lg shadow-lg flex justify-between items-center">
        <div>
            <h3 class="text-gray-400 text-sm font-medium">SEU SALDO DE DISPARO</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($saldo_disparo, 0, ',', '.') ?> créditos</p>
        </div>
        <a href="comprar_planos.php" class="bg-azul-acento text-white font-bold px-6 py-3 rounded-md hover:bg-blue-600 transition">
            Adicionar Créditos
        </a>
        </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Campanhas Ativas</h2><p class="text-3xl font-bold text-white"><?= $stats['ativas'] ?></p></div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Campanhas Concluídas</h2><p class="text-3xl font-bold text-white"><?= $stats['concluidas'] ?></p></div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg"><h2 class="text-gray-400 text-sm font-medium">Total de Mensagens Enviadas</h2><p class="text-3xl font-bold text-white"><?= $stats['total_enviado'] ?></p></div>
    </div>

    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">Minhas Campanhas</h2>
            <button @click="isCreateModalOpen = true" class="bg-verde-sucesso text-white font-bold px-4 py-2 rounded-md hover:bg-green-600 transition">+ Nova Campanha</button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-4">Campanha</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Progresso</th>
                        <th class="p-4">Data</th>
                        <th class="p-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campanhas)): ?>
                        <tr><td colspan="5" class="text-center p-6 text-gray-400">Nenhuma campanha criada. Clique em "+ Nova Campanha" para começar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($campanhas as $campanha): ?>
                            <tr data-campaign-id="<?= $campanha['id'] ?>" class="border-b border-gray-700 hover:bg-fundo-principal">
                                <td class="p-4 font-medium text-white"><?= htmlspecialchars($campanha['nome_campanha']) ?></td>
                                <td class="p-4">
                                    <?php
                                        $status = $campanha['status'];
                                        $color_class = 'bg-gray-500'; // Padrão
                                        if ($status === 'Enviando') $color_class = 'bg-blue-500';
                                        if ($status === 'Pausada') $color_class = 'bg-yellow-600';
                                        if ($status === 'Concluída') $color_class = 'bg-verde-sucesso';
                                        if ($status === 'Falha') $color_class = 'bg-vermelho-erro';
                                        if ($status === 'Agendada') $color_class = 'bg-purple-600';
                                    ?>
                                    <span id="status-<?= $campanha['id'] ?>" class="px-3 py-1 text-sm rounded-full text-white <?= $color_class ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                    <?php if ($status === 'Agendada'): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Para: <?= (new DateTime($campanha['data_agendamento']))->format('d/m/Y H:i') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="w-full bg-fundo-principal rounded-full h-2.5">
                                        <div id="progress-bar-<?= $campanha['id'] ?>" class="bg-roxo-principal h-2.5 rounded-full" style="width: 0%"></div>
                                    </div>
                                </td>
                                <td class="p-4 text-gray-400"><?= (new DateTime($campanha['data_criacao']))->format('d/m/Y H:i') ?></td>
                                <td class="p-4 flex items-center space-x-2" id="actions-cell-<?= $campanha['id'] ?>">
                                    <?php if ($campanha['status'] === 'Pausada'): ?>
                                        <a href="../../src/controllers/disparo_controller.php?action=update_status&id=<?= $campanha['id'] ?>&status=Enviando" class="bg-blue-500 text-white px-3 py-1 rounded-md text-sm">Continuar</a>
                                    <?php elseif ($campanha['status'] === 'Enviando'): ?>
                                        <a href="../../src/controllers/disparo_controller.php?action=update_status&id=<?= $campanha['id'] ?>&status=Pausada" class="bg-yellow-600 text-white px-3 py-1 rounded-md text-sm">Pausar</a>
                                    <?php endif; ?>
                                    <a href="relatorio_campanha.php?id=<?= $campanha['id'] ?>" class="bg-gray-600 text-white px-3 py-1 rounded-md text-sm hover:bg-gray-700">Relatório</a>
                                    <a href="../../src/controllers/disparo_controller.php?action=delete_campaign&id=<?= $campanha['id'] ?>" onclick="return confirm('ATENÇÃO: Esta ação é irreversível e apagará a campanha e toda a sua fila de envio. Deseja continuar?');" class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isCreateModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isCreateModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl w-full max-w-4xl flex flex-col max-h-[90vh]">
            
            <div class="p-6 border-b border-gray-700 flex-shrink-0">
                <h2 class="text-2xl font-bold text-white">Configurar Nova Campanha de Disparo</h2>
            </div>

            <?php if (empty($instancias_conectadas)): ?>
                <div class="text-center bg-fundo-principal p-8 rounded-lg">
                    <h3 class="text-xl font-semibold text-yellow-400 mb-4">Nenhuma instância conectada!</h3>
                    <p class="text-gray-300 mb-6">Você precisa ter pelo menos uma instância conectada para criar uma campanha de disparo.</p>
                    <a href="dashboard.php" class="bg-roxo-principal text-white font-bold py-3 px-6 rounded-md hover:bg-purple-600 transition">Criar ou Conectar uma Instância Agora</a>
                </div>
            <?php else: ?>
                <form id="campaign-form" action="../../src/controllers/disparo_controller.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()" class="flex flex-col overflow-hidden">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="p-6 overflow-y-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="nome_campanha" class="block text-gray-300 text-sm font-medium mb-2">Nome da Campanha</label>
                                <input type="text" name="nome_campanha" id="nome_campanha" class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" placeholder="Ex: Promoção de Inverno" required>
                            </div>
                            <div>
                                <label for="lista_contatos" class="block text-gray-300 text-sm font-medium mb-2">Lista de Contatos (.csv)</label>
                                <input type="file" name="lista_contatos" id="lista_contatos" onchange="handleFileSelection(event)" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-roxo-principal file:text-white hover:file:bg-purple-600 cursor-pointer" required accept=".csv">
                                <div class="flex justify-between items-center mt-1">
                                    <p class="text-xs text-gray-500">A planilha deve ter uma coluna 'numero'.</p>
                                    <a href="../../assets/downloads/exemplo_contatos.csv" download="exemplo_contatos.csv" class="text-sm text-roxo-principal hover:underline">Baixar exemplo</a>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6 p-4 border border-gray-700 rounded-lg">
                            <label class="block text-gray-300 text-sm font-medium mb-3">Selecione as Instâncias e a Distribuição (%)</label>
                            <div id="instancias-container" class="space-y-3">
                                <?php foreach ($instancias_conectadas as $instancia): ?>
                                    <div class="flex items-center gap-4 p-2 rounded-md bg-fundo-principal">
                                        <input type="checkbox" name="instancias[]" value="<?= $instancia['id'] ?>" id="inst_<?= $instancia['id'] ?>" class="h-5 w-5 bg-gray-700 border-gray-600 rounded text-roxo-principal focus:ring-roxo-principal">
                                        <label for="inst_<?= $instancia['id'] ?>" class="flex-grow text-white"><?= htmlspecialchars($instancia['nome_instancia']) ?></label>
                                        <input type="number" name="porcentagens[]" value="0" min="0" max="100" class="w-24 px-2 py-1 bg-gray-700 text-white border border-gray-600 rounded-md focus:outline-none focus:ring-1 focus:ring-roxo-principal" placeholder="%">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p id="percentage-error" class="text-red-500 text-sm mt-2" style="display: none;">A soma das porcentagens das instâncias selecionadas deve ser 100%.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="mensagem" class="block text-gray-300 text-sm font-medium mb-2">Mensagem</label>
                                <textarea name="mensagem" id="mensagem" rows="6" class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" placeholder="Olá, {{nome}}! ..." required></textarea>
                                <div id="variables-wrapper" class="mt-2 p-3 bg-fundo-principal rounded-md border border-gray-700 hidden">
                                    <label class="block text-gray-300 text-sm font-medium mb-2">Variáveis (clique para inserir):</label>
                                    <div id="variables-container" class="flex flex-wrap gap-2"></div>
                                </div>
                            </div>
                            <div>
                                <label for="midia" class="block text-gray-300 text-sm font-medium mb-2">Anexar Mídia (Opcional)</label>
                                <input type="file" name="midia" id="midia" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-roxo-principal file:text-white hover:file:bg-purple-600 cursor-pointer">
                                
                                <div class="mt-4">
                                    <label class="block text-gray-300 text-sm font-medium mb-2">Agendar Disparo (Opcional)</label>
                                    <input type="datetime-local" name="data_agendamento" id="data_agendamento" class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal">
                                    <p class="text-xs text-gray-500 mt-1">Deixe em branco para iniciar imediatamente.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="delay_min" class="block text-gray-300 text-sm font-medium mb-2">Delay Mínimo (segundos)</label>
                                <input type="number" name="delay_min" id="delay_min" value="5" min="1" class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                                <p class="text-xs text-gray-500 mt-1">Tempo mínimo de espera entre o envio de cada mensagem.</p>
                            </div>
                            <div>
                                <label for="delay_max" class="block text-gray-300 text-sm font-medium mb-2">Delay Máximo (segundos)</label>
                                <input type="number" name="delay_max" id="delay_max" value="15" min="1" class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                                <p class="text-xs text-gray-500 mt-1">Tempo máximo de espera entre o envio de cada mensagem.</p>
                            </div>
                        </div>
                    </div> <div class="p-6 border-t border-gray-700 flex justify-end flex-shrink-0">
                        <button type="button" @click="isCreateModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-700 transition mr-4">Cancelar</button>
                        <button type="submit" class="bg-verde-sucesso text-white font-bold py-3 px-8 rounded-md hover:bg-green-600 transition">Salvar e Iniciar</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Função para validar a soma das porcentagens
function validateForm() {
    const checkboxes = document.querySelectorAll('input[name="instancias[]"]:checked');
    if (checkboxes.length === 0) {
        alert('Selecione pelo menos uma instância para o disparo.');
        return false;
    }

    let percentageSum = 0;
    checkboxes.forEach(checkbox => {
        const percentageInput = checkbox.closest('.flex').querySelector('input[name="porcentagens[]"]');
        percentageSum += parseInt(percentageInput.value) || 0;
    });

    const errorEl = document.getElementById('percentage-error');
    if (percentageSum !== 100) {
        errorEl.style.display = 'block';
        return false;
    } else {
        errorEl.style.display = 'none';
        return true;
    }
}
</script>


<?php require_once '../partials/footer.php'; ?>