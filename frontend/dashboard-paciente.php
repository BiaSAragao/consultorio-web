<?php
// --- TRUQUE TEMPORÁRIO PARA VISUALIZAÇÃO DO PACIENTE ---
session_start();
// Simulamos um usuário logado para a página não te redirecionar
$_SESSION['usuario_id'] = 1; // Um ID de teste qualquer
$_SESSION['usuario_nome'] = "Paciente Teste"; // Um nome de teste para aparecer no cabeçalho
$_SESSION['tipo_usuario'] = 'paciente'; // <-- MUDE AQUI para 'paciente'

// --- FIM DO TRUQUE ---

$titulo_pagina = 'Meu Painel - SmileUp';
$is_dashboard = true;

// A linha abaixo já carrega todo o cabeçalho, menu e os links de CSS
include 'templates/header.php';
?>

<main class="main-container">

    <section id="consultas" class="section-container">
        <h2 class="section-title">Suas Próximas Consultas</h2>
        
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Especialidade</th>
                        <th>Profissional</th>
                        <th>Data / Hora</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Limpeza e Prevenção</td>
                        <td>Dr. Ana Costa</td>
                        <td>25/09/2025 às 14:00</td>
                        <td><span class="status status-confirmada">Confirmada</span></td>
                        <td>
                            <button class="btn-tabela btn-secondary">Reagendar</button>
                            <button class="btn-tabela btn-cancelar">Cancelar</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Ortodontia</td>
                        <td>Dr. Carlos Moura</td>
                        <td>15/10/2025 às 10:30</td>
                        <td><span class="status status-pendente">Pendente</span></td>
                        <td>
                            <button class="btn-tabela btn-secondary">Reagendar</button>
                            <button class="btn-tabela btn-cancelar">Cancelar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="agendar" class="section-container">
        <h2 class="section-title">Agendar Nova Consulta</h2>
        
        <form action="../backend/agendar_consulta.php" method="POST" id="form-agendamento">

            <div id="passo-1" class="passo-agendamento">
                <div class="form-group">
                    <label for="especialidade">Primeiro, selecione o serviço que deseja agendar:</label>
                    <select id="especialidade" name="especialidade" required>
                        <option value="" disabled selected>Selecione um serviço...</option>
                        <option value="limpeza">Limpeza e Prevenção</option>
                        <option value="estetica">Estética Dental</option>
                        <option value="ortodontia">Ortodontia</option>
                        <option value="endodontia">Endodontia (Canal)</option>
                        <option value="implantes">Implantes Dentários</option>
                    </select>
                </div>
                <div class="botoes-navegacao">
                    <button type="button" class="btn-primary" onclick="irParaPasso(2)">Continuar</button>
                </div>
            </div>

            <div id="passo-2" class="passo-agendamento" style="display: none;">
                <div class="form-group">
                    <label for="dentista">Ótimo! Agora, escolha o profissional:</label>
                    <select id="dentista" name="dentista" required>
                        <option value="">Escolha uma especialidade primeiro...</option>
                    </select>
                </div>
                <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(1)">Voltar</button>
                    <button type="button" class="btn-primary" onclick="irParaPasso(3)">Continuar</button>
                </div>
            </div>

            <div id="passo-3" class="passo-agendamento" style="display: none;">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Selecione a data</label>
                        <input type="date" id="data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label>Horários Disponíveis para a data</label>
                        <div class="horarios-disponiveis" id="lista-horarios">
                            <p>Selecione uma data para ver os horários.</p>
                        </div>
                        <input type="hidden" id="horario_selecionado" name="horario_selecionado" required>
                    </div>
                </div>
                 <div class="botoes-navegacao">
                    <button type="button" class="btn-secondary" onclick="irParaPasso(2)">Voltar</button>
                    <button type="submit" class="btn-primary">Confirmar Agendamento</button>
                </div>
            </div>

        </form>
    </section>

    <section id="laudos" class="section-container">
        <h2 class="section-title">Meus Laudos e Documentos</h2>
        <div style="overflow-x:auto;">
            <table class="tabela-consultas">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Data de Upload</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Raio-X Panorâmico.pdf</td>
                        <td>15/08/2025</td>
                        <td><a href="#" class="btn-primary" style="text-decoration: none;">Baixar</a></td>
                    </tr>
                    <tr>
                        <td>Orçamento Tratamento.pdf</td>
                        <td>10/08/2025</td>
                        <td><a href="#" class="btn-primary" style="text-decoration: none;">Baixar</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</main>

