<?php
// Inicia a sessão para acessar as variáveis de login
session_start();

// Carrega o arquivo de conexão com o banco de dados
require_once "../backend/conexao.php";

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO E TIPO DE USUÁRIO
// A verificação agora é feita diretamente no banco de dados.

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Pega o ID do usuário logado
$usuario_id = $_SESSION['usuario_id'];

try {
    // Confirma se o usuario_id existe na tabela de pacientes
    $stmt_verifica = $pdo->prepare("SELECT usuario_id FROM Paciente WHERE usuario_id = ?");
    $stmt_verifica->execute([$usuario_id]);
    
    // Se não encontrar o ID, o usuário não é um paciente.
    if (!$stmt_verifica->fetch()) {
        header("Location: login.php");
        exit();
    }

} catch (PDOException $e) {
    // Em caso de erro, redireciona para a página de login
    header("Location: login.php");
    exit();
}

// Agora que o usuário é validado como paciente, pegamos as outras informações
$usuario_nome = $_SESSION['usuario_nome'];

$titulo_pagina = 'Meu Painel - SmileUp';
$is_dashboard = true;

// Carrega cabeçalho e menu
include 'templates/header.php';

// 2. BUSCAR CONSULTAS DO PACIENTE LOGADO
$stmt = $pdo->prepare("
    SELECT 
        c.consulta_id, 
        c.data, 
        c.hora, 
        c.valor, 
        c.observacoes,
        d.nome AS nome_dentista
    FROM 
        Consulta c
    JOIN 
        Usuario d ON c.usuario_dentista = d.usuario_id
    WHERE 
        c.usuario_paciente = ?
    ORDER BY 
        c.data, c.hora
");
$stmt->execute([$usuario_id]);
$consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. FUNÇÃO PARA BUSCAR SERVIÇOS DE CADA CONSULTA
function buscarServicos($pdo, $consulta_id) {
    $stmt = $pdo->prepare("
        SELECT 
            s.nome_servico
        FROM 
            Consulta_servico cs
        JOIN 
            Servico s ON cs.servico_id = s.servico_id
        WHERE 
            cs.consulta_id = ?
    ");
    $stmt->execute([$consulta_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 4. NOVA FUNÇÃO PARA BUSCAR TODOS OS DENTISTAS DISPONÍVEIS
function buscarTodosDentistas($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            d.usuario_id, 
            u.nome
        FROM 
            Dentista d
        JOIN 
            Usuario u ON d.usuario_id = u.usuario_id
        ORDER BY
            u.nome
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para buscar serviços e categorias (mantida)
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

// 5. OBTENDO DADOS DO BANCO PARA O JS E O PHP
$servicosECategorias = buscarServicosECategorias($pdo);
$todosDentistas = buscarTodosDentistas($pdo);
?>

<main class="main-container">

    <?php if (isset($_GET['msg'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; margin: 15px 0; border: 1px solid #c3e6cb; border-radius: 5px;">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <section id="consultas" class="section-container">
        <h2 class="section-title">Suas Próximas Consultas</h2>
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Dentista</th>
                        <th>Valor</th>
                        <th>Serviços</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $c): ?>
                            <tr>
                                <td>
                                    <?php 
                                        echo date("d/m/Y", strtotime($c['data'])) . 
                                             " às " . date("H:i", strtotime($c['hora']));
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['nome_dentista']); ?></td>
                                <td>R$ <?php echo number_format($c['valor'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                        $servicos = buscarServicos($pdo, $c['consulta_id']);
                                        echo htmlspecialchars(implode(', ', $servicos));
                                    ?>
                                </td>
                                <td>
                                    <a href="editar_consulta.php?id=<?php echo $c['consulta_id']; ?>" 
                                        class="btn-tabela btn-secondary">Editar</a>
                                    <a href="../backend/excluir_consulta.php?id=<?php echo $c['consulta_id']; ?>" 
                                        class="btn-tabela btn-cancelar" 
                                        onclick="return confirm('Tem certeza que deseja cancelar esta consulta?')">Cancelar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Nenhuma consulta agendada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="agendar" class="section-container">
        <h2 class="section-title">Agendar Nova Consulta</h2>
        <form action="agendar_consulta.php" method="POST" id="form-agendamento">
            <div id="passo-1" class="passo-agendamento">
                <div class="form-group">
                    <label for="servico">Primeiro, selecione o serviço que deseja agendar:</label>
                    <select id="servico" name="servico" required>
                        <option value="" disabled selected>Selecione um serviço...</option>
                        <?php foreach ($servicosECategorias as $servico): ?>
                            <option value="<?php echo $servico['servico_id']; ?>">
                                <?php echo htmlspecialchars($servico['nome_categoria'] . ' - ' . $servico['nome_servico']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="irParaPasso(2)">Continuar</button>
                </div>
            </div>
            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <div class="form-group">
                    <label for="dentista">Ótimo! Agora, escolha o profissional:</label>
                    <select id="dentista" name="dentista" required>
                        <option value="" disabled selected>Selecione um profissional...</option>
                        <?php foreach ($todosDentistas as $dentista): ?>
                            <option value="<?php echo $dentista['usuario_id']; ?>">
                                <?php echo htmlspecialchars($dentista['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="irParaPasso(3)">Continuar</button>
                </div>
            </div>
            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Selecione a data</label>
                        <input type="date" id="data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label>Horários Disponíveis para a data</label>
                        <div class="horarios-disponiveis" id="lista-horarios">
                            <p>Selecione uma data para ver os horários.</p>
                        </div>
                        <input type="hidden" id="horario_selecionado" name="horario_selecionado" required>
                    </div>
                </div>
                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Confirmar Agendamento</button>
                </div>
            </div>
        </form>
    </section>

    <section id="laudos" class="section-container">
        <h2 class="section-title">Meus Laudos e Documentos</h2>
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Data de Upload</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3">Nenhum documento disponível no momento.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</main>

<style>
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
</style>

<script>
    // --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

    // Dados que vieram do banco de dados via PHP
    const todosDentistas = <?php echo json_encode($todosDentistas); ?>;
    const servicosECategorias = <?php echo json_encode($servicosECategorias); ?>;
    
    // NÓS REMOVEMOS A VARIÁVEL 'dentistasPorCategoria' POIS NÃO É MAIS NECESSÁRIA.

    const selectServico = document.getElementById('servico');
    const selectDentista = document.getElementById('dentista');
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');

    // A lógica de filtragem foi removida.
    // Agora o select de dentistas é preenchido diretamente no PHP,
    // com todos os dentistas disponíveis.

    // Quando o usuário escolhe uma data e dentista, busca os horários disponíveis
    inputData.addEventListener('change', function() {
        const data = this.value;
        const dentistaId = selectDentista.value;
        
        if (!data || !dentistaId) {
            divHorarios.innerHTML = '<p>Selecione um profissional e uma data para ver os horários.</p>';
            return;
        }

        // --- PONTO DE INTEGRAÇÃO COM BACKEND ---
        // Aqui você faria uma requisição (fetch/AJAX) para o backend,
        // passando a data e o ID do dentista para buscar os horários
        // realmente disponíveis no banco de dados.

        // Por enquanto, vamos usar os horários simulados:
        const horariosSimulados = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"];
        divHorarios.innerHTML = ''; // Limpa a lista
        horariosSimulados.forEach(horario => {
            const btnHorario = document.createElement('button');
            btnHorario.type = 'button';
            btnHorario.className = 'horario-item';
            btnHorario.textContent = horario;
            btnHorario.dataset.horario = horario;

            btnHorario.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(btn => {
                    btn.classList.remove('selected');
                });
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });

            divHorarios.appendChild(btnHorario);
        });
    });

    // Função para navegar entre os passos do formulário
    function irParaPasso(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => {
            passo.style.display = 'none';
        });
        document.getElementById(`passo-${numeroPasso}`).style.display = 'block';
    }

</script>

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>