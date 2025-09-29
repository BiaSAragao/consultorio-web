<?php
// admin/ficha-paciente.php

session_start();
// Inclui o arquivo de conexão PDO
require '../backend/conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO (Obriga o login)
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    // Redireciona para a página de login se o dentista não estiver logado
    header('Location: login-dentista.php'); 
    exit;
}

$titulo_pagina = 'Ficha do Paciente - SmileUp';
$is_dashboard = true; 
$erro_db = null;

// 2. Pega o ID da URL e valida
$id_paciente = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_paciente) {
    echo "<main class='main-container'><p class='section-container'>Erro: ID do paciente não fornecido ou inválido.</p></main>";
    include '../frontend/templates/footer.php';
    exit(); 
}

// ===================================================================
// LÓGICA DO BANCO DE DADOS
// ===================================================================

try {
    // A. DADOS PESSOAIS DO PACIENTE
    // Buscamos dados da tabela Usuario (nome, email, tel) e Paciente (cpf, plano, dt_nasc)
    $sql_dados_paciente = "
        SELECT 
            u.nome, u.tel AS telefone, u.email, 
            p.cpf, p.plano_saude, p.data_nascimento
        FROM Usuario u
        JOIN Paciente p ON u.usuario_id = p.usuario_id
        WHERE u.usuario_id = :id_paciente
    ";
    $stmt_paciente = $pdo->prepare($sql_dados_paciente);
    $stmt_paciente->execute([':id_paciente' => $id_paciente]);
    $dados_paciente = $stmt_paciente->fetch(PDO::FETCH_ASSOC);

    // Se o paciente não foi encontrado no banco, $dados_paciente estaria vazio
    if (!$dados_paciente) {
        echo "<main class='main-container'><p class='section-container'>Paciente com ID $id_paciente não encontrado ou não é um paciente.</p></main>";
        include '../frontend/templates/footer.php';
        exit();
    }
    
    // B. HISTÓRICO DE CONSULTAS
    $sql_historico = "
        SELECT 
            c.data, 
            c.status,
            s.nome_servico AS procedimento
        FROM Consulta c
        JOIN Consulta_Servico cs ON c.consulta_id = cs.consulta_id
        JOIN Servico s ON cs.servico_id = s.servico_id
        WHERE c.usuario_paciente = :id_paciente
        ORDER BY c.data DESC
    ";
    $stmt_historico = $pdo->prepare($sql_historico);
    $stmt_historico->execute([':id_paciente' => $id_paciente]);
    $historico_consultas = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);

    // C. LAUDOS E DOCUMENTOS
    $sql_laudos = "
        SELECT 
            documento_id AS id_laudo, 
            nome_arquivo_original, 
            data_upload
        FROM Documento -- Substitua 'Documento' pelo nome real da sua tabela de laudos/arquivos
        WHERE usuario_paciente = :id_paciente
        ORDER BY data_upload DESC
    ";
    $stmt_laudos = $pdo->prepare($sql_laudos);
    $stmt_laudos->execute([':id_paciente' => $id_paciente]);
    $lista_laudos = $stmt_laudos->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $erro_db = "Erro de comunicação com o sistema ao carregar a ficha. Por favor, tente novamente.";
}


// 3. Lógica para calcular a idade
$nascimento = new DateTime($dados_paciente['data_nascimento']);
$hoje = new DateTime();
$idade = $nascimento->diff($hoje)->y;
// --- FIM DA LÓGICA DO BACKEND ---

include '../frontend/templates/header.php';
?>

<main class="main-container">

    <?php if (isset($erro_db)): ?>
        <section class="section-container">
            <p style="color: red; padding: 10px; border: 1px solid red; background-color: #fee2e2; border-radius: 4px;"><?php echo htmlspecialchars($erro_db); ?></p>
        </section>
    <?php endif; ?>

    <section id="dados-pessoais" class="section-container">
        <h2 class="section-title">Ficha Completa de - <?php echo htmlspecialchars($dados_paciente['nome']); ?></h2>
        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <p><strong>Telefone:</strong> <?php echo htmlspecialchars($dados_paciente['telefone']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($dados_paciente['email']); ?></p>
            <p><strong>CPF:</strong> <?php echo htmlspecialchars($dados_paciente['cpf']); ?></p>
            <p><strong>Plano de Saúde:</strong> <?php echo htmlspecialchars($dados_paciente['plano_saude'] ?? 'N/A'); ?></p>
            <p><strong>Data Nasc.:</strong> <?php echo date('d/m/Y', strtotime($dados_paciente['data_nascimento'])); ?></p>
            <p><strong>Idade:</strong> <?php echo $idade; ?> anos</p>
        </div>
    </section>

    <section id="historico-consultas" class="section-container">
        <h3 class="section-title">Histórico de Consultas</h3>
        <table class="tabela-consultas">
            <thead><tr><th>Data</th><th>Procedimento</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($historico_consultas)): ?>
                    <tr><td colspan="3" style="text-align: center;">Nenhuma consulta registrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($historico_consultas as $consulta): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($consulta['data'])); ?></td>
                        <td><?php echo htmlspecialchars($consulta['procedimento']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($consulta['status'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section id="laudos" class="section-container">
        <h3 class="section-title">Laudos e Documentos</h3>
        <table class="tabela-consultas">
            <thead><tr><th>Nome do Arquivo</th><th>Data de Upload</th><th>Ação</th></tr></thead>
            <tbody>
                <?php if (empty($lista_laudos)): ?>
                    <tr><td colspan="3" style="text-align: center;">Nenhum documento encontrado para este paciente.</td></tr>
                <?php else: ?>
                    <?php foreach ($lista_laudos as $laudo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($laudo['nome_arquivo_original']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($laudo['data_upload'])); ?></td>
                        
                        <td><a href="../backend/download_laudo.php?id=<?php echo $laudo['id_laudo']; ?>" class="btn-tabela btn-secondary">Baixar</a></td>

                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h4 style="margin-top: 2rem;">Enviar Novo Documento</h4>
        <form action="../backend/upload_laudo.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_paciente" value="<?php echo $id_paciente; ?>">
            <div class="form-group">
                <label for="arquivo">Selecione o arquivo (PDF, JPG, etc.)</label>
                <input type="file" name="arquivo" id="arquivo" required>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 1rem;">Enviar Arquivo</button>
        </form>
    </section>

</main>

<?php
include '../frontend/templates/footer.php';
?>