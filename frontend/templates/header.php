<?php
// Inicia a sessão em todas as páginas que incluírem este header.
// A @ suprime o aviso caso a sessão já tenha sido iniciada na página principal.
@session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina : 'SmileUp Odontologia'; ?></title>
    
    <link rel="stylesheet" href="css/home.css">
    
    <?php if (isset($is_dashboard) && $is_dashboard): ?>
        <link rel="stylesheet" href="css/dashboard.css">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <header class="header">
        <a href="home.html" class="logo" style="text-decoration: none;">SmileUp</a>
        <nav class="nav">
            <ul class="nav-list">
                <?php
                // Verifica se o usuário está LOGADO
                if (isset($_SESSION['usuario_id'])):
                    $nome_paciente = $_SESSION['usuario_nome'];
                ?>
                    <li><a href="dashboard-paciente.php#consultas" class="nav-link">Minhas Consultas</a></li>
                    <li><a href="agendar_consulta.php" class="nav-link">Agendar</a></li>
                    <li><a href="dashboard-paciente.php#laudos" class="nav-link">Meus Laudos</a></li>
                    <li><a href="editar-perfil.php" class="nav-link">Olá, <?php echo htmlspecialchars(explode(' ', $nome_paciente)[0]); ?>!</a></li>
                    <li><a href="../backend/logout.php" class="nav-link btn-secondary" style="padding: 0.5rem 1rem; border-radius: 9999px;">Sair</a></li>
                <?php else: ?>
                    <li><a href="home.htmp#home" class="nav-link">Home</a></li>
                    <li><a href="home.html#servicos" class="nav-link">Serviços</a></li>
                    <li><a href="home.html#contatos" class="nav-link">Contatos</a></li>
                    <li><a href="../admin/login-dentista.php" class="nav-link">Sou Dentista</a></li>
                    <li><a href="login.php" class="nav-link btn-primary" style="padding: 0.5rem 1rem; border-radius: 9999px; color: #fff;">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <button class="menu-button">&#9776;</button> </header>