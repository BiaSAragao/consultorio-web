<?php
// Inicia a sessão para acessar o usuário logado
session_start();

// 1. REQUERIMENTO DO ARQUIVO DE CONEXÃO
// Supondo que 'conexao.php' retorna uma variável $pdo (objeto PDO conectado)
require_once '../backend/conexao.php'; 

// 2. VERIFICAÇÃO DE LOGIN E PERMISSÃO
// Garante que apenas um dentista logado possa acessar
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php'); // Redireciona para o login se não estiver logado
    exit;
}

$dentista_id_logado = $_SESSION['usuario_id'];
$titulo_pagina = 'Painel do Dentista - SmileUp';
$is_dashboard = true; 
$data_hoje = date('Y-m-d');
$data_fim_semana = date('Y-m-d', strtotime('+7 days'));

// --- LÓGICA DO BACKEND (Dados Reais do Banco) ---

// 1. CARREGAR DADOS BÁSICOS DO DENTISTA (Apenas o nome)
$sql_dentista_info = "
    SELECT u.nome
    FROM Usuario u
    WHERE u.usuario_id = :dentista_id
";
$stmt_dentista = $pdo->prepare($sql_dentista_info);
$stmt_dentista->execute([':dentista_id' => $dentista_id_logado]);
$dentista_info = $stmt_dentista->fetch(PDO::FETCH_ASSOC);

$_SESSION['usuario_nome'] = $dentista_info['nome'] ?? 'Dentista'; // Atualiza o nome da sessão

// ** REMOVIDA A CONSULTA DE ESPECIALIDADE **

// 2. DADOS PARA A AGENDA SEMANAL
// Consulta: Retorna as consultas do dentista logado para a próxima semana
$sql_agenda = "
    SELECT 
        c.consulta_id AS id, 
        TO_CHAR(c.data, 'DD/MM (Day)') AS dia_formatado,
        TO_CHAR(c.hora, 'HH24:MI') AS hora, 
        u_paciente.nome AS paciente, 
        s.nome_servico AS procedimento,
        c.valor,
        c.observacoes,
        c.status
    FROM Consulta c
    JOIN Paciente p ON c.usuario_paciente = p.usuario_id
    JOIN Usuario u_paciente ON p.usuario_id = u_paciente.usuario_id
    LEFT JOIN Consulta_servico cs ON c.consulta_id = cs.consulta_id
    LEFT JOIN Servico s ON cs.servico_id = s.servico_id
    WHERE c.usuario_dentista = :dentista_id 
      AND c.data BETWEEN :data_hoje AND :data_fim_semana
    ORDER BY c.data, c.hora;
";
$stmt_agenda = $pdo->prepare($sql_agenda);
$stmt_agenda->execute([
    ':dentista_id' => $dentista_id_logado,
    ':data_hoje' => $data_hoje,
    ':data_fim_semana' => $data_fim_semana
]);
$agenda_semanal = $stmt_agenda->fetchAll(PDO::FETCH_ASSOC);


// 3. DADOS PARA A CONFIGURAÇÃO DE HORÁRIOS
// Consulta Horários Salvos (Da tabela Disponibilidade_Dentista)
$sql_horarios = "
    SELECT dia_semana, TO_CHAR(horario, 'HH24:MI') as horario
    FROM Disponibilidade_Dentista
    WHERE usuario_id = :dentista_id;
";
$stmt_horarios = $pdo->prepare($sql_horarios);
$stmt_horarios->execute([':dentista_id' => $dentista_id_logado]);
$horarios_db = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);

// Reorganiza os horários para o formato do array HTML
$horarios_salvos = [ 'segunda' => [], 'terca' => [], 'quarta' => [], 'quinta' => [], 'sexta' => [] ];
foreach ($horarios_db as $registro) {
    if (isset($horarios_salvos[$registro['dia_semana']])) {
        $horarios_salvos[$registro['dia_semana']][] = $registro['horario'];
    }
}


