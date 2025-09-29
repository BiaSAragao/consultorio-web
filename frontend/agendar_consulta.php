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

// Função para buscar serviços e categorias
function buscarServicosECategorias($pdo) {
    $stmt = $pdo->query("
        SELECT 
            s.servico_id, 
            s.nome_servico,
            s.descricao,
            s.preco,
            c.nome AS nome_categoria,
            c.categoria_id
        FROM Servico s
        JOIN Categoria c ON s.categoria_id = c.categoria_id
        ORDER BY c.nome, s.nome_servico
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$servicosECategorias = buscarServicosECategorias($pdo);

include 'templates/header.php';
?>

<main class="main-container">
<section id="agendamento" class="section-container">
<h2 class="section-title">Agendar Sua Nova Consulta</h2>

<form action="../backend/processa_consulta.php" method="POST" id="form-agendamento">

    <input type="hidden" name="dentista" id="dentista">

    <!-- PASSO 1 -->
    <div id="passo-1" class="passo-agendamento">
        <div class="form-group">
            <label>Selecione os serviços:</label>
            <input type="hidden" name="servicos_validacao" id="servicos_validacao" required>

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
                            class="servico-checkbox"
                            id="servico_<?php echo $servico['servico_id']; ?>" 
                            name="servicos[]" 
                            value="<?php echo $servico['servico_id']; ?>" 
                            data-preco="<?php echo $servico['preco']; ?>"
                            data-categoria="<?php echo $servico['categoria_id']; ?>">

                        <label for="servico_<?php echo $servico['servico_id']; ?>">
                            <?php echo htmlspecialchars($servico['nome_servico']); ?> 
                            (R$ <?php echo number_format($servico['preco'], 2, ',', '.'); ?>)
                        </label>
                    </div>
                <?php endforeach; ?>
                </fieldset> 
            </div>
        </div>

        <div class="form-group">
            <label>Profissional:</label>
            <p id="dentista_info" style="padding:10px; border:1px solid #ccc; background:#f8f9fa; border-radius:4px;">
                Selecione um serviço para ver o dentista responsável
            </p>
        </div>

        <div class="botoes-navegacao">
            <button type="button" class="btn-primary" onclick="validarEPularPasso(1, 2)">Continuar</button>
        </div>
    </div>

    <!-- PASSO 2 -->
    <div id="passo-2" class="passo-agendamento" style="display: none;">
        <h3 class="subsection-title">Escolha a Data e Horário</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="data">Data</label>
                <input type="date" id="data" name="data" required>
            </div>
            <div class="form-group">
                <label>Horários Disponíveis</label>
                <div class="horarios-disponiveis" id="lista-horarios">
                    <p>Selecione uma data para ver os horários disponíveis.</p>
                </div>
                <input type="hidden" id="horario_selecionado" name="horario_selecionado" required>
            </div>
        </div>
        <div class="botoes-navegacao">
            <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
            <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
        </div>
    </div>

    <!-- PASSO 3 -->
    <div id="passo-3" class="passo-agendamento" style="display: none;">
        <h3 class="subsection-title">Informações Adicionais (Opcional)</h3>
        <div class="form-group">
            <label for="observacoes">Observações sobre a Consulta:</label>
            <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($obs['obs'] ?? ''); ?></textarea>
        </div>
        <h3 class="subsection-title" style="margin-top: 2rem;">Seu Histórico de Saúde (Opcional)</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="historico">Histórico Odontológico/Médico:</label>
                <textarea id="historico" name="historico"><?php echo htmlspecialchars($obs['historico'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="alergias">Alergias Conhecidas:</label>
                <textarea id="alergias" name="alergias"><?php echo htmlspecialchars($obs['alergias'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="botoes-navegacao">
            <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
            <button type="submit" class="btn-primary">Salvar alterações</button>
        </div>
    </div>

</form>
</section>
</main>

<script src="js/agendamento.js"></script>

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>
