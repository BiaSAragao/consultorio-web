<?php
// frontend/login.php

session_start(); // Inicia a sessão
$erro = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require '../backend/conexao.php'; // Conexão com o banco

    $email = $_POST['email'];
    $senha = $_POST['senha'];

    try {
        // Buscar o usuário na tabela 'usuario'
        $stmt = $pdo->prepare("SELECT usuario_id, nome, senha FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['usuario_id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['tipo_usuario'] = 'paciente';

            header("Location: dashboard-paciente.php");
            exit();
        } else {
            $erro = "E-mail ou senha inválidos. Por favor, tente novamente.";
        }

    } catch (PDOException $e) {
        $erro = "Ocorreu uma falha no sistema. Tente mais tarde.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - SmileUp</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
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

        <?php
        if ($erro) {
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
