<?php
require_once "conexao.php";
session_start();

if (!isset($_SESSION["usuario_id"])) {
    die("Você precisa estar logado para agendar uma consulta.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = $_POST["data"];
    $hora = $_POST["hora"];
    $servicos = $_POST["servicos"] ?? [];
    $observacoes = $_POST["observacoes"] ?? '';
    $historico   = $_POST["historico"] ?? '';
    $alergias    = $_POST["alergias"] ?? '';

    $usuario_paciente = $_SESSION["usuario_id"];
    $usuario_dentista = 3; // substitua pelo ID real do dentista cadastrado

    // Transformar em JSON
    $observacoes_json = json_encode([
        'obs'       => $observacoes,
        'historico' => $historico,
        'alergias'  => $alergias
    ]);

    // calcular valor total
    $valor_total = 0;
    if (!empty($servicos)) {
        $ids = implode(',', array_map('intval', $servicos));
        $sql = "SELECT SUM(preco) AS total FROM servico WHERE servico_id IN ($ids)";
        $valor_total = $pdo->query($sql)->fetchColumn();
    }

    // inserir consulta
    $stmt = $pdo->prepare("INSERT INTO consulta (data, hora, usuario_paciente, usuario_dentista, valor, observacoes)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data,
        $hora,
        $usuario_paciente,
        $usuario_dentista,
        $valor_total,
        json_encode([
            "observacoes" => $observacoes,
            "historico" => $historico,
            "alergias" => $alergias
        ])
    ]);

    // pegar o id da consulta criada
    $consulta_id = $pdo->lastInsertId();

    // vincular serviços
    if (!empty($servicos)) {
        $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
        foreach ($servicos as $s) {
            $stmt_s->execute([$consulta_id, $s]);
        }
    }

    header("Location: ../frontend/dashboard-paciente.php?msg=Consulta agendada com sucesso!");
    exit;

} else {
    die("Método inválido.");
}