<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$campanha_ids = $_GET['ids'] ?? null;
if (!$campanha_ids) {
    echo json_encode(['success' => false, 'message' => 'Nenhum ID de campanha fornecido']);
    exit;
}

$cliente_id = $_SESSION['user_id'];
$ids_array = explode(',', $campanha_ids);
$placeholders = implode(',', array_fill(0, count($ids_array), '?'));

$progress_data = [];

try {
    // Busca o status de todas as campanhas de uma vez
    $stmt_campanhas = $pdo->prepare("SELECT id, status FROM campanhas WHERE id IN ($placeholders) AND cliente_id = ?");
    $stmt_campanhas->execute(array_merge($ids_array, [$cliente_id]));
    $campanhas_status = $stmt_campanhas->fetchAll(PDO::FETCH_KEY_PAIR);

    // Busca as estatísticas de todas as campanhas de uma vez
    $stmt_stats = $pdo->prepare("
        SELECT 
            campanha_id,
            COUNT(id) as total,
            SUM(CASE WHEN status != 'Pendente' THEN 1 ELSE 0 END) as processadas
        FROM fila_envio 
        WHERE campanha_id IN ($placeholders)
        GROUP BY campanha_id
    ");
    $stmt_stats->execute($ids_array);
    $all_stats = $stmt_stats->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

    foreach ($ids_array as $id) {
        $stats = $all_stats[$id] ?? ['total' => 0, 'processadas' => 0];
        $progresso = ($stats['total'] > 0) ? ($stats['processadas'] / $stats['total']) * 100 : 0;
        
        $progress_data[$id] = [
            'progress' => round($progresso),
            'status' => $campanhas_status[$id] ?? 'Desconhecido'
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $progress_data]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>