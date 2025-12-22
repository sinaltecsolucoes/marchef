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
            $html .= '<a class="dropdown-item" href="' . $baseUrl . '/index.php?page=' . $nomeLink . '">';
            $html .= ucfirst($nomeLink); // ucfirst() capitaliza a primeira letra
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    return $html;
}

/**
 * Converte recursivamente os valores de um array para maiúsculas,
 * ignorando campos sensíveis e parâmetros de controlo do DataTables.
 * * @param array $data O array de dados (ex: $_POST)
 * @return array O array processado
 */
function sanitize_upper(array $data): array
{
    // Campos que NÃO devem ser alterados (Senhas, E-mails, Arquivos, Controlos)
    $camposIgnorados = [
        // 1. Segurança e Dados Sensíveis
        'usu_senha',
        'senha',
        'confirmar_senha',
        'password',
        'email',
        'ent_email',
        'usu_tipo',
        'permissao_pagina',

        // 2. Arquivos e Caminhos
        'config_logo_path',
        'fila_foto_path',
        'foto',
        'arquivo',
        'caminho',
        'permissoes',
        'produto_especie',
        'prod_especie',
        'produto_denominacao',
        'criterios',
        'criterio_grupo',
        'criterio_nome',
        'criterio_unidade',
        'criterio_valor',
        'ficha_medidas_emb_primaria',
        'ficha_medidas_emb_secundaria',
        'tipo_entrada_mp',
        'itemType',
        

        // 3. Tokens e Sistema
        'csrf_token',
        'token',
        'action',
        'page',
        'controller',
        'id',
        'ficha_id',
        'resumo_id', // IDs geralmente não precisam de uppercase
        'html_content', // Conteúdo rico

        // 4. Parâmetros do DataTables (ESSENCIAIS para não quebrar as listas)
        'draw',
        'columns',
        'order',
        'start',
        'length',
        'search',

        // 5. Filtros de Listagem (ESSENCIAIS para os dados aparecerem)
        'filtro_situacao',
        'filtro_tipo_entidade',
        'tipo_entidade',
        'filtro_data_inicio',
        'filtro_data_fim',
        'filtro_status'
    ];

    foreach ($data as $key => $value) {
        // Se for um array (ex: search[value] do DataTables), chama recursivamente
        if (is_array($value)) {
            // Se a chave do array for um dos ignorados (ex: 'columns'), não mexemos nos filhos
            if (in_array($key, $camposIgnorados)) {
                $data[$key] = $value;
            } else {
                $data[$key] = sanitize_upper($value);
            }
        }
        // Se for string e a chave NÃO estiver na lista de ignorados
        elseif (is_string($value) && !in_array($key, $camposIgnorados)) {
            // Converte para maiúsculo
            $data[$key] = mb_strtoupper($value, 'UTF-8');
        }
    }

    return $data;
}

// Suas outras funções de validação continuam aqui, sem alterações...
function validate_string($value, $minLength = 1, $maxLength = 255, $allowedCharsRegex = null)
{ /* ... */
}
function validate_selection($value, array $allowedValues)
{ /* ... */
}
