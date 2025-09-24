<?php
session_start();
header('Content-Type: application/json');

// Função para remover o "caracter fantasma" (BOM) do início de um texto
function remove_utf8_bom($text) {
    return preg_replace("/^\xEF\xBB\xBF/", '', $text);
}

// Resposta padrão em caso de falha
$response = [
    'success' => false,
    'headers' => [],
    'message' => 'Ocorreu um erro inesperado.'
];

// Validação de segurança
if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit;
}

// Verifica se um arquivo foi enviado corretamente
if (isset($_FILES['lista_contatos']) && $_FILES['lista_contatos']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['lista_contatos']['tmp_name'];
    
    // Abre o arquivo CSV enviado para leitura
    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
        // Lê apenas a primeira linha do arquivo
        $primeira_linha_str = fgets($handle);
        fclose($handle); // Fecha o arquivo

        // Limpa o BOM da primeira linha
        $primeira_linha_limpa = remove_utf8_bom($primeira_linha_str);
        
        // Detecta o delimitador (vírgula ou ponto e vírgula)
        $delimitador = (strpos($primeira_linha_limpa, ';') !== false) ? ';' : ',';
        
        // Converte a string do cabeçalho em um array
        $headers = str_getcsv($primeira_linha_limpa, $delimitador);

        if ($headers) {
            // Limpa cada item do cabeçalho de espaços e aspas
            $cleanedHeaders = array_map(function($h) {
                return trim(str_replace('"', '', $h));
            }, $headers);

            // Preenche a resposta de sucesso com os cabeçalhos reais
            $response['success'] = true;
            $response['headers'] = array_filter($cleanedHeaders); // Remove quaisquer itens vazios
            $response['message'] = 'Cabeçalho lido com sucesso.';
        } else {
            $response['message'] = 'Não foi possível ler o cabeçalho do arquivo CSV.';
        }
    } else {
        $response['message'] = 'Falha ao abrir o arquivo enviado.';
    }
} else {
    $response['message'] = 'Nenhum arquivo CSV válido foi enviado.';
}

// Retorna a resposta final em formato JSON para o JavaScript
echo json_encode($response);
exit;
?>