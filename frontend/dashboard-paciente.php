<?php
session_start();

// Se o usuário não estiver logado, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once "../backend/conexao.php";

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];

$titulo_pagina = 'Meu Painel - SmileUp';
$is_dashboard = true;

// Carrega cabeçalho e menu (tags <html>, <head>, <body>)
include 'templates/header.php';

// Buscar consultas do paciente logado
$stmt = $pdo->prepare("
    SELECT c.consulta_id, c.data, c.hora, c.valor, c.observacoes
    FROM consulta c
    WHERE c.usuario_paciente = ?
    ORDER BY c.data, c.hora
");
$stmt->execute([$usuario_id]);
$consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para buscar serviços de cada consulta
function buscarServicos($pdo, $consulta_id) {
    $stmt = $pdo->prepare("
        SELECT s.nome_servico
        FROM consulta_servico cs
        JOIN servico s ON cs.servico_id = s.servico_id
        WHERE cs.consulta_id = ?
    ");
    $stmt->execute([$consulta_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<main class="main-container">

    <!-- Mensagem de sucesso -->
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
                        <th>Valor</th>
                        <th>Observações</th>
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
                                <td>R$ <?php echo number_format($c['valor'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                        $obs = json_decode($c['observacoes'], true);
                                        echo htmlspecialchars($obs['obs'] ?? '');
                                    ?>
                                </td>
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

        <div style="margin-top: 15px;">
            <a href="agendar_consulta.php" class="btn-primary">Agendar Nova Consulta</a>
        </div>
    </section>

</main>

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>
