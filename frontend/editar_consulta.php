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

// Busca a consulta original
$stmt = $pdo->prepare("SELECT * FROM consulta WHERE consulta_id = ? AND usuario_paciente = ?");
$stmt->execute([$consulta_id, $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    header("Location: dashboard-paciente.php?msg_erro=Consulta não encontrada ou acesso negado.");
    exit();
}

$dentista_id = $consulta['usuario_dentista']; // Mantém o dentista original (fixo em 3)

// 2. BUSCAR DADOS DE EDIÇÃO
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

$mensagem_erro = ''; // Variável para armazenar mensagens de erro de validação


// 3. PROCESSAMENTO POST (SALVAR ALTERAÇÕES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $hora = $_POST['hora']; 
    $servicos_selecionados = $_POST['servicos'] ?? [];
    
    // CORREÇÃO: Usar as chaves corretas do JSON
    $observacoes = $_POST['observacoes'] ?? '';
    $historico = $_POST['historico'] ?? '';
    $alergias = $_POST['alergias'] ?? '';

    
    // ==========================================================
    // ** LÓGICA DE VERIFICAÇÃO DE CONFLITO (CORREÇÃO AQUI) **
    // ==========================================================
    
    // 1. Verificar se a nova data/hora já está ocupada
    $stmt_conflito = $pdo->prepare("
        SELECT consulta_id 
        FROM consulta 
        WHERE data = ? 
        AND hora = ? 
        AND usuario_dentista = ?
        AND consulta_id != ?
    ");
    $stmt_conflito->execute([$data, $hora, $dentista_id, $consulta_id]);
    $conflito = $stmt_conflito->fetch(PDO::FETCH_ASSOC);

    if ($conflito) {
        // Conflito encontrado, interrompe o processo e define a mensagem de erro
        $mensagem_erro = "A data e horário ($data às $hora) que você selecionou já estão ocupados para este profissional. Por favor, escolha outro horário.";
        // Não fazemos o EXIT ainda, para que a mensagem seja exibida no formulário.

    } else {
        // ==========================================================
        // ** SE NÃO HOUVE CONFLITO, PROCESSA A ATUALIZAÇÃO **
        // ==========================================================

        // Lógica de cálculo (Valor Total)
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
            header("Location: dashboard-paciente.php?msg=Consulta #$consulta_id atualizada com sucesso!");
            exit;
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: dashboard-paciente.php?msg_erro=Erro ao salvar as alterações. Tente novamente.");
            exit;
        }
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
        <p class="subtitle-secondary">Modifique os detalhes da sua consulta.</p>
        
        <?php if (!empty($mensagem_erro)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; margin: 15px 0; border: 1px solid #f5c6cb; border-radius: 5px;">
                <?php echo htmlspecialchars($mensagem_erro); ?>
            </div>
        <?php endif; ?>
        
        <?php 
            $data_form = $_POST['data'] ?? $consulta['data'];
            $hora_form = $_POST['hora'] ?? $consulta['hora'];
            $servicos_form = $_POST['servicos'] ?? $servicos_atuais;
            $obs_form = $_POST['observacoes'] ?? $obs['observacoes'] ?? $obs['obs'] ?? '';
            $hist_form = $_POST['historico'] ?? $obs['historico'] ?? '';
            $alerg_form = $_POST['alergias'] ?? $obs['alergias'] ?? '';
        ?>

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
                                       <?= in_array($servico['servico_id'], $servicos_form) ? 'checked' : '' ?>>
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
                        **Dr(a). Fixo (ID <?= $dentista_id; ?>)**
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
                               value="<?= htmlspecialchars($data_form) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="hora">Hora da Consulta</label>
                        <input type="time" id="hora" name="hora" 
                               value="<?= htmlspecialchars($hora_form) ?>" required>
                        <p style="margin-top: 10px; color: #666; font-size: 0.9em;">**Se você alterar a data/hora, o sistema verificará conflitos de agenda ao salvar.**</p>
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
                    <textarea id="observacoes" name="observacoes" placeholder="Ex: Preferência por anestesia local..." rows="3"><?= htmlspecialchars($obs_form) ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico Médico Relevante:</label>
                        <textarea id="historico" name="historico" placeholder="Condições médicas, cirurgias..." rows="3"><?= htmlspecialchars($hist_form) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias Conhecidas:</label>
                        <textarea id="alergias" name="alergias" placeholder="Alergias a medicamentos, látex..." rows="3"><?= htmlspecialchars($alerg_form) ?></textarea>
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
    const mensagemErro = document.querySelector('.main-container .section-container > div[style*="background: #f8d7da"]');
    
    // Identifica o passo inicial. Se houver erro de POST, volta para o Passo 2.
    let passoInicial = 1;
    if (mensagemErro) {
         // Se houver mensagem de erro (conflito de horário), volta ao passo 2
        passoInicial = 2;
    }

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


    // Inicializa o formulário no primeiro passo ou no passo com erro
    document.addEventListener('DOMContentLoaded', () => {
        irParaPasso(passoInicial);
    });
</script>

<?php 
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>