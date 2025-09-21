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
    SELECT c.consulta_id, c.data, c.hora, c.valor, c.observacoes,
           d.nome AS dentista_nome
    FROM consulta c
    JOIN dentista d ON c.usuario_dentista = d.usuario_id
    WHERE c.usuario_paciente = ?
    ORDER BY c.data, c.hora
");
$stmt->execute([$usuario_id]);
$consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <th>Profissional</th>
                        <th>Data / Hora</th>
                        <th>Valor</th>
                        <th>Observações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['dentista_nome']); ?></td>
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
                                    <a href="editar_consulta.php?id=<?php echo $c['consulta_id']; ?>" class="btn-tabela btn-secondary">Editar</a>
                                    <a href="../backend/excluir_consulta.php?id=<?php echo $c['consulta_id']; ?>" class="btn-tabela btn-cancelar" onclick="return confirm('Tem certeza que deseja cancelar esta consulta?')">Cancelar</a>
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
        <form action="agendar_consulta.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="data">Data da Consulta</label>
                    <input type="date" id="data" name="data" required>
                </div>
                <div class="form-group">
                    <label for="hora">Hora</label>
                    <input type="time" id="hora" name="hora" required>
                </div>
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 2rem; width: 100%; max-width: 250px;">
                Confirmar Agendamento
            </button>
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
                        <td>Raio-X Panorâmico.pdf</td>
                        <td>15/08/2025</td>
                        <td><a href="#" class="btn-primary" style="text-decoration: none;">Baixar</a></td>
                    </tr>
                    <tr>
                        <td>Orçamento Tratamento.pdf</td>
                        <td>10/08/2025</td>
                        <td><a href="#" class="btn-primary" style="text-decoration: none;">Baixar</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php
// Fecha as tags <body> e <html>
include 'templates/footer.php';
?>
