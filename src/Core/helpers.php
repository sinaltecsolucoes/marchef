<?php
// /src/Core/helpers.php

/**
 * Retorna um valor de sessão HTML-escapado ou um valor padrão.
 */
function get_session_data_attr(string $key, $default = ''): string
{
    return htmlspecialchars($_SESSION[$key] ?? $default);
}

/**
 * Renderiza os itens de um menu dropdown com base nas permissões do usuário.
 *
 * @param array $paginasPermitidas Um array associativo de 'nomeLink' => 'arquivo.php'.
 * @param array $paginasPermitidasUsuario Um array simples com os nomes das páginas permitidas.
 * @param string $baseUrl A URL base da aplicação para criar links absolutos.
 * @return string O HTML dos itens do menu.
 */
// A função agora recebe a BASE_URL como um terceiro parâmetro
function render_menu_items(array $paginasPermitidas, array $paginasPermitidasUsuario, string $baseUrl): string
{
    $html = '';
    foreach ($paginasPermitidas as $nomeLink => $arquivo) {
        if ($nomeLink == 'home' || $nomeLink == 'permissoes') {
            continue;
        }

        if (in_array($nomeLink, $paginasPermitidasUsuario)) {
            $html .= '<li>';
            // AQUI ESTÁ A CORREÇÃO: Usando a $baseUrl e "?page="
            $html .= '<a class="dropdown-item" href="' . $baseUrl . '/index.php?page=' . $nomeLink . '">';
            $html .= ucfirst($nomeLink); // ucfirst() capitaliza a primeira letra
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    return $html;
}

// Suas outras funções de validação continuam aqui, sem alterações...
function validate_string($value, $minLength = 1, $maxLength = 255, $allowedCharsRegex = null) { /* ... */ }
function validate_selection($value, array $allowedValues) { /* ... */ }

?>