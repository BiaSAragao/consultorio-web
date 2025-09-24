<?php
require_once "conexao.php";
session_start();

// Garante que a sessão está ativa
if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../frontend/login.php");
    exit("Você precisa estar logado para agendar/editar uma consulta.");
}

// Garante que é uma submissão POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método inválido.");
}

$acao = $_GET["acao"] ?? "criar";

try {
    // 1. COLETA E NORMALIZAÇÃO DOS DADOS
    $data        = $_POST["data"];
    $hora        = $_POST["horario_selecionado"];
    $servicos    = $_POST["servicos"] ?? [];
    $observacoes = $_POST["observacoes"] ?? '';
    $historico   = $_POST["historico"] ?? '';
    $alergias    = $_POST["alergias"] ?? '';

    $usuario_paciente = $_SESSION["usuario_id"];
    $usuario_dentista = 3; // Dentista Fixo conforme agendar_consulta.php

    // 2. VERIFICAÇÃO DE DISPONIBILIDADE (exceto quando edição mantiver mesmo horário)
    $sql_check = "SELECT COUNT(*) FROM consulta WHERE usuario_dentista = ? AND data = ? AND hora = ?";
    $params_check = [$usuario_dentista, $data, $hora];

    if ($acao === "editar") {
        $consulta_id = intval($_GET["id"] ?? 0);
        if ($consulta_id <= 0) {
            die("Consulta inválida para edição.");
        }
        // Não conta a própria consulta ao checar conflito de horário
        $sql_check .= " AND consulta_id <> ?";
        $params_check[] = $consulta_id;
    }

    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params_check);

    if ($stmt_check->fetchColumn() > 0) {
        $msg = "O horário selecionado não está mais disponível.";
        if ($acao === "editar") {
            header("Location: ../frontend/editar_consulta.php?id=$consulta_id&msg=$msg");
        } else {
            header("Location: ../frontend/agendar_consulta.php?msg=$msg");
        }
        exit;
    }

    // 3. CÁLCULO DO VALOR TOTAL
    $valor_total = 0;
    if (!empty($servicos)) {
        $ids = implode(',', array_map('intval', $servicos));
        $sql = "SELECT SUM(preco) AS total FROM servico WHERE servico_id IN ($ids)";
        $valor_total = $pdo->query($sql)->fetchColumn();
    }

    // 4. INICIA A TRANSAÇÃO
    $pdo->beginTransaction();

    // Prepara observações extras como JSON
    $observacoes_json_final = json_encode([
        "observacoes" => $observacoes,
        "historico"   => $historico,
        "alergias"    => $alergias
    ]);

    if ($acao === "criar") {
        // 5A. INSERÇÃO DA CONSULTA PRINCIPAL
        $stmt = $pdo->prepare("
            INSERT INTO consulta (data, hora, usuario_paciente, usuario_dentista, valor, observacoes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data,
            $hora,
            $usuario_paciente,
            $usuario_dentista,
            $valor_total,
            $observacoes_json_final
        ]);
        $consulta_id = $pdo->lastInsertId();

    } elseif ($acao === "editar") {
        // 5B. UPDATE DA CONSULTA EXISTENTE
        $stmt = $pdo->prepare("
            UPDATE consulta 
            SET data = ?, hora = ?, valor = ?, observacoes = ?
            WHERE consulta_id = ? AND usuario_paciente = ?
        ");
        $stmt->execute([
            $data,
            $hora,
            $valor_total,
            $observacoes_json_final,
            $consulta_id,
            $usuario_paciente
        ]);

        // Limpa serviços antigos
        $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?")
            ->execute([$consulta_id]);
    }

    // 6. VINCULAR SERVIÇOS
    if (!empty($servicos)) {
        $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
        foreach ($servicos as $s) {
            $stmt_s->execute([$consulta_id, $s]);
        }
    }

    // 7. CONFIRMA A TRANSAÇÃO
    $pdo->commit();

    // 8. REDIRECIONAMENTO DE SUCESSO
    if ($acao === "editar") {
        header("Location: ../frontend/editar_consulta.php?id=$consulta_id&msg=Consulta atualizada com sucesso!");
    } else {
        header("Location: ../frontend/dashboard-paciente.php?msg=Consulta agendada com sucesso!");
    }
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = "Ocorreu um erro ao processar a consulta.";
    if ($acao === "editar") {
        header("Location: ../frontend/editar_consulta.php?id=$consulta_id&msg=$msg");
    } else {
        header("Location: ../frontend/agendar_consulta.php?msg=$msg");
    }
    exit;
}
