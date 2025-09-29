// Arquivo: /frontend/js/agendamento.js

// --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

document.addEventListener('DOMContentLoaded', () => {
    
    // Elementos do formulário
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputDentistaSelecionado = document.getElementById('dentista_selecionado');
    const dentistaInfoP = document.getElementById('dentista_info'); 
    const inputData = document.getElementById('data');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    // Variável de controle para garantir que todos os serviços são do mesmo DENTISTA
    let dentistaAtualSelecionado = null;

    // --- FUNÇÃO PARA LIGAR OS EVENTOS DE CLIQUE DOS HORÁRIOS ---
    // Necessário para horários renderizados pelo PHP na recarga da página
    function ligarEventosHorarios() {
        document.querySelectorAll('.horario-item').forEach(btnHorario => {
            // Verifica se o horário já foi ligado para evitar duplicação
            if (btnHorario.dataset.listener !== 'true') {
                btnHorario.addEventListener('click', function() {
                    document.querySelectorAll('.horario-item.selected').forEach(btn => btn.classList.remove('selected'));
                    this.classList.add('selected');
                    inputHorarioSelecionado.value = this.dataset.horario;
                });
                btnHorario.dataset.listener = 'true';
            }
        });
    }

    // LIGA OS EVENTOS DE HORÁRIO APÓS O DOM CARREGAR
    ligarEventosHorarios();
    
    // --- LÓGICA DE NAVEGAÇÃO INICIAL (APÓS RECARREGAMENTO) ---
    const hash = window.location.hash;
    if (hash.startsWith('#passo-')) {
        const passoNum = parseInt(hash.replace('#passo-', ''));
        if (!isNaN(passoNum)) {
            irParaPasso(passoNum);
        }
    } else {
        irParaPasso(1);
    }
    
    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dentistaId = this.dataset.dentistaId;
            const nomeDentista = this.dataset.dentistaNome;
            
            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
            
            // 1. Lógica de Restrição de Dentista
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
            } else if (servicosMarcados.length > 0) {
                dentistaAtualSelecionado = servicosMarcados[0].dataset.dentistaId;
            }

            // 2. Atualizar o estado do formulário
            if (servicosMarcados.length === 0) {
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
                // Quando o dentista muda ou é desmarcado, a página deve ser recarregada
                window.location.href = `agendar_consulta.php#passo-1`; 
            } else {
                inputDentistaSelecionado.value = dentistaAtualSelecionado;
                dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
                inputServicosValidacao.value = 'selecionado';

                // Se o dentista mudar e a data já estiver preenchida, força a recarga para buscar novos horários
                if (inputData.value) {
                    const data = inputData.value;
                    const dentistaId = dentistaAtualSelecionado;
                    // Redireciona para buscar horários do novo dentista
                    window.location.href = `agendar_consulta.php?dentista_id=${dentistaId}&data=${data}#passo-2`;
                }
            }
        });
    });

    // --- EVENTO DE SELEÇÃO DE DATA (AGORA RECARREGA A PÁGINA) ---
    if(inputData) {
        inputData.addEventListener('change', function() {
            const dataSelecionada = new Date(this.value + "T00:00:00");
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (dataSelecionada < hoje) {
                alert('Você não pode agendar para uma data passada.');
                this.value = '';
                return;
            }

            const data = this.value;
            const dentistaId = inputDentistaSelecionado.value;

            if (data && dentistaId) {
                // *** AÇÃO PRINCIPAL: REDIRECIONAR PARA O GET COM OS NOVOS VALORES ***
                window.location.href = `agendar_consulta.php?dentista_id=${dentistaId}&data=${data}#passo-2`;
            } else {
                alert('Selecione um serviço e o dentista no Passo 1 antes de escolher a data.');
            }
        });
    }

    // A função buscarHorarios FOI REMOVIDA
});

// --- FUNÇÕES DE NAVEGAÇÃO DE PASSOS (Mantidas) ---
function irParaPasso(numeroPasso) {
    document.querySelectorAll('.passo-agendamento').forEach(passo => {
        passo.style.display = 'none';
    });
    const passoAtual = document.getElementById(`passo-${numeroPasso}`);
    if(passoAtual) {
        passoAtual.style.display = 'block';
    }
}

function validarEPularPasso(passoAtual, proximoPasso) {
    let valido = true;
    
    if (passoAtual === 1) {
        const inputServicosValidacao = document.getElementById('servicos_validacao');
        const inputDentistaSelecionado = document.getElementById('dentista_selecionado');
        
        if (inputServicosValidacao.value !== 'selecionado') {
            alert(inputServicosValidacao.dataset.errorMessage);
            valido = false;
        } else if (!inputDentistaSelecionado.value) {
            alert(document.getElementById('dentista_selecionado').dataset.errorMessage);
            valido = false;
        }
    } else if (passoAtual === 2) {
        const inputData = document.getElementById('data');
        const inputHorarioSelecionado = document.getElementById('horario_selecionado');
        // A validação se a data e o horário foram selecionados
        if (!inputData.value || !inputHorarioSelecionado.value) {
            alert('Selecione uma data e um horário disponível.');
            valido = false;
        }
    }
    
    if (valido) {
        irParaPasso(proximoPasso);
    }
}