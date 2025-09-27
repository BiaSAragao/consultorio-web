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

$titulo_pagina = 'Ficha do Paciente - SmileUp';
$is_dashboard = true; // Para carregar o CSS do painel

include '../frontend/templates/header.php';

// --- LÓGICA DO BACKEND (Busca no Banco de Dados) ---

// 1. Pega o ID da URL e valida para segurança
$id_paciente = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_paciente) {
    // Se não houver um ID válido, mostra um erro e para a execução
    echo "<main class='main-container'><p class='section-container'>Erro: ID do paciente não fornecido ou inválido.</p></main>";
    include '../frontend/templates/footer.php';
    exit(); // Para o script aqui
}

/*
 * ===================================================================
 * GUIA PARA A DUPLA DO BANCO DE DADOS
 * ===================================================================
 * A query principal agora deve buscar também os novos campos:
 * $stmt = $pdo->prepare("SELECT nome, telefone, email, cpf, plano_saude, data_nascimento FROM pacientes WHERE id = ?");
 * $stmt->execute([$id_paciente]);
 * $dados_paciente = $stmt->fetch(PDO::FETCH_ASSOC);
 * */

// 3. DADOS FICTÍCIOS - ATUALIZADOS COM OS NOVOS CAMPOS
$dados_paciente = [
    'id' => $id_paciente, 
    'nome' => 'Maria Oliveira da Silva', 
    'telefone' => '(75) 99999-8888', 
    'email' => 'maria.oliveira@email.com',
    'cpf' => '123.456.789-00',
    'plano_saude' => 'OdontoPlus',
    'data_nascimento' => '1990-05-15' // Formato AAAA-MM-DD para calcular a idade
];

// Lógica para calcular a idade a partir da data de nascimento
$nascimento = new DateTime($dados_paciente['data_nascimento']);
$hoje = new DateTime();
$idade = $nascimento->diff($hoje)->y;


// (Dados fictícios para histórico de consultas e laudos)
$historico_consultas = [
    ['data' => '2025-09-09', 'procedimento' => 'Limpeza e Prevenção', 'status' => 'Realizado'],
    ['data' => '2025-03-15', 'procedimento' => 'Avaliação Inicial', 'status' => 'Realizado'],
];
$lista_laudos = [
    ['id' => 1, 'nome_arquivo' => 'Raio-X_Panoramico_2025.pdf', 'data_upload' => '2025-03-15'],
];
// MUDANÇA: O histórico financeiro fictício foi removido.

// Se o paciente não foi encontrado no banco, $dados_paciente estaria vazio
if (empty($dados_paciente)) {
     echo "<main class='main-container'><p class='section-container'>Paciente com ID $id_paciente não encontrado.</p></main>";
    include '../frontend/templates/footer.php';
    exit();
}
// --- FIM DA LÓGICA DO BACKEND ---
?>

<main class="main-container">

  <section id="dados-pessoais" class="section-container">
    <h2 class="section-title">Ficha Completa de - <?php echo htmlspecialchars($dados_paciente['nome']); ?></h2>
    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($dados_paciente['telefone']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($dados_paciente['email']); ?></p>
        <p><strong>CPF:</strong> <?php echo htmlspecialchars($dados_paciente['cpf']); ?></p>
        <p><strong>Plano de Saúde:</strong> <?php echo htmlspecialchars($dados_paciente['plano_saude']); ?></p>
         <p><strong>Idade:</strong> <?php echo $idade; ?> anos</p>
    </div>
</section>

    <section id="historico-consultas" class="section-container">
        <h3 class="section-title">Histórico de Consultas</h3>
        <table class="tabela-consultas">
            <thead><tr><th>Data</th><th>Procedimento</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($historico_consultas as $consulta): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($consulta['data'])); ?></td>
                    <td><?php echo htmlspecialchars($consulta['procedimento']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($consulta['status'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="laudos" class="section-container">
        <h3 class="section-title">Laudos e Documentos</h3>
        <table class="tabela-consultas">
              <thead><tr><th>Nome do Arquivo</th><th>Data de Upload</th><th>Ação</th></tr></thead>
              <tbody>
                <?php
                // --- LÓGICA REAL PARA BUSCAR OS LAUDOS DO BANCO ---
                /*
                 * Substitua a tabela 'laudos' e as colunas pelos nomes corretos do seu banco.
                */
                // $stmt_laudos = $pdo->prepare("SELECT id_laudo, nome_arquivo_original, data_upload FROM laudos WHERE id_paciente = ? ORDER BY data_upload DESC");
                // $stmt_laudos->execute([$id_paciente]);
                // $lista_laudos = $stmt_laudos->fetchAll(PDO::FETCH_ASSOC);

                // USANDO DADOS FICTÍCIOS POR ENQUANTO (COMENTE A LÓGICA ACIMA)
                $lista_laudos = [
                    ['id_laudo' => 1, 'nome_arquivo_original' => 'Raio-X_Panoramico_2025.pdf', 'data_upload' => '2025-03-15'],
                    ['id_laudo' => 2, 'nome_arquivo_original' => 'Exame_de_Sangue.jpg', 'data_upload' => '2025-04-01'],
                ];
                
                if (empty($lista_laudos)): ?>
                    <tr><td colspan="3">Nenhum documento encontrado para este paciente.</td></tr>
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