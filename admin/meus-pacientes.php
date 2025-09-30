<?php
// admin/meus-pacientes.php

session_start();

// 1. TRUQUE TEMPORÁRIO (REMOVER EM PRODUÇÃO)
// Apenas para testes, garante que um dentista esteja "logado".
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 101; 
    $_SESSION['usuario_nome'] = "Dr. Carlos Moura";
    $_SESSION['tipo_usuario'] = 'dentista';
}

// 2. VERIFICAÇÃO DE LOGIN
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

$id_dentista = $_SESSION['usuario_id'];
$titulo_pagina = 'Meus Pacientes - SmileUp';
$is_dashboard = true; 

// --- LÓGICA DO BACKEND (Busca no Banco de Dados) ---

// Inclui o arquivo de conexão PDO
require '../backend/conexao.php'; 

$lista_pacientes = [];

try {
    // 3. Consulta SQL REAL
    // Busca todos os pacientes que já tiveram uma consulta (DISTINCT) com o dentista logado.
    $sql_pacientes = "
        SELECT DISTINCT 
            u.usuario_id AS id, 
            u.nome, 
            u.tel AS telefone
        FROM Usuario u
        JOIN Paciente p ON u.usuario_id = p.usuario_id
        JOIN Consulta c ON u.usuario_id = c.usuario_paciente
        WHERE c.usuario_dentista = :id_dentista
        ORDER BY u.nome
    ";

    $stmt = $pdo->prepare($sql_pacientes);
    $stmt->execute([':id_dentista' => $id_dentista]);
    $lista_pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Em caso de erro de banco de dados, a lista fica vazia e exibe a mensagem de erro.
    $lista_pacientes = [];
    $erro_db = "Erro ao carregar pacientes: " . $e->getMessage();
    // Você pode preferir logar o erro e exibir uma mensagem amigável:
    // $erro_db = "Falha de comunicação com o sistema.";
}
// --- FIM DA LÓGICA DO BACKEND ---


// O caminho ../frontend/ é necessário porque este arquivo está na pasta 'admin'
include '../frontend/templates/header.php';
?>

<main class="main-container">
    <section id="lista-de-pacientes" class="section-container">
        <h2 class="section-title">Meus Pacientes</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">
            Esta é a sua lista central de pacientes. Clique em "Ver Ficha" para acessar o histórico completo de consultas, documentos e pagamentos.
        </p>
        
        <?php if (isset($erro_db)): ?>
            <p style="color: red; padding: 10px; border: 1px solid red; background-color: #fee2e2; border-radius: 4px;"><?php echo htmlspecialchars($erro_db); ?></p>
        <?php endif; ?>
        
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Nome Completo do Paciente</th>
                        <th>Telefone</th>
                        <th style="width: 200px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista_pacientes)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">Nenhum paciente encontrado ou erro de carregamento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lista_pacientes as $paciente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($paciente['nome']); ?></td>
                                <td><?php echo htmlspecialchars($paciente['telefone']); ?></td>
                                <td>
                                    <a href="ficha-paciente.php?id=<?php echo $paciente['id']; ?>" class="btn-tabela btn-secondary">Ver Ficha</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php
include '../frontend/templates/footer.php';
?>