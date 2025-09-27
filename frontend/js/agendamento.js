// --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

// Espera o documento carregar completamente para garantir que todos os elementos HTML existam
document.addEventListener('DOMContentLoaded', () => {
    
    // Pega os elementos do formulário pelos seus IDs
    const inputDentistaId = document.getElementById('dentista');
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    // Inicializa o formulário no primeiro passo
    irParaPasso(1);

    // Adiciona o Event Listener para o campo de data
    if(inputData) {
        inputData.addEventListener('change', function() {
            // Garante que a data não é passada
            const dataSelecionada = new Date(this.value + "T00:00:00"); // Adiciona T00:00:00 para evitar problemas de fuso horário
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (dataSelecionada < hoje) {
                alert('Você não pode agendar para uma data passada.');
                this.value = '';
                divHorarios.innerHTML = '<p>Selecione uma data válida.</p>';
                return;
            }

            const data = this.value;
            const dentistaId = inputDentistaId.value;

            if (data && dentistaId) {
                buscarHorarios(data, dentistaId);
            } else {
                divHorarios.innerHTML = '<p>Selecione uma data para ver os horários.</p>';
            }
        });
    }
});


// Função para navegar entre os passos do formulário
function irParaPasso(numeroPasso) {
    document.querySelectorAll('.passo-agendamento').forEach(passo => {
        passo.style.display = 'none';
    });
    const passoAtual = document.getElementById(`passo-${numeroPasso}`);
    if(passoAtual) {
        passoAtual.style.display = 'block';
    }
    window.scrollTo(0, 0); // Rola para o topo da página
}

// Função para validar o passo atual antes de avançar
function validarEPularPasso(passoAtual, proximoPasso) {
    let valido = true;
    
    if (passoAtual === 1) {
        const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
        const inputServicosValidacao = document.getElementById('servicos_validacao');
        const servicosSelecionados = Array.from(checkboxesServicos).some(checkbox => checkbox.checked);

        if (!servicosSelecionados) {
            alert(inputServicosValidacao.dataset.errorMessage);
            valido = false;
        } else {
            inputServicosValidacao.value = 'selecionado';
        }
    } else if (passoAtual === 2) {
        const inputData = document.getElementById('data');
        const inputHorarioSelecionado = document.getElementById('horario_selecionado');
        if (!inputData.value || !inputHorarioSelecionado.value) {
            alert('Selecione uma data e um horário disponível.');
            valido = false;
        }
    }
    
    if (valido) {
        irParaPasso(proximoPasso);
    }
}

// --- LÓGICA DE HORÁRIOS (SIMULAÇÃO) ---
function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';

    // *** PONTO DE INTEGRAÇÃO COM BACKEND: Usar fetch/AJAX aqui! ***
    
    // Simulação (remova isso ao integrar com o backend real):
    const hoje = new Date().toISOString().split('T')[0];
    const amanha = new Date(new Date().getTime() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    let horariosSimulados = [];

    if (data === hoje) {
        horariosSimulados = ["15:00", "16:00", "17:00"];
    } else if (data === amanha) {
        horariosSimulados = ["08:00", "09:30", "11:00", "14:00", "15:30"];
    } else {
        horariosSimulados = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"];
    }

    divHorarios.innerHTML = '';
    if (horariosSimulados.length === 0) {
        divHorarios.innerHTML = '<p>Nenhum horário disponível para esta data.</p>';
    } else {
        horariosSimulados.forEach(horario => {
            const btnHorario = document.createElement('button');
            btnHorario.type = 'button';
            btnHorario.className = 'horario-item';
            btnHorario.textContent = horario;
            btnHorario.dataset.horario = horario;

            btnHorario.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(btn => {
                    btn.classList.remove('selected');
                });
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });

            divHorarios.appendChild(btnHorario);
        });
    }
}