// Arquivo: /frontend/js/agendamento.js

document.addEventListener('DOMContentLoaded', () => {

    // Elementos do formulário
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputDentistaSelecionado = document.getElementById('dentista_selecionado');
    const dentistaInfoP = document.getElementById('dentista_info'); 
    const inputData = document.getElementById('data');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    const obsTextarea = document.getElementById('observacoes');
    const historicoTextarea = document.getElementById('historico');
    const alergiasTextarea = document.getElementById('alergias');

    // Variável de controle para o dentista
    let dentistaAtualSelecionado = inputDentistaSelecionado.value || null;

    // --- FUNÇÃO PARA EXIBIR HORÁRIOS ---
    function exibirHorarios() {
        const listaHorarios = document.getElementById('lista-horarios');
        listaHorarios.innerHTML = '';

        if (!window.horariosDisponiveis || horariosDisponiveis.length === 0) {
            listaHorarios.innerHTML = '<p>Nenhum horário disponível para esta data.</p>';
            inputHorarioSelecionado.value = '';
            return;
        }

        horariosDisponiveis.forEach(h => {
            const btn = document.createElement('div');
            btn.classList.add('horario-item');
            btn.dataset.horario = h;
            btn.textContent = h.substr(0,5);

            // Pré-seleção do horário vindo da URL
            if (inputHorarioSelecionado.value && inputHorarioSelecionado.value === h) {
                btn.classList.add('selected');
            }

            btn.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });

            listaHorarios.appendChild(btn);
        });
    }

    // --- INICIALIZA ESTADO DO FORMULÁRIO ---
    function inicializarEstadoFormulario() {
        const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);

        if (servicosMarcados.length > 0) {
            const primeiroServico = servicosMarcados[0];
            const nomeDentista = primeiroServico.dataset.dentistaNome;
            const dentistaId = primeiroServico.dataset.dentistaId;

            inputServicosValidacao.value = 'selecionado'; 
            dentistaAtualSelecionado = dentistaId; 
            inputDentistaSelecionado.value = dentistaId;
            dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
        } else {
            inputServicosValidacao.value = '';
            dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
        }

        // Exibe os horários já carregados
        exibirHorarios();
    }

    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dentistaId = this.dataset.dentistaId;
            const nomeDentista = this.dataset.dentistaNome;

            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);

            // Restrição: apenas um dentista
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

            // Atualiza campo e exibe dentista
            if (servicosMarcados.length === 0) {
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
            } else {
                inputDentistaSelecionado.value = dentistaAtualSelecionado;
                dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
            }
        });
    });

    // --- EVENTO DE SELEÇÃO DE DATA ---
    if(inputData) {
        inputData.addEventListener('change', function() {
            const dataSelecionada = new Date(this.value + "T00:00:00");
            const hoje = new Date();
            hoje.setHours(0,0,0,0);

            if (dataSelecionada < hoje) {
                alert('Você não pode agendar para uma data passada.');
                this.value = '';
                return;
            }

            // Atualiza os horários para a data selecionada
            if (dentistaAtualSelecionado) {
                // Aqui você pode recarregar a página ou gerar os horários dinamicamente
                // Se você não quiser recarregar, precisa ter todos os horários carregados no PHP
                // exibirHorarios(); // já usa a variável global "horariosDisponiveis"
            } else {
                alert('Selecione um serviço primeiro para determinar o dentista.');
                this.value = '';
            }
        });
    }

    // --- FUNÇÕES DE NAVEGAÇÃO ---
    function irParaPasso(numeroPasso) {
        document.querySelectorAll('.passo-agendamento').forEach(passo => passo.style.display = 'none');
        const passoAtual = document.getElementById(`passo-${numeroPasso}`);
        if(passoAtual) passoAtual.style.display = 'block';
    }

    function validarEPularPasso(passoAtual, proximoPasso) {
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
            if (!inputData.value || !inputHorarioSelecionado.value) {
                alert('Selecione uma data e um horário disponível.');
                valido = false;
            }
        }

        if (valido) irParaPasso(proximoPasso);
    }

    // --- INICIALIZA TUDO ---
    inicializarEstadoFormulario();
});
