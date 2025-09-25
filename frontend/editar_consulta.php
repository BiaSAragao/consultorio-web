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

// Serviços já vinculados à consulta
$servicosMarcados = array_column(
    $pdo->query("SELECT servico_id FROM consulta_servico WHERE consulta_id = $consulta_id")->fetchAll(PDO::FETCH_ASSOC),
    'servico_id'
);

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
$titulo_pagina = "Editar Consulta - SmileUp";
$is_dashboard = false;
include 'templates/header.php';
?>

<main class="main-container">

    <section id="editar-consulta" class="section-container">
        <h2 class="section-title">Editar Consulta</h2>

        <form method="POST" id="form-editar">

            <!-- Passo 1: Serviços -->
            <div id="passo-1" class="passo-agendamento">
                <div class="form-group">
                    <label>Selecione os serviços:</label>
                    <div class="servicos-list">
                        <fieldset class="fieldset-servico">
                            <legend><strong>Serviços Disponíveis</strong></legend>
                            <?php foreach ($servicos as $s): ?>
                                <div class="servico-item">
                                    <input type="checkbox"
                                           id="servico_<?= $s['servico_id'] ?>"
                                           name="servicos[]"
                                           value="<?= $s['servico_id'] ?>"
                                           <?= in_array($s['servico_id'], $servicosMarcados) ? 'checked' : '' ?>>
                                    <label for="servico_<?= $s['servico_id'] ?>">
                                        <?= htmlspecialchars($s['nome_servico']) ?>
                                        (R$ <?= number_format($s['preco'], 2, ',', '.') ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="irParaPasso(2)">Continuar</button>
                </div>
            </div>

            <!-- Passo 2: Data e Hora -->
            <div id="passo-2" class="passo-agendamento" style="display:none;">
                <h3 class="subsection-title">Escolha a Data e Horário</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" value="<?= htmlspecialchars($consulta['data']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="hora">Hora</label>
                        <input type="time" id="hora" name="hora" value="<?= htmlspecialchars($consulta['hora']) ?>" required>
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="irParaPasso(3)">Continuar</button>
                </div>
            </div>

            <!-- Passo 3: Observações -->
            <div id="passo-3" class="passo-agendamento" style="display:none;">
                <h3 class="subsection-title">Informações Adicionais</h3>
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes"><?= htmlspecialchars($obs['obs'] ?? '') ?></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico</label>
                        <textarea id="historico" name="historico"><?= htmlspecialchars($obs['historico'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias</label>
                        <textarea id="alergias" name="alergias"><?= htmlspecialchars($obs['alergias'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </div>

        </form>
    </section>
</main>

<style>
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    .servicos-list {
        border: 1px solid #ccc;
        padding: 15px;
        border-radius: 5px;
        max-height: 300px;
        overflow-y: auto;
    }
    .fieldset-servico legend {
        font-size: 1.1em;
        margin-bottom: 5px;
        color: #007bff;
    }
    .servico-item { margin-bottom: 5px; }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    @media (min-width: 768px) {
        .form-grid { grid-template-columns: 1fr 1fr; }
    }
    .subsection-title {
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
        margin-bottom: 15px;
        color: #333;
    }
</style>

<script>
    function irParaPasso(numero) {
        document.querySelectorAll('.passo-agendamento').forEach(p => p.style.display = 'none');
        document.getElementById('passo-' + numero).style.display = 'block';
        window.scrollTo(0,0);
    }
    document.addEventListener('DOMContentLoaded', () => irParaPasso(1));
</script>

<?php include 'templates/footer.php'; ?>
