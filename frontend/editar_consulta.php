<?php
require_once "../backend/conexao.php";
session_start();

if (!isset($_SESSION['usuario_id'])) {
    die("Você precisa estar logado para editar uma consulta.");
}

if (!isset($_GET['id'])) {
    die("ID da consulta não informado.");
}

$consulta_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

// Busca a consulta
$stmt = $pdo->prepare("SELECT * FROM consulta WHERE consulta_id = ? AND usuario_paciente = ?");
$stmt->execute([$consulta_id, $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    die("Consulta não encontrada ou não pertence a você.");
}

// Buscar serviços disponíveis
$servicos = $pdo->query("SELECT servico_id, nome_servico, preco FROM servico ORDER BY nome_servico")->fetchAll(PDO::FETCH_ASSOC);

// Se enviou POST, atualiza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $hora = $_POST['hora'];
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    $valor_total = 0;
    if (!empty($servicos_selecionados)) {
        $ids = implode(',', array_map('intval', $servicos_selecionados));
        $valor_total = $pdo->query("SELECT SUM(preco) FROM servico WHERE servico_id IN ($ids)")->fetchColumn();
    }

    $observacoes_json = json_encode([
        'obs' => $observacoes,
        'historico' => $historico,
        'alergias' => $alergias
    ]);

    // Atualizar consulta
    $stmt_update = $pdo->prepare("UPDATE consulta SET data = ?, hora = ?, valor = ?, observacoes = ? WHERE consulta_id = ?");
    $stmt_update->execute([$data, $hora, $valor_total, $observacoes_json, $consulta_id]);

    // Atualiza os serviços
    $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?")->execute([$consulta_id]);
    $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
    foreach ($servicos_selecionados as $s) {
        $stmt_s->execute([$consulta_id, $s]);
    }

    header("Location: dashboard-paciente.php?msg=Consulta atualizada com sucesso!");
    exit;
}

// Decodifica observações
$obs = json_decode($consulta['observacoes'], true);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Consulta</title>
</head>
<body>
    <h1>Editar Consulta</h1>
    <form method="POST">
        <label>Data:</label>
        <input type="date" name="data" value="<?= htmlspecialchars($consulta['data']) ?>" required><br><br>

        <label>Hora:</label>
        <input type="time" name="hora" value="<?= htmlspecialchars($consulta['hora']) ?>" required><br><br>

        <fieldset>
            <legend>Serviços</legend>
            <?php foreach ($servicos as $s): ?>
                <div>
                    <input type="checkbox" name="servicos[]" value="<?= $s['servico_id'] ?>"
                        <?= in_array($s['servico_id'], array_column($pdo->query("SELECT servico_id FROM consulta_servico WHERE consulta_id = $consulta_id")->fetchAll(PDO::FETCH_ASSOC), 'servico_id')) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($s['nome_servico']) ?>
                </div>
            <?php endforeach; ?>
        </fieldset><br>

        <label>Observações:</label><br>
        <textarea name="observacoes"><?= htmlspecialchars($obs['obs'] ?? '') ?></textarea><br><br>

        <label>Histórico:</label><br>
        <textarea name="historico"><?= htmlspecialchars($obs['historico'] ?? '') ?></textarea><br><br>

        <label>Alergias:</label><br>
        <textarea name="alergias"><?= htmlspecialchars($obs['alergias'] ?? '') ?></textarea><br><br>

        <button type="submit">Salvar Alterações</button>
    </form>
</body>
</html>