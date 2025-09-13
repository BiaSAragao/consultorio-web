<?php
// backend/logout.php

// Inicia ou continua a sessão existente.
session_start();

// Limpa todas as variáveis da sessão.
$_SESSION = array();

// Destrói a sessão.
session_destroy();

// Redireciona o usuário para a página inicial ou de login.
header("Location: ../frontend/home.html");
exit();
?>