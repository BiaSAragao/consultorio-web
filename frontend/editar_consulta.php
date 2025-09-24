<?php
// Assumindo que este arquivo está em /frontend/editar_consulta.php

require_once "../backend/conexao.php";
session_start();

if (!isset($_SESSION['usuario_id'])) {
    // Redireciona para o login se não estiver logado
    header("Location: login.php");
    exit();
}

// 1. VERIFICAÇÃO INICIAL E BUSCA DA CONSULTA
if (!isset($_GET['id'])) {
    die("ID da consulta não informado.");
}

$consulta_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

// Busca a consulta (incluindo dentista para futura validação)
$stmt = $pdo->prepare("SELECT * FROM consulta WHERE consulta_id = ? AND usuario_paciente = ?");
$stmt->execute([$consulta_id, $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    header("Location: dashboard-paciente.php?msg=Consulta não encontrada ou acesso negado.");
    exit();
}

// 2. BUSCAR DADOS DE EDIÇÃO

// Buscar serviços disponíveis
$servicos_disponiveis = $pdo->query("SELECT servico_id, nome_servico, preco FROM servico ORDER BY nome_servico")->fetchAll(PDO::FETCH_ASSOC);

// Buscar os serviços ATUALMENTE vinculados à consulta
$stmt_servicos_atuais = $pdo->prepare("SELECT servico_id FROM consulta_servico WHERE consulta_id = ?");
$stmt_servicos_atuais->execute([$consulta_id]);
$servicos_atuais = array_column($stmt_servicos_atuais->fetchAll(PDO::FETCH_ASSOC), 'servico_id');

// Decodifica observações (JSONB)
$obs = json_decode($consulta['observacoes'], true);


// 3. PROCESSAMENTO POST (SALVAR ALTERAÇÕES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CORREÇÃO AQUI: o campo de hora no agendamento era 'horario_selecionado', 
    // mas como este formulário usa <input type="time" name="hora">, vamos usar 'hora'.
    $data = $_POST['data'];
    $hora = $_POST['hora']; 
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    // Lógica de cálculo (como no processa_consulta)
    $valor_total = 0;
    if (!empty($servicos_selecionados)) {
        $ids = implode(',', array_map('intval', $servicos_selecionados));
        // Melhoria de segurança: use prepare/execute para a soma
        $stmt_valor = $pdo->query("SELECT SUM(preco) FROM servico WHERE servico_id IN ($ids)");
        $valor_total = $stmt_valor->fetchColumn();
    }

    $observacoes_json_final = json_encode([
        'observacoes' => $observacoes,
        'historico' => $historico,
        'alergias' => $alergias
    ]);
    
    try {
        $pdo->beginTransaction();

        // Atualizar consulta
        $stmt_update = $pdo->prepare("UPDATE consulta SET data = ?, hora = ?, valor = ?, observacoes = ? WHERE consulta_id = ?");
        $stmt_update->execute([$data, $hora, $valor_total, $observacoes_json_final, $consulta_id]);

        // Atualiza os serviços (DELETE + INSERT - Mais simples do que UPDATE)
        $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?")->execute([$consulta_id]);
        $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
        foreach ($servicos_selecionados as $s) {
            $stmt_s->execute([$consulta_id, $s]);
        }
        
        $pdo->commit();
        header("Location: dashboard-paciente.php?msg=Consulta atualizada com sucesso!");
        exit;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: dashboard-paciente.php?msg_erro=Erro ao salvar as alterações.");
        exit;
    }
}


// 4. INCLUSÃO DO LAYOUT (HEADER E FOOTER)
$titulo_pagina = "Editar Consulta ID: " . $consulta_id;
$is_dashboard = true; // Necessário para carregar o CSS do dashboard
include 'templates/header.php';
?>

<main class="main-container">
    <section class="container-default">
        <h2 class="title-primary">Editar Agendamento</h2>
        <p class="subtitle-secondary">Modifique os detalhes da sua consulta agendada para **<?= date('d/m/Y', strtotime($consulta['data'])) ?>**.</p>
        
        <form method="POST" class="form-agendamento">
            
            <div class="card-form mb-4">
                <h3 class="card-title-step">1. Data e Hora</h3>
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label for="data">Data da Consulta</label>
                        <input type="date" id="data" name="data" 
                               value="<?= htmlspecialchars($consulta['data']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="hora">Hora da Consulta</label>
                        <input type="time" id="hora" name="hora" 
                               value="<?= htmlspecialchars($consulta['hora']) ?>" required>
                        </div>
                </div>
            </div>

            <div class="card-form mb-4">
                <h3 class="card-title-step">2. Serviços</h3>
                <fieldset class="form-group">
                    <legend class="detail-label mb-3">Selecione os serviços:</legend>
                    <div class="servicos-list">
                        <?php foreach ($servicos_disponiveis as $s): ?>
                            <div class="servico-item">
                                <input type="checkbox" 
                                       id="servico-<?= $s['servico_id'] ?>" 
                                       name="servicos[]" 
                                       value="<?= $s['servico_id'] ?>"
                                       <?= in_array($s['servico_id'], $servicos_atuais) ? 'checked' : '' ?>>
                                <label for="servico-<?= $s['servico_id'] ?>">
                                    <?= htmlspecialchars($s['nome_servico']) ?> 
                                    (R$ <?= number_format($s['preco'], 2, ',', '.') ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            
            <div class="card-form mb-4">
                <h3 class="card-title-step">3. Informações Adicionais</h3>
                
                <div class="form-group">
                    <label for="observacoes">Observações sobre a consulta:</label>
                    <textarea id="observacoes" name="observacoes" rows="3"><?= htmlspecialchars($obs['observacoes'] ?? $obs['obs'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="historico">Histórico Médico Relevante:</label>
                    <textarea id="historico" name="historico" rows="3"><?= htmlspecialchars($obs['historico'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="alergias">Alergias Conhecidas:</label>
                    <textarea id="alergias" name="alergias" rows="3"><?= htmlspecialchars($obs['alergias'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions mt-5">
                <a href="dashboard-paciente.php" class="btn-tabela btn-secondary">Cancelar</a>
                <button type="submit" class="btn-tabela btn-confirmar">Salvar Alterações</button>
            </div>
        </form>

    </section>
</main>

<?php 
include 'templates/footer.php';
?>