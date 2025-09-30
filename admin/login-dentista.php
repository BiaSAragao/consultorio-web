<?php
// admin/login-dentista.php

session_start(); // Inicia a sessão

$erro = null; // Variável para a mensagem de erro

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    require '../backend/conexao.php'; 

    $email = $_POST['email'];
    $senha = $_POST['senha']; // Senha em texto puro, digitada pelo usuário

    try {
        // 1. Verifica se o usuário existe e OBTÉM o HASH da senha salva
        // Selecionamos a coluna 'senha' para comparação
        $stmt = $pdo->prepare("SELECT usuario_id, nome, senha FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // ** ALTERAÇÃO PRINCIPAL AQUI: COMPARANDO COM HASH USANDO password_verify() **
        // Se o usuário existe E a senha digitada CORRESPONDE ao hash salvo no banco:
        // A função password_verify() é segura e compara a senha digitada com o hash.
        if ($usuario && password_verify($senha, $usuario['senha'])) { 
            $usuario_id = $usuario['usuario_id'];

            // 2. Verifica se este usuário é um dentista
            $stmt = $pdo->prepare("SELECT usuario_id FROM dentista WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $dentista = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dentista) {
                // ✅ É dentista, faz login
                // ATENÇÃO: Os nomes das variáveis de sessão devem ser 'usuario_id' e 'tipo_usuario' 
                // para funcionar com o dashboard-dentista.php, que você me mostrou anteriormente.
                $_SESSION['usuario_id'] = $usuario['usuario_id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['tipo_usuario'] = 'dentista'; // Necessário para o controle de acesso no dashboard

                header("Location: dashboard-dentista.php");
                exit();
            } else {
                $erro = "Este usuário não é um dentista.";
            }
        } else {
            // A falha ocorre se o usuário não existir OU se a senha digitada não for correspondente ao hash.
            $erro = "E-mail ou senha inválidos.";
        }

    } catch (PDOException $e) {
        // Se a conexão ainda falhar (problema no conexao.php), a exceção é capturada.
        $erro = "Falha de comunicação com o sistema.";
        // Dica de Debug: Altere temporariamente para $erro = "Erro: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Dentista</title>
    <link rel="stylesheet" href="../frontend/css/logind.css" />
</head>
<body>
    <main class="main-container">
        <section class="section-container">
            <form id="form-login-dentista" action="login-dentista.php" method="post" class="form-container">
                <div id="mensagem-erro-login-dentista" class="erro-js" style="display: none;"></div>
                <h2 class="section-title-alt section-header-center">Acesso do Dentista</h2>
                <p class="section-text-alt">Por favor, faça login com suas credenciais.</p>

                <?php if (!empty($erro)) { echo "<p class='erro'>$erro</p>"; } ?>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="senha" required>
                </div>

                <button type="submit" class="btn-primary">Entrar</button>
            </form>
        </section>
    </main>
    <script src="../frontend/script.js"></script>
</body>
</html>