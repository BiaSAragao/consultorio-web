<?php
// Inicia a sessão para poder exibir mensagens de sucesso ou erro que vêm do backend
session_start();

// Define o título da página que aparecerá na aba do navegador
$titulo_pagina = 'Fale Conosco - SmileUp';

// Inclui o cabeçalho padrão do seu site (com o menu de navegação)
// O caminho é 'templates/header.php' porque o arquivo contato.php está na mesma pasta (frontend) que a pasta 'templates'
include 'templates/header.php';
?>

<main class="main-container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    
    <section style="width: 100%; max-width: 500px;">
        
        <form id="form-contato" action="../backend/enviar-contato.php" method="POST" style="background-color: #fff; padding: 2.5rem; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            
            <h1 style="font-size: 2rem; font-weight: 700; color: #c69963; text-align: center; margin-top: 0; margin-bottom: 1.5rem;">Fale Conosco</h1>
            <p style="text-align: center; color: #4b5563; margin-bottom: 2rem;">Tem alguma dúvida ou sugestão? Envie uma mensagem para nós!</p>

            <?php
            // Bloco PHP para exibir a mensagem de SUCESSO se ela existir
            if (isset($_SESSION['mensagem_sucesso'])) {
                echo "<p style='color: #155724; background-color: #D4EDDA; padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center;'>" . $_SESSION['mensagem_sucesso'] . "</p>";
                unset($_SESSION['mensagem_sucesso']); // Limpa a mensagem para não aparecer de novo
            }

            // Bloco PHP para exibir a mensagem de ERRO se ela existir
            if (isset($_SESSION['mensagem_erro'])) {
                echo "<p style='color: #D8000C; background-color: #FFBABA; padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center;'>" . $_SESSION['mensagem_erro'] . "</p>";
                unset($_SESSION['mensagem_erro']); // Limpa a mensagem
            }
            ?>

            <label for="nome" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Seu Nome:</label>
            <input type="text" id="nome" name="nome" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 1rem;">

            <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Seu Email:</label>
            <input type="email" id="email" name="email" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 1rem;">
            
            <label for="assunto" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Assunto:</label>
            <input type="text" id="assunto" name="assunto" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 1rem;">

            <label for="mensagem" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Mensagem:</label>
            <textarea id="mensagem" name="mensagem" rows="5" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 1rem;"></textarea>

            <button type="submit" style="width: 100%; padding: 0.875rem; border: none; border-radius: 9999px; background-color: #cab078; color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer;">Enviar Mensagem</button>
        </form>
    </section>
</main>

<?php
// Inclui o rodapé padrão do seu site
include 'templates/footer.php';
?>