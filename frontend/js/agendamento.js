// Arquivo: /frontend/js/agendamento.js

// --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

document.addEventListener('DOMContentLoaded', () => {
    
    // Elementos do formulário
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputDentistaSelecionado = document.getElementById('dentista_selecionado');
    const dentistaInfoP = document.getElementById('dentista_info'); 
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    // Inicializa o formulário no primeiro passo
    irParaPasso(1);

    // Variável de controle para garantir que todos os serviços são do mesmo DENTISTA
    let dentistaAtualSelecionado = null;

    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dentistaId = this.dataset.dentistaId;
            const nomeDentista = this.dataset.dentistaNome;
            
            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
            
            // 1. Lógica de Restrição de Dentista (garantir que seja o mesmo dentista)
            if (this.checked) {
                if (dentistaAtualSelecionado === null) {
                    dentistaAtualSelecionado = dentistaId;
                } else if (dentistaAtualSelecionado !== dentistaId) {
                    alert(`Você só pode agendar serviços do(a) mesmo(a) profissional (${nomeDentista}).`);
                    this.checked = false; // Desmarca a última seleção inválida
                    return; 
                }
            } else if (servicosMarcados.length === 0) {
                 // Limpa o estado se desmarcou o último serviço
                 dentistaAtualSelecionado = null;
            } else if (servicosMarcados.length > 0) {
                // Se desmarcou, mas ainda há serviços, reconfirma o ID do dentista restante
                dentistaAtualSelecionado = servicosMarcados[0].dataset.dentistaId;
            }


            // 2. Atualizar o estado do formulário
            if (servicosMarcados.length === 0) {
                // Nenhum serviço marcado, limpa o estado
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
                divHorarios.innerHTML = '<p>Selecione uma data para ver os horários disponíveis.</p>';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
            } else {
                // Atualiza com o dentista selecionado
                inputDentistaSelecionado.value = dentistaAtualSelecionado;
                dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
                inputServicosValidacao.value = 'selecionado';

                // Se já houver data selecionada, busca os horários desse dentista
                if (inputData.value) {
                    buscarHorarios(inputData.value, dentistaAtualSelecionado);
                }
            }
        });
    });

    // --- EVENTO DE SELEÇÃO DE DATA ---
    if(inputData) {
        inputData.addEventListener('change', function() {
            const dataSelecionada = new Date(this.value + "T00:00:00");
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (dataSelecionada < hoje) {
                alert('Você não pode agendar para uma data passada.');
                this.value = '';
                divHorarios.innerHTML = '<p>Selecione uma data válida.</p>';
                inputHorarioSelecionado.value = '';
                return;
            }

            const data = this.value;
            const dentistaId = inputDentistaSelecionado.value;

            if (data && dentistaId) {
                // buscarHorarios usa o dentistaId do campo hidden
                buscarHorarios(data, dentistaId);
            } else {
                divHorarios.innerHTML = '<p>Selecione um serviço e o dentista no Passo 1.</p>';
                inputHorarioSelecionado.value = '';
            }
        });
    }
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
    window.scrollTo(0, 0);
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
        if (!inputData.value || !inputHorarioSelecionado.value) {
            alert('Selecione uma data e um horário disponível.');
            valido = false;
        }
    }
    
    if (valido) {
        irParaPasso(proximoPasso);
    }
}

// --- FUNÇÃO DE BUSCA DE HORÁRIOS DISPONÍVEIS (Chama buscar_horarios.php) ---
async function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';
    divHorarios.innerHTML = '<p>Carregando horários...</p>';

    try {
        // Chamada para o novo script que retorna HTML
        const resp = await fetch('../buscar_horarios.php', { 
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `dentista_id=${dentistaId}&data=${data}`
        });
        
        // Se a requisição HTTP falhar (ex: 404, 500), tratamos aqui
        if (!resp.ok) {
            throw new Error(`Erro HTTP ${resp.status}`);
        }

        // NOVO: Lê o resultado como TEXTO (HTML)
        const html = await resp.text(); 

        divHorarios.innerHTML = html;

        // Re-liga os Eventos de Clique aos novos botões de horário
        document.querySelectorAll('.horario-item').forEach(btnHorario => {
            btnHorario.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(btn => btn.classList.remove('selected'));
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });
        });

    } catch (e) {
        console.error('Erro na busca de horários:', e);
        // Esta mensagem só aparece se o fetch FALHAR (conexão, 404, etc.)
        divHorarios.innerHTML = `<p style="color: red;">Erro de comunicação com o servidor. Tente novamente.</p>`;
    }
}