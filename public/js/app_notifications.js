// /public/js/app_notifications.js

/**
 * Exibe uma notificação de sucesso que fecha automaticamente.
 * @param {string} title O título da mensagem.
 * @param {string} text O texto opcional da mensagem.
 */
function notificacaoSucesso(title, text = '') {
    Swal.fire({
        icon: 'success',
        title: title,
        text: text,
        timer: 2000, // Fecha após 2 segundos
        showConfirmButton: false
    });
}

/**
 * Exibe uma notificação de erro.
 * @param {string} title O título do erro.
 * @param {string} text O texto opcional do erro.
 */
function notificacaoErro(title, text = '') {
    Swal.fire({
        icon: 'error',
        title: title,
        text: text
    });
}

/**
 * Exibe um modal de confirmação para uma ação.
 * Retorna uma promessa que resolve se o usuário confirmar.
 * @param {string} title O título da confirmação (ex: "Remover Fila?").
 * @param {string} text O texto descritivo da ação.
 * @returns {Promise<any>}
 */
function confirmacaoAcao(title, text) {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, confirmar!',
        cancelButtonText: 'Cancelar'
    });
}