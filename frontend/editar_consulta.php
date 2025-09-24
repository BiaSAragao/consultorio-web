<?php
// Carrega o arquivo de conexão com o banco de dados
require_once "../backend/conexao.php";
session_start();

// Garante que só entra quem está logado E é paciente
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];
$titulo_pagina = 'Editar Consulta - SmileUp';
$is_dashboard = false;

// O ID da consulta a editar
if (!isset($_GET['id'])) {
    die("Consulta não especificada.");
}
$consulta_id = intval($_GET['id']);

// O ID do dentista fixo (conforme sua solicitação)
$dentista_fixo_id = 3; 

// 1. Função para buscar serviços e categorias
function buscarServicosECategorias($pdo) {
    $stmt = $pdo->query("
        SELECT 
            s.servico_id, 
            s.nome_servico,
            s.descricao,
            s.preco,
            c.nome AS nome_categoria,
            c.categoria_id
        FROM 
            Servico s
        JOIN 
            Categoria c ON s.categoria_id = c.categoria_id
        ORDER BY
            c.nome, s.nome_servico
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Buscar dados atuais da consulta
$stmt = $pdo->prepare("
    SELECT c.consulta_id, c.data, c.horario, c.observacoes, c.historico, c.alergias
    FROM Consulta c
    WHERE c.consulta_id = :id AND c.paciente_id = :usuario_id
");
$stmt->execute([':id' => $consulta_id, ':usuario_id' => $usuario_id]);
$consulta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consulta) {
    die("Consulta não encontrada ou não pertence a este usuário.");
}

// Buscar serviços já vinculados à consulta
$stmtServicos = $pdo->prepare("
    SELECT servico_id 
    FROM Consulta_Servico 
    WHERE consulta_id = :id
");
$stmtServicos->execute([':id' => $consulta_id]);
$servicosSelecionados = $stmtServicos->fetchAll(PDO::FETCH_COLUMN);

// 3. Obter todos os serviços/categorias
$servicosECategorias = buscarServicosECategorias($pdo);

// Carrega cabeçalho e menu
include 'templates/header.php';
?>

<main class="main-container">

    <section id="agendamento" class="section-container">
        <h2 class="section-title">Editar Consulta #<?php echo $consulta_id; ?></h2>
        
        <?php if (isset($_GET['msg'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; margin: 15px 0; border: 1px solid #c3e6cb; border-radius: 5px;">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <form action="../backend/processa_consulta.php?acao=editar&id=<?php echo $consulta_id; ?>" method="POST" id="form-agendamento">
            
            <input type="hidden" name="dentista" id="dentista" value="<?php echo $dentista_fixo_id; ?>">

            <div id="passo-1" class="passo-agendamento">
                
                <div class="form-group">
                    <label>Serviços da Consulta:</label>
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
                                       <?php echo in_array($servico['servico_id'], $servicosSelecionados) ? 'checked' : ''; ?>>
                                <label for="servico_<?php echo $servico['servico_id']; ?>">
                                    <?php echo htmlspecialchars($servico['nome_servico']); ?> 
                                    (R$ <?php echo number_format($servico['preco'], 2, ',', '.'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                        </fieldset> </div>
                </div>
                
                <div class="form-group">
                    <label for="dentista_info">Profissional:</label>
                    <p style="padding: 10px; border: 1px solid #ccc; background-color: #f8f9fa; border-radius: 4px;">
                        Dr(a). Fixo (ID <?php echo $dentista_fixo_id; ?>)
                    </p>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(1, 2)">Continuar</button>
                </div>
            </div>

            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Escolha a Data e Horário</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" required value="<?php echo htmlspecialchars($consulta['data']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Horários Disponíveis</label>
                        <div class="horarios-disponiveis" id="lista-horarios">
                            <p>Selecione uma data para ver os horários disponíveis.</p>
                        </div>
                        <input type="hidden" id="horario_selecionado" name="horario_selecionado" required value="<?php echo htmlspecialchars($consulta['horario']); ?>" data-error-message="Selecione um horário.">
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
                </div>
            </div>

            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Informações Adicionais</h3>
                <div class="form-group">
                    <label for="observacoes">Observações:</label>
                    <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($consulta['observacoes']); ?></textarea>
                </div>

                <h3 class="subsection-title" style="margin-top: 2rem;">Histórico de Saúde</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico Odontológico/Médico:</label>
                        <textarea id="historico" name="historico"><?php echo htmlspecialchars($consulta['historico']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias Conhecidas:</label>
                        <textarea id="alergias" name="alergias"><?php echo htmlspecialchars($consulta['alergias']); ?></textarea>
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

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>
