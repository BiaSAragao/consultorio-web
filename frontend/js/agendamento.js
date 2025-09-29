// --- LÓGICA PARA O FORMULÁRIO DE AGENDAMENTO EM PASSOS ---

document.addEventListener('DOMContentLoaded', () => {
    
    const inputDentistaId = document.getElementById('dentista');
    const inputData = document.getElementById('data');
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    const inputServicosValidacao = document.getElementById('servicos_validacao');

    // Inicializa o formulário no primeiro passo
    irParaPasso(1);

    // --- EVENTO DE SELEÇÃO DE SERVIÇO ---
    const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
    checkboxesServicos.forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
            // Se desmarcou, verifica se ainda há algum serviço marcado da mesma categoria
            const categoriaId = this.dataset.categoria;
            const servicosSelecionados = Array.from(checkboxesServicos).filter(cb => cb.checked && cb.dataset.categoria === categoriaId);

            if (servicosSelecionados.length === 0) {
                // Nenhum serviço dessa categoria marcado, limpa o dentista
                inputDentistaId.value = '';
                document.getElementById('dentista_info').innerText = '**Selecione um serviço para ver o dentista responsável**';
                divHorarios.innerHTML = '<p>Selecione uma data para ver os horários.</p>';
                inputHorarioSelecionado.value = '';
                return;
            }

            // Pega a categoria do primeiro serviço marcado para buscar o dentista
            const categoriaBusca = servicosSelecionados[0].dataset.categoria;

            try {
                const resp = await fetch(`../backend/get_dentista.php?categoria_id=${categoriaBusca}`);
                const dentista = await resp.json();
                if (dentista && dentista.usuario_id) {
                    document.getElementById('dentista_info').innerText = dentista.nome + ' (CRO: ' + dentista.cro + ')';
                    inputDentistaId.value = dentista.usuario_id;
                    // Se já houver data selecionada, busca os horários desse dentista
                    if (inputData.value) {
                        buscarHorarios(inputData.value, dentista.usuario_id);
                    }
                }
            } catch (e) {
                console.error(e);
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
            const dentistaId = inputDentistaId.value;

            if (data && dentistaId) {
                buscarHorarios(data, dentistaId);
            } else {
                divHorarios.innerHTML = '<p>Selecione um serviço para ver o dentista e horários.</p>';
            }
        });
    }
});

// --- FUNÇÕES DE NAVEGAÇÃO DE PASSOS ---
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
        const checkboxesServicos = document.querySelectorAll('input[name="servicos[]"]');
        const servicosSelecionados = Array.from(checkboxesServicos).some(cb => cb.checked);

        if (!servicosSelecionados) {
            alert(document.getElementById('servicos_validacao').dataset.errorMessage);
            valido = false;
        } else {
            document.getElementById('servicos_validacao').value = 'selecionado';
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

// --- FUNÇÃO DE BUSCA DE HORÁRIOS DISPONÍVEIS ---
async function buscarHorarios(data, dentistaId) {
    const divHorarios = document.getElementById('lista-horarios');
    const inputHorarioSelecionado = document.getElementById('horario_selecionado');
    inputHorarioSelecionado.value = '';
    divHorarios.innerHTML = '<p>Carregando horários...</p>';

    try {
        const resp = await fetch(`../backend/get_horarios.php?dentista_id=${dentistaId}&data=${data}`);
        const horarios = await resp.json();

        divHorarios.innerHTML = '';
        if (!horarios || horarios.length === 0) {
            divHorarios.innerHTML = '<p>Nenhum horário disponível para esta data.</p>';
            return;
        }

        horarios.forEach(horario => {
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
    } catch (e) {
        console.error(e);
        divHorarios.innerHTML = '<p>Erro ao carregar horários.</p>';
    }
}
