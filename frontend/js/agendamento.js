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
    
    // --- FUNÇÃO PARA LIGAR OS EVENTOS DE CLIQUE DOS HORÁRIOS ---
    function ligarEventosHorarios() {
        // Pega o valor do horário que veio na URL para pré-selecionar
        const horarioGet = inputHorarioSelecionado.value; 

        document.querySelectorAll('.horario-item').forEach(btnHorario => {
            // Pré-seleciona se o valor da URL for igual ao horário do botão
            if (horarioGet && btnHorario.dataset.horario === horarioGet) {
                 btnHorario.classList.add('selected');
            }

            // Liga o evento de clique uma única vez
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

    // --- FUNÇÃO PARA INICIALIZAR ESTADO DO FORMULÁRIO (Pré-preenchimento) ---
    function inicializarEstadoFormulario() {
        const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
        
        if (servicosMarcados.length > 0) {
            const primeiroServico = servicosMarcados[0];
            const nomeDentista = primeiroServico.dataset.dentistaNome;
            const dentistaId = primeiroServico.dataset.dentistaId;
            
            // Atualiza o estado visual e variáveis
            dentistaAtualSelecionado = dentistaId; 
            inputDentistaSelecionado.value = dentistaId;
            dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
            inputServicosValidacao.value = 'selecionado';

            // Verifica se deve avançar o passo
            const hash = window.location.hash;
            if (hash.startsWith('#passo-')) {
                const passoNum = parseInt(hash.replace('#passo-', ''));
                if (!isNaN(passoNum)) {
                    irParaPasso(passoNum);
                }
            } else {
                irParaPasso(1);
            }
        } else {
             // Garante que o passo 1 é exibido se nada estiver selecionado
            irParaPasso(1); 
        }

        // Liga os listeners dos botões de horário, incluindo a pré-seleção
        ligarEventosHorarios();
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

            // 2. Atualizar o estado do formulário e recarregar se necessário
            if (servicosMarcados.length === 0) {
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional responsável.**';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
                // Recarrega sem dados (apenas para limpar a URL)
                window.location.href = `agendar_consulta.php#passo-1`; 
            } else {
                inputDentistaSelecionado.value = dentistaAtualSelecionado;
                
                // Se o dentista mudar e a data já estiver preenchida, força a recarga para buscar novos horários
                if (inputData.value) {
                    const data = inputData.value;
                    const dentistaId = dentistaAtualSelecionado;
                    
                    // COLETAR TODOS OS DADOS NOVAMENTE PARA RECARGA
                    const servicosSelecionados = servicosMarcados
                        .map(cb => `servicos[]=${cb.value}`)
                        .join('&');

                    // Coletar campos adicionais, se houver
                    const obs = obsTextarea?.value;
                    const historico = historicoTextarea?.value;
                    const alergias = alergiasTextarea?.value;

                    let url = `agendar_consulta.php?dentista_id=${dentistaId}&data=${data}&${servicosSelecionados}`;

                    if (obs) url += `&observacoes=${encodeURIComponent(obs)}`;
                    if (historico) url += `&historico=${encodeURIComponent(historico)}`;
                    if (alergias) url += `&alergias=${encodeURIComponent(alergias)}`;
                    
                    url += `#passo-2`;
                    
                    window.location.href = url;
                } else {
                     // Apenas atualiza o nome do dentista no Passo 1
                    dentistaInfoP.innerHTML = `Profissional Escolhido: Dr(a). **${nomeDentista}**`;
                }
            }
        });
    });

    // --- EVENTO DE SELEÇÃO DE DATA (AGORA RECARREGA A PÁGINA COM TODOS OS DADOS) ---
    if(inputData) {
        inputData.addEventListener('change', function() {
            // ... (Verificação de data passada mantida)
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
                // 1. COLETAR IDS dos SERVIÇOS SELECIONADOS
                const servicosSelecionados = Array.from(checkboxesServicos)
                    .filter(cb => cb.checked)
                    .map(cb => `servicos[]=${cb.value}`)
                    .join('&');

                // **COLETAR CAMPOS ADICIONAIS (REABASTECIMENTO)**
                const obs = obsTextarea?.value;
                const historico = historicoTextarea?.value;
                const alergias = alergiasTextarea?.value;
                
                // 2. MONTAR A URL COM TODOS OS PARÂMETROS
                let url = `agendar_consulta.php?dentista_id=${dentistaId}&data=${data}`;

                // Adiciona os serviços
                if (servicosSelecionados) {
                    url += `&${servicosSelecionados}`;
                }
                
                // Adiciona os campos adicionais SE estiverem preenchidos
                if (obs) url += `&observacoes=${encodeURIComponent(obs)}`;
                if (historico) url += `&historico=${encodeURIComponent(historico)}`;
                if (alergias) url += `&alergias=${encodeURIComponent(alergias)}`;
                
                // Adiciona a âncora para ir ao passo 2
                url += `#passo-2`;
                
                window.location.href = url;
                
            } else {
                alert('Selecione um serviço e o dentista no Passo 1 antes de escolher a data.');
                this.value = '';
            }
        });
    }

    // Chama a função de inicialização para definir o estado inicial após o DOM carregar
    inicializarEstadoFormulario(); 
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