<?php
require_once "conexao.php";

if (!isset($_GET['categoria_id'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Categoria não informada']);
    exit;
}

$categoria_id = (int) $_GET['categoria_id'];

// Busca o dentista responsável por essa categoria
$stmt = $pdo->prepare("
    SELECT u.usuario_id, u.nome, d.cro
    FROM Dentista d
    JOIN Usuario u ON u.usuario_id = d.usuario_id
    JOIN Servico s ON s.categoria_id = :categoria_id
    WHERE s.categoria_id = :categoria_id
    LIMIT 1
");
$stmt->execute(['categoria_id' => $categoria_id]);
$dentista = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dentista) {
    echo json_encode($dentista);
} else {
    echo json_encode(['erro' => 'Nenhum dentista encontrado']);
}
