// Arquivo: js/agendamento.js (Atualizado)

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

    // Variável de controle para garantir que todos os serviços são da mesma categoria
    let categoriaAtualSelecionada = null;

    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
            const categoriaId = this.dataset.categoriaId;
            
            const servicosMarcados = Array.from(checkboxesServicos).filter(cb => cb.checked);
            
            // 1. Lógica de Restrição de Categoria (apenas serviços da mesma categoria)
            if (this.checked) {
                if (categoriaAtualSelecionada === null) {
                    categoriaAtualSelecionada = categoriaId;
                } else if (categoriaAtualSelecionada !== categoriaId) {
                    const nomeCategoria = this.dataset.categoriaNome;
                    alert(`Você só pode agendar serviços da mesma categoria (${nomeCategoria}).`);
                    this.checked = false; // Desmarca a última seleção inválida
                    return; 
                }
            }
            
            // Se desmarcou, verifica se ainda há algum serviço marcado
            if (servicosMarcados.length === 0) {
                // Nenhum serviço marcado, limpa o estado
                categoriaAtualSelecionada = null;
                inputDentistaSelecionado.value = '';
                dentistaInfoP.innerHTML = '**Selecione um serviço para ver o dentista responsável.**';
                divHorarios.innerHTML = '<p>Selecione uma data para ver os horários disponíveis.</p>';
                inputHorarioSelecionado.value = '';
                inputServicosValidacao.value = '';
                return;
            }

            // 2. Atualiza campo de validação de serviços
            inputServicosValidacao.value = servicosMarcados.length > 0 ? 'selecionado' : '';

            // 3. Buscar Dentista via AJAX
            const categoriaBusca = servicosMarcados[0].dataset.categoriaId;

            try {
                // Chama get_dentista.php via GET (conforme o código original que você enviou)
                const resp = await fetch(`../backend/get_dentista.php?categoria_id=${categoriaBusca}`);
                const dentista = await resp.json();
                
                if (dentista.error) {
                    dentistaInfoP.innerHTML = `**ERRO:** ${dentista.error}`;
                    inputDentistaSelecionado.value = '';
                    divHorarios.innerHTML = '<p>Selecione uma data para ver os horários disponíveis.</p>';
                    return;
                }

                if (dentista && dentista.usuario_id) {
                    dentistaInfoP.innerHTML = `Dr(a). **${dentista.nome}** (CRO: ${dentista.cro})`;
                    inputDentistaSelecionado.value = dentista.usuario_id;
                    
                    // Se já houver data selecionada, busca os horários desse dentista
                    if (inputData.value) {
                        buscarHorarios(inputData.value, dentista.usuario_id);
                    }
                }
            } catch (e) {
                console.error("Erro na busca do dentista:", e);
                dentistaInfoP.innerHTML = '**Erro ao buscar dentista. Verifique a conexão.**';
                inputDentistaSelecionado.value = '';
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

// --- FUNÇÃO DE BUSCA DE HORÁRIOS DISPONÍVEIS (Usa POST para buscar_horarios.php) ---
async function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';
    divHorarios.innerHTML = '<p>Carregando horários...</p>';

    try {
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
        
        const horarios = dataJson.disponiveis || []; 

        if (horarios.length === 0) {
            divHorarios.innerHTML = `<p>${dataJson.message || 'Nenhum horário disponível para esta data.'}</p>`;
            return;
        }

        horarios.forEach(horario => {
            const btnHorario = document.createElement('div');
            btnHorario.className = 'horario-item';
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
        divHorarios.innerHTML = '<p>Erro ao carregar horários. Verifique a conexão e o arquivo buscar_horarios.php.</p>';
    }
}