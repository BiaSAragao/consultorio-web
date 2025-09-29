<?php
require_once "conexao.php";

if (!isset($_GET['dentista_id']) || !isset($_GET['data'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$dentista_id = (int) $_GET['dentista_id'];
$data = $_GET['data'];
$dia_semana = strtolower(date('l', strtotime($data)));

// Converte nomes do PHP para correspondência do banco
$mapa_dia = [
    'monday'=>'segunda',
    'tuesday'=>'terca',
    'wednesday'=>'quarta',
    'thursday'=>'quinta',
    'friday'=>'sexta',
];
$dia_semana = $mapa_dia[$dia_semana] ?? null;

if (!$dia_semana) {
    echo json_encode([]);
    exit;
}

// Busca horários do dentista nesse dia da semana
$stmt = $pdo->prepare("
    SELECT horario
    FROM disponibilidade_dentista
    WHERE usuario_id = :dentista_id
      AND dia_semana = :dia_semana
");
$stmt->execute(['dentista_id'=>$dentista_id, 'dia_semana'=>$dia_semana]);
$horarios = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Remove horários já ocupados
$stmt2 = $pdo->prepare("
    SELECT hora 
    FROM Consulta
    WHERE usuario_dentista = :dentista_id
      AND data = :data
");
$stmt2->execute(['dentista_id'=>$dentista_id, 'data'=>$data]);
$ocupados = $stmt2->fetchAll(PDO::FETCH_COLUMN);

$disponiveis = array_values(array_diff($horarios, $ocupados));

echo json_encode($disponiveis);
