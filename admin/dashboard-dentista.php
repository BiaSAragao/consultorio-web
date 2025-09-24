<?php
// --- TRUQUE TEMPORÁRIO PARA VISUALIZAÇÃO ---
session_start();
$_SESSION['usuario_id'] = 101; 
$_SESSION['usuario_nome'] = "Dr. Carlos Moura";
$_SESSION['tipo_usuario'] = 'dentista';
// --- FIM DO TRUQUE ---

$titulo_pagina = 'Painel do Dentista - SmileUp';
$is_dashboard = true; 

// O header deve ser incluído apenas uma vez, no topo.
include '../frontend/templates/header.php';

// --- LÓGICA DO BACKEND (Dados Fictícios para Visualização) ---

// 1. DADOS PARA A AGENDA DO DIA
$especialidade_dentista = "Ortodontia"; // Viria da tabela 'dentistas'
$agenda_de_hoje = [
    ['id' => 1, 'hora' => '09:00', 'paciente' => 'Maria Oliveira', 'procedimento' => 'Manutenção Ortodôntica', 'status' => 'Aguardando'],
    ['id' => 2, 'hora' => '10:30', 'paciente' => 'João Santos', 'procedimento' => 'Avaliação de Aparelho', 'status' => 'Atendido'],
];

// 2. DADOS PARA A CONFIGURAÇÃO DE SERVIÇO E HORÁRIOS
$especialidade_atual = 'Ortodontia'; // Viria da tabela 'dentistas'
$lista_de_servicos = ['Clínico Geral', 'Ortodontia', 'Endodontia', 'Estética Dental', 'Implantes']; // Viria da tabela 'servicos'
$horarios_salvos = [ // Viria da tabela 'disponibilidade_dentista'
    'segunda' => ['09:00', '10:00', '11:00'],
    'terca'   => ['09:00', '10:00', '14:00', '15:00'],
    'quarta'  => [],
    'quinta'  => ['09:00', '10:00', '11:00', '14:00', '15:00'],
    'sexta'   => ['09:00', '10:00'],
];

// 3. DADOS PARA O CONTROLE DE ESTOQUE MENSAL
$itens_estoque_mes_atual = [ // Viria da tabela 'estoque' com filtro por mês/ano
    ['id' => 1, 'nome' => 'Luvas Descartáveis (Caixa)', 'quantidade' => 10, 'preco' => 45.50],
    ['id' => 2, 'nome' => 'Máscaras Cirúrgicas (Caixa)', 'quantidade' => 5, 'preco' => 30.00],
    ['id' => 3, 'nome' => 'Resina Composta (Unidade)', 'quantidade' => 50, 'preco' => 120.00],
];
$total_gasto_no_mes = 0;
foreach ($itens_estoque_mes_atual as $item) {
    $total_gasto_no_mes += $item['preco'] * $item['quantidade'];
}
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
$mes_ano_atual = strftime('%B/%Y');

?>

<main class="main-container">

    <section id="agenda-do-dia" class="section-container">
        <h2 class="section-title">Minha Agenda - Hoje (24/09/2025) <span class="badge-especialidade"><?php echo $especialidade_dentista; ?></span></h2>
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Horário</th>
                        <th>Paciente</th>
                        <th>Procedimento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agenda_de_hoje)): ?>
                        <tr><td colspan="4" style="text-align:center;">Nenhuma consulta para hoje.</td></tr>
                    <?php else: ?>
                        <?php foreach ($agenda_de_hoje as $consulta): ?>
                        <tr>
                            <td><?php echo $consulta['hora']; ?></td>
                            <td><?php echo htmlspecialchars($consulta['paciente']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['procedimento']); ?></td>
                            <td>
                                <select name="status_atendimento" class="select-status" data-id-consulta="<?php echo $consulta['id']; ?>">
                                    <option value="Aguardando" <?php echo ($consulta['status'] == 'Aguardando') ? 'selected' : ''; ?>>Aguardando</option>
                                    <option value="Atendido" <?php echo ($consulta['status'] == 'Atendido') ? 'selected' : ''; ?>>Atendido</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="meus-horarios" class="section-container">
        <h2 class="section-title">Meu Serviço e Horários Disponíveis</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Selecione o serviço que você oferece e marque seus horários de atendimento. Isso será exibido para os pacientes.</p>
        <form action="../backend/salvar_disponibilidade.php" method="POST">
            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="servico_oferecido"><strong>1. Qual serviço você oferece?</strong></label>
                <select name="servico_oferecido" id="servico_oferecido" required>
                    <option value="">Selecione uma especialidade...</option>
                    <?php foreach ($lista_de_servicos as $servico): ?>
                        <option value="<?php echo $servico; ?>" <?php echo ($servico == $especialidade_atual) ? 'selected' : ''; ?>><?php echo $servico; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                 <label><strong>2. Quais seus horários disponíveis na semana?</strong></label>
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
        <h2 class="section-title">Controle de Compras - <?php echo ucfirst($mes_ano_atual); ?></h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Adicione aqui os produtos comprados este mês. A lista será reiniciada no próximo mês.</p>
        <form action="../backend/adicionar_estoque.php" method="POST" class="form-grid" style="margin-bottom: 2rem;">
            <div class="form-group"><label for="nome_produto">Nome do Produto</label><input type="text" id="nome_produto" name="nome_produto" required></div>
            <div class="form-group"><label for="quantidade">Quantidade</label><input type="number" id="quantidade" name="quantidade" required></div>
            <div class="form-group"><label for="preco">Preço Unitário (R$)</label><input type="text" id="preco" name="preco" required></div>
            <div class="form-group"><button type="submit" class="btn-primary" style="align-self: flex-end;">Adicionar Item</button></div>
        </form>

        <h3 style="margin-top: 2rem;">Itens Comprados este Mês</h3>
        <table class="tabela-consultas">
            <thead><tr><th>Produto</th><th>Quantidade</th><th>Preço Unitário</th><th>Valor Total</th><th style="width: 150px;">Ações</th></tr></thead>
            <tbody>
                <?php foreach ($itens_estoque_mes_atual as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                    <td><?php echo $item['quantidade']; ?></td>
                    <td>R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></td>
                    <td><a href="../backend/deletar_produto.php?id=<?php echo $item['id']; ?>" class="btn-tabela btn-cancelar" onclick="return confirm('Tem certeza?');">Excluir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background-color: #f9fafb;">
                    <td colspan="4" style="text-align: right; padding-right: 1rem;">TOTAL GASTO ESTE MÊS:</td>
                    <td>R$ <?php echo number_format($total_gasto_no_mes, 2, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>
    </section>
</main>

<style>
.badge-especialidade { font-size: 1rem; background-color: #e0e7ff; color: #3730a3; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; vertical-align: middle; margin-left: 1rem; }
.select-status { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d1d5db; background-color: #fff; cursor: pointer; }
.select-status:focus { outline-color: #cab078; }
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
        // Aqui entra a chamada AJAX para o backend salvar a alteração
        alert(`Status da consulta ${idConsulta} alterado para: ${novoStatus}\n(Simulação)`);
    });
});
</script>

<?php 
// O footer deve ser incluído apenas uma vez, no final do arquivo.
include '../frontend/templates/footer.php'; 
?>