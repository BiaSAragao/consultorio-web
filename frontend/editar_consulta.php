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

// Decodifica observações
$obs = json_decode($consulta['observacoes'], true);

// Buscar serviços + categorias + dentistas
$servicosECategorias = $pdo->query("
    SELECT 
        s.servico_id, 
        s.nome_servico,
        s.descricao,
        s.preco,
        c.nome AS nome_categoria,
        c.categoria_id,
        d.usuario_id AS dentista_id, 
        u.nome AS nome_dentista, 
        d.cro
    FROM Servico s
    JOIN Categoria c ON s.categoria_id = c.categoria_id
    JOIN Dentista d ON c.dentista_id = d.usuario_id 
    JOIN Usuario u ON d.usuario_id = u.usuario_id
    ORDER BY c.nome, s.nome_servico
")->fetchAll(PDO::FETCH_ASSOC);

// Serviços já vinculados à consulta
$servicosMarcados = array_column(
    $pdo->query("SELECT servico_id FROM consulta_servico WHERE consulta_id = $consulta_id")->fetchAll(PDO::FETCH_ASSOC),
    'servico_id'
);

$titulo_pagina = "Editar Consulta - SmileUp";
$is_dashboard = false;

// Se enviou POST, atualiza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? null;
    $hora = $_POST['horario'] ?? null; // igual ao agendamento
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $usuario_dentista = $_POST['dentista'] ?? null;

    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    if (!$data || !$hora || !$usuario_dentista || empty($servicos_selecionados)) {
        die("Preencha todos os campos obrigatórios.");
    }

    // Valor total
    $valor_total = 0;
    if (!empty($servicos_selecionados)) {
        $ids = implode(',', array_map('intval', $servicos_selecionados));
        $valor_total = $pdo->query("SELECT SUM(preco) FROM servico WHERE servico_id IN ($ids)")->fetchColumn();
    }

    $observacoes_json = json_encode([
        'observacoes' => $observacoes,
        'historico' => $historico,
        'alergias' => $alergias
    ]);

    // Atualizar consulta
    $stmt_update = $pdo->prepare("
        UPDATE consulta 
        SET data = ?, hora = ?, usuario_dentista = ?, valor = ?, observacoes = ? 
        WHERE consulta_id = ?
    ");
    $stmt_update->execute([$data, $hora, $usuario_dentista, $valor_total, $observacoes_json, $consulta_id]);

    // Atualiza os serviços
    $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?")->execute([$consulta_id]);
    $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
    foreach ($servicos_selecionados as $s) {
        $stmt_s->execute([$consulta_id, $s]);
    }

    header("Location: dashboard-paciente.php?msg=Consulta atualizada com sucesso!");
    exit;
}

include 'templates/header.php';
?>

<main class="main-container">
<section id="editar-consulta" class="section-container">
<h2 class="section-title">Editar Consulta</h2>

<form method="POST" id="form-editar">
    <input type="hidden" name="dentista" id="dentista_selecionado" value="">

    <!-- PASSO 1: Serviços -->
    <div id="passo-1" class="passo-agendamento">
        <div class="form-group">
            <label>Selecione os serviços que deseja agendar (apenas do mesmo profissional):</label>
            <input type="hidden" name="servicos_validacao" id="servicos_validacao" required data-error-message="Selecione ao menos um serviço para continuar.">
            <div class="servicos-list">
                <?php 
                $categoria_atual = '';
                foreach ($servicosECategorias as $servico): 
                    if ($servico['nome_categoria'] != $categoria_atual):
                        if ($categoria_atual != ''): ?>
                            </fieldset>
                        <?php endif;
                        $categoria_atual = $servico['nome_categoria']; ?>
                        <fieldset class="fieldset-servico">
                            <legend><strong><?php echo htmlspecialchars($categoria_atual); ?></strong></legend>
                    <?php endif; ?>
                    <div class="servico-item">
                        <input type="checkbox" 
                               id="servico_<?php echo $servico['servico_id']; ?>" 
                               name="servicos[]" 
                               value="<?php echo $servico['servico_id']; ?>" 
                               data-preco="<?php echo $servico['preco']; ?>"
                               data-categoria-id="<?php echo $servico['categoria_id']; ?>" 
                               data-dentista-id="<?php echo $servico['dentista_id']; ?>"
                               data-dentista-nome="<?php echo htmlspecialchars($servico['nome_dentista']); ?>"
                               <?php echo in_array($servico['servico_id'], $servicosMarcados) ? 'checked' : ''; ?>>
                        <label for="servico_<?php echo $servico['servico_id']; ?>">
                            <?php echo htmlspecialchars($servico['nome_servico']); ?> 
                            (Com Dr(a). <?php echo htmlspecialchars($servico['nome_dentista']); ?> - 
                            R$ <?php echo number_format($servico['preco'], 2, ',', '.'); ?>)
                        </label>
                    </div>
                <?php endforeach; ?>
                </fieldset> 
            </div>
        </div>

        <div class="form-group">
            <label for="dentista_info">Profissional Escolhido:</label>
            <p id="dentista_info" style="padding: 10px; border: 1px solid #ccc; background-color: #f8f9fa; border-radius: 4px;">
                **Selecione um serviço para ver o profissional responsável.**
            </p>
        </div>

        <div class="botoes-navegacao">
            <button type="button" class="btn-primary" onclick="validarEPularPasso(1, 2)">Continuar</button>
        </div>
    </div>

    <!-- PASSO 2: Data e Horário -->
    <div id="passo-2" class="passo-agendamento" style="display: none;">
        <h3 class="subsection-title">Escolha a Data e Horário</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="data">Data Desejada</label>
                <input type="date" id="data" name="data" value="<?php echo htmlspecialchars($consulta['data']); ?>" required>
            </div>
            <div class="form-group">
                <label>Horários Disponíveis</label>
                <div id="lista-horarios" class="horarios-disponiveis">
                    <?php 
                    $horarios_simulados = ["08:00", "09:00", "10:00", "14:00", "15:00", "16:00"];
                    foreach ($horarios_simulados as $horario) {
                        $selected = ($consulta['hora'] === $horario) ? "selected" : "";
                        echo "<div class='horario-item $selected' data-horario='{$horario}'>{$horario}</div>";
                    }
                    ?>
                </div>
                <input type="hidden" id="horario_selecionado" name="horario" value="<?php echo htmlspecialchars($consulta['hora']); ?>" required data-error-message="Selecione um horário.">
            </div>
        </div>
        <div class="botoes-navegacao">
            <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
            <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
        </div>
    </div>

    <!-- PASSO 3: Observações -->
    <div id="passo-3" class="passo-agendamento" style="display: none;">
        <h3 class="subsection-title">Informações Adicionais (Opcional)</h3>
        <div class="form-group">
            <label for="observacoes">Observações sobre a Consulta:</label>
            <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($obs['observacoes'] ?? ''); ?></textarea>
        </div>
        <h3 class="subsection-title" style="margin-top: 2rem;">Seu Histórico de Saúde (Opcional)</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="historico">Histórico Odontológico/Médico:</label>
                <textarea id="historico" name="historico"><?php echo htmlspecialchars($obs['historico'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="alergias">Alergias Conhecidas:</label>
                <textarea id="alergias" name="alergias"><?php echo htmlspecialchars($obs['alergias'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="botoes-navegacao">
            <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
            <button type="submit" class="btn-primary">Salvar alterações</button>
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

.horarios-disponiveis {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
}

.horario-item {
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s, box-shadow 0.2s;
}

.horario-item:hover {
    background-color: #e0e0e0;
}

.horario-item.selected {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.servicos-list {
    border: 1px solid #ccc;
    padding: 15px;
    border-radius: 5px;
    max-height: 300px;
    overflow-y: auto;
}

.fieldset-servico {
    border: none;
    padding: 0;
    margin-bottom: 15px;
}

.fieldset-servico legend {
    font-size: 1.1em;
    margin-bottom: 5px;
    color: #007bff;
}

.servico-item {
    margin-bottom: 5px;
}

.subsection-title {
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
    margin-bottom: 15px;
    color: #333;
}
</style>

<script src="js/editar-agendamento.js"></script>

<?php
include 'templates/footer.php';
?>
