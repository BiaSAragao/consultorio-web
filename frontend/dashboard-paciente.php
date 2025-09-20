<?php
// frontend/dashboard-paciente.php

// Continua a sessão que foi iniciada no login.
session_start();

// --- BLOCO DE SEGURANÇA ---
// Verifica se a variável de sessão 'usuario_id' NÃO existe.
// Se não existir, significa que o usuário não fez login.
if (!isset($_SESSION['usuario_id'])) {
    // Redireciona o usuário para a página de login.
    header("Location: login.html");
    exit(); // Garante que o script pare de ser executado.
}
// --- FIM DO BLOCO DE SEGURANÇA ---

// Se o script chegou até aqui, o usuário está logado.
// Podemos pegar os dados da sessão para personalizar a página.
$nome_paciente = $_SESSION['usuario_nome'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel - <?php echo htmlspecialchars($nome_paciente); ?></title>
    <link rel="stylesheet" href="home.css"> </head>
<body>

    <header class="header">
        <h1 class="logo">Bem-vindo(a), <?php echo htmlspecialchars($nome_paciente); ?>!</h1>
        <nav class="nav">
            <ul class="nav-list">
                <li><a href="#" class="nav-link">Minhas Consultas</a></li>
                <li><a href="#" class="agendar_consulta.php">Agendar Consulta</a></li>
                <li><a href="../backend/logout.php" class="nav-link">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-container">
        <section class="section-container">
            <h2>Suas Próximas Consultas</h2>
            <p>
                Você ainda não tem consultas agendadas.
            </p>
        </section>

         <section class="section-container">
            <h2>Agendar Nova Consulta</h2>
            <form action="../backend/agendar_consulta.php" method="POST">
                <button type="submit" class="btn-primary">Agendar</button>
            </form>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2025 Clínica Odontológica. Todos os direitos reservados.</p>
    </footer>

</body>
</html>