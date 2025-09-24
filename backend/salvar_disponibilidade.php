<?php
session_start();
// include '../backend/conexao.php'; // Arquivo de conexão com o banco

// 1. Validação inicial
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    die("Acesso negado.");
}

$id_dentista = $_SESSION['usuario_id'];
$servico = $_POST['servico_oferecido'] ?? null;
$horarios = $_POST['horarios'] ?? [];

// $pdo = conectar(); // Conecta ao banco

// --- TAREFA A: ATUALIZAR O SERVIÇO ---
if ($servico) {
    // $stmt_servico = $pdo->prepare("UPDATE dentistas SET especialidade = ? WHERE id = ?");
    // $stmt_servico->execute([$servico, $id_dentista]);
}

// --- TAREFA B: ATUALIZAR OS HORÁRIOS ---
// 1. Limpa os horários antigos
// $stmt_delete = $pdo->prepare("DELETE FROM disponibilidade_dentista WHERE id_dentista = ?");
// $stmt_delete->execute([$id_dentista]);

// 2. Insere os novos horários
if (!empty($horarios)) {
    // $stmt_insert = $pdo->prepare("INSERT INTO disponibilidade_dentista (id_dentista, dia_semana, horario) VALUES (?, ?, ?)");
    // foreach ($horarios as $dia => $slots) {
    //     foreach ($slots as $horario) {
    //         $stmt_insert->execute([$id_dentista, $dia, $horario]);
    //     }
    // }
}

// --- ETAPA FINAL: REDIRECIONAMENTO ---
header('Location: ../admin/dashboard-dentista.php?status=disponibilidade_salva');
exit();
?>