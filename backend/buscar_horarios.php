// Arquivo: backend/buscar_horarios.php

<?php
// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

// Carrega o arquivo de conexão com o banco de dados
require_once "conexao.php";

// 1. Validar e capturar dados de entrada (via POST, conforme o JS)
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
    if ($timestamp === false) {
        throw new Exception("Formato de data inválido.");
    }

    $dia_semana_en = strtolower(date('l', $timestamp)); // Ex: 'monday'
    
    // Mapeamento de dias para o formato do banco de dados (tabela disponibilidade_dentista)
    $dias_semana = [
        'monday' => 'segunda',
        'tuesday' => 'terca',
        'wednesday' => 'quarta',
        'thursday' => 'quinta',
        'friday' => 'sexta',
    ];

    $dia_semana_db = $dias_semana[$dia_semana_en] ?? null;

    if ($dia_semana_db === null) {
        // Se for fim de semana ou um dia não mapeado
        echo json_encode(['disponiveis' => [], 'message' => 'O dentista não trabalha neste dia da semana.']);
        exit();
    }

    // 3. Consultar horários disponíveis:
    $sql = "
        SELECT 
            dd.horario
        FROM 
            disponibilidade_dentista dd
        LEFT JOIN 
            consulta c ON dd.usuario_id = c.dentista_id 
                        AND dd.horario = c.horario 
                        AND c.data = :data_consulta
        WHERE 
            dd.usuario_id = :dentista_id 
            AND dd.dia_semana = :dia_semana_db
            -- A condição é que a consulta 'c' seja NULA (ou seja, horário não ocupado)
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