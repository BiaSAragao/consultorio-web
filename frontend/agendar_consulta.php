<?php
require_once "../backend/conexao.php";
session_start();

// garante que só entra quem está logado
if (!isset($_SESSION["usuario_id"])) {
    die("Você precisa estar logado para agendar uma consulta.");
}

// buscar serviços
$sql = "SELECT servico_id, nome_servico, preco FROM servico ORDER BY nome_servico";
$servicos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Agendar Consulta</title>
</head>
<body>
    <h1>Agendar Consulta</h1>

    <form action="processa_agendamento.php" method="POST">
        <!-- Data -->
        <label for="data">Data:</label>
        <input type="date" id="data" name="data" required><br><br>

        <!-- Hora -->
        <label for="hora">Hora:</label>
        <input type="time" id="hora" name="hora" required><br><br>

        <!-- Serviços -->
        <fieldset>
            <legend>Serviços</legend>
            <?php foreach ($servicos as $s): ?>
                <div>
                    <input type="checkbox" name="servicos[]" value="<?= $s['servico_id'] ?>">
                    <?= htmlspecialchars($s['nome_servico']) ?> - 
                    R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <br>

        <!-- Observações -->
        <label for="observacoes">Observações:</label><br>
        <textarea id="observacoes" name="observacoes" placeholder="Digite aqui..."></textarea><br><br>

        <!-- Info -->
        <p><strong>O valor total será calculado automaticamente.</strong></p>

        <button type="submit">Agendar</button>
    </form>
</body>
</html>
