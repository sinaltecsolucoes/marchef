<?php
// includes/helpers.php

/**
 * Retorna um valor de sessão HTML-escapado ou um valor padrão.
 * Útil para preencher atributos data-* ou exibir valores de sessão com segurança.
 *
 * @param string $key A chave da variável de sessão (ex: 'nomeUsuario').
 * @param mixed $default O valor padrão a ser retornado se a chave não existir na sessão.
 * @return string O valor HTML-escapado da sessão ou o valor padrão.
 */
function get_session_data_attr(string $key, $default = ''): string
{
    return htmlspecialchars($_SESSION[$key] ?? $default);
}

/**
 * Renderiza os itens de um menu dropdown com base nas permissões do usuário.
 *
 * @param array $paginasPermitidas Um array associativo de 'nomeLink' => 'arquivo.php'.
 * @param array $paginasPermitidasUsuario Um array simples com os nomes das páginas permitidas para o usuário logado.
 * @return string O HTML dos itens do menu.
 */
function render_menu_items(array $paginasPermitidas, array $paginasPermitidasUsuario): string
{
    $html = '';
    foreach ($paginasPermitidas as $nomeLink => $arquivo) {
        // Exclui 'home' e 'permissoes' do menu "Cadastros"
        if ($nomeLink == 'home' || $nomeLink == 'permissoes') {
            continue;
        }

        // Verifica se o usuário tem permissão para este link antes de exibi-lo
        if (in_array($nomeLink, $paginasPermitidasUsuario)) {
            $html .= '<li>';
            $html .= '<a class="dropdown-item" href="index.php?pag=' . $nomeLink . '">';
            $html .= ucfirst($nomeLink); // ucfirst() capitaliza a primeira letra
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    return $html;
}

/**
 * Valida e sanitiza uma string.
 *
 * @param string $value O valor da string a ser validada.
 * @param int $minLength O comprimento mínimo permitido para a string.
 * @param int $maxLength O comprimento máximo permitido para a string.
 * @param string|null $allowedCharsRegex Uma expressão regular para caracteres permitidos (ex: '/^[a-zA-Z\s]+$/u').
 * @return array Um array associativo com 'valid' (booleano) e 'message' (string) ou 'value' (string sanitizada).
 */
function validate_string($value, $minLength = 1, $maxLength = 255, $allowedCharsRegex = null) {
    $value = trim($value);
    if (empty($value)) {
        return ['valid' => false, 'message' => 'Campo obrigatório.'];
    }
    if (mb_strlen($value) < $minLength) {
        return ['valid' => false, 'message' => "Mínimo de {$minLength} caracteres."];
    }
    if (mb_strlen($value) > $maxLength) {
        return ['valid' => false, 'message' => "Máximo de {$maxLength} caracteres."];
    }
    if ($allowedCharsRegex && !preg_match($allowedCharsRegex, $value)) {
        return ['valid' => false, 'message' => 'Contém caracteres inválidos.'];
    }
    return ['valid' => true, 'value' => $value];
}

/**
 * Valida se um valor está em uma lista de valores permitidos.
 *
 * @param string $value O valor a ser validado.
 * @param array $allowedValues Um array de valores permitidos.
 * @return array Um array associativo com 'valid' (booleano) e 'message' (string) ou 'value' (string).
 */
function validate_selection($value, array $allowedValues) {
    $value = trim($value);
    if (!in_array($value, $allowedValues, true)) {
        return ['valid' => false, 'message' => 'Valor inválido selecionado.'];
    }
    return ['valid' => true, 'value' => $value];
}

?>
