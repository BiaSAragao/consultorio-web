<?php
// admin/login-dentista.php

session_start(); // Inicia a sessão

$erro = null; // Variável para a mensagem de erro

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Usa o '../' para voltar uma pasta e encontrar o backend
    require '../backend/conexao.php'; 

    $email = $_POST['email'];
    $senha = $_POST['senha'];

    try {
        // Busca na tabela 'dentistas'
        $stmt = $pdo->prepare("SELECT id, nome, senha FROM dentistas WHERE email = ?");
        $stmt->execute([$email]);
        $dentista = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se encontrou o dentista E a senha está correta
        if ($dentista && password_verify($senha, $dentista['senha'])) {
            
            // Sucesso! Guarda os dados na sessão de admin
            $_SESSION['admin_id'] = $dentista['id'];
            $_SESSION['admin_nome'] = $dentista['nome'];
            
            // Redireciona para o painel principal do admin
            header("Location: dashboard-dentista.php");
            exit();

        } else {
            // Se falhar, define a mensagem de erro
            $erro = "E-mail ou senha de administrador inválidos.";
        }

    } catch (PDOException $e) {
        $erro = "Falha de comunicação com o sistema.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Login - Dentista</title>
        <link rel="stylesheet" href="../frontend/home.css" />
        <style>
             .erro { color: #D8000C; background-color: #FFBABA; border: 1px solid; margin: 10px 0px; padding: 15px; border-radius: 5px; text-align: center; }
        </style>
    </head>
    <body>
        <main class="main-container">
        <section class="section-container">
            <form id="form-login-dentista" action="login-dentista.php" method="post" class="form-container">
                
                <div id="mensagem-erro-login-dentista" class="erro-js" style="display: none;"></div>
                <h2 class="section-title-alt section-header-center">Acesso do Dentista</h2>
                <p class="section-text-alt">
                    Por favor, faça login com suas credenciais.
                </p>

                <form action="login-dentista.php" method="post" class="form-container">
                    
                    <?php
                    // Exibe a mensagem de erro, se ela existir
                    if (isset($erro)) {
                        echo "<p class='erro'>$erro</p>";
                    }
                    ?>
                    
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