document.addEventListener('DOMContentLoaded', function () {
    const switchSituacao = document.getElementById('situacao-perfil');
    const textoSituacao = document.getElementById('texto-situacao-perfil');
    const perfilModal = document.getElementById('perfil');

    // Funções auxiliares (já existiam)
    function atualizarTextoSwitch() {
        if (switchSituacao && textoSituacao) { // Verifica novamente dentro da função
            if (switchSituacao.checked) {
                textoSituacao.textContent = 'Ativo';
            } else {
                textoSituacao.textContent = 'Desativado';
            }
        }
    }

    if (perfilModal) {
        perfilModal.addEventListener('show.bs.modal', function (event) {
            // Obtenha os elementos do formulário
            const nomeInput = document.getElementById('nome-perfil');
            const loginInput = document.getElementById('login-perfil');
            const senhaInput = document.getElementById('senha-perfil');
            const nivelSelect = document.getElementById('nivel-perfil');
            const exibirSenhaCheckbox = document.getElementById('exibir-senha-perfil');

            // Limpar campo de senha e desmarcar exibir senha ao abrir o modal
            if (senhaInput) senhaInput.value = '';
            if (exibirSenhaCheckbox) {
                exibirSenhaCheckbox.checked = false;
                if (senhaInput) senhaInput.type = 'password';
            }

            // PEGA OS DADOS DOS ATRIBUTOS DATA- DO MODAL
            const modalElement = event.currentTarget; // O modal que está sendo aberto
            const nomeUsuario = modalElement.dataset.nomeUsuario;
            const loginUsuario = modalElement.dataset.loginUsuario;
            const situacaoUsuario = modalElement.dataset.situacaoUsuario === 'A'; // Converte para booleano
            const tipoUsuario = modalElement.dataset.tipoUsuario;

            // PREENCHE OS CAMPOS DO FORMULÁRIO COM OS DADOS RECUPERADOS
            if (nomeInput) nomeInput.value = nomeUsuario;
            if (loginInput) loginInput.value = loginUsuario;

            if (switchSituacao) {
                switchSituacao.checked = situacaoUsuario;
                atualizarTextoSwitch(); // Atualiza o texto do switch
            }

            if (nivelSelect) {
                nivelSelect.value = tipoUsuario; // Define a opção selecionada
            }
        });
    }

    // Listener para o switch de situação (fora do show.bs.modal)
    if (switchSituacao) {
         switchSituacao.addEventListener('change', atualizarTextoSwitch);
    }

    // Script para exibir/ocultar senha (já tinha)
    const senhaInput = document.getElementById('senha-perfil');
    const exibirSenhaCheckbox = document.getElementById('exibir-senha-perfil');

    if (senhaInput && exibirSenhaCheckbox) {
        exibirSenhaCheckbox.addEventListener('change', function () {
            if (this.checked) {
                senhaInput.type = 'text';
            } else {
                senhaInput.type = 'password';
            }
        });
    }
});