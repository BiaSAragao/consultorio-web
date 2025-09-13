<?php
// frontend/login.php

// Inicia a sessão. É essencial para guardar a informação de que o usuário está logado.
session_start();

// Inicia a variável de erro como nula. Ela só terá valor se o login falhar.
$erro = null; 

// PASSO 1: VERIFICAR SE O FORMULÁRIO FOI ENVIADO
// O código dentro deste 'if' só executa quando o usuário clica no botão "Entrar".
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Inclui o arquivo de conexão com o banco de dados
    require '../backend/conexao.php'; 

    // Pega o email e a senha que o usuário digitou no formulário
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    try {
        // PASSO 2: BUSCAR O USUÁRIO NO BANCO DE DADOS
        // Prepara a consulta SQL para encontrar um paciente com o email fornecido
        $stmt = $pdo->prepare("SELECT id, nome, senha FROM pacientes WHERE email = ?");
        $stmt->execute([$email]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

        // PASSO 3: VERIFICAR A SENHA E REDIRECIONAR
        // Se encontrou um paciente E a senha digitada corresponde à senha criptografada no banco...
        if ($paciente && password_verify($senha, $paciente['senha'])) {
            
            // Sucesso! Guarda os dados do paciente na sessão (a "pulseira VIP")
            $_SESSION['usuario_id'] = $paciente['id'];
            $_SESSION['usuario_nome'] = $paciente['nome'];
            
            // Redireciona o usuário para o seu painel protegido
            header("Location: dashboard-paciente.php");
            exit(); // Encerra o script aqui para garantir o redirecionamento

        } else {
            // Se o usuário não foi encontrado ou a senha está errada...
            // Define a mensagem de erro que será exibida no formulário.
            $erro = "E-mail ou senha inválidos. Por favor, tente novamente.";
        }

    } catch (PDOException $e) {
        // Se houver um erro de conexão com o banco, define uma mensagem de erro.
        $erro = "Ocorreu uma falha no sistema. Tente mais tarde.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - SmileUp</title>
    <link rel="stylesheet" href="home.css">
    <style>
        /* Você pode colocar este estilo no seu arquivo styles.css */
        .erro {
            color: #D8000C;
            background-color: #FFBABA;
            border: 1px solid #D8000C;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <form id="form-login-paciente" action="login.php" method="POST">
        <h1>Faça seu login</h1>

        <div id="mensagem-erro-login-paciente" class="erro-js" style="display: none;"></div>


        <?php
        // PASSO 4: EXIBIR A MENSAGEM DE ERRO (SE ELA EXISTIR)
        // Este pequeno bloco PHP verifica se a variável $erro tem algum conteúdo.
        // Se tiver, ele exibe a mensagem de erro para o usuário.
        if (isset($erro)) {
            echo "<p class='erro'>$erro</p>";
        }
        ?>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br><br>

        <label for="senha">Senha:</label>
        <input type="password" id="senha" name="senha" required><br><br>

        <button type="submit">Entrar</button>

        <p class="cadastro-link">
            Ainda não tem conta? <a href="cadastro.php">Clique aqui para cadastrar</a>
        </p>
    </form>
</body>
</html>