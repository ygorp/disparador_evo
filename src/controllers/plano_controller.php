<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Validação de segurança
if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'views/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$cliente_id = $_SESSION['user_id'];
$plano_id = $_POST['plano_id'] ?? null;

if (!$plano_id) {
    header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
    exit;
}

try {
    // Busca os detalhes do plano selecionado
    $stmt_plano = $pdo->prepare("SELECT tipo, creditos_disparo, preco FROM planos WHERE id = ?");
    $stmt_plano->execute([$plano_id]);
    $plano = $stmt_plano->fetch();

    if ($plano) {
        // Roteador de Ações de Compra
        if ($action === 'buy_credits_disparo' && $plano['tipo'] === 'disparo') {
            
            // Lógica para adicionar créditos de DISPARO
            // TODO: Adicionar integração com gateway de pagamento para o valor $plano['preco']
            
            $creditos_a_adicionar = $plano['creditos'];
            $stmt_update = $pdo->prepare("UPDATE clientes SET saldo_creditos_disparo = saldo_creditos_disparo + ? WHERE id = ?");
            $stmt_update->execute([$creditos_a_adicionar, $cliente_id]);
            
            header('Location: ' . BASE_URL . 'views/client/disparos.php');
            exit;

        } elseif ($action === 'buy_credits_maturacao' && $plano['tipo'] === 'maturacao') {
            
            // Lógica para adicionar créditos de MATURAÇÃO
            // TODO: Adicionar integração com gateway de pagamento para o valor $plano['preco']

            $creditos_a_adicionar = $plano['preco']; // Na maturação, o 'preço' é o custo em créditos
            $stmt_update = $pdo->prepare("UPDATE clientes SET saldo_creditos_maturacao = saldo_creditos_maturacao + ? WHERE id = ?");
            $stmt_update->execute([$creditos_a_adicionar, $cliente_id]);

            header('Location: ' . BASE_URL . 'views/client/maturacao.php');
            exit;
        }
    }

} catch (PDOException $e) {
    die("Erro ao processar a compra: " . $e->getMessage());
}

// Se algo der errado, volta para a página de compra
header('Location: ' . BASE_URL . 'views/client/comprar_planos.php');
exit;