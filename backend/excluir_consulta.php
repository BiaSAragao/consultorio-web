<?php
require_once "conexao.php";
session_start();

// Garante que só quem está logado pode excluir
if (!isset($_SESSION['usuario_id'])) {
    die("Você precisa estar logado para cancelar uma consulta.");
}

if (!isset($_GET['id'])) {
    die("ID da consulta não informado.");
}

$consulta_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

// Verifica se a consulta pertence ao paciente logado
$stmt = $pdo->prepare("SELECT * FROM consulta WHERE consulta_id = ? AND usuario_paciente = ?");
$stmt->execute([$consulta_id, $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    die("Consulta não encontrada ou não pertence a você.");
}

// Primeiro, remove os serviços vinculados
$stmt_s = $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?");
$stmt_s->execute([$consulta_id]);

// Depois, remove a própria consulta
$stmt_c = $pdo->prepare("DELETE FROM consulta WHERE consulta_id = ?");
$stmt_c->execute([$consulta_id]);

header("Location: ../frontend/dashboard-paciente.php?msg=Consulta cancelada com sucesso!");
exit;