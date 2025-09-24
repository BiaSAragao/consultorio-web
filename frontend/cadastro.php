<?php
require '../backend/conexao.php';
require '../backend/validacao_cadastro.php'; // funções de validação: validaCPF, validaEmail, validaSenha, validaDataNascimento

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $telefone = $_POST['telefone'];
    $cpf = $_POST['cpf'];
    $plano = $_POST['plano'];
    $data_nascimento = $_POST['data_nascimento'];

    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Validações
    if (!validaEmail($email)) {
        $erro = "Email inválido.";
    } elseif (!validaSenha($senha)) {
        $erro = "Senha fraca. Use ao menos 8 caracteres, com letra maiúscula e número.";
    } elseif (!validaCPF($cpf)) {
        $erro = "CPF inválido.";
    } elseif (!validaDataNascimento($data_nascimento)) {
        $erro = "Data de nascimento inválida.";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT usuario_id FROM usuario WHERE email = ?");
            $stmt_check->execute([$email]);

            if ($stmt_check->rowCount() > 0) {
                $erro = "Este e-mail já está cadastrado.";
            } else {
                // Inserção na tabela usuario
                $stmt_usuario = $pdo->prepare(
                    "INSERT INTO usuario (nome, email, senha, tel) VALUES (?, ?, ?, ?)"
                );
                $stmt_usuario->execute([$nome, $email, $senha_hash, $telefone]);

                $usuario_id = $pdo->lastInsertId();

                // Inserção na tabela paciente
                $stmt_paciente = $pdo->prepare(
                    "INSERT INTO paciente (usuario_id, cpf, plano, data_nascimento) VALUES (?, ?, ?, ?)"
                );
                $stmt_paciente->execute([$usuario_id, $cpf, $plano, $data_nascimento]);

                header("Location: login.php?sucesso=cadastro_ok");
                exit();
            }
        } catch (PDOException $e) {
            $erro = "Erro ao conectar com o banco de dados: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cadastro - SmileUp</title>
        <link rel="stylesheet" href="css/cadastro.css">
        <style>
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
        <form id="form-cadastro" action="" method="POST">
            <h1>Faça seu cadastro</h1>

            <?php if ($erro) echo "<div class='erro-js'>$erro</div>"; ?>

            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required><br><br>

            <label for="telefone">Telefone:</label>
            <input type="tel" id="telefone" name="telefone"><br><br>

            <label for="cpf">CPF:</label>
            <input type="text" id="cpf" name="cpf" required><br><br>

            <label for="plano">Plano de Saúde:</label>
            <input type="text" id="plano" name="plano"><br><br>

            <label for="data_nascimento">Data de Nascimento:</label>
            <input type="date" id="data_nascimento" name="data_nascimento" required><br><br>

            <button type="submit">Cadastrar</button>

            <p class="login-link">
                Já tem conta? <a href="login.php">Clique aqui para fazer login</a>
            </p>
        </form>
    </body>
</html>
