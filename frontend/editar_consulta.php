<?php
// ==========================================================
// 1. INCLUSÃO E INICIALIZAÇÃO DO PHP
// ==========================================================
// 1.1. Configurações e Conexão (Ajuste o caminho conforme o seu projeto)
require_once "conexao.php";  

// Inicia a sessão se necessário (para verificar login, por exemplo)
// session_start(); 

// 1.2. Verifica o ID da Consulta
$consulta_id = $_GET['id'] ?? null;

if (!$consulta_id) {
    // Redireciona se o ID não foi fornecido
    header("Location: dashboard.php");
    exit;
}

// 1.3. Busca Dados da Consulta Existente
try {
    $stmt_consulta = $pdo->prepare("
        SELECT c.*, D.nome AS nome_dentista 
        FROM consulta c
        JOIN usuario D ON c.usuario_dentista = D.usuario_id
        WHERE c.consulta_id = :id
    ");
    $stmt_consulta->bindParam(':id', $consulta_id, PDO::PARAM_INT);
    $stmt_consulta->execute();
    $consulta = $stmt_consulta->fetch(PDO::FETCH_ASSOC);

    if (!$consulta) {
        // Redireciona se a consulta não foi encontrada
        header("Location: dashboard.php");
        exit;
    }

    // Variáveis para pré-preenchimento
    $dentista_id = $consulta['usuario_dentista'];
    $paciente_id = $consulta['usuario_paciente'];
    $data_form = $consulta['data'];
    $hora_form = $consulta['hora'];
    $servicos_existentes = json_decode($consulta['servicos_json'], true);
    $observacoes_form = $consulta['observacoes'];

} catch (PDOException $e) {
    // Tratar erro de banco de dados
    die("Erro ao carregar consulta: " . $e->getMessage());
}

// 1.4. Busca de Categorias e Serviços (Estrutura idêntica ao agendar_consulta)
try {
    // Serviços agrupados por categoria (Ajuste a estrutura SQL se necessário)
    $stmt_servicos = $pdo->query("
        SELECT S.servico_id, S.nome AS servico_nome, C.categoria_id, C.nome AS categoria_nome 
        FROM servico S
        JOIN categoria C ON S.categoria_id = C.categoria_id
        ORDER BY C.nome, S.nome
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

    // 2.1. Checagem de Conflito de Horário (CRÍTICO NA EDIÇÃO)
    if (empty($erro_msg)) {
        try {
            // Busca outras consultas para o MESMO dentista, na MESMA data/hora, 
            // mas EXCLUI a consulta que está sendo editada ($consulta_id).
            $stmt_conflito = $pdo->prepare("
                SELECT consulta_id 
                FROM consulta 
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
                
                // Força o formulário a voltar ao Passo 2 para escolher novo horário
                // $data_form, $hora_form, etc., manterão os valores novos (inválidos) para reexibir.
                // O JavaScript usará a flag $erro_msg para iniciar no Passo 2.
            }

        } catch (PDOException $e) {
            $erro_msg = "Erro interno ao checar disponibilidade.";
        }
    }


    // 2.2. Execução do Update
    if (empty($erro_msg)) {
        try {
            $servicos_json_nova = json_encode($servicos_novos);

            $stmt_update = $pdo->prepare("
                UPDATE consulta SET 
                    data = :data_nova, 
                    hora = :hora_nova, 
                    servicos_json = :servicos_json_nova, 
                    observacoes = :observacoes_nova
                WHERE consulta_id = :consulta_id
            ");
            
            $stmt_update->bindParam(':data_nova', $data_nova);
            $stmt_update->bindParam(':hora_nova', $hora_nova);
            $stmt_update->bindParam(':servicos_json_nova', $servicos_json_nova);
            $stmt_update->bindParam(':observacoes_nova', $observacoes_nova);
            $stmt_update->bindParam(':consulta_id', $consulta_id, PDO::PARAM_INT);
            
            if ($stmt_update->execute()) {
                // Sucesso: Redireciona com mensagem
                header("Location: dashboard.php?msg=Consulta%20atualizada%20com%20sucesso!");
                exit;
            } else {
                $erro_msg = "Falha ao atualizar a consulta.";
            }

        } catch (PDOException $e) {
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
        /* Estilos básicos para os passos e a simulação de horários */
        .passo-agendamento { display: none; margin-bottom: 20px; }
        .passo-agendamento h2 { border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-buttons button { padding: 10px 20px; margin-right: 10px; cursor: pointer; }

        /* Estilo dos Horários (manter) */
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
        
        /* Estilo dos Serviços (Onde a correção visual é aplicada) */
        /* Categoria principal (o que você disse que aparece em azul) */
        .categoria-container {
            border: 1px solid #eee;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .categoria-header {
            background-color: #007bff; /* Azul, como você pediu */
            color: white;
            padding: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        .servicos-list {
            padding: 15px;
            /* A classe que controla a abertura/fechamento do accordion pode ser .collapsed */
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
                                <?php $checked = in_array($servico['servico_id'], $servicos_existentes) ? 'checked' : ''; ?>
                                <div class="servico-item">
                                    <label>
                                        <input type="checkbox" name="servicos[]" value="<?= $servico['servico_id'] ?>" <?= $checked ?>>
                                        <?= htmlspecialchars($servico['servico_nome']) ?>
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
        // Seletores e Variáveis Globais (Mantidos do código anterior)
        const inputData = document.getElementById('data');
        const inputHorarioSelecionado = document.getElementById('horario_selecionado');
        const divHorarios = document.getElementById('horarios_disponiveis');
        const alertaErro = document.getElementById('alerta-erro');
        const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
        const inputServicosValidacao = document.getElementById('servicos_validacao');
        
        const dentistaId = <?= $dentista_id ?>;
        // Captura o horário atual da consulta para pré-selecionar no formulário
        let horarioOriginal = "<?= htmlspecialchars($hora_form) ?>"; 
        
        
        // --- LÓGICA DE SERVIÇOS (ABRIR/FECHAR CATEGORIAS) ---
        // Se você usava uma lógica de 'accordion' no agendar_consulta, esta função é essencial.
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
            // Limpa seleções anteriores
            inputHorarioSelecionado.value = '';
            divHorarios.innerHTML = ''; // Limpa a lista

            // SIMULAÇÃO COPIADA DO SEU CÓDIGO
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
                        horarioOriginal = this.dataset.horario; // Mantém a seleção
                    });

                    divHorarios.appendChild(btnHorario);
                });
            }
        }


        // --- LÓGICA DE NAVEGAÇÃO ENTRE PASSOS (MANTIDA) ---
        let passoInicial = 1;
        // Se houver erro de conflito (erro_msg do PHP), inicia no Passo 2
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
                // Validação de horário no Passo 2
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
            
            // Inicia com os horários carregados se já houver data no formulário (útil na reabertura por erro)
            if (inputData.value) {
                buscarHorarios(inputData.value, dentistaId);
            }
            
            // Garante que todas as categorias comecem fechadas, exceto a primeira, se for a sua lógica
            document.querySelectorAll('.servicos-list').forEach(div => {
                div.style.display = 'none'; 
            });
        });
    </script>
</body>
</html>