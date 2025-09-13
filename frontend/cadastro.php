<?php
// frontend/cadastro.php

// --- Início do Bloco de Processamento PHP ---

// Verifica se o formulário foi enviado (se a requisição é do tipo POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Inclui o arquivo de conexão com o banco de dados
    require '../backend/conexao.php';

    // Pega os dados do formulário
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $telefone = $_POST['telefone'];

    // Criptografa a senha para segurança
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    try {
        // Verifica se o e-mail já está em uso
        $stmt_check = $pdo->prepare("SELECT id FROM pacientes WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->rowCount() > 0) {
            $erro = "Este e-mail já está cadastrado.";
        } else {
            // Insere o novo usuário no banco de dados
            $stmt = $pdo->prepare(
                "INSERT INTO pacientes (nome, email, senha, telefone) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$nome, $email, $senha_hash, $telefone]);

            // Se deu tudo certo, redireciona para o login com mensagem de sucesso
            header("Location: login.html?sucesso=cadastro_ok");
            exit();
        }
    } catch (PDOException $e) {
        $erro = "Erro ao conectar com o banco de dados: " . $e->getMessage();
    }
}
// --- Fim do Bloco de Processamento PHP ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cadastro - SmileUp</title>
        <link rel="stylesheet" href="home.css">
        <style>
            /* Adicione este estilo para a mensagem de erro */
            .erro-js {
                color: red;
                background-color: #ffebee;
                border: 1px solid red;
                padding: 10px;
                border-radius: 5px;
                text-align: center;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <form action="cadastro.php" method="POST">
            <h1>Faça seu cadastro</h1>
            <div id="mensagem-erro" class="erro-js" style="display: none;"></div>

            <?php
            // Este bloco PHP agora vai funcionar!
            // Se a variável $erro foi definida lá em cima, ela será exibida aqui.
            if (isset($erro)) {
                echo "<p class='erro'>$erro</p>";
            }
            ?>

            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required><br><br>

            <label for="telefone">Telefone:</label>
            <input type="tel" id="telefone" name="telefone"><br><br>

            <button type="submit">Cadastrar</button>

            <p class="login-link">
                Já tem conta? <a href="login.php">Clique aqui para fazer login</a>
            </p>
        </form>
        <script src="script.js"></script>
    </body>
</html>