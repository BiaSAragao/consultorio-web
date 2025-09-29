<?php
// Inicia a sessão para acessar as variáveis de login
session_start();

// Carrega o arquivo de conexão com o banco de dados
require_once "../backend/conexao.php";

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO E TIPO DE USUÁRIO
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

// AS FUNÇÕES 'buscarTodosDentistas' E 'buscarServicosECategorias' FORAM REMOVIDAS
// JÁ QUE SÓ SERÃO NECESSÁRIAS NA PÁGINA 'agendar_consulta.php'

?>

<main class="main-container">

    <?php if (isset($_GET['msg'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; margin: 15px 0; border: 1px solid #c3e6cb; border-radius: 5px;">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <section id="consultas" class="section-container">
        <h2 class="section-title">Suas Próximas Consultas</h2>
        <div class="botoes-navegacao" style="margin-bottom: 2rem; justify-content: flex-start;">
            <a href="agendar_consulta.php" class="btn-primary">Agendar Nova Consulta</a>
        </div>
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
                    <tbody>
                        <?php
                        // --- LÓGICA REAL PARA BUSCAR OS LAUDOS DO PACIENTE LOGADO ---
                        try {
                            // Busca os laudos na nova tabela 'Documento'
                            $stmt_laudos = $pdo->prepare("
                                SELECT 
                                    documento_id AS id_laudo, 
                                    nome_arquivo_original, 
                                    data_upload
                                FROM Documento 
                                WHERE usuario_paciente = :id_paciente 
                                ORDER BY data_upload DESC
                            ");
                            $stmt_laudos->execute([':id_paciente' => $usuario_id]);
                            $lista_laudos_paciente = $stmt_laudos->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            // Em caso de erro de banco de dados
                            $lista_laudos_paciente = []; 
                            echo '<tr><td colspan="3" style="color: red; text-align: center;">Erro ao carregar documentos do banco.</td></tr>';
                        }
                        
                        if (empty($lista_laudos_paciente)): ?>
                            <tr><td colspan="3">Nenhum documento disponível no momento.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista_laudos_paciente as $laudo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($laudo['nome_arquivo_original']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($laudo['data_upload'])); ?></td>
                                
                                <td><a href="../backend/download_laudo.php?id=<?php echo $laudo['id_laudo']; ?>" class="btn-tabela btn-secondary">Baixar</a></td>

                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<style>
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end; /* Mantido para o futuro, mas ajustado acima para o botão */
        gap: 1rem;
        margin-top: 2rem;
    }

    /* Estilo para os botões de horário, se você os reutilizar */
    .horarios-disponiveis {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        max-height: 150px; /* Exemplo */
        overflow-y: auto; /* Exemplo */
    }

    .horario-item {
        background-color: #f0f0f0;
        border: 1px solid #ccc;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .horario-item:hover {
        background-color: #e0e0e0;
    }

    .horario-item.selected {
        background-color: #007bff; /* Cor primária */
        color: white;
        border-color: #007bff;
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
</style>

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>