// Arquivo: /backend/buscar_horarios.php

<?php
// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

// Carrega o arquivo de conexão com o banco de dados
require_once "conexao.php"; // Verifique se este caminho está correto

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
    // Requer que você tenha a tabela 'disponibilidade_dentista'
    $sql = "
        SELECT 
            dd.horario
        FROM 
            disponibilidade_dentista dd
        LEFT JOIN 
            Consulta c ON dd.usuario_id = c.usuario_dentista 
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
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor ao buscar horários: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>