<style>
    .botoes-navegacao {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
</style>


<script>
    // --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

    // Objeto para simular os dentistas por especialidade.
    // O ideal é que isso venha do banco de dados com uma requisição (AJAX).
    const dentistasPorEspecialidade = {
        limpeza: [
            { id: 1, nome: "Dr. Ana Costa" },
            { id: 2, nome: "Dr. João Silva" }
        ],
        estetica: [
            { id: 3, nome: "Dra. Sofia Lima" }
        ],
        ortodontia: [
            { id: 4, nome: "Dr. Carlos Moura" }
        ],
        endodontia: [
            { id: 5, nome: "Dr. Pedro Alves" }
        ],
        implantes: [
            { id: 6, nome: "Dra. Beatriz Santos" }
        ]
    };

    // Objeto para simular os horários.
    // Isso também deve vir do banco de dados com base no dentista e na data.
    const horariosSimulados = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"];

    const selectEspecialidade = document.getElementById('especialidade');
    const selectDentista = document.getElementById('dentista');
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');

    // Quando o usuário muda a especialidade, atualiza a lista de dentistas
    selectEspecialidade.addEventListener('change', function() {
        const especialidade = this.value;
        const dentistas = dentistasPorEspecialidade[especialidade] || [];

        selectDentista.innerHTML = '<option value="" disabled selected>Selecione um profissional...</option>'; // Limpa opções antigas

        dentistas.forEach(dentista => {
            const option = document.createElement('option');
            option.value = dentista.id;
            option.textContent = dentista.nome;
            selectDentista.appendChild(option);
        });
    });

    // Quando o usuário escolhe uma data, busca os horários
    inputData.addEventListener('change', function() {
        // --- PONTO DE INTEGRAÇÃO COM BACKEND ---
        // Aqui você faria uma requisição (fetch/AJAX) para o backend,
        // passando a data (this.value) e o ID do dentista (selectDentista.value)
        // para buscar os horários realmente disponíveis no banco de dados.

        // Por enquanto, vamos usar os horários simulados:
        divHorarios.innerHTML = ''; // Limpa a lista
        horariosSimulados.forEach(horario => {
            const btnHorario = document.createElement('button');
            btnHorario.type = 'button';
            btnHorario.className = 'horario-item';
            btnHorario.textContent = horario;
            btnHorario.dataset.horario = horario;

            btnHorario.addEventListener('click', function() {
                // Remove a seleção de outros botões
                document.querySelectorAll('.horario-item.selected').forEach(btn => {
                    btn.classList.remove('selected');
                });
                // Adiciona a classe 'selected' ao botão clicado
                this.classList.add('selected');
                // Atualiza o valor do input hidden que será enviado com o form
                inputHorarioSelecionado.value = this.dataset.horario;
            });

            divHorarios.appendChild(btnHorario);
        });
    });

    // Função para navegar entre os passos do formulário
    function irParaPasso(numeroPasso) {
        // Esconde todos os passos
        document.querySelectorAll('.passo-agendamento').forEach(passo => {
            passo.style.display = 'none';
        });

        // Mostra o passo desejado
        document.getElementById(`passo-${numeroPasso}`).style.display = 'block';
    }

</script>


<?php
// A linha abaixo já carrega todo o rodapé e fecha as tags </body> e </html>
include 'templates/footer.php';
?>