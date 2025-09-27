// Espera o documento carregar completamente
document.addEventListener('DOMContentLoaded', () => {
    
    // Pega os elementos do formulário
    const inputDentistaId = document.getElementById('dentista');
    const inputData = document.getElementById('data');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    
    // Lógica inicial ao carregar a página
    irParaPasso(1);

    // Se a página já carrega com uma data, busca os horários para ela
    if (inputData && inputData.value) {
        buscarHorarios(inputData.value, inputDentistaId.value);

        // Pequeno atraso para garantir que os botões de horário foram criados antes de tentar selecionar um
        setTimeout(() => {
            const horarioAtual = inputHorarioSelecionado.value;
            const divHorarios = document.getElementById('lista-horarios');
            const btns = divHorarios.querySelectorAll('.horario-item');

            btns.forEach(btn => {
                if (btn.dataset.horario === horarioAtual) {
                    btn.classList.add('selected');
                }
            });
        }, 100); // 100ms de atraso é geralmente suficiente
    }

    // Adiciona o Event Listener para o campo de data
    if(inputData) {
        inputData.addEventListener('change', function() {
            const dataSelecionada = new Date(this.value + "T00:00:00");
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (dataSelecionada < hoje) {
                alert('Você não pode agendar para uma data passada.');
                this.value = '';
                document.getElementById('lista-horarios').innerHTML = '<p>Selecione uma data válida.</p>';
                return;
            }

            if (this.value && inputDentistaId.value) {
                buscarHorarios(this.value, inputDentistaId.value);
            }
        });
    }
});

// As funções abaixo são idênticas às do arquivo agendamento.js
// e são chamadas pelos botões no HTML.

function irParaPasso(numeroPasso) {
    document.querySelectorAll('.passo-agendamento').forEach(passo => passo.style.display = 'none');
    const passoAtual = document.getElementById(`passo-${numeroPasso}`);
    if(passoAtual) {
        passoAtual.style.display = 'block';
    }
    window.scrollTo(0, 0);
}

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
    if (valido) irParaPasso(proximoPasso);
}

function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';

    // Simulação de busca de horários
    const hoje = new Date().toISOString().split('T')[0];
    const amanha = new Date(new Date().getTime() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    let horariosSimulados = [];

    if (data === hoje) horariosSimulados = ["15:00", "16:00", "17:00"];
    else if (data === amanha) horariosSimulados = ["08:00", "09:30", "11:00", "14:00", "15:30"];
    else horariosSimulados = ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"];

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
                document.querySelectorAll('.horario-item.selected').forEach(btn => btn.classList.remove('selected'));
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });
            divHorarios.appendChild(btnHorario);
        });
    }
}