// 4. DADOS PARA O CONTROLE DE ESTOQUE INDIVIDUAL
// Consulta: Retorna os itens de estoque *deste dentista* na tabela Estoque_Dentista
$sql_estoque = "
    SELECT 
        ed.item_id AS id, 
        e.nome_item AS nome, 
        ed.qtde AS quantidade, 
        e.preco 
    FROM Estoque_Dentista ed
    JOIN Estoque e ON ed.item_id = e.item_id
    WHERE ed.usuario_id = :dentista_id
";
$stmt_estoque = $pdo->prepare($sql_estoque);
$stmt_estoque->execute([':dentista_id' => $dentista_id_logado]);
$itens_estoque_individual = $stmt_estoque->fetchAll(PDO::FETCH_ASSOC);

// Cálculo do valor total de estoque (para relatórios)
$total_valor_estoque = 0;
foreach ($itens_estoque_individual as $item) {
    $total_valor_estoque += $item['preco'] * $item['quantidade'];
}

// Configurações de data para o Brasil
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
$mes_ano_atual = strftime('%B/%Y');

// O header deve ser incluído apenas uma vez, no topo.
include '../frontend/templates/header.php';
?>

<main class="main-container">

    <section id="agenda-semanal" class="section-container">
        <h2 class="section-title">Minha Agenda Semanal</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Consultas agendadas entre hoje (<?php echo date('d/m'); ?>) e <?php echo date('d/m', strtotime('+7 days')); ?>.</p>
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Dia e Hora</th>
                        <th>Paciente</th>
                        <th>Serviço</th>
                        <th>Status</th>
                        <th>Valor (R$)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agenda_semanal)): ?>
                        <tr><td colspan="6" style="text-align:center;">Nenhuma consulta agendada para a próxima semana.</td></tr>
                    <?php else: ?>
                        <?php foreach ($agenda_semanal as $consulta): ?>
                        <tr>
                            <td><?php echo $consulta['dia_formatado'] . ' - ' . $consulta['hora']; ?></td>
                            <td><?php echo htmlspecialchars($consulta['paciente']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['procedimento']); ?></td>
                            <td>
                                <select name="status_atendimento" class="select-status" data-id-consulta="<?php echo $consulta['id']; ?>">
                                    <option value="Agendado" <?php echo (isset($consulta['status']) && $consulta['status'] == 'Agendado') ? 'selected' : ''; ?>>Agendado</option>
                                    <option value="Aguardando" <?php echo (isset($consulta['status']) && $consulta['status'] == 'Aguardando') ? 'selected' : ''; ?>>Aguardando</option>
                                    <option value="Atendido" <?php echo (isset($consulta['status']) && $consulta['status'] == 'Atendido') ? 'selected' : ''; ?>>Atendido</option>
                                    <option value="Cancelado" <?php echo (isset($consulta['status']) && $consulta['status'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </td>
                            <td>R$ <?php echo number_format($consulta['valor'], 2, ',', '.'); ?></td>
                            <td><button class="btn-tabela btn-detalhes">Detalhes</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="meus-horarios" class="section-container">
        <h2 class="section-title">Horários de Atendimento</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Marque seus horários disponíveis na semana.</p>
        <form action="../backend/salvar_disponibilidade.php" method="POST">
            <div class="form-group">
                <label><strong>1. Quais seus horários disponíveis na semana?</strong></label>
                 <div class="tabela-horarios-container">
                    <table class="tabela-horarios">
                        <thead><tr><th>Dia</th><th>Horários</th></tr></thead>
                        <tbody>
                            <?php 
                            $dias_semana = ['segunda' => 'Segunda-feira', 'terca' => 'Terça-feira', 'quarta' => 'Quarta-feira', 'quinta' => 'Quinta-feira', 'sexta' => 'Sexta-feira'];
                            $horarios_dia = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
                            foreach ($dias_semana as $dia_key => $dia_nome):
                            ?>
                            <tr>
                                <td><strong><?php echo $dia_nome; ?></strong></td>
                                <td>
                                    <div class="horarios-checkboxes">
                                        <?php foreach ($horarios_dia as $horario): 
                                            $checked = in_array($horario, $horarios_salvos[$dia_key] ?? []) ? 'checked' : '';
                                        ?>
                                            <label class="horario-label">
                                                <input type="checkbox" name="horarios[<?php echo $dia_key; ?>][]" value="<?php echo $horario; ?>" <?php echo $checked; ?>> <?php echo $horario; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 2rem;">Salvar Disponibilidade</button>
        </form>
    </section>

    <section id="estoque" class="section-container">
        <h2 class="section-title">Meu Estoque Individual</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Visualize os itens em seu inventário pessoal. Valor total do estoque: <strong>R$ <?php echo number_format($total_valor_estoque, 2, ',', '.'); ?></strong></p>
        
        <div class="estoque-acoes">
            <a href="../backend/adicionar_item.php" class="btn-estoque btn-primary">Adicionar Novo Item</a>
        </div>

        <h3 style="margin-top: 2rem;">Itens em Meu Inventário</h3>
        <table class="tabela-consultas">
            <thead><tr><th>ID</th><th>Produto</th><th>Qtde.</th><th>Preço Unitário</th><th>Valor Total</th><th style="width: 200px;">Gerenciar</th></tr></thead>
            <tbody>
                <?php if (empty($itens_estoque_individual)): ?>
                    <tr><td colspan="6" style="text-align:center;">Você não possui itens no seu estoque individual.</td></tr>
                <?php else: ?>
                    <?php foreach ($itens_estoque_individual as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['nome']); ?></td>
                        <td><?php echo $item['quantidade']; ?></td>
                        <td>R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="../backend/editar_item.php?id=<?php echo $item['id']; ?>" class="btn-tabela btn-editar">Editar</a>
                            <a href="../backend/remover_qtde_item.php?id=<?php echo $item['id']; ?>" class="btn-tabela btn-cancelar">Remover Qtd.</a>
                            <a 
                                href="../backend/remover_item_total.php?id=<?php echo $item['id']; ?>" 
                                class="btn-tabela btn-secundario-danger" 
                                onclick="return confirm('ATENÇÃO: Você deseja remover TODOS os registros e a quantidade restante deste item do seu inventário? Esta ação é irreversível.');"
                            >
                                Remover Total
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<style>
/* Estilos mantidos para consistência */
.select-status { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d1d5db; background-color: #fff; cursor: pointer; }
.btn-editar { background-color: #fcd34d; color: #333; }
.btn-detalhes { background-color: #a5b4fc; color: #333; }
.btn-tabela { padding: 5px 10px; border-radius: 5px; text-decoration: none; margin: 0 2px; display: inline-block;}
.btn-cancelar { background-color: #fca5a5; color: #333; }
.estoque-acoes { margin-bottom: 1.5rem; display: flex; gap: 10px; }
.btn-estoque { padding: 10px 15px; text-decoration: none; }
.tabela-consultas th:nth-child(5) { text-align: right; }
.tabela-consultas td:nth-child(5) { text-align: right; font-weight: 600; }
.tabela-horarios-container { overflow-x: auto; }
.tabela-horarios { width: 100%; border-collapse: collapse; }
.tabela-horarios th, .tabela-horarios td { padding: 0.75rem; border: 1px solid #e5e7eb; text-align: left; }
.horarios-checkboxes { display: flex; flex-wrap: wrap; gap: 1rem; }
.horario-label { display: flex; align-items: center; gap: 0.25rem; cursor: pointer; }
</style>

<script>
document.querySelectorAll('.select-status').forEach(select => {
    select.addEventListener('change', function() {
        const idConsulta = this.dataset.idConsulta;
        const novoStatus = this.value;
        
        if (confirm(`Confirmar alteração do status da consulta ${idConsulta} para: ${novoStatus}?`)) {
            console.log(`Enviando atualização para o ID ${idConsulta} com status ${novoStatus}`);
            // Chamada fetch/AJAX para o backend aqui
        } else {
            this.value = this.dataset.currentStatus; 
        }
    });

    select.dataset.currentStatus = select.value; 
});
</script>

<?php 
// O footer deve ser incluído apenas uma vez, no final do arquivo.
include '../frontend/templates/footer.php'; 
?>