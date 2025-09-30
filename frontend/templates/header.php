<?php
@session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina : 'SmileUp Odontologia'; ?></title>
    
    <link rel="stylesheet" href="/frontend/css/home.css">
    
    <?php if (isset($is_dashboard) && $is_dashboard): ?>
        <link rel="stylesheet" href="/frontend/css/dashboard.css">
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
                // Verifica se há um usuário logado
                if (isset($_SESSION['usuario_id'])):
                    $nome_usuario = $_SESSION['usuario_nome'];
                    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'paciente'; // Padrão é paciente

                    // --- MENU DO DENTISTA ---
                    if ($tipo_usuario === 'dentista'):
                ?>
                        <li><a href="/dashboard-dentista.php#agenda-do-dia" class="nav-link">Minha Agenda</a></li>
                        <li><a href="/dashboard-dentista.php#meus-horarios" class="nav-link">Gerenciar Horários</a></li>
                        <li><a href="/meus-pacientes.php" class="nav-link">Meus Pacientes</a></li>
                        <li><a href="/dashboard-dentista.php#estoque" class="nav-link">Estoque </a></li>
                        <li><a href="/editar-perfil-dentista.php" class="nav-link">Editar Perfil</a></li>
                        <li><a href="../backend/logout.php" class="nav-link btn-secondary" style="padding: 0.5rem 1rem; border-radius: 9999px;">Sair</a></li>

                <?php
                    // --- MENU DO PACIENTE ---
                    else:
                ?>
                        <li><a href="dashboard-paciente.php#consultas" class="nav-link">Minhas Consultas</a></li>
                        <li><a href="agendar_consulta.php" class="nav-link">Agendar</a></li>
                        <li><a href="dashboard-paciente.php#laudos" class="nav-link">Meus Laudos</a></li>
                        <li><a href="perfil-paciente.php" class="nav-link">Ver Perfil</a></li>
                        <li><a href=" " class="nav-link">Olá, <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?>!</a></li>
                        <li><a href="../backend/logout.php" class="nav-link btn-secondary" style="padding: 0.5rem 1rem; border-radius: 9999px;">Sair</a></li>

                <?php
                    endif;
                // --- MENU PARA VISITANTES (NÃO LOGADOS) ---
                else:
                ?>
                    <li><a href="home.html#home" class="nav-link">Home</a></li>
                    <li><a href="home.html#servicos" class="nav-link">Serviços</a></li>
                    <li><a href="home.html#contatos" class="nav-link">Contatos</a></li>
                    <li><a href="../admin/login-dentista.php" class="nav-link">Sou Dentista</a></li>
                    <li><a href="login.php" class="nav-link btn-primary" style="padding: 0.5rem 1rem; border-radius: 9999px; color: #fff;">Login</a></li>
                <?php 
                endif; 
                ?>
            </ul>
        </nav>
        <button class="menu-button">&#9776;</button>
    </header>