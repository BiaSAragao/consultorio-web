<?php
session_start();
require '../backend/conexao.php'; 

$titulo_pagina = 'Editar Perfil - Dentista';
$is_dashboard = true; 
$mensagem_sucesso = null;
$mensagem_erro = null;

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: login-dentista.php');
    exit;
}

$dentista_id = $_SESSION['usuario_id'];

// --- LÓGICA DE PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $cro = $_POST['cro'] ?? ''; 
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';

    try {
        // A. Processamento da Mudança de Senha
        if (!empty($nova_senha)) {
            
            // 1. Busca o HASH atual da senha
            $stmt_hash = $pdo->prepare("SELECT senha FROM Usuario WHERE usuario_id = :id");
            $stmt_hash->execute([':id' => $dentista_id]);
            $usuario = $stmt_hash->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || !password_verify($senha_atual, $usuario['senha'])) {
                $mensagem_erro = "Senha atual incorreta. A senha não foi alterada.";
                // Interrompe o processo para que o usuário insira a senha atual correta
            } else if (strlen($nova_senha) < 6) { 
                $mensagem_erro = "A nova senha deve ter pelo menos 6 caracteres.";
            } else {
                // 2. Cria o novo HASH da senha e faz o UPDATE
                $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt_update_senha = $pdo->prepare("UPDATE Usuario SET senha = :hash WHERE usuario_id = :id");
                $stmt_update_senha->execute([':hash' => $novo_hash, ':id' => $dentista_id]);
                $mensagem_sucesso = "Sua senha foi atualizada com sucesso!";
            }
        }

        // B. Processamento dos Dados Pessoais (Nome e Telefone na tabela Usuario)
        if (empty($mensagem_erro)) { // Só prossegue se não houve erro de senha
            
            $stmt_update_usuario = $pdo->prepare("UPDATE Usuario SET nome = :nome, telefone = :tel WHERE usuario_id = :id");
            $stmt_update_usuario->execute([':nome' => $nome, ':tel' => $telefone, ':id' => $dentista_id]);

            // C. Processamento do CRO (na tabela Dentista)
            $stmt_update_dentista = $pdo->prepare("UPDATE Dentista SET cro = :cro WHERE usuario_id = :id");
            $stmt_update_dentista->execute([':cro' => $cro, ':id' => $dentista_id]);

            $mensagem_sucesso = $mensagem_sucesso ? $mensagem_sucesso . " e seus dados pessoais também." : "Seus dados pessoais foram atualizados com sucesso.";
            
            // Atualiza o nome na sessão caso ele tenha sido mudado
            $_SESSION['usuario_nome'] = $nome;

        }

    } catch (PDOException $e) {
        $mensagem_erro = "Erro ao salvar: Falha na comunicação com o banco de dados.";
        // Em debug, use: $mensagem_erro = "Erro: " . $e->getMessage();
    }
}


// --- LÓGICA DE CARREGAMENTO (GET/Após POST) ---

try {
    // 2. Busca dos dados atuais do usuário (tabela Usuario)
    $sql_usuario = "SELECT nome, email, tel AS telefone FROM Usuario WHERE usuario_id = :id";
    $stmt_usuario = $pdo->prepare($sql_usuario);
    $stmt_usuario->execute([':id' => $dentista_id]);
    $dados_usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

    // 3. Busca do CRO (tabela Dentista)
    $sql_dentista = "SELECT cro FROM Dentista WHERE usuario_id = :id";
    $stmt_dentista = $pdo->prepare($sql_dentista);
    $stmt_dentista->execute([':id' => $dentista_id]);
    $dados_dentista = $stmt_dentista->fetch(PDO::FETCH_ASSOC);

    // Combina os dados
    $perfil = array_merge($dados_usuario, $dados_dentista);

} catch (PDOException $e) {
    $mensagem_erro = "Erro ao carregar dados do perfil.";
    $perfil = ['nome' => '', 'email' => '', 'telefone' => '', 'cro' => ''];
}


// Inclui o cabeçalho e HTML
include '../frontend/templates/header.php';
?>

<main class="main-container">
    <section id="edicao-perfil" class="section-container">
        <h2 class="section-title">Editar Perfil e Senha</h2>

        <?php if ($mensagem_sucesso): ?>
            <div class="alerta sucesso">
                <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alerta erro">
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <form action="editar-perfil-dentista.php" method="POST" class="form-container">
            <h3>Dados Pessoais</h3>
            
            <div class="form-group">
                <label for="nome">Nome Completo:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($perfil['nome']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email (Não Editável):</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($perfil['email']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label for="telefone">Telefone:</label>
                <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($perfil['telefone']); ?>">
            </div>

            <div class="form-group">
                <label for="cro">CRO:</label>
                <input type="text" id="cro" name="cro" value="<?php echo htmlspecialchars($perfil['cro']); ?>">
            </div>

            <hr style="margin: 2rem 0;">

            <h3>Alterar Senha (Opcional)</h3>
            <p style="color: #6b7280; margin-bottom: 1rem;">Preencha os campos abaixo SOMENTE se desejar alterar sua senha.</p>
            
            <div class="form-group">
                <label for="senha_atual">Senha Atual:</label>
                <input type="password" id="senha_atual" name="senha_atual" placeholder="Necessária para confirmar a mudança">
            </div>
            
            <div class="form-group">
                <label for="nova_senha">Nova Senha:</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 6 caracteres">
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 1.5rem;">Salvar Alterações</button>
            <a href="dashboard-dentista.php" class="btn-secondary" style="margin-left: 10px;">Voltar ao Dashboard</a>

        </form>
    </section>
</main>

<style>
    /* Estilos básicos para os alertas */
    .alerta {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        font-weight: bold;
    }
    .sucesso {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .erro {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .form-container { max-width: 700px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
</style>

<?php
include '../frontend/templates/footer.php';
?>