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
// Adicionando a Categoria para replicação exata do layout do agendar_consulta
$servicos_disponiveis = $pdo->query("
    SELECT s.servico_id, s.nome_servico, s.preco, c.nome AS nome_categoria
    FROM Servico s
    JOIN Categoria c ON s.categoria_id = c.categoria_id
    ORDER BY c.nome, s.nome_servico
")->fetchAll(PDO::FETCH_ASSOC);

// Buscar os serviços ATUALMENTE vinculados
$stmt_servicos_atuais = $pdo->prepare("SELECT servico_id FROM consulta_servico WHERE consulta_id = ?");
$stmt_servicos_atuais->execute([$consulta_id]);
$servicos_atuais = array_column($stmt_servicos_atuais->fetchAll(PDO::FETCH_ASSOC), 'servico_id');

// Decodifica observações (JSONB)
$obs = json_decode($consulta['observacoes'], true);

// 3. PROCESSAMENTO POST (SALVAR ALTERAÇÕES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AQUI USAMOS O 'hora' do input type="time"
    $data = $_POST['data'];
    $hora = $_POST['hora']; 
    $servicos_selecionados = $_POST['servicos'] ?? [];
    
    // CORREÇÃO: Usar as chaves corretas do JSON para extrair os dados
    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    // Lógica de cálculo (Valor Total)
    $valor_total = 0;
    if (!empty($servicos_selecionados)) {
        $ids = implode(',', array_map('intval', $servicos_selecionados));
        $stmt_valor = $pdo->query("SELECT SUM(preco) FROM servico WHERE servico_id IN ($ids)");
        $valor_total = $stmt_valor->fetchColumn();
    }

    $observacoes_json_final = json_encode([
        // Chaves iguais ao processa_consulta: 'observacoes', 'historico', 'alergias'
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
        header("Location: dashboard-paciente.php?msg=Consulta #$consulta_id atualizada com sucesso!");
        exit;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Em um sistema real, você registraria $e->getMessage()
        header("Location: dashboard-paciente.php?msg_erro=Erro ao salvar as alterações.");
        exit;
    }
}


// 4. INCLUSÃO DO LAYOUT (HEADER E FOOTER)
$titulo_pagina = "Editar Consulta ID: " . $consulta_id;
$is_dashboard = true; // Carrega o CSS dashboard.css
include 'templates/header.php';
?>

<main class="main-container">
    <section id="edicao-consulta" class="section-container">
        <h2 class="section-title">Editar Consulta Agendada</h2>
        <p class="subtitle-secondary">Modifique os detalhes da sua consulta.</p>
        
        <form method="POST" id="form-edicao">
            <input type="hidden" name="consulta_id" value="<?= $consulta_id ?>">
            
            <div id="passo-1" class="passo-agendamento">
                <h3 class="subsection-title">1. Serviços</h3>
                
                <div class="form-group">
                    <label>Serviços Selecionados (marque ou desmarque):</label>
                    <input type="hidden" name="servicos_validacao" id="servicos_validacao" required data-error-message="Selecione ao menos um serviço para continuar.">
                    
                    <div class="servicos-list">
                        <?php 
                        $categoria_atual = '';
                        foreach ($servicos_disponiveis as $servico): 
                            if ($servico['nome_categoria'] != $categoria_atual):
                                if ($categoria_atual != ''): ?>
                                    </fieldset>
                                <?php endif;
                                $categoria_atual = $servico['nome_categoria']; ?>
                                <fieldset class="fieldset-servico">
                                    <legend><strong><?= htmlspecialchars($categoria_atual); ?></strong></legend>
                            <?php endif; ?>
                            <div class="servico-item">
                                <input type="checkbox" 
                                       id="servico_<?= $servico['servico_id']; ?>" 
                                       name="servicos[]" 
                                       value="<?= $servico['servico_id']; ?>" 
                                       data-preco="<?= $servico['preco']; ?>"
                                       <?= in_array($servico['servico_id'], $servicos_atuais) ? 'checked' : '' ?>>
                                <label for="servico_<?= $servico['servico_id']; ?>">
                                    <?= htmlspecialchars($servico['nome_servico']); ?> 
                                    (R$ <?= number_format($servico['preco'], 2, ',', '.'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                        </fieldset>
                    </div> 
                    <p style="margin-top: 15px;">**O valor total será recalculado no momento de salvar.**</p>
                </div>
                
                <div class="form-group">
                    <label for="dentista_info">Profissional Escolhido:</label>
                    <p style="padding: 10px; border: 1px solid #ccc; background-color: #f8f9fa; border-radius: 4px;">
                        **Dr(a). Fixo (ID <?= $consulta['usuario_dentista']; ?>)**
                    </p>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(1, 2)">Continuar</button>
                </div>
            </div>

            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">2. Data e Hora</h3>
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
                        <p style="margin-top: 10px; color: #666; font-size: 0.9em;">**Se você alterar a data/hora, o sistema não verificará conflitos de agenda.**</p>
                    </div>

                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
                </div>
            </div>

            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">3. Informações Adicionais</h3>
                
                <div class="form-group">
                    <label for="observacoes">Observações sobre a Consulta:</label>
                    <textarea id="observacoes" name="observacoes" placeholder="Ex: Preferência por anestesia local..." rows="3"><?= htmlspecialchars($obs['observacoes'] ?? $obs['obs'] ?? '') ?></textarea>
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
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </form>

    </section>
</main>

<script>
    // Seletores necessários para as funções
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputServicosValidacao = document.getElementById('servicos_validacao');
    const inputData = document.getElementById('data');
    const inputHora = document.getElementById('hora');

    // Função para navegar entre os passos do formulário
    function irParaPasso(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => {
            passo.style.display = 'none';
        });
        document.getElementById(`passo-${numeroPasso}`).style.display = 'block';
        window.scrollTo(0, 0); // Rola para o topo da página ao mudar o passo
    }

    // Função para validar o passo atual antes de avançar
    function validarEPularPasso(passoAtual, proximoPasso) {
        let valido = true;
        
        if (passoAtual === 1) {
            // Validação de serviços: deve haver pelo menos um selecionado
            const servicosSelecionados = Array.from(checkboxesServicos).some(checkbox => checkbox.checked);
            if (!servicosSelecionados) {
                alert(inputServicosValidacao.dataset.errorMessage);
                valido = false;
            } else {
                inputServicosValidacao.value = 'selecionado'; // Preenche o campo hidden para validação
            }
        } else if (passoAtual === 2) {
            // Validação de data e hora
            if (!inputData.value || !inputHora.value) {
                alert('A data e a hora da consulta devem ser preenchidas.');
                valido = false;
            }
        }
        
        // Se todas as validações do passo atual passarem, avança
        if (valido) {
            irParaPasso(proximoPasso);
        }
    }


    // Inicializa o formulário no primeiro passo ao carregar a página
    document.addEventListener('DOMContentLoaded', () => {
        irParaPasso(1);
    });
</script>

<?php 
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>