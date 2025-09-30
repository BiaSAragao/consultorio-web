document.addEventListener('DOMContentLoaded', () => {
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputDentistaSelecionado = document.getElementById('dentista_selecionado');
    const dentistaInfoP = document.getElementById('dentista_info'); 
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    let dentistaAtualSelecionado = inputDentistaSelecionado.value || null;

    // --- Ativar clique nos horários ---
    function inicializarHorarios() {
        document.querySelectorAll('.horario-item').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });
        });
    }

    // --- Estado inicial ---
    function inicializarEstadoFormulario() {
        const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
        if (servicosMarcados.length > 0) {
            const primeiroServico = servicosMarcados[0];
            const nomeDentista = primeiroServico.dataset.dentistaNome;
            const dentistaId = primeiroServico.dataset.dentistaId;

            inputServicosValidacao.value = 'selecionado'; 
            dentistaAtualSelecionado = dentistaId; 
            inputDentistaSelecionado.value = dentistaId;
            dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). <strong>${nomeDentista}</strong>`;
        } else {
            inputServicosValidacao.value = '';
            dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
        }

        inicializarHorarios();
    }

    // --- Restringir serviços a um dentista ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dentistaId = this.dataset.dentistaId;
            const nomeDentista = this.dataset.dentistaNome;
            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);

            if (this.checked) {
                if (dentistaAtualSelecionado === null) {
                    dentistaAtualSelecionado = dentistaId;
                } else if (dentistaAtualSelecionado !== dentistaId) {
                    alert(`Você só pode agendar serviços do(a) mesmo(a) profissional (${nomeDentista}).`);
                    this.checked = false; 
                    return; 
                }
            } else if (servicosMarcados.length === 0) {
                dentistaAtualSelecionado = null;
            } else {
                dentistaAtualSelecionado = servicosMarcados[0].dataset.dentistaId;
            }

            if (servicosMarcados.length === 0) {
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
            } else {
                inputDentistaSelecionado.value = dentistaAtualSelecionado;
                dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). <strong>${nomeDentista}</strong>`;
            }
        });
    });

    // --- Navegação ---
    window.irParaPasso = function(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => passo.style.display = 'none');
        const passoAtual = document.getElementById(`passo-${numeroPasso}`);
        if (passoAtual) passoAtual.style.display = 'block';
    };

    window.validarEPularPasso = function(passoAtual, proximoPasso) {
        let valido = true;

        if (passoAtual === 1) {
            if (inputServicosValidacao.value !== 'selecionado') {
                alert(inputServicosValidacao.dataset.errorMessage);
                valido = false;
            } else if (!inputDentistaSelecionado.value) {
                alert(document.getElementById('dentista_selecionado').dataset.errorMessage);
                valido = false;
            }
        } else if (passoAtual === 2) {
            const inputData = document.getElementById('data');
            if (!inputData.value || !inputHorarioSelecionado.value) {
                alert('Selecione uma data e um horário disponível.');
                valido = false;
            }
        }

        if (valido) irParaPasso(proximoPasso);
    };

    inicializarEstadoFormulario();
});
