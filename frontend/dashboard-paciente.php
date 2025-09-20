<?php
// --- TRUQUE TEMPORÁRIO PARA VISUALIZAÇÃO ---
session_start();
// Simulamos um usuário logado para a página não te redirecionar
$_SESSION['usuario_id'] = 1; // Um ID de teste qualquer
$_SESSION['usuario_nome'] = "Paciente Teste"; // Um nome de teste para aparecer no cabeçalho
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
        <form action="../backend/agendar_consulta.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="especialidade">Especialidade</label>
                    <select id="especialidade" name="especialidade" required>
                        <option value="">Selecione um serviço...</option>
                        <option value="limpeza">Limpeza e Prevenção</option>
                        <option value="estetica">Estética Dental</option>
                        <option value="ortodontia">Ortodontia</option>
                        <option value="endodontia">Endodontia (Canal)</option>
                        <option value="implantes">Implantes Dentários</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dentista">Profissional</label>
                    <select id="dentista" name="dentista" required>
                        <option value="">Escolha uma especialidade primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data">Data da Consulta</label>
                    <input type="date" id="data" name="data" required>
                </div>
            </div>
            <div class="form-group">
                <label>Horários Disponíveis</label>
                <div class="horarios-disponiveis">
                    <button type="button" class="horario-item">09:00</button>
                    <button type="button" class="horario-item">10:00</button>
                    <button type="button" class="horario-item selected">11:00</button>
                    <button type="button" class="horario-item">14:00</button>
                    <button type="button" class="horario-item">15:00</button>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 2rem; width: 100%; max-width: 250px;">Confirmar Agendamento</button>
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
<?php
// A linha abaixo já carrega todo o rodapé e fecha as tags </body> e </html>
include 'templates/footer.php';
?>