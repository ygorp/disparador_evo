<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$cliente_id = $_SESSION['user_id'];

try {
    // Busca instâncias conectadas do cliente
    $stmt_instancias = $pdo->prepare("SELECT id, nome_instancia, numero_telefone FROM instancias WHERE cliente_id = ? AND status = 'Conectado' ORDER BY nome_instancia");
    $stmt_instancias->execute([$cliente_id]);
    $instancias_conectadas = $stmt_instancias->fetchAll();

    // Busca planos de maturação
    $stmt_planos = $pdo->prepare("SELECT id, nome, descricao, preco, duracao_dias FROM planos WHERE tipo = 'maturacao' AND ativo = TRUE ORDER BY preco");
    $stmt_planos->execute();
    $planos_maturacao = $stmt_planos->fetchAll();

    // Busca maturações ativas
    $stmt_maturacoes = $pdo->prepare("
        SELECT i.id, i.nome_instancia, i.numero_telefone, i.data_fim_maturacao, i.maturacao_restante_secs,
               p.nome as nome_plano, p.duracao_dias, p.preco
        FROM instancias i 
        LEFT JOIN planos p ON i.plano_maturacao_id = p.id 
        WHERE i.cliente_id = ? AND (i.data_fim_maturacao IS NOT NULL OR i.maturacao_restante_secs > 0)
        ORDER BY i.nome_instancia
    ");
    $stmt_maturacoes->execute([$cliente_id]);
    $maturacoes_ativas = $stmt_maturacoes->fetchAll();

    // Busca saldo de créditos
    $stmt_saldo = $pdo->prepare("SELECT saldo_creditos_maturacao FROM clientes WHERE id = ?");
    $stmt_saldo->execute([$cliente_id]);
    $saldo_maturacao = $stmt_saldo->fetchColumn();

    // Estatísticas
    $total_maturando = 0;
    $total_pausadas = 0;
    foreach ($maturacoes_ativas as $maturacao) {
        if ($maturacao['data_fim_maturacao'] && new DateTime($maturacao['data_fim_maturacao']) > new DateTime()) {
            $total_maturando++;
        } elseif ($maturacao['maturacao_restante_secs'] > 0) {
            $total_pausadas++;
        }
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados de maturação: " . $e->getMessage());
}
?>

<div x-data="{ isStartModalOpen: false, selectedInstance: '', selectedPlan: '', planPrice: 0 }">

    <h1 class="text-3xl font-bold text-white mb-2">Maturação de Números</h1>
    <p class="text-gray-400 mb-8">Aqueça seus números do WhatsApp para evitar bloqueios e aumentar a taxa de entrega.</p>

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

    <!-- Card de Saldo -->
    <div class="mb-8 p-6 bg-card-fundo rounded-lg shadow-lg flex justify-between items-center">
        <div>
            <h3 class="text-gray-400 text-sm font-medium">SEU SALDO DE MATURAÇÃO</h3>
            <p class="text-3xl font-bold text-white"><?= number_format($saldo_maturacao, 2, ',', '.') ?> créditos</p>
        </div>
        <a href="comprar_planos.php" class="bg-azul-acento text-white font-bold px-6 py-3 rounded-md hover:bg-blue-600 transition">
            Adicionar Créditos
        </a>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
            <h2 class="text-gray-400 text-sm font-medium">Maturações Ativas</h2>
            <p class="text-3xl font-bold text-white"><?= $total_maturando ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
            <h2 class="text-gray-400 text-sm font-medium">Maturações Pausadas</h2>
            <p class="text-3xl font-bold text-white"><?= $total_pausadas ?></p>
        </div>
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-green-500">
            <h2 class="text-gray-400 text-sm font-medium">Instâncias Disponíveis</h2>
            <p class="text-3xl font-bold text-white"><?= count($instancias_conectadas) ?></p>
        </div>
    </div>

    <!-- Planos Disponíveis -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">Planos de Maturação Disponíveis</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($planos_maturacao as $plano): ?>
                <div class="bg-card-fundo rounded-lg p-6 border-2 border-gray-700 hover:border-blue-500 transition">
                    <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($plano['nome']) ?></h3>
                    <p class="text-gray-400 text-sm mb-4"><?= htmlspecialchars($plano['descricao']) ?></p>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-2xl font-bold text-blue-400"><?= $plano['duracao_dias'] ?> dias</span>
                        <span class="text-lg font-semibold text-white"><?= number_format($plano['preco'], 2, ',', '.') ?> créditos</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Maturações Ativas/Pausadas -->
    <div class="bg-card-fundo rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">Gerenciar Maturações</h2>
            <?php if (!empty($instancias_conectadas) && !empty($planos_maturacao)): ?>
                <button @click="isStartModalOpen = true" class="bg-verde-sucesso text-white font-bold px-4 py-2 rounded-md hover:bg-green-600 transition">
                    + Iniciar Maturação
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($instancias_conectadas)): ?>
            <div class="text-center py-8">
                <div class="flex flex-col items-center">
                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-yellow-400 mb-2">Nenhuma instância conectada</h3>
                    <p class="text-gray-300 mb-4">Você precisa ter pelo menos uma instância conectada para iniciar a maturação.</p>
                    <a href="instancias.php" class="bg-roxo-principal text-white font-bold py-2 px-4 rounded-md hover:bg-purple-600 transition">
                        Gerenciar Instâncias
                    </a>
                </div>
            </div>
        <?php elseif (empty($planos_maturacao)): ?>
            <div class="text-center py-8">
                <div class="flex flex-col items-center">
                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-yellow-400 mb-2">Nenhum plano disponível</h3>
                    <p class="text-gray-300">Entre em contato com o suporte para ativar planos de maturação.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700">
                            <th class="p-4">Instância</th>
                            <th class="p-4">Plano</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Progresso</th>
                            <th class="p-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maturacoes_ativas)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-8 text-gray-400">
                                    <p class="mb-4">Nenhuma maturação ativa no momento.</p>
                                    <button @click="isStartModalOpen = true" class="bg-roxo-principal text-white px-4 py-2 rounded-md hover:bg-purple-600 transition">
                                        Iniciar Primera Maturação
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($maturacoes_ativas as $maturacao): ?>
                                <?php
                                    $is_ativa = $maturacao['data_fim_maturacao'] && new DateTime($maturacao['data_fim_maturacao']) > new DateTime();
                                    $is_pausada = $maturacao['maturacao_restante_secs'] > 0;
                                    
                                    if ($is_ativa) {
                                        $data_fim = new DateTime($maturacao['data_fim_maturacao']);
                                        $agora = new DateTime();
                                        $diff = $data_fim->diff($agora);
                                        $dias_restantes = $diff->days;
                                        $progresso = (($maturacao['duracao_dias'] - $dias_restantes) / $maturacao['duracao_dias']) * 100;
                                        $status = 'Ativa';
                                        $status_class = 'bg-blue-500';
                                    } elseif ($is_pausada) {
                                        $horas_restantes = round($maturacao['maturacao_restante_secs'] / 3600, 1);
                                        $progresso = 0; // Não sabemos o progresso quando pausada
                                        $status = 'Pausada';
                                        $status_class = 'bg-yellow-500';
                                    } else {
                                        $progresso = 0;
                                        $status = 'Finalizada';
                                        $status_class = 'bg-green-500';
                                    }
                                ?>
                                <tr class="border-b border-gray-700 hover:bg-fundo-principal">
                                    <td class="p-4">
                                        <div class="font-medium text-white"><?= htmlspecialchars($maturacao['nome_instancia']) ?></div>
                                        <div class="text-sm text-gray-400"><?= htmlspecialchars($maturacao['numero_telefone']) ?></div>
                                    </td>
                                    <td class="p-4 text-gray-300">
                                        <div class="font-semibold"><?= htmlspecialchars($maturacao['nome_plano']) ?></div>
                                        <div class="text-sm text-gray-400"><?= $maturacao['duracao_dias'] ?> dias - <?= number_format($maturacao['preco'], 2, ',', '.') ?> créditos</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 text-sm rounded-full text-white <?= $status_class ?>">
                                            <?= $status ?>
                                        </span>
                                        <?php if ($is_ativa): ?>
                                            <div class="text-xs text-gray-400 mt-1">Termina em: <?= $dias_restantes ?> dia(s)</div>
                                        <?php elseif ($is_pausada): ?>
                                            <div class="text-xs text-gray-400 mt-1">Restam: <?= $horas_restantes ?>h</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="w-full bg-fundo-principal rounded-full h-2.5">
                                            <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= max(0, min(100, $progresso)) ?>%"></div>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1"><?= round($progresso) ?>%</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-2">
                                            <?php if ($is_ativa): ?>
                                                <a href="../../src/controllers/maturacao_controller.php?action=pause&instance_id=<?= $maturacao['id'] ?>" 
                                                   onclick="return confirm('Deseja pausar esta maturação?');"
                                                   class="bg-yellow-600 text-white px-3 py-1 rounded-md text-sm hover:bg-yellow-700">
                                                    Pausar
                                                </a>
                                                <a href="../../src/controllers/maturacao_controller.php?action=stop&instance_id=<?= $maturacao['id'] ?>" 
                                                   onclick="return confirm('Deseja finalizar esta maturação? Esta ação não pode ser desfeita.');"
                                                   class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">
                                                    Finalizar
                                                </a>
                                            <?php elseif ($is_pausada): ?>
                                                <a href="../../src/controllers/maturacao_controller.php?action=resume&instance_id=<?= $maturacao['id'] ?>" 
                                                   class="bg-green-500 text-white px-3 py-1 rounded-md text-sm hover:bg-green-600">
                                                    Retomar
                                                </a>
                                                <a href="../../src/controllers/maturacao_controller.php?action=cancel&instance_id=<?= $maturacao['id'] ?>" 
                                                   onclick="return confirm('Deseja cancelar esta maturação pausada?');"
                                                   class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">
                                                    Cancelar
                                                </a>
                                            <?php else: ?>
                                                <span class="text-green-400 text-sm">Concluída</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Iniciar Maturação -->
    <div x-show="isStartModalOpen" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="isStartModalOpen = false" class="bg-card-fundo rounded-lg shadow-xl p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-white mb-4">Iniciar Nova Maturação</h2>
            <form action="../../src/controllers/maturacao_controller.php" method="POST">
                <input type="hidden" name="action" value="start">
                
                <div class="mb-4">
                    <label for="instance_id" class="block text-gray-300 text-sm font-medium mb-2">Selecionar Instância</label>
                    <select name="instance_id" id="instance_id" x-model="selectedInstance"
                            class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                        <option value="">Escolha uma instância...</option>
                        <?php foreach ($instancias_conectadas as $instancia): ?>
                            <option value="<?= $instancia['id'] ?>">
                                <?= htmlspecialchars($instancia['nome_instancia']) ?> (<?= htmlspecialchars($instancia['numero_telefone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="plano_id" class="block text-gray-300 text-sm font-medium mb-2">Selecionar Plano</label>
                    <select name="plano_id" id="plano_id" x-model="selectedPlan"
                            @change="planPrice = $event.target.selectedOptions[0].dataset.price || 0"
                            class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                        <option value="">Escolha um plano...</option>
                        <?php foreach ($planos_maturacao as $plano): ?>
                            <option value="<?= $plano['id'] ?>" data-price="<?= $plano['preco'] ?>">
                                <?= htmlspecialchars($plano['nome']) ?> - <?= $plano['duracao_dias'] ?> dias (<?= number_format($plano['preco'], 2, ',', '.') ?> créditos)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6 p-4 bg-fundo-principal rounded-lg" x-show="planPrice > 0">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-400">Seu saldo atual:</span>
                        <span class="text-white"><?= number_format($saldo_maturacao, 2, ',', '.') ?> créditos</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-1">
                        <span class="text-gray-400">Custo do plano:</span>
                        <span class="text-red-400" x-text="parseFloat(planPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' créditos'"></span>
                    </div>
                    <hr class="my-2 border-gray-600">
                    <div class="flex justify-between items-center font-semibold">
                        <span class="text-gray-300">Saldo após compra:</span>
                        <span class="text-white" x-text="(<?= $saldo_maturacao ?> - parseFloat(planPrice)).toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' créditos'"></span>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" @click="isStartModalOpen = false" class="px-4 py-2 rounded-md text-gray-300 hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-verde-sucesso text-white font-bold hover:bg-green-600 transition">Iniciar Maturação</button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once '../partials/footer.php'; ?>