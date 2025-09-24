<?php
// ==========================================================
// 1. INCLUSÃO E INICIALIZAÇÃO DO PHP
// ==========================================================
require_once "../backend/conexao.php"; 

$consulta_id = $_GET['id'] ?? null;

if (!$consulta_id) {
    header("Location: dashboard.php");
    exit;
}

try {
    $stmt_consulta = $pdo->prepare("
        SELECT c.*, D.nome AS nome_dentista 
        FROM Consulta c
        JOIN Usuario D ON c.usuario_dentista = D.usuario_id
        WHERE c.consulta_id = :id
    ");
    $stmt_consulta->bindParam(':id', $consulta_id, PDO::PARAM_INT);
    $stmt_consulta->execute();
    $consulta = $stmt_consulta->fetch(PDO::FETCH_ASSOC);

    if (!$consulta) {
        header("Location: dashboard.php");
        exit;
    }

    $stmt_servicos_existentes = $pdo->prepare("
        SELECT servico_id 
        FROM Consulta_servico 
        WHERE consulta_id = :consulta_id
    ");
    $stmt_servicos_existentes->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
    $stmt_servicos_existentes->execute();
    $servicos_existentes = $stmt_servicos_existentes->fetchAll(PDO::FETCH_COLUMN); 

    $dentista_id = $consulta['usuario_dentista'];
    $paciente_id = $consulta['usuario_paciente'];
    $data_form = $consulta['data'];
    $hora_form = $consulta['hora'];
    $observacoes_form = $consulta['observacoes'] ?? ''; 

} catch (PDOException $e) {
    die("Erro ao carregar consulta: " . $e->getMessage());
}

try {
    $stmt_servicos = $pdo->query("
        SELECT S.servico_id, S.nome_servico, S.preco, C.categoria_id, C.nome AS categoria_nome 
        FROM Servico S
        JOIN Categoria C ON S.categoria_id = C.categoria_id
        ORDER BY categoria_nome, S.nome_servico
    ");
    $servicos_raw = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

    $categorias_servicos = [];
    foreach ($servicos_raw as $servico) {
        $categoria_id = $servico['categoria_id'];
        $categoria_nome = $servico['categoria_nome'];

        if (!isset($categorias_servicos[$categoria_id])) {
            $categorias_servicos[$categoria_id] = [
                'nome' => $categoria_nome,
                'servicos' => []
            ];
        }
        $categorias_servicos[$categoria_id]['servicos'][] = $servico;
    }

} catch (PDOException $e) {
    die("Erro ao carregar serviços: " . $e->getMessage()); 
}


// ==========================================================
// 2. PROCESSAMENTO DO FORMULÁRIO (Update da Consulta)
// ==========================================================
$erro_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dentista_id_novo = filter_input(INPUT_POST, 'dentista_id', FILTER_SANITIZE_NUMBER_INT);
    $data_nova = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_SPECIAL_CHARS);
    $hora_nova = filter_input(INPUT_POST, 'horario_selecionado', FILTER_SANITIZE_SPECIAL_CHARS);
    $servicos_novos = $_POST['servicos'] ?? [];
    $observacoes_nova = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($servicos_novos) || empty($data_nova) || empty($hora_nova)) {
        $erro_msg = "Todos os campos obrigatórios (Serviços, Data, Horário) devem ser preenchidos.";
    }

    if (empty($erro_msg)) {
        try {
            $stmt_conflito = $pdo->prepare("
                SELECT consulta_id 
                FROM Consulta 
                WHERE usuario_dentista = :dentista_id 
                  AND data = :data_nova 
                  AND hora = :hora_nova 
                  AND consulta_id != :consulta_id
            ");
            $stmt_conflito->bindParam(':dentista_id', $dentista_id_novo);
            $stmt_conflito->bindParam(':data_nova', $data_nova);
            $stmt_conflito->bindParam(':hora_nova', $hora_nova);
            $stmt_conflito->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
            $stmt_conflito->execute();

            if ($stmt_conflito->rowCount() > 0) {
                $erro_msg = "O horário selecionado ($data_nova às $hora_nova) não está mais disponível. Por favor, escolha outro.";
            }

        } catch (PDOException $e) {
            $erro_msg = "Erro interno ao checar disponibilidade.";
        }
    }

    if (empty($erro_msg)) {
        try {
            $pdo->beginTransaction();

            $stmt_update = $pdo->prepare("
                UPDATE Consulta SET 
                    data = :data_nova, 
                    hora = :hora_nova, 
                    observacoes = :observacoes_nova
                WHERE consulta_id = :consulta_id
            ");
            $stmt_update->bindParam(':data_nova', $data_nova);
            $stmt_update->bindParam(':hora_nova', $hora_nova);
            $stmt_update->bindParam(':observacoes_nova', $observacoes_nova);
            $stmt_update->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
            $stmt_update->execute();

            $stmt_delete = $pdo->prepare("
                DELETE FROM Consulta_servico 
                WHERE consulta_id = :consulta_id
            ");
            $stmt_delete->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
            $stmt_delete->execute();

            if (!empty($servicos_novos)) {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO Consulta_servico (consulta_id, servico_id) 
                    VALUES (:consulta_id, :servico_id)
                ");
                foreach ($servicos_novos as $servico_id) {
                    $stmt_insert->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':servico_id', $servico_id, PDO::PARAM_INT);
                    $stmt_insert->execute();
                }
            }
            
            $pdo->commit();
            header("Location: dashboard.php?msg=Consulta%20atualizada%20com%20sucesso!");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro_msg = "Erro ao atualizar no banco: " . $e->getMessage();
        }
    }

    if (!empty($erro_msg)) {
        $data_form = $data_nova;
        $hora_form = $hora_nova;
        $servicos_existentes = $servicos_novos;
        $observacoes_form = $observacoes_nova;
    }
}


// ==========================================================
// 3. INCLUSÃO DO HEADER E INÍCIO DO HTML
// ==========================================================
include 'templates/header.php';
?>

<main class="main-container">

    <section id="editar-consulta" class="section-container">
        <h2 class="section-title">Editar Consulta #<?= $consulta_id ?></h2>

        <?php if (!empty($erro_msg)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; margin: 15px 0; border: 1px solid #f5c6cb; border-radius: 5px;">
                <?= htmlspecialchars($erro_msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="form-editar">
            <input type="hidden" name="consulta_id" value="<?= $consulta_id ?>">
            <input type="hidden" name="paciente_id" value="<?= $paciente_id ?>">
            <input type="hidden" name="dentista_id" id="dentista_id" value="<?= $dentista_id ?>">
            <input type="hidden" name="horario_selecionado" id="horario_selecionado" value="<?= htmlspecialchars($hora_form) ?>">

            <!-- Passo 1 -->
            <div id="passo-1" class="passo-agendamento">
                <div class="form-group">
                    <label>Selecione os serviços:</label>
                    <input type="hidden" id="servicos_validacao" data-error-message="Selecione pelo menos um serviço.">
                    
                    <div class="servicos-list">
                        <?php foreach ($categorias_servicos as $id_cat => $categoria): ?>
                            <fieldset class="fieldset-servico">
                                <legend><strong><?= htmlspecialchars($categoria['nome']) ?></strong></legend>
                                <?php foreach ($categoria['servicos'] as $servico): 
                                    $checked = in_array($servico['servico_id'], $servicos_existentes) ? 'checked' : ''; ?>
                                    <div class="servico-item">
                                        <input type="checkbox" 
                                               id="servico_<?= $servico['servico_id'] ?>" 
                                               name="servicos[]" 
                                               value="<?= $servico['servico_id'] ?>" 
                                               <?= $checked ?>>
                                        <label for="servico_<?= $servico['servico_id'] ?>">
                                            <?= htmlspecialchars($servico['nome_servico']) ?> 
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(1, 2)">Continuar</button>
                </div>
            </div>

            <!-- Passo 2 -->
            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Escolha a Data e Horário</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" name="data" id="data" value="<?= htmlspecialchars($data_form) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Horários Disponíveis</label>
                        <div class="horarios-disponiveis" id="lista-horarios">
                            <p>Selecione uma data para ver os horários disponíveis.</p>
                        </div>
                        <input type="hidden" id="horario_validacao" data-error-message="Selecione um horário.">
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
                </div>
            </div>

            <!-- Passo 3 -->
            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Observações</h3>
                <div class="form-group">
                    <textarea name="observacoes" id="observacoes" placeholder="Adicione observações sobre a consulta."><?= htmlspecialchars($observacoes_form) ?></textarea>
                </div>

                <p style="margin-top: 1.5rem;">**Você está editando a consulta #<?= $consulta_id ?>.**</p>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Confirmar Edição</button>
                </div>
            </div>

        </form>
    </section>

</main>

<script>
    const inputData = document.getElementById('data');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const divHorarios = document.getElementById('lista-horarios');
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputServicosValidacao = document.getElementById('servicos_validacao');
    const dentistaId = <?= $dentista_id ?>;
    let horarioOriginal = "<?= htmlspecialchars($hora_form) ?>"; 

    function buscarHorarios(data, dentistaId) {
        inputHorarioSelecionado.value = '';
        divHorarios.innerHTML = ''; 

        const hoje = new Date().toISOString().split('T')[0];
        const amanha = new Date(new Date().getTime() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        let horariosSimulados = [];

        if (data === hoje) {
            horariosSimulados = ["15:00", "16:00", "17:00"]; 
        } else if (data === amanha) {
            horariosSimulados = ["08:00", "09:30", "11:00", "14:00", "15:30"];
        } else {
            horariosSimulados = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"];
        }

        if (horariosSimulados.length === 0) {
            divHorarios.innerHTML = '<p>Nenhum horário disponível para esta data.</p>';
        } else {
            horariosSimulados.forEach(horario => {
                const btnHorario = document.createElement('button');
                btnHorario.type = 'button';
                btnHorario.className = 'horario-item';
                btnHorario.textContent = horario;
                btnHorario.dataset.horario = horario;

                if (horario === horarioOriginal) {
                    btnHorario.classList.add('selected');
                    inputHorarioSelecionado.value = horario; 
                }

                btnHorario.addEventListener('click', function() {
                    document.querySelectorAll('.horario-item.selected').forEach(btn => {
                        btn.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    inputHorarioSelecionado.value = this.dataset.horario;
                    horarioOriginal = this.dataset.horario; 
                });

                divHorarios.appendChild(btnHorario);
            });
        }
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
            }
        } else if (passoAtual === 2) {
            if (!inputData.value || !inputHorarioSelecionado.value) {
                alert("Selecione uma data e um horário para continuar.");
                valido = false;
            }
        }

        if (valido) {
            irParaPasso(proximoPasso);
        }
    }

    inputData.addEventListener('change', function() {
        buscarHorarios(inputData.value, dentistaId);
    });

    document.addEventListener('DOMContentLoaded', () => {
        irParaPasso(1);
        if (inputData.value) {
            buscarHorarios(inputData.value, dentistaId);
        }
    });
</script>

<?php 
include 'templates/footer.php';
?>
