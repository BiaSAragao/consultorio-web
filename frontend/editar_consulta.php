<?php
// Arquivo: /frontend/editar_consulta.php

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

// Assumindo que o dentista_id é fixo na edição
$dentista_id = $consulta['usuario_dentista'];

// 2. BUSCAR DADOS PARA O FORMULÁRIO (Inicialização dos valores)
$servicos_disponiveis = $pdo->query("
    SELECT s.servico_id, s.nome_servico, s.preco, c.nome AS nome_categoria
    FROM Servico s
    JOIN Categoria c ON s.categoria_id = c.categoria_id
    ORDER BY c.nome, s.nome_servico
")->fetchAll(PDO::FETCH_ASSOC);

$stmt_servicos_atuais = $pdo->prepare("SELECT servico_id FROM consulta_servico WHERE consulta_id = ?");
$stmt_servicos_atuais->execute([$consulta_id]);
$servicos_atuais = array_column($stmt_servicos_atuais->fetchAll(PDO::FETCH_ASSOC), 'servico_id');

$obs = json_decode($consulta['observacoes'], true);

// Preenche os dados do formulário com POST (se houve erro) ou com os dados atuais
$data_form = $_POST['data'] ?? $consulta['data'];
$hora_form = $_POST['hora'] ?? $consulta['hora'];
$servicos_form = $_POST['servicos'] ?? $servicos_atuais;
$obs_form = $_POST['observacoes'] ?? $obs['observacoes'] ?? $obs['obs'] ?? '';
$hist_form = $_POST['historico'] ?? $obs['historico'] ?? '';
$alerg_form = $_POST['alergias'] ?? $obs['alergias'] ?? '';

// Captura a mensagem de erro da URL
$mensagem_erro = $_GET['msg_erro'] ?? '';


// 3. PROCESSAMENTO POST (SALVAR ALTERAÇÕES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // As variáveis de formulário já estão carregadas acima: $data_form, $hora_form, etc.
    
    // --- Lógica de Validação e Conflito no Backend ---
    
    // 1. Verificar se a nova data/hora já está ocupada por OUTRA consulta
    $stmt_conflito = $pdo->prepare("
        SELECT consulta_id 
        FROM consulta 
        WHERE data = ? 
        AND hora = ? 
        AND usuario_dentista = ?
        AND consulta_id != ? 
    ");
    $stmt_conflito->execute([$data_form, $hora_form, $dentista_id, $consulta_id]);
    $conflito = $stmt_conflito->fetch(PDO::FETCH_ASSOC);

    if ($conflito) {
        // Conflito encontrado, interrompe e redireciona com mensagem de erro
        $erro_msg = "O horário selecionado ($hora_form de $data_form) já está ocupado por outro agendamento para o Dr(a). Fixo. Por favor, escolha outra data/hora.";
        $erro_msg_url = urlencode($erro_msg);
        
        // Redireciona de volta para a mesma página, forçando a reexibição do erro
        header("Location: editar_consulta.php?id=$consulta_id&msg_erro=$erro_msg_url");
        exit;

    } else {
        // --- Nenhuma Conflito, Continua a Atualização ---
        
        // Lógica de cálculo (Valor Total)
        $valor_total = 0;
        if (!empty($servicos_form)) {
            $ids = implode(',', array_map('intval', $servicos_form));
            $stmt_valor = $pdo->query("SELECT SUM(preco) FROM servico WHERE servico_id IN ($ids)");
            $valor_total = $stmt_valor->fetchColumn();
        }

        $observacoes_json_final = json_encode([
            'observacoes' => $obs_form, 
            'historico' => $hist_form,
            'alergias' => $alerg_form
        ]);
        
        try {
            $pdo->beginTransaction();

            // Atualizar consulta
            $stmt_update = $pdo->prepare("UPDATE consulta SET data = ?, hora = ?, valor = ?, observacoes = ? WHERE consulta_id = ?");
            $stmt_update->execute([$data_form, $hora_form, $valor_total, $observacoes_json_final, $consulta_id]);

            // Atualiza os serviços (DELETE + INSERT)
            $pdo->prepare("DELETE FROM consulta_servico WHERE consulta_id = ?")->execute([$consulta_id]);
            $stmt_s = $pdo->prepare("INSERT INTO consulta_servico (consulta_id, servico_id) VALUES (?, ?)");
            foreach ($servicos_form as $s) {
                $stmt_s->execute([$consulta_id, $s]);
            }
            
            $pdo->commit();
            header("Location: dashboard-paciente.php?msg=Consulta #$consulta_id atualizada com sucesso!");
            exit;
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: dashboard-paciente.php?msg_erro=Erro interno ao salvar as alterações.");
            exit;
        }
    }
}


// 4. INCLUSÃO DO LAYOUT
$titulo_pagina = "Editar Consulta ID: " . $consulta_id;
$is_dashboard = true; 
include 'templates/header.php';
?>

<main class="main-container">
    <section id="edicao-consulta" class="section-container">
        <h2 class="section-title">Editar Consulta Agendada</h2>
        <p class="subtitle-secondary">Modifique os detalhes da sua consulta.</p>
        
        <?php if (!empty($mensagem_erro)): ?>
            <div id="alerta-erro" style="background: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border: 1px solid #f5c6cb; border-radius: 5px; font-weight: bold;">
                <?= htmlspecialchars($mensagem_erro); ?>
            </div>
        <?php endif; ?>
        

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
                
                <div class="form-group">
                    <label for="data">Data da Consulta</label>
                    <input type="date" id="data" name="data" 
                           value="<?= htmlspecialchars($data_form) ?>" required>
                </div>

                <div class="form-group">
                    <label>Selecione um horário:</label>
                    <input type="hidden" name="hora" id="horario_selecionado" 
                           value="<?= htmlspecialchars($hora_form) ?>" required data-error-message="Selecione um horário para continuar.">
                    
                    <div id="horarios_disponiveis" class="horarios-disponiveis">
                        <p class="aviso-horarios">Selecione uma data acima para carregar os horários.</p>
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
    // Seletores necessários
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputServicosValidacao = document.getElementById('servicos_validacao');
    const inputData = document.getElementById('data');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const divHorarios = document.getElementById('horarios_disponiveis');
    const alertaErro = document.getElementById('alerta-erro');
    
    // Variáveis PHP injetadas no JS
    const dentistaId = <?= $dentista_id ?>;
    const consultaId = <?= $consulta_id ?>;
    
    // ==========================================================
    // LÓGICA DE BUSCA E RENDERIZAÇÃO DE HORÁRIOS (AJAX)
    // ==========================================================

    function buscarHorarios() {
        const dataSelecionada = inputData.value;
        divHorarios.innerHTML = '<p class="loading">Carregando horários...</p>';
        
        if (!dataSelecionada) {
            divHorarios.innerHTML = '<p class="aviso-horarios">Selecione uma data para carregar os horários.</p>';
            inputHorarioSelecionado.value = '';
            return;
        }

        // A URL envia o ID da consulta para que o backend IGNORE o horário atual dela.
        const url = `../backend/buscar_horarios.php?dentista_id=${dentistaId}&data=${dataSelecionada}&consulta_id=${consultaId}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na rede ou no servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(horarios => {
                renderizarHorarios(horarios);
            })
            .catch(error => {
                console.error('Erro ao buscar horários:', error);
                divHorarios.innerHTML = '<p class="erro-horarios">Erro ao carregar horários. Tente novamente.</p>';
                inputHorarioSelecionado.value = '';
            });
    }

    function renderizarHorarios(horarios) {
        divHorarios.innerHTML = '';
        
        if (horarios.length === 0) {
            divHorarios.innerHTML = '<p class="aviso-horarios">Nenhum horário disponível nesta data.</p>';
            inputHorarioSelecionado.value = '';
            return;
        }

        horarios.forEach(horario => {
            const slot = document.createElement('span');
            slot.classList.add('horario-item');
            slot.textContent = horario;
            slot.dataset.hora = horario;

            // Marca o horário se ele for o selecionado no campo hidden (útil no carregamento inicial)
            if (horario === inputHorarioSelecionado.value) {
                slot.classList.add('selecionado');
            }
            
            slot.addEventListener('click', function() {
                // Remove a seleção de todos os slots
                document.querySelectorAll('.horario-item').forEach(item => item.classList.remove('selecionado'));
                
                // Adiciona a seleção no slot clicado
                this.classList.add('selecionado');
                
                // Atualiza o campo hidden que será enviado no POST
                inputHorarioSelecionado.value = this.dataset.hora;
            });

            divHorarios.appendChild(slot);
        });
    }

    // ==========================================================
    // LÓGICA DE NAVEGAÇÃO E VALIDAÇÃO DE FORMULÁRIO
    // ==========================================================

    // Identifica o passo inicial. Se houver erro de conflito, força o Passo 2.
    let passoInicial = 1;
    if (alertaErro) {
        passoInicial = 2;
    }

    function irParaPasso(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => {
            passo.style.display = 'none';
        });
        document.getElementById(`passo-${numeroPasso}`).style.display = 'block';
        window.scrollTo(0, 0); 
    }

    function validarEPularPasso(passoAtual, proximoPasso) {
        let valido = true;
        
        if (passoAtual === 1) {
            const servicosSelecionados = Array.from(checkboxesServicos).some(checkbox => checkbox.checked);
            if (!servicosSelecionados) {
                alert(inputServicosValidacao.dataset.errorMessage);
                valido = false;
            } else {
                inputServicosValidacao.value = 'selecionado';
            }
        } else if (passoAtual === 2) {
            // Validação: deve ter uma data e um horário selecionado (no campo hidden)
            if (!inputData.value || !inputHorarioSelecionado.value) {
                alert(inputHorarioSelecionado.dataset.errorMessage);
                valido = false;
            }
        }
        
        if (valido) {
            irParaPasso(proximoPasso);
        }
    }

    // Event Listeners e Inicialização
    inputData.addEventListener('change', buscarHorarios);

    document.addEventListener('DOMContentLoaded', () => {
        irParaPasso(passoInicial);
        
        // No carregamento, se já houver data, carrega os horários
        if (inputData.value) {
            buscarHorarios();
        }
    });
</script>

<?php 
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>