<?php
// ==========================================================
// 1. INCLUSÃO E INICIALIZAÇÃO DO PHP
// ==========================================================
// Ajuste o caminho da sua conexão e funções
require_once "../backend/conexao.php"; 

// 1.2. Verifica o ID da Consulta
$consulta_id = $_GET['id'] ?? null;

if (!$consulta_id) {
    header("Location: dashboard.php");
    exit;
}

// 1.3. Busca Dados da Consulta Existente
try {
    // 1. Busca os dados principais da Consulta
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

    // 2. Busca os IDs de serviço da tabela de associação Consulta_servico
    $stmt_servicos_existentes = $pdo->prepare("
        SELECT servico_id 
        FROM Consulta_servico 
        WHERE consulta_id = :consulta_id
    ");
    $stmt_servicos_existentes->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
    $stmt_servicos_existentes->execute();
    
    // Retorna um array simples dos IDs (ex: [1, 3, 5])
    $servicos_existentes = $stmt_servicos_existentes->fetchAll(PDO::FETCH_COLUMN); 

    // Variáveis para pré-preenchimento
    $dentista_id = $consulta['usuario_dentista'];
    $paciente_id = $consulta['usuario_paciente'];
    $data_form = $consulta['data'];
    $hora_form = $consulta['hora'];
    // Tratamento para JSONB de observações (assume que está sendo tratado como string/text)
    $observacoes_form = $consulta['observacoes'] ?? ''; 

} catch (PDOException $e) {
    die("Erro ao carregar consulta: " . $e->getMessage());
}

// 1.4. Busca de Categorias e Serviços (Com a correção do SQL)
try {
    // Busca todos os serviços agrupados por categoria (para renderizar o formulário)
    $stmt_servicos = $pdo->query("
        SELECT S.servico_id, S.nome_servico, C.categoria_id, C.nome AS categoria_nome 
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
    // Captura os dados POST e sanitiza
    $dentista_id_novo = filter_input(INPUT_POST, 'dentista_id', FILTER_SANITIZE_NUMBER_INT);
    $data_nova = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_SPECIAL_CHARS);
    $hora_nova = filter_input(INPUT_POST, 'horario_selecionado', FILTER_SANITIZE_SPECIAL_CHARS);
    $servicos_novos = $_POST['servicos'] ?? [];
    $observacoes_nova = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validações Mínimas
    if (empty($servicos_novos) || empty($data_nova) || empty($hora_nova)) {
        $erro_msg = "Todos os campos obrigatórios (Serviços, Data, Horário) devem ser preenchidos.";
    }

    // 2.1. Checagem de Conflito de Horário
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


    // 2.2. Execução do Update com Transação para Consulta e Consulta_servico
    if (empty($erro_msg)) {
        try {
            // Inicia a transação para garantir que Consulta e Consulta_servico sejam atualizadas corretamente
            $pdo->beginTransaction();

            // 2.2.1. Atualiza a tabela Consulta (data, hora, observacoes)
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

            // 2.2.2. Remove todos os serviços antigos (limpeza da tabela de associação)
            $stmt_delete = $pdo->prepare("
                DELETE FROM Consulta_servico 
                WHERE consulta_id = :consulta_id
            ");
            $stmt_delete->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
            $stmt_delete->execute();


            // 2.2.3. Insere os novos serviços na tabela de associação
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
            
            $pdo->commit(); // Confirma todas as operações

            // Sucesso: Redireciona
            header("Location: dashboard.php?msg=Consulta%20atualizada%20com%20sucesso!");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack(); // Desfaz se houver qualquer erro
            $erro_msg = "Erro ao atualizar no banco: " . $e->getMessage();
        }
    }

    // Se houver erro, reatribui as variáveis para manter os dados no formulário
    if (!empty($erro_msg)) {
        $data_form = $data_nova;
        $hora_form = $hora_nova;
        $servicos_existentes = $servicos_novos;
        $observacoes_form = $observacoes_nova;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Consulta - ID #<?= $consulta_id ?></title>
    <link rel="stylesheet" href="caminho/para/seu/estilo.css"> 
    <style>
        /* Estilos base */
        .passo-agendamento { display: none; margin-bottom: 20px; }
        .passo-agendamento h2 { border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-buttons button { padding: 10px 20px; margin-right: 10px; cursor: pointer; }

        /* Estilo dos Horários */
        .horarios-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .horario-item { 
            padding: 8px 12px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            cursor: pointer; 
            background-color: #f9f9f9;
        }
        .horario-item.selecionado { 
            background-color: #007bff; /* Azul */
            color: white; 
            border-color: #007bff;
        }
        
        /* Estilo dos Serviços (Conforme agendar_consulta) */
        .categoria-container {
            border: 1px solid #eee;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .categoria-header {
            background-color: #007bff; /* Azul */
            color: white;
            padding: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        .servicos-list {
            padding: 15px;
        }
        .servico-item label {
            display: block;
            margin-bottom: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <h1>Editar Consulta #<?= $consulta_id ?></h1>

    <?php if (!empty($erro_msg)): ?>
        <div id="alerta-erro" style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 20px;">
            <?= htmlspecialchars($erro_msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="consulta_id" value="<?= $consulta_id ?>">
        <input type="hidden" name="paciente_id" value="<?= $paciente_id ?>">
        <input type="hidden" name="dentista_id" id="dentista_id" value="<?= $dentista_id ?>">
        <input type="hidden" name="horario_selecionado" id="horario_selecionado" value="<?= htmlspecialchars($hora_form) ?>">


        <div id="passo-1" class="passo-agendamento">
            <h2>Passo 1: Seleção de Serviços (Dentista: <?= htmlspecialchars($consulta['nome_dentista']) ?>)</h2>
            
            <div id="servicos-container">
                
                <?php foreach ($categorias_servicos as $id_cat => $categoria): ?>
                    <div class="categoria-container">
                        <div class="categoria-header" onclick="toggleServicos(<?= $id_cat ?>)">
                            <?= htmlspecialchars($categoria['nome']) ?>
                        </div>
                        <div id="servicos-<?= $id_cat ?>" class="servicos-list">
                            <?php foreach ($categoria['servicos'] as $servico): ?>
                                <?php 
                                    // Verifica se o ID do serviço está no array dos serviços existentes
                                    $checked = in_array($servico['servico_id'], $servicos_existentes) ? 'checked' : ''; 
                                ?>
                                <div class="servico-item">
                                    <label>
                                        <input type="checkbox" name="servicos[]" value="<?= $servico['servico_id'] ?>" <?= $checked ?>>
                                        <?= htmlspecialchars($servico['nome_servico']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            
            <input type="hidden" id="servicos_validacao" data-error-message="Selecione pelo menos um serviço.">

            <div class="nav-buttons">
                <button type="button" onclick="validarEPularPasso(1, 2)">Próximo</button>
            </div>
        </div>

        <div id="passo-2" class="passo-agendamento">
            <h2>Passo 2: Data e Horário</h2>

            <label for="data">Data:</label>
            <input type="date" name="data" id="data" value="<?= htmlspecialchars($data_form) ?>" required>

            <h3 style="margin-top: 20px;">Horários Disponíveis (Simulação):</h3>
            <div id="horarios_disponiveis" class="horarios-container">
                <p>Selecione uma data acima.</p>
            </div>
            <input type="hidden" id="horario_validacao" data-error-message="Selecione um horário.">

            <div class="nav-buttons" style="margin-top: 20px;">
                <button type="button" onclick="irParaPasso(1)">Anterior</button>
                <button type="button" onclick="validarEPularPasso(2, 3)">Próximo</button>
            </div>
        </div>

        <div id="passo-3" class="passo-agendamento">
            <h2>Passo 3: Observações e Confirmação</h2>
            
            <label for="observacoes">Observações:</label>
            <textarea name="observacoes" id="observacoes" rows="5" placeholder="Adicione observações sobre a consulta."><?= htmlspecialchars($observacoes_form) ?></textarea>

            <p style="margin-top: 15px;">**Você está reagendando a consulta #<?= $consulta_id ?>.**</p>

            <div class="nav-buttons">
                <button type="button" onclick="irParaPasso(2)">Anterior</button>
                <button type="submit" style="background-color: green; color: white;">Confirmar Edição</button>
            </div>
        </div>

    </form>


    <script>
        const inputData = document.getElementById('data');
        const inputHorarioSelecionado = document.getElementById('horario_selecionado');
        const divHorarios = document.getElementById('horarios_disponiveis');
        const alertaErro = document.getElementById('alerta-erro');
        const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
        const inputServicosValidacao = document.getElementById('servicos_validacao');
        
        const dentistaId = <?= $dentista_id ?>;
        let horarioOriginal = "<?= htmlspecialchars($hora_form) ?>"; 
        
        
        // --- LÓGICA DE SERVIÇOS (ACORDION) ---
        function toggleServicos(categoriaId) {
            const servicosDiv = document.getElementById(`servicos-${categoriaId}`);
            if (servicosDiv.style.display === 'block') {
                servicosDiv.style.display = 'none';
            } else {
                servicosDiv.style.display = 'block';
            }
        }
        
        // --- FUNÇÃO DE BUSCA DE HORÁRIOS (SIMULAÇÃO COPIADA) ---
        function buscarHorarios(data, dentistaId) {
            inputHorarioSelecionado.value = '';
            divHorarios.innerHTML = ''; 

            // SIMULAÇÃO COPIADA
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

            // Renderiza os botões
            if (horariosSimulados.length === 0) {
                  divHorarios.innerHTML = '<p>Nenhum horário disponível para esta data.</p>';
            } else {
                horariosSimulados.forEach(horario => {
                    const btnHorario = document.createElement('span');
                    btnHorario.className = 'horario-item';
                    btnHorario.textContent = horario;
                    btnHorario.dataset.horario = horario;

                    // PRÉ-SELEÇÃO PARA EDIÇÃO
                    if (horario === horarioOriginal) {
                        btnHorario.classList.add('selecionado');
                        inputHorarioSelecionado.value = horario; 
                    }

                    btnHorario.addEventListener('click', function() {
                        document.querySelectorAll('.horario-item.selecionado').forEach(btn => {
                            btn.classList.remove('selecionado');
                        });
                        this.classList.add('selecionado');
                        inputHorarioSelecionado.value = this.dataset.horario;
                        horarioOriginal = this.dataset.horario; 
                    });

                    divHorarios.appendChild(btnHorario);
                });
            }
        }


        // --- LÓGICA DE NAVEGAÇÃO ENTRE PASSOS ---
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

        // --- INICIALIZAÇÃO E LISTENERS ---
        inputData.addEventListener('change', function() {
            buscarHorarios(inputData.value, dentistaId);
        });

        document.addEventListener('DOMContentLoaded', () => {
            irParaPasso(passoInicial);
            
            if (inputData.value) {
                buscarHorarios(inputData.value, dentistaId);
            }
            
            // Inicia todas as categorias fechadas (comportamento de accordion)
            document.querySelectorAll('.servicos-list').forEach(div => {
                div.style.display = 'none'; 
            });
        });
    </script>
</body>
</html>