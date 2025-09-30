<?php
require_once "../backend/conexao.php";
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];
$titulo_pagina = 'Agendar Consulta - SmileUp';
$is_dashboard = false;

// 1. Buscar serviços e categorias
function buscarServicosECategorias($pdo) {
    $stmt = $pdo->query("
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
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Buscar todos horários cadastrados do dentista (sem data)
function buscarTodosHorarios($pdo, $dentista_id) {
    $sql = "
        SELECT DISTINCT dd.horario
        FROM disponibilidade_dentista dd
        WHERE dd.usuario_id = :dentista_id
        ORDER BY dd.horario ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':dentista_id', $dentista_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ----------------------------------------------------------------------------------
// Reabastecimento (mantém escolhas após submit com erro ou voltar)

$data_selecionada = $_GET['data'] ?? '';
$dentista_selecionado = $_GET['dentista_id'] ?? '';
$servicos_selecionados_get = $_GET['servicos'] ?? [];
$horario_selecionado_get = $_GET['horario'] ?? '';
$obs_selecionada = $_GET['observacoes'] ?? '';
$historico_selecionado = $_GET['historico'] ?? '';
$alergias_selecionadas = $_GET['alergias'] ?? '';

$servicosECategorias = buscarServicosECategorias($pdo);
$horarios_disponiveis = !empty($dentista_selecionado) 
    ? buscarTodosHorarios($pdo, $dentista_selecionado)
    : [];

// Carrega header
include 'templates/header.php';
?>

<main class="main-container">

    <section id="agendamento" class="section-container">
        <h2 class="section-title">Agendar Sua Nova Consulta</h2>
        
        <?php if (isset($_GET['msg'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; margin: 15px 0; border: 1px solid #c3e6cb; border-radius: 5px;">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <form action="../backend/processa_consulta.php" method="POST" id="form-agendamento">
            
            <input type="hidden" name="dentista" id="dentista_selecionado" 
                value="<?php echo htmlspecialchars($dentista_selecionado); ?>" 
                required data-error-message="O dentista deve ser selecionado após a escolha do serviço.">

            <!-- Passo 1 -->
            <div id="passo-1" class="passo-agendamento">
                <h3 class="subsection-title">Passo 1: Seleção de Serviço</h3>
                <div class="form-group">
                    <label>Selecione os serviços que deseja agendar (devem ser do mesmo profissional):</label>
                    
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
                            <?php endif; 
                            
                            $servico_id_str = (string)$servico['servico_id'];
                            $checked = in_array($servico_id_str, $servicos_selecionados_get) ? 'checked' : '';
                            ?>
                            <div class="servico-item">
                                <input type="checkbox" 
                                    id="servico_<?php echo $servico['servico_id']; ?>" 
                                    name="servicos[]" 
                                    value="<?php echo $servico['servico_id']; ?>" 
                                    data-preco="<?php echo $servico['preco']; ?>"
                                    data-categoria-id="<?php echo $servico['categoria_id']; ?>" 
                                    data-dentista-id="<?php echo $servico['dentista_id']; ?>"
                                    data-dentista-nome="<?php echo htmlspecialchars($servico['nome_dentista']); ?>"
                                    <?= $checked ?>>
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
                
                <input type="hidden" id="servicos_validacao" data-error-message="Selecione ao menos um serviço." value="">

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

            <!-- Passo 2 -->
            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Passo 2: Escolha a Data e Horário</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data Desejada:</label>
                        <input type="date" id="data" name="data" class="form-control" required 
                            value="<?php echo htmlspecialchars($data_selecionada); ?>">
                    </div>

                    <div class="form-group">
                        <label>Horários Disponíveis:</label>
                        <div id="lista-horarios" class="horarios-grid">
                            <?php 
                            if (empty($horarios_disponiveis)) {
                                echo '<p>Selecione um serviço primeiro para ver os horários.</p>';
                            } else {
                                foreach ($horarios_disponiveis as $horario) {
                                    $hora_formatada = substr($horario, 0, 5);
                                    $selected_class = ($horario === $horario_selecionado_get) ? 'selected' : '';
                                    echo "<div class='horario-item {$selected_class}' data-horario='{$horario}'>{$hora_formatada}</div>";
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <input type="hidden" id="horario_selecionado" name="horario" 
                        value="<?php echo htmlspecialchars($horario_selecionado_get); ?>"
                        required data-error-message="Selecione um horário disponível.">
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
                </div>
            </div>

            <!-- Passo 3 -->
            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Passo 3: Informações Adicionais</h3>
                <div class="form-group">
                    <label for="observacoes">Observações:</label>
                    <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($obs_selecionada); ?></textarea>
                </div>

                <h3 class="subsection-title" style="margin-top: 2rem;">Histórico de Saúde</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico Médico/Odontológico:</label>
                        <textarea id="historico" name="historico"><?php echo htmlspecialchars($historico_selecionado); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias:</label>
                        <textarea id="alergias" name="alergias"><?php echo htmlspecialchars($alergias_selecionadas); ?></textarea>
                    </div>
                </div>
                
                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Confirmar Agendamento</button>
                </div>
            </div>
        </form>
    </section>
</main>

<style>
    /* O CSS que você enviou foi incluído aqui para manter o arquivo completo */
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }

    .horarios-grid {
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
        background-color: #007bff; /* Cor primária */
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

<script>
    const horariosDisponiveis = <?= json_encode($horarios_html_array ?? []) ?>;
</script>

<script src="js/agendamento.js"></script>


<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>