<?php
// Define o cabeçalho para retornar JSON
// --- LINHAS DE DEBUG ATIVADAS ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ------------------------------------

// 1. Caminho de conexão corrigido (subindo um nível para /backend/)
require_once "../backend/conexao.php"; 

// 2. Verificação de Escopo do $pdo: Garante que a variável de conexão existe
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'ERRO FATAL: Variável de conexão $pdo não está acessível.']);
    exit();
}
// ------------------------------------

header('Content-Type: application/json');

// 1. Validar e capturar dados de entrada (via POST)
if (!isset($_POST['dentista_id']) || !isset($_POST['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados insuficientes (Dentista ID e Data são obrigatórios).']);
    exit();
}

$dentista_id = filter_var($_POST['dentista_id'], FILTER_VALIDATE_INT);
$data_consulta = $_POST['data'];

if (!$dentista_id || empty($data_consulta)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados de dentista ou data inválidos.']);
    exit();
}

try {
    // 2. Determinar o dia da semana em português
    $timestamp = strtotime($data_consulta);
    $dia_semana_en = strtolower(date('l', $timestamp)); 
    
    $dias_semana = [
        'monday' => 'segunda', 'tuesday' => 'terca', 'wednesday' => 'quarta',
        'thursday' => 'quinta', 'friday' => 'sexta',
    ];

    $dia_semana_db = $dias_semana[$dia_semana_en] ?? null;

    if ($dia_semana_db === null) {
        echo json_encode(['disponiveis' => [], 'message' => 'O dentista não trabalha neste dia da semana.']);
        exit();
    }

    // 3. Consultar horários disponíveis
    $sql = "
        SELECT 
            dd.horario
        FROM 
            disponibilidade_dentista dd
        LEFT JOIN 
            consulta c ON dd.usuario_id = c.usuario_dentista  -- CORREÇÃO: 'consulta' em minúsculo
                        AND dd.horario = c.hora              
                        AND c.data = :data_consulta
        WHERE 
            dd.usuario_id = :dentista_id 
            AND dd.dia_semana = :dia_semana_db
            -- Horário é disponível se não houver consulta agendada
            AND c.consulta_id IS NULL
        ORDER BY
            dd.horario ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':dentista_id', $dentista_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_consulta', $data_consulta);
    $stmt->bindParam(':dia_semana_db', $dia_semana_db);
    $stmt->execute();
    
    $horarios_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($horarios_disponiveis)) {
        echo json_encode(['disponiveis' => [], 'message' => 'Nenhum horário disponível encontrado para esta data.']);
    } else {
        echo json_encode(['disponiveis' => $horarios_disponiveis]);
    }

} catch (PDOException $e) {
    // Captura erros SQL (Ex: coluna não encontrada)
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor (PDO): ' . $e->getMessage()]);
} catch (Exception $e) {
    // Captura erros gerais
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>