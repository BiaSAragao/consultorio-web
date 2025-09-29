// --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

document.addEventListener('DOMContentLoaded', () => {
    
    // Elementos do formulário
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    const inputDentistaSelecionado = document.getElementById('dentista_selecionado'); // Novo ID: 'dentista_selecionado'
    const dentistaInfoP = document.getElementById('info-dentista');                   // Novo ID: 'info-dentista'
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    // Mapeia IDs de Dentistas para Nomes (necessário para exibição)
    const dentistasMap = {};
    checkboxesServicos.forEach(checkbox => {
        const dentistaId = checkbox.getAttribute('data-dentista-id');
        // Extrai o nome do dentista do texto do label (assumindo o formato "Com Dr(a). NOME...")
        const labelText = checkbox.nextElementSibling.textContent;
        const match = labelText.match(/Com Dr\(a\)\. (.*?) - R\$/);
        const nomeDentista = match ? match[1].trim() : `Dentista ID ${dentistaId}`;
        dentistasMap[dentistaId] = nomeDentista;
    });

    // Inicializa o formulário no primeiro passo
    irParaPasso(1);

    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
            
            // 1. Validar seleção de um único dentista
            const dentistasSelecionados = servicosMarcados.map(cb => cb.getAttribute('data-dentista-id'));
            const dentistaUnico = dentistasSelecionados.length > 0 ? dentistasSelecionados[0] : null;

            if (servicosMarcados.length > 0 && dentistasSelecionados.some(id => id !== dentistaUnico)) {
                alert('Você só pode agendar serviços de um único profissional por vez.');
                this.checked = false; // Desmarca a última seleção inválida
                return; 
            }

            // 2. Atualizar o Campo Hidden (Dentista ID) e Info
            if (dentistaUnico) {
                const nomeDentista = dentistasMap[dentistaUnico] || `ID ${dentistaUnico}`;
                inputDentistaSelecionado.value = dentistaUnico;
                dentistaInfoP.innerHTML = `Profissional(is) Escolhido(s): **Dr(a). ${nomeDentista}**`;
                
                // Se já houver data selecionada, busca os horários desse dentista
                if (inputData.value) {
                    buscarHorarios(inputData.value, dentistaUnico);
                }
            } else {
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o profissional associado.**';
                // Limpa horários se não houver dentista
                divHorarios.innerHTML = '<p>Selecione uma data para ver os horários disponíveis.</p>';
                inputHorarioSelecionado.value = '';
            }

            // 3. Atualizar campo de validação de serviços
            inputServicosValidacao.value = servicosMarcados.length > 0 ? 'selecionado' : '';
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
            const dentistaId = inputDentistaSelecionado.value; // Pega do campo hidden

            if (data && dentistaId) {
                buscarHorarios(data, dentistaId);
            } else {
                divHorarios.innerHTML = '<p>Selecione um serviço e um dentista no Passo 1.</p>';
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
        
        if (!inputServicosValidacao.value) {
            alert(inputServicosValidacao.dataset.errorMessage);
            valido = false;
        } else if (!inputDentistaSelecionado.value) {
            alert('Um profissional deve ser selecionado automaticamente após a escolha do serviço.');
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

// --- FUNÇÃO DE BUSCA DE HORÁRIOS DISPONÍVEIS (Atualizada para usar POST e URL correta) ---
async function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';
    divHorarios.innerHTML = '<p>Carregando horários...</p>';

    try {
        // Mudança para usar FETCH com método POST e a URL do novo script de backend
        const resp = await fetch('../backend/buscar_horarios.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `dentista_id=${dentistaId}&data=${data}`
        });
        
        const dataJson = await resp.json();

        divHorarios.innerHTML = '';

        if (dataJson.error) {
             divHorarios.innerHTML = `<p style="color: red;">Erro: ${dataJson.error}</p>`;
             return;
        }
        
        const horarios = dataJson.disponiveis || []; // Pega a chave 'disponiveis' do JSON

        if (horarios.length === 0) {
            divHorarios.innerHTML = `<p>${dataJson.message || 'Nenhum horário disponível para esta data.'}</p>`;
            return;
        }

        horarios.forEach(horario => {
            const btnHorario = document.createElement('div'); // Alterado para DIV para seguir o CSS de item
            btnHorario.type = 'button';
            btnHorario.className = 'horario-item';
            // Formato o horário (retira os segundos, ex: 10:00:00 -> 10:00)
            btnHorario.textContent = horario.substring(0, 5); 
            btnHorario.dataset.horario = horario;

            btnHorario.addEventListener('click', function() {
                document.querySelectorAll('.horario-item.selected').forEach(btn => btn.classList.remove('selected'));
                this.classList.add('selected');
                inputHorarioSelecionado.value = this.dataset.horario;
            });

            divHorarios.appendChild(btnHorario);
        });
    } catch (e) {
        console.error('Erro na busca de horários:', e);
        divHorarios.innerHTML = '<p>Erro ao carregar horários. Verifique a conexão.</p>';
    }
}