// frontend/script.js

// Espera o HTML da página ser completamente carregado para então executar o script.
document.addEventListener('DOMContentLoaded', function() {

    // Seleciona o formulário de cadastro pelo ID que vamos adicionar a ele.
    const formCadastro = document.getElementById('form-cadastro');

    // Se o formulário de cadastro existir nesta página, executa a validação.
    if (formCadastro) {
        
        // Adiciona um "escutador" para o evento de 'submit' (quando o usuário clica no botão para enviar).
        formCadastro.addEventListener('submit', function(event) {
            
            // Pega os campos do formulário pelos seus IDs.
            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value.trim();
            
            // Seleciona o local onde vamos exibir a mensagem de erro.
            const mensagemErro = document.getElementById('mensagem-erro');
            
            // Verifica se algum dos campos está vazio.
            if (nome === '' || email === '' || senha === '') {
                // Impede o envio do formulário para o servidor.
                event.preventDefault();
                
                // Exibe a mensagem de erro.
                mensagemErro.textContent = 'Por favor, preencha todos os campos obrigatórios.';
                mensagemErro.style.display = 'block'; // Torna a mensagem visível
            } else {
                // Se todos os campos estiverem preenchidos, esconde qualquer mensagem de erro antiga.
                mensagemErro.style.display = 'none';
            }
        });
    }
    
     // --- NOVO CÓDIGO: LÓGICA PARA O LOGIN DO PACIENTE ---
    const formLoginPaciente = document.getElementById('form-login-paciente');
    if (formLoginPaciente) {
        formLoginPaciente.addEventListener('submit', function(event) {
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value.trim();
            const mensagemErro = document.getElementById('mensagem-erro-login-paciente');

            if (email === '' || senha === '') {
                event.preventDefault();
                mensagemErro.textContent = 'Email e senha são obrigatórios.';
                mensagemErro.style.display = 'block';
            } else {
                mensagemErro.style.display = 'none';
            }
        });
    }

    // --- NOVO CÓDIGO: LÓGICA PARA O LOGIN DO DENTISTA ---
    const formLoginDentista = document.getElementById('form-login-dentista');
    if (formLoginDentista) {
        formLoginDentista.addEventListener('submit', function(event) {
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value.trim();
            const mensagemErro = document.getElementById('mensagem-erro-login-dentista');

            if (email === '' || senha === '') {
                event.preventDefault();
                mensagemErro.textContent = 'Email e senha são obrigatórios.';
                mensagemErro.style.display = 'block';
            } else {
                mensagemErro.style.display = 'none';
            }
        });
    }

});

