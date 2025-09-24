<?php
// Assumindo que este arquivo está em /frontend/editar_consulta.php

require_once "../backend/conexao.php";
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 1. VERIFICAÇÃO INICIAL E BUSCA DA CONSULTA
if (!isset($_GET['id'])) {
    header("Location: dashboard-paciente.php?msg_erro=ID da consulta não informado.");
    exit();
}

$consulta_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

// Busca a consulta
$stmt = $pdo->prepare("SELECT * FROM consulta WHERE consulta_id = ? AND usuario_paciente = ?");
$stmt->execute([$consulta_id, $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    header("Location: dashboard-paciente.php?msg=Consulta não encontrada ou acesso negado.");
    exit();
}

// 2. BUSCAR DADOS DE EDIÇÃO
$servicos_disponiveis = $pdo->query("SELECT servico_id, nome_servico, preco, categoria_id FROM servico ORDER BY nome_servico")->fetchAll(PDO::FETCH_ASSOC);

// Buscar os serviços ATUALMENTE vinculados
$stmt_servicos_atuais = $pdo->prepare("SELECT servico_id FROM consulta_servico WHERE consulta_id = ?");
$stmt_servicos_atuais->execute([$consulta_id]);
$servicos_atuais = array_column($stmt_servicos_atuais->fetchAll(PDO::FETCH_ASSOC), 'servico_id');

// Decodifica observações (JSONB)
$obs = json_decode($consulta['observacoes'], true);


// 3. PROCESSAMENTO POST (SALVAR ALTERAÇÕES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $hora = $_POST['hora']; // Note: Usamos 'hora' aqui, não 'horario_selecionado'
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    // Lógica de cálculo
    $valor_total = 0;
    if (!empty($servicos_selecionados)) {
        $ids = implode(',', array_map('intval', $servicos_selecionados));
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

        // Atualiza os serviços (DELETE + INSERT)
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
$is_dashboard = true; 
include 'templates/header.php';
?>

<main class="main-container">
    <section id="edicao-consulta" class="section-container">
        <h2 class="section-title">Editar Consulta Agendada</h2>
        <p class="subtitle-secondary">ID da Consulta: #<?= $consulta_id ?> | Dentista Fixo: ID 3</p>
        
        <form method="POST" id="form-edicao">
            
            <div id="passo-1" class="passo-agendamento">
                <h3 class="subsection-title">1. Serviços, Data e Hora</h3>
                
                <div class="form-grid mb-4">
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

                <div class="form-group">
                    <label>Selecione os serviços (Selecione os que já estavam ou adicione novos):</label>
                    <div class="servicos-list">
                        <?php 
                        $categoria_atual = '';
                        foreach ($servicos_disponiveis as $s): 
                             // Lógica para agrupar por categoria (melhora o visual)
                             if ($s['categoria_id'] != $categoria_atual):
                                if ($categoria_atual != ''): ?>
                                    </fieldset>
                                <?php endif;
                                $categoria_atual = $s['categoria_id']; ?>
                                <fieldset class="fieldset-servico">
                                    <legend><strong><?= htmlspecialchars($s['categoria_id'] ?? 'Serviço') ?></strong></legend>
                            <?php endif; ?>
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
                        </fieldset>
                    </div>
                </div>
                
                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="irParaPasso(2)">Continuar</button>
                </div>
            </div>

            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">2. Informações Adicionais</h3>
                
                <div class="form-group">
                    <label for="observacoes">Observações sobre a consulta:</label>
                    <textarea id="observacoes" name="observacoes" placeholder="Observações..." rows="3"><?= htmlspecialchars($obs['observacoes'] ?? $obs['obs'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico Médico Relevante:</label>
                        <textarea id="historico" name="historico" placeholder="Condições médicas, cirurgias..." rows="3"><?= htmlspecialchars($obs['historico'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias Conhecidas:</label>
                        <textarea id="alergias" name="alergias" placeholder="Alergias a medicamentos, látex..." rows="3"><?= htmlspecialchars($obs['alergias'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </form>

    </section>
</main>

<script>
    // Função para navegar entre os passos do formulário
    function irParaPasso(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => {
            passo.style.display = 'none';
        });
        document.getElementById(`passo-${numeroPasso}`).style.display = 'block';
        window.scrollTo(0, 0); // Rola para o topo da página ao mudar o passo
    }
    
    // Inicializa o formulário no primeiro passo ao carregar a página
    document.addEventListener('DOMContentLoaded', () => {
        irParaPasso(1);
    });
</script>

<?php 
include 'templates/footer.php';
?>