<?php
// --- TRUQUE TEMPORÁRIO PARA VISUALIZAÇÃO ---
session_start();
// Simulamos um DENTISTA logado para a página funcionar
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 101; 
    $_SESSION['usuario_nome'] = "Dr. Carlos Moura";
    $_SESSION['tipo_usuario'] = 'dentista';
}
// --- FIM DO TRUQUE ---

$titulo_pagina = 'Meus Pacientes - SmileUp';
$is_dashboard = true; // Para carregar o CSS do painel

// O caminho ../frontend/ é necessário porque este arquivo está na pasta 'admin'
include '../frontend/templates/header.php';

// --- LÓGICA DO BACKEND (Busca no Banco de Dados) ---

// 1. Pegar o ID do dentista da sessão
$id_dentista = $_SESSION['usuario_id'];

/*
 * ===================================================================
 * PARTE A SER SUBSTITUÍDA PELA SUA LÓGICA DE BANCO DE DADOS
 * ===================================================================
 * * Aqui você faria a conexão com o banco e executaria uma query como:
 * * include '../backend/conexao.php'; // Seu arquivo de conexão
 * $pdo = conectar();
 * $stmt = $pdo->prepare("SELECT id, nome, telefone FROM pacientes WHERE id_dentista_responsavel = ? ORDER BY nome");
 * $stmt->execute([$id_dentista]);
 * $lista_pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 * */

// 2. DADOS FICTÍCIOS (PARA VISUALIZAÇÃO ATÉ O BANCO ESTAR PRONTO)
// Apague esta parte quando for conectar ao banco de dados real.
$lista_pacientes = [
    ['id' => 123, 'nome' => 'Maria Oliveira da Silva', 'telefone' => '(75) 99999-8888'],
    ['id' => 124, 'nome' => 'João Santos de Jesus', 'telefone' => '(75) 98888-7777'],
    ['id' => 125, 'nome' => 'Ana Souza Costa', 'telefone' => '(75) 99111-2222'],
    ['id' => 126, 'nome' => 'Pedro Alves Pereira', 'telefone' => '(75) 99222-3333'],
];
// --- FIM DA LÓGICA DO BACKEND ---
?>

<main class="main-container">
    <section id="lista-de-pacientes" class="section-container">
        <h2 class="section-title">Meus Pacientes</h2>
        <p style="margin-bottom: 1.5rem; color: #6b7280;">
            Esta é a sua lista central de pacientes. Clique em "Ver Ficha" para acessar o histórico completo de consultas, documentos e pagamentos.
        </p>
        
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
                            <td colspan="3" style="text-align: center;">Nenhum paciente encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lista_pacientes as $paciente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($paciente['nome']); ?></td>
                                <td><?php echo htmlspecialchars($paciente['telefone']); ?></td>
                                <td>
                                    <a href="ficha-paciente.php?id=<?php echo $paciente['id']; ?>" class="btn-tabela btn-primary">
                                        Ver Ficha
                                    </a>
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
// O caminho ../frontend/ é necessário porque este arquivo está na pasta 'admin'
include '../frontend/templates/footer.php';
?>