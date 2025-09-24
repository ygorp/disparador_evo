<?php
require_once '../partials/header.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$cliente_id = $_SESSION['user_id'];

try {
    // Busca informações básicas do cliente
    $stmt_cliente = $pdo->prepare("SELECT nome, saldo_creditos_disparo, saldo_creditos_maturacao FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente = $stmt_cliente->fetch();

    // Estatísticas de instâncias
    $stmt_inst = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Conectado' THEN 1 ELSE 0 END) as conectadas FROM instancias WHERE cliente_id = ?");
    $stmt_inst->execute([$cliente_id]);
    $stats_instancias = $stmt_inst->fetch();

    // Estatísticas de campanhas
    $stmt_camp = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Enviando' THEN 1 ELSE 0 END) as ativas FROM campanhas WHERE cliente_id = ?");
    $stmt_camp->execute([$cliente_id]);
    $stats_campanhas = $stmt_camp->fetch();

    // Maturações ativas
    $stmt_mat = $pdo->prepare("SELECT COUNT(*) as ativas FROM instancias WHERE cliente_id = ? AND (data_fim_maturacao > NOW() OR maturacao_restante_secs > 0)");
    $stmt_mat->execute([$cliente_id]);
    $maturacoes_ativas = $stmt_mat->fetchColumn();

    // Últimas atividades (campanhas recentes)
    $stmt_atividades = $pdo->prepare("SELECT nome_campanha, status, data_criacao FROM campanhas WHERE cliente_id = ? ORDER BY data_criacao DESC LIMIT 5");
    $stmt_atividades->execute([$cliente_id]);
    $atividades_recentes = $stmt_atividades->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar o dashboard: " . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Cabeçalho de Boas-vindas -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Olá, <?= htmlspecialchars(explode(' ', $cliente['nome'])[0]) ?>! 👋</h1>
        <p class="text-gray-400">Bem-vindo ao seu painel de controle. Aqui você pode acompanhar suas atividades e acessar todas as funcionalidades.</p>
    </div>

    <!-- Cards de Saldo -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-gradient-to-br from-azul-principal to-azul-acento rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium opacity-90">Créditos para Disparos</h3>
                    <p class="text-3xl font-bold"><?= number_format($cliente['saldo_creditos_disparo'], 0, ',', '.') ?></p>
                    <p class="text-sm opacity-75 mt-1">créditos disponíveis</p>
                </div>
                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium opacity-90">Créditos para Maturação</h3>
                    <p class="text-3xl font-bold"><?= number_format($cliente['saldo_creditos_maturacao'], 2, ',', '.') ?></p>
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

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-roxo-principal">
            <h3 class="text-gray-400 text-sm font-medium">Instâncias</h3>
            <div class="flex items-center justify-between">
                <p class="text-2xl font-bold text-white"><?= $stats_instancias['conectadas'] ?>/<?= $stats_instancias['total'] ?></p>
                <span class="text-xs text-gray-500">conectadas</span>
            </div>
        </div>

        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-green-500">
            <h3 class="text-gray-400 text-sm font-medium">Campanhas Ativas</h3>
            <p class="text-2xl font-bold text-white"><?= $stats_campanhas['ativas'] ?></p>
        </div>

        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
            <h3 class="text-gray-400 text-sm font-medium">Maturações Ativas</h3>
            <p class="text-2xl font-bold text-white"><?= $maturacoes_ativas ?></p>
        </div>

        <div class="bg-card-fundo p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
            <h3 class="text-gray-400 text-sm font-medium">Total de Campanhas</h3>
            <p class="text-2xl font-bold text-white"><?= $stats_campanhas['total'] ?></p>
        </div>
    </div>

    <!-- Grid Principal -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Ações Rápidas -->
        <div class="lg:col-span-2">
            <div class="bg-card-fundo rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-white mb-6">Ações Rápidas</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <a href="instancias.php" class="group bg-fundo-principal rounded-lg p-6 hover:bg-gray-700 transition-all duration-200 border border-gray-700 hover:border-roxo-principal">
                        <div class="flex items-center space-x-4">
                            <div class="bg-roxo-principal p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white group-hover:text-roxo-principal">Gerenciar Instâncias</h3>
                                <p class="text-sm text-gray-400">Criar, conectar e configurar</p>
                            </div>
                        </div>
                    </a>

                    <a href="disparos.php" class="group bg-fundo-principal rounded-lg p-6 hover:bg-gray-700 transition-all duration-200 border border-gray-700 hover:border-green-500">
                        <div class="flex items-center space-x-4">
                            <div class="bg-green-500 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white group-hover:text-green-400">Criar Campanha</h3>
                                <p class="text-sm text-gray-400">Disparos em massa</p>
                            </div>
                        </div>
                    </a>

                    <a href="maturacao.php" class="group bg-fundo-principal rounded-lg p-6 hover:bg-gray-700 transition-all duration-200 border border-gray-700 hover:border-blue-500">
                        <div class="flex items-center space-x-4">
                            <div class="bg-blue-500 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white group-hover:text-blue-400">Iniciar Maturação</h3>
                                <p class="text-sm text-gray-400">Aquecer números</p>
                            </div>
                        </div>
                    </a>

                    <a href="comprar_planos.php" class="group bg-fundo-principal rounded-lg p-6 hover:bg-gray-700 transition-all duration-200 border border-gray-700 hover:border-yellow-500">
                        <div class="flex items-center space-x-4">
                            <div class="bg-yellow-500 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white group-hover:text-yellow-400">Comprar Créditos</h3>
                                <p class="text-sm text-gray-400">Recarregar saldo</p>
                            </div>
                        </div>
                    </a>

                </div>
            </div>
        </div>

        <!-- Atividades Recentes -->
        <div>
            <div class="bg-card-fundo rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-white mb-6">Atividades Recentes</h2>
                
                <?php if (empty($atividades_recentes)): ?>
                    <div class="text-center py-6">
                        <svg class="w-12 h-12 text-gray-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-gray-400 text-sm">Nenhuma campanha criada ainda.</p>
                        <a href="disparos.php" class="text-roxo-principal text-sm hover:underline mt-2 inline-block">Criar primeira campanha</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($atividades_recentes as $atividade): ?>
                            <div class="flex items-center space-x-3 p-3 bg-fundo-principal rounded-lg">
                                <div class="flex-shrink-0">
                                    <?php
                                        $icon_color = 'bg-gray-500';
                                        if ($atividade['status'] === 'Enviando') $icon_color = 'bg-blue-500';
                                        elseif ($atividade['status'] === 'Concluída') $icon_color = 'bg-green-500';
                                        elseif ($atividade['status'] === 'Pausada') $icon_color = 'bg-yellow-500';
                                    ?>
                                    <div class="w-3 h-3 rounded-full <?= $icon_color ?>"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($atividade['nome_campanha']) ?></p>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs text-gray-400"><?= $atividade['status'] ?></span>
                                        <span class="text-xs text-gray-500">•</span>
                                        <span class="text-xs text-gray-500"><?= (new DateTime($atividade['data_criacao']))->format('d/m H:i') ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-700">
                        <a href="disparos.php" class="text-sm text-azul-acento hover:underline">Ver todas as campanhas →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Banner de Ajuda (opcional) -->
    <div class="mt-8 bg-gradient-to-r from-azul-acento to-azul-principal rounded-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-2">Precisa de ajuda?</h3>
                <p class="text-sm opacity-90">Confira nossos tutoriais e aprenda a usar todas as funcionalidades da plataforma.</p>
            </div>
            <div class="flex space-x-3">
                <a href="https://clube.nexiustech.com.br" class="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg text-sm font-medium transition">
                    Ver Tutoriais
                </a>
                <a href="https://wa.me/552734415852?text=Ol%C3%A1%2C%20gostaria%20de%20ajudar%20sobre%20o%20sistema%20de%20matura%C3%A7%C3%A3o%20e%20disparo." class="bg-white text-azul-principal px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100 transition">
                    Contatar Suporte
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once '../partials/footer.php'; ?>