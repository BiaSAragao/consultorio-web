<?php
require_once "conexao.php";
session_start();

// Garante que a sessão está ativa
if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../frontend/login.php");
    exit("Você precisa estar logado para agendar uma consulta.");
}

// Garante que é uma submissão POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método inválido.");
}

try {
    // 1. COLETA E NORMALIZAÇÃO DOS DADOS
    $data = $_POST["data"] ?? null; 
    $hora = $_POST["horario"] ?? null; // CORRIGIDO: Usa 'horario'
    $servicos = $_POST["servicos"] ?? [];
    
    // CAPTURA O ID DO DENTISTA DO FORMULÁRIO
    $usuario_dentista = $_POST["dentista_selecionado"] ?? null; 
    
    // Dados adicionais
    $observacoes = $_POST["observacoes"] ?? '';
    $historico = $_POST["historico"] ?? '';
    $alergias = $_POST["alergias"] ?? '';

    $usuario_paciente = $_SESSION["usuario_id"];
    
    // VERIFICAÇÃO DE CAMPOS OBRIGATÓRIOS
    if (empty($data) || empty($hora) || empty($usuario_dentista) || empty($servicos)) {
        header("Location: ../frontend/agendar_consulta.php?msg=Por favor, preencha todos os campos obrigatórios (Serviços, Data, e Horário).");
        exit;
    }


    // 2. VERIFICAÇÃO DE DISPONIBILIDADE (CRÍTICO)
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM consulta 
        WHERE usuario_dentista = ? AND data = ? AND hora = ?
    ");
    $stmt_check->execute([$usuario_dentista, $data, $hora]);

    if ($stmt_check->fetchColumn() > 0) {
        header("Location: ../frontend/agendar_consulta.php?msg=O horário selecionado não está mais disponível.");
        exit;
    }

    // 3. CÁLCULO DO VALOR TOTAL
    $valor_total = 0;
    if (!empty($servicos)) {
        $ids = implode(',', array_map('intval', $servicos));
        $sql = "SELECT SUM(preco) AS total FROM servico WHERE servico_id IN ($ids)";
        $valor_total = $pdo->query($sql)->fetchColumn();
    }
    
    // Inicia a transação
    $pdo->beginTransaction();

    // 4. INSERÇÃO DA CONSULTA PRINCIPAL
    $observacoes_json_final = json_encode([
        "observacoes" => $observacoes,
        "historico" => $historico,
        "alergias" => $alergias
    ]);
    
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

    // Pega o ID da consulta criada
    $consulta_id = $pdo->lastInsertId();

    // 5. VINCULAR SERVIÇOS
    if (!empty($servicos)) {
        $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
        foreach ($servicos as $s) {
            $stmt_s->execute([$consulta_id, $s]);
        }
    }
    
    // Confirma a transação
    $pdo->commit();

    // 6. REDIRECIONAMENTO DE SUCESSO
    header("Location: ../frontend/dashboard-paciente.php?msg=Consulta agendada com sucesso!");
    exit;

} catch (PDOException $e) {
    // Em caso de erro, desfaz a transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Para debug:
    // echo "Erro: " . $e->getMessage(); exit;
    header("Location: ../frontend/agendar_consulta.php?msg=Ocorreu um erro ao agendar a consulta.");
    exit;
}