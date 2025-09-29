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
$titulo_pagina = 'Agendar Consulta - SmileUp';
$is_dashboard = false;

// O ID do dentista fixo (conforme sua solicitação) FOI REMOVIDO.
// A busca por todos os dentistas (função buscarTodosDentistas) FOI REMOVIDA.

// 1. Função para buscar serviços, categorias E O DENTISTA ASSOCIADO À CATEGORIA
function buscarServicosECategorias($pdo) {
    // Adicionamos o JOIN com a tabela 'dentista' (que tem 'usuario_id' do dentista) e 'Categoria_Dentista' para buscar o dentista
    $stmt = $pdo->query("
        SELECT 
            s.servico_id, 
            s.nome_servico,
            s.descricao,
            s.preco,
            c.nome AS nome_categoria,
            c.categoria_id,
            d.usuario_id AS dentista_id,
            u.nome AS nome_dentista
        FROM 
            Servico s
        JOIN 
            Categoria c ON s.categoria_id = c.categoria_id
        JOIN
            Categoria_Dentista cd ON c.categoria_id = cd.categoria_id
        JOIN
            Dentista d ON cd.dentista_id = d.usuario_id
        JOIN
            Usuario u ON d.usuario_id = u.usuario_id
        ORDER BY
            c.nome, s.nome_servico, u.nome
    ");
    // Retornamos todos os serviços. Note que se uma categoria tiver dois dentistas, o serviço aparecerá duas vezes,
    // o que é um comportamento que o JS terá que gerenciar (ex: agrupar por serviço e dentista) ou o backend
    // precisará ser ajustado para pegar apenas UM dentista principal por categoria/serviço.
    // Para simplificar, assumimos que o JS fará o trabalho de mostrar as opções.
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. OBTENDO DADOS DO BANCO PARA O FORMULÁRIO
$servicosECategorias = buscarServicosECategorias($pdo);

// Carrega cabeçalho e menu
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
            
            <input type="hidden" name="dentista" id="dentista_selecionado" required data-error-message="Selecione um dentista para continuar.">

            <div id="passo-1" class="passo-agendamento">
                
                <div class="form-group">
                    <label>Selecione os serviços que deseja agendar (Você pode selecionar mais de um):</label>
                    <input type="hidden" name="servicos_validacao" id="servicos_validacao" required data-error-message="Selecione ao menos um serviço para continuar.">
                    
                    <div class="servicos-list">
                        <?php 
                        $categoria_atual = '';
                        // Usamos um array auxiliar para garantir que cada serviço/dentista seja único na lista de checkbox
                        $servicos_unicos = [];
                        foreach ($servicosECategorias as $servico): 
                            // Criando uma chave única que inclui o dentista, pois agora ele é a variável
                            $chave_servico_dentista = $servico['servico_id'] . '_' . $servico['dentista_id'];
                            
                            // Ignora se já listamos esse par serviço/dentista
                            if (isset($servicos_unicos[$chave_servico_dentista])) {
                                continue;
                            }
                            $servicos_unicos[$chave_servico_dentista] = true;

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
                                       id="servico_<?php echo $servico['servico_id']; ?>_dentista_<?php echo $servico['dentista_id']; ?>" 
                                       name="servicos[]" 
                                       value="<?php echo $servico['servico_id'] . '|' . $servico['dentista_id']; ?>" 
                                       data-preco="<?php echo $servico['preco']; ?>"
                                       data-dentista-id="<?php echo $servico['dentista_id']; ?>">
                                <label for="servico_<?php echo $servico['servico_id']; ?>_dentista_<?php echo $servico['dentista_id']; ?>">
                                    <?php echo htmlspecialchars($servico['nome_servico']); ?> 
                                    (Com Dr(a). <?php echo htmlspecialchars($servico['nome_dentista']); ?> - R$ <?php echo number_format($servico['preco'], 2, ',', '.'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                        </fieldset> </div>
                    <p style="margin-top: 15px;">**O valor final será a soma dos serviços selecionados.**</p>
                </div>
                
                <div class="form-group">
                    <label for="dentista_info">Profissional(is) Escolhido(s):</label>
                    <p id="info-dentista" style="padding: 10px; border: 1px solid #ccc; background-color: #f8f9fa; border-radius: 4px;">
                        **Selecione um serviço para ver o profissional associado.**
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
                        <label for="data">Data Desejada</label>
                        <input type="date" id="data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label>Horários Disponíveis</label>
                        <div class="horarios-disponiveis" id="lista-horarios">
                            <p>Selecione uma **data** e um **dentista/serviço** para ver os horários disponíveis.</p>
                        </div>
                        <input type="hidden" id="horario_selecionado" name="horario_selecionado" required data-error-message="Selecione um horário.">
                    </div>
                </div>

                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="validarEPularPasso(2, 3)">Continuar</button>
                </div>
            </div>

            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <h3 class="subsection-title">Informações Adicionais (Opcional)</h3>
                <div class="form-group">
                    <label for="observacoes">Observações sobre a Consulta:</label>
                    <textarea id="observacoes" name="observacoes" placeholder="Ex: Preferência por anestesia local, dúvidas sobre o procedimento."></textarea>
                </div>

                <h3 class="subsection-title" style="margin-top: 2rem;">Seu Histórico de Saúde (Opcional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="historico">Histórico Odontológico/Médico:</label>
                        <textarea id="historico" name="historico" placeholder="Informe condições médicas relevantes, cirurgias recentes, ou tratamentos odontológicos anteriores."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="alergias">Alergias Conhecidas:</label>
                        <textarea id="alergias" name="alergias" placeholder="Informe alergias a medicamentos, látex, etc."></textarea>
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
/* ... O CSS permanece inalterado ... */
/* Estilos para a navegação */
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }

    /* Estilos para a lista de horários */
    .horarios-disponiveis {
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

    /* Estilos de layout da grade */
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

    /* Estilos para a lista de serviços */
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

<script src="js/agendamento.js"></script>


<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>