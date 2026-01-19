/**
 * Designer de Etiquetas Marchef v6.0 - Versão "Zebra Designer Web"
 * Recursos: Drag&Drop, Resize, Lasso Select, Grupo, Copy/Paste, ZPL Import, Renderização Real.
 */

const MM_TO_PX = 3.78; // 96 DPI / 25.4 mm (Ajuste fino para tela)
var contadorId = 0;

// Estado Global
var clipboard = [];
var isLassoSelecting = false;
var lassoStartX, lassoStartY;

$(document).ready(function () {
    // 1. Configuração Inicial
    atualizarTamanhoCanvas(100, 150); // Default 100x150mm

    // 2. Atalhos de Teclado
    $(document).keydown(function (e) {
        if (e.key === "Delete") removerSelecionados();
        if (e.ctrlKey && (e.key === 'a' || e.key === 'A')) { e.preventDefault(); selecionarTodos(); }
        if (e.ctrlKey && (e.key === 'c' || e.key === 'C')) { e.preventDefault(); copiarElementos(); }
        if (e.ctrlKey && (e.key === 'v' || e.key === 'V')) { e.preventDefault(); colarElementos(); }
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            e.preventDefault();
            moverSelecionadosTeclado(e.key, e.shiftKey ? 10 : 1);
        }
    });

    // 3. Inputs de Propriedades (Tempo Real)
    $('#painel-propriedades input, #painel-propriedades textarea').on('input change', function () {
        $('.elemento-etiqueta.selecionado').each(function () {
            aplicarMudancasVisual($(this));
        });
    });

    // 4. Inicializa Seleção em Área (Lasso)
    setupLassoSelection();

    // 5. Configurações de Tamanho da Etiqueta
    $('#config-largura, #config-altura').on('change', function () {
        atualizarTamanhoCanvas($('#config-largura').val(), $('#config-altura').val());
    });

    // 6. Importador de ZPL (Legado)
    $('#input-import-prn').on('change', function (e) {
        let file = e.target.files[0];
        if (!file) return;
        let reader = new FileReader();
        reader.onload = function (e) { parseZPLToDesigner(e.target.result); };
        reader.readAsText(file);
        $(this).val('');
    });
});

// =========================================================
//  LÓGICA DE ALINHAMENTO (CORRIGIDA)
// =========================================================
function alinhar(modo) {
    let sels = $('.elemento-etiqueta.selecionado');
    if (sels.length < 2) {
        alert("Selecione pelo menos 2 elementos para alinhar.");
        return;
    }

    // Encontrar extremos
    let minLeft = 99999, maxRight = 0, minTop = 99999, maxBottom = 0;

    sels.each(function () {
        let p = $(this).position();
        let w = $(this).outerWidth();
        let h = $(this).outerHeight();

        if (p.left < minLeft) minLeft = p.left;
        if (p.left + w > maxRight) maxRight = p.left + w;
        if (p.top < minTop) minTop = p.top;
        if (p.top + h > maxBottom) maxBottom = p.top + h;
    });

    // Calcular centros
    let centerH = (minLeft + maxRight) / 2;
    let centerV = (minTop + maxBottom) / 2;

    sels.each(function () {
        let el = $(this);
        let w = el.outerWidth();
        let h = el.outerHeight();

        switch (modo) {
            case 'esquerda': el.css('left', minLeft + 'px'); break;
            case 'direita': el.css('left', (maxRight - w) + 'px'); break;
            case 'centro-h': el.css('left', (centerH - (w / 2)) + 'px'); break;

            case 'topo': el.css('top', minTop + 'px'); break;
            case 'base': el.css('top', (maxBottom - h) + 'px'); break;
            case 'centro-v': el.css('top', (centerV - (h / 2)) + 'px'); break;
        }
    });
}

// =========================================================
//  LÓGICA CORE: CRIAÇÃO DE ELEMENTOS
// =========================================================

function criarElemento(tipo, conteudo, xMm = 5, yMm = 5, wMm = 0, hMm = 0) {
    contadorId++;
    let id = 'el_' + contadorId;

    // Conversão MM -> PX
    let left = xMm * MM_TO_PX;
    let top = yMm * MM_TO_PX;
    let width = wMm > 0 ? wMm * MM_TO_PX : 'auto';
    let height = hMm > 0 ? hMm * MM_TO_PX : 'auto';

    // HTML Base
    let html = `<div id="${id}" class="elemento-etiqueta" data-tipo="${tipo}" tabindex="0">
                    <div class="conteudo-renderizado"></div>
                </div>`;

    $('#etiqueta-canvas').append(html);
    let el = $(`#${id}`);

    // Aplica Posição Inicial
    el.css({ top: top, left: left, position: 'absolute' });
    if (width !== 'auto') el.width(width);
    if (height !== 'auto') el.height(height);

    // Renderiza o Conteúdo (Barcode, QR, Texto...)
    renderizarConteudo(el, tipo, conteudo);

    // Adiciona Interatividade (JQuery UI)
    setupInteratividade(el, tipo);

    // Seleciona o novo elemento
    selecionarElemento(el);
    return el;
}

function renderizarConteudo(el, tipo, conteudo) {
    let container = el.find('.conteudo-renderizado');
    container.empty();
    el.attr('data-valor-original', conteudo); // Guarda valor original para edição

    switch (tipo) {
        case 'texto_fixo':
        case 'variavel':
            container.text(conteudo).css({
                'font-family': 'Arial, sans-serif',
                'white-space': 'nowrap',
                'width': '100%', 'height': '100%',
                'display': 'flex', 'align-items': 'center'
            });
            if (!el.css('font-size') || el.css('font-size') === '0px') el.css('font-size', '14px');
            break;

        case 'barcode':
            let canvasId = 'bc_' + el.attr('id');
            container.html(`<svg id="${canvasId}" style="width:100%; height:100%;"></svg>`);
            try {
                JsBarcode("#" + canvasId, conteudo, {
                    width: 2, height: 40, displayValue: true, margin: 0, background: "transparent"
                });
            } catch (e) { container.text("Erro Barcode"); }
            break;

        case 'qrcode':
            let qrDiv = $('<div>').css({ width: '100%', height: '100%' });
            container.append(qrDiv);
            try {
                new QRCode(qrDiv[0], {
                    text: conteudo,
                    width: el.width() || 80,
                    height: el.height() || 80,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) { container.text("QR"); }
            break;

        case 'imagem':
            container.css({
                'background-image': `url('${conteudo}')`,
                'background-size': 'contain',
                'background-repeat': 'no-repeat',
                'background-position': 'center',
                'width': '100%', 'height': '100%'
            });
            break;

        case 'linha':
            el.css({ 'background-color': '#000', 'height': (el.height() || 3) + 'px' });
            break;

        case 'retangulo':
            el.css({ 'border': '2px solid #000', 'background-color': 'transparent' });
            break;
    }
}

function setupInteratividade(el, tipo) {
    // 1. DRAG (Mover)
    el.draggable({
        containment: ".canvas-area",
        start: function (event, ui) {
            if (!$(this).hasClass('selecionado')) selecionarElemento($(this), false);

            // Guarda posição inicial para mover em grupo
            $('.elemento-etiqueta.selecionado').each(function () {
                $(this).data('start-pos', $(this).position());
            });
        },
        drag: function (event, ui) {
            let current = $(this);
            let start = current.data('start-pos');
            let dx = ui.position.left - start.left;
            let dy = ui.position.top - start.top;

            $('.elemento-etiqueta.selecionado').not(current).each(function () {
                let s = $(this).data('start-pos');
                $(this).css({ left: s.left + dx, top: s.top + dy });
            });
        }
    });

    // 2. RESIZE (Redimensionar)
    let handles = "n, e, s, w, se, sw, ne, nw";
    if (tipo === 'linha') handles = "e, w, s, n";

    el.resizable({
        containment: "document",
        handles: handles,
        aspectRatio: (tipo === 'qrcode'),
        resize: function (event, ui) {
            let elemento = $(this);
            let w = ui.size.width;
            let h = ui.size.height;

            // --- LÓGICA DE AUTO-FIT PARA TEXTO ---
            if (tipo === 'texto_fixo' || tipo === 'variavel') {
                // Chama a nossa nova função inteligente
                autoAjustarTexto(elemento, w, h);
            }
            // Lógica para QR Code (Re-renderizar para não pixelizar)
            else if (tipo === 'qrcode') {
                let c = elemento.find('.conteudo-renderizado'); c.empty();
                new QRCode(c[0], {
                    text: elemento.attr('data-valor-original'),
                    width: w, height: h
                });
            }
        },
        stop: function () { atualizarUISelecao(); }
    });

    // Clique para Selecionar
    el.on('mousedown', function (e) {
        e.stopPropagation();
        if (e.ctrlKey) toggleSelecao($(this));
        else if (!$(this).hasClass('selecionado')) selecionarElemento($(this), false);
    });
}

// =========================================================
//  FERRAMENTAS DE UI (Botões)
// =========================================================

function addTexto() { criarElemento('texto_fixo', 'Texto', 10, 10); }
function addRetangulo() { criarElemento('retangulo', '', 10, 25, 30, 20); }
function addLinha() { criarElemento('linha', '', 10, 50, 40, 0.8); } // 0.8mm altura
function addBarcode() { criarElemento('barcode', '1234567890', 10, 10, 50, 15); }
function addQRCode() { criarElemento('qrcode', 'https://marchef.com.br', 65, 10, 25, 25); }

function selecionarVariavel(campo) {
    criarElemento('variavel', '{' + campo + '}', 10, 10);
    $('#modalVariaveis').modal('hide');
}

function triggerUploadImagem() { $('#input-img-upload').click(); }

// Upload de Imagem
$('#input-img-upload').on('change', function (e) {
    let file = e.target.files[0];
    if (file) {
        let reader = new FileReader();
        reader.onload = function (evt) {
            criarElemento('imagem', evt.target.result, 10, 10, 30, 30);
        };
        reader.readAsDataURL(file);
    }
    $(this).val('');
});

// =========================================================
//  SELEÇÃO E ÁREA DE TRANSFERÊNCIA
// =========================================================

function selecionarElemento(el, add = false) {
    if (!add) $('.elemento-etiqueta').removeClass('selecionado');
    el.addClass('selecionado');
    atualizarUISelecao();
}

function toggleSelecao(el) {
    el.toggleClass('selecionado');
    atualizarUISelecao();
}

function selecionarTodos() {
    $('.elemento-etiqueta').addClass('selecionado');
    atualizarUISelecao();
}

function removerSelecionados() {
    $('.elemento-etiqueta.selecionado').remove();
    atualizarUISelecao();
}

function moverSelecionadosTeclado(key, step) {
    $('.elemento-etiqueta.selecionado').each(function () {
        let p = $(this).position();
        if (key === 'ArrowUp') $(this).css('top', p.top - step);
        if (key === 'ArrowDown') $(this).css('top', p.top + step);
        if (key === 'ArrowLeft') $(this).css('left', p.left - step);
        if (key === 'ArrowRight') $(this).css('left', p.left + step);
    });
}

function copiarElementos() {
    clipboard = [];
    $('.elemento-etiqueta.selecionado').each(function () {
        let el = $(this);
        clipboard.push({
            tipo: el.data('tipo'),
            conteudo: el.attr('data-valor-original'),
            w: el.width() / MM_TO_PX, h: el.height() / MM_TO_PX, // Salva em MM
            style: el.attr('style') // Copia estilos inline brutos
        });
    });
}

function colarElementos() {
    if (clipboard.length === 0) return;
    $('.elemento-etiqueta').removeClass('selecionado'); // Limpa seleção atual

    clipboard.forEach(item => {
        // Cria elemento com pequeno offset
        let el = criarElemento(item.tipo, item.conteudo, 0, 0);

        // Reaplica estilos copiados (gambiarra controlada para manter fontes e cores)
        // O ideal é copiar propriedade por propriedade, mas para demo isso serve
        let oldTop = parseFloat(el.css('top')) || 0;
        let oldLeft = parseFloat(el.css('left')) || 0;

        el.css({
            width: (item.w * MM_TO_PX) + 'px',
            height: (item.h * MM_TO_PX) + 'px',
            top: (oldTop + 20) + 'px',
            left: (oldLeft + 20) + 'px'
        });

        // Re-renderiza conteúdo visual complexo
        renderizarConteudo(el, item.tipo, item.conteudo);
    });
}

// =========================================================
//  PAINEL DE PROPRIEDADES (Direita)
// =========================================================

function atualizarUISelecao() {
    let sels = $('.elemento-etiqueta.selecionado');

    // Alinhamento
    if (sels.length > 1) $('.toolbar-alinhamento').fadeIn().css('display', 'flex');
    else $('.toolbar-alinhamento').fadeOut();

    // Propriedades
    if (sels.length === 1) {
        let el = $(sels[0]);
        let tipo = el.data('tipo');
        $('#painel-propriedades').fadeIn();

        // Dimensões
        $('#prop-largura').val(Math.round(el.width()));
        $('#prop-altura').val(Math.round(el.height()));

        // Campos Específicos
        if (['texto_fixo', 'variavel'].includes(tipo)) {
            $('.prop-texto-only').show(); $('.prop-conteudo-box').show();
            $('#prop-conteudo').val(el.attr('data-valor-original'));
            $('#prop-tamanho').val(parseInt(el.css('font-size')));
            $('#prop-negrito').prop('checked', el.css('font-weight') === 'bold' || parseInt(el.css('font-weight')) >= 700);
        } else if (['barcode', 'qrcode'].includes(tipo)) {
            $('.prop-texto-only').hide(); $('.prop-conteudo-box').show();
            $('#prop-conteudo').val(el.attr('data-valor-original'));
        } else {
            $('.prop-texto-only').hide(); $('.prop-conteudo-box').hide();
        }
    } else {
        $('#painel-propriedades').fadeOut();
    }
}

function aplicarMudancasVisual(el) {
    let tipo = el.data('tipo');

    // Dimensões
    if (tipo !== 'texto_fixo' && tipo !== 'variavel') {
        el.css({
            width: $('#prop-largura').val() + 'px',
            height: $('#prop-altura').val() + 'px'
        });
    }

    // Conteúdo
    if (['texto_fixo', 'variavel', 'barcode', 'qrcode'].includes(tipo)) {
        let novoVal = $('#prop-conteudo').val();
        if (novoVal !== el.attr('data-valor-original')) {
            renderizarConteudo(el, tipo, novoVal);
        }
    }

    // Estilo Texto
    if (['texto_fixo', 'variavel'].includes(tipo)) {
        el.css('font-size', $('#prop-tamanho').val() + 'px');
        el.css('font-weight', $('#prop-negrito').is(':checked') ? 'bold' : 'normal');
    }
}

// =========================================================
//  IMPORTAÇÃO DE ZPL AVANÇADA (SUPORTE A ^FT E ^GFA)
// =========================================================
function parseZPLToDesigner(zpl) {
    $('.elemento-etiqueta').remove(); // Limpa tela

    // 1. Tenta achar dimensões (^PW, ^LL)
    // Assumindo 8 dots/mm (203 DPI) - Padrão Zebra mais comum
    // Se sua impressora for 300 DPI, mude para 12
    const DPI_DIVISOR = 8;

    let w = zpl.match(/\^PW(\d+)/);
    let h = zpl.match(/\^LL(\d+)/);

    if (w && h) {
        let larguraMm = Math.round(parseInt(w[1]) / DPI_DIVISOR);
        let alturaMm = Math.round(parseInt(h[1]) / DPI_DIVISOR);

        $('#config-largura').val(larguraMm);
        $('#config-altura').val(alturaMm);
        atualizarTamanhoCanvas(larguraMm, alturaMm);
    }

    // 2. Limpeza prévia para facilitar o Regex
    // Remove quebras de linha para tratar tudo como string única
    let zplClean = zpl.replace(/(\r\n|\n|\r)/gm, "");

    // 3. Estratégia de Captura:
    // O ZPL não é estruturado, então vamos buscar padrões de comandos de campo.
    // Regex explica: Procura ^F(O ou T) seguido de x,y ... até encontrar um ^FS (Field Separator)
    // Isso captura um bloco inteiro de comando.
    let regexElementos = /\^F[OT](\d+),(\d+)(.*?)(\^FS)/g;
    let match;

    while ((match = regexElementos.exec(zplClean)) !== null) {
        let xDots = parseInt(match[1]);
        let yDots = parseInt(match[2]);
        let corpoComando = match[3]; // O que tem dentro (Fonte, Dados, Barcode...)

        let x = xDots / DPI_DIVISOR;
        let y = yDots / DPI_DIVISOR;

        // --- Ajuste Fino para ^FT (Field Typeset) ---
        // O ^FT define a posição pela BASE da fonte, não pelo topo. 
        // Para o designer visual (que usa Top/Left), o elemento vai parecer que "subiu" um pouco.
        // Vamos tentar compensar levemente ou aceitar que o usuário precise ajustar.
        let isFT = match[0].startsWith('^FT');

        // Extrai o conteúdo (^FD...)
        let fdMatch = corpoComando.match(/\^FD(.*?)$/); // Pega até o fim do bloco capturado
        // As vezes o ^FD tem caracteres de escape, vamos simplificar
        let conteudo = fdMatch ? fdMatch[1] : '';

        // Remove caracteres de escape do ZPL se houver (ex: \^ torna-se ^)
        conteudo = conteudo.replace(/\\/g, '');

        // --- IDENTIFICAÇÃO DO TIPO ---

        if (corpoComando.includes('^BC')) {
            // CÓDIGO DE BARRAS 128
            // Tenta achar altura ^BCo,h,...
            let bcMatch = corpoComando.match(/\^BC[A-Z]?,(\d+)/);
            let hBar = bcMatch ? parseInt(bcMatch[1]) / DPI_DIVISOR : 15;

            criarElemento('barcode', conteudo, x, y, 50, hBar);

        } else if (corpoComando.includes('^BQ')) {
            // QR CODE
            // Limpa o prefixo QA, ou LA, comum em QR Codes ZPL
            let qrContent = conteudo.replace(/^[QLM][A-Z],/, '');
            criarElemento('qrcode', qrContent, x, y, 25, 25); // Tamanho estimado

        } else if (corpoComando.includes('^GB')) {
            // LINHAS E CAIXAS (Graphic Box)
            let gb = corpoComando.match(/\^GB(\d+),(\d+),(\d+)/);
            if (gb) {
                let wBox = parseInt(gb[1]) / DPI_DIVISOR;
                let hBox = parseInt(gb[2]) / DPI_DIVISOR;
                let border = parseInt(gb[3]) / DPI_DIVISOR;

                if (hBox <= 2 || wBox <= 2) {
                    // É uma linha
                    criarElemento('linha', '', x, y, wBox, Math.max(hBox, 1));
                } else {
                    // É um retângulo
                    criarElemento('retangulo', '', x, y, wBox, hBox);
                }
            }
        } else if (corpoComando.includes('^GFA')) {
            // IMAGEM BINÁRIA (Logo)
            // É muito difícil converter hex ZPL volta para imagem no browser.
            // Vamos criar um placeholder cinza.
            let el = criarElemento('imagem', '', x, y, 30, 30);
            el.css('background-color', '#ccc');
            el.find('.conteudo-renderizado').text('IMG (Upload)');

        } else {
            // TEXTO (Padrão)
            // Tenta detectar tamanho da fonte ^A0N,h,w
            let fontMatch = corpoComando.match(/\^A[0-9A-Z]?[N,R]?,(\d+),(\d+)/);
            let fontSize = 14; // Default
            if (fontMatch) {
                // A altura da fonte no ZPL é em dots. Convertendo para PX de tela aprox.
                let hFontDots = parseInt(fontMatch[1]);
                fontSize = (hFontDots / DPI_DIVISOR) * MM_TO_PX * 0.8; // Fator de ajuste visual
            }

            // Se for FT, subtrai a altura da fonte do Y para aproximar a posição visual
            if (isFT) {
                y -= (fontSize / MM_TO_PX);
            }

            // Verifica se é uma variável do seu sistema (ex: linhaPeso, nomeFantasia)
            // Se parecer variável (camelCase e sem espaços), tratamos como variável? 
            // Por enquanto tratamos tudo como texto, mas o usuário pode mudar o tipo depois.

            let tipoElemento = 'texto_fixo';
            // Detecção simples de variável: começa minuscula, tem maiuscula no meio, sem espaço
            if (/^[a-z]+[A-Z][a-zA-Z0-9]*$/.test(conteudo)) {
                tipoElemento = 'variavel';
                conteudo = '{' + conteudo + '}'; // Adiciona chaves para visualização
            }

            criarElemento(tipoElemento, conteudo, x, y);

            // Aplica o tamanho da fonte detectado no ultimo elemento criado
            let ultimoEl = $('.elemento-etiqueta').last();
            ultimoEl.css('font-size', Math.round(fontSize) + 'px');
        }
    }

    // Tratamento especial para Imagens ^GFA que usam ^FO separado (seu caso tem isso)
    // O loop acima pega ^FD, mas ^GFA não usa ^FD, usa dados diretos.
    let regexGFA = /\^FO(\d+),(\d+)\^GFA/g;
    while ((match = regexGFA.exec(zplClean)) !== null) {
        let x = parseInt(match[1]) / DPI_DIVISOR;
        let y = parseInt(match[2]) / DPI_DIVISOR;

        let el = criarElemento('imagem', '', x, y, 40, 40); // Tamanho estimado
        el.css('background-color', '#e0e0e0');
        el.css('border', '1px dashed #666');
        el.find('.conteudo-renderizado')
            .html('<div style="font-size:10px; text-align:center; padding-top:10px">LOGO ZPL<br>(Substitua)</div>');
    }

    alert('Importação Concluída! Ajuste as posições conforme necessário.');
}

// =========================================================
//  AUXILIARES
// =========================================================
function atualizarTamanhoCanvas(w, h) {
    $('#etiqueta-canvas').css({ width: w * MM_TO_PX + 'px', height: h * MM_TO_PX + 'px' });
}

function setupLassoSelection() {
    $('.canvas-area').on('mousedown', function (e) {
        if ($(e.target).closest('.elemento-etiqueta, .toolbar-alinhamento').length > 0) return;
        if (!e.ctrlKey) $('.elemento-etiqueta').removeClass('selecionado');

        isLassoSelecting = true;
        let offset = $(this).offset();
        lassoStartX = e.pageX - offset.left + $(this).scrollLeft();
        lassoStartY = e.pageY - offset.top + $(this).scrollTop();

        $('<div class="lasso-box"></div>').appendTo('.canvas-area').css({
            left: lassoStartX, top: lassoStartY, width: 0, height: 0
        });
    });

    $(document).on('mousemove', function (e) {
        if (!isLassoSelecting) return;
        let offset = $('.canvas-area').offset();
        let curX = e.pageX - offset.left + $('.canvas-area').scrollLeft();
        let curY = e.pageY - offset.top + $('.canvas-area').scrollTop();

        let w = Math.abs(curX - lassoStartX);
        let h = Math.abs(curY - lassoStartY);
        let l = (curX < lassoStartX) ? curX : lassoStartX;
        let t = (curY < lassoStartY) ? curY : lassoStartY;

        $('.lasso-box').css({ left: l, top: t, width: w, height: h });
    });

    $(document).on('mouseup', function () {
        if (!isLassoSelecting) return;
        isLassoSelecting = false;
        let lasso = $('.lasso-box');
        if (lasso.length) {
            // Detecção Simples de Colisão
            let l = lasso.offset();
            let lW = lasso.width(), lH = lasso.height();
            $('.elemento-etiqueta').each(function () {
                let el = $(this).offset();
                if (l.left < el.left + $(this).outerWidth() && l.left + lW > el.left &&
                    l.top < el.top + $(this).outerHeight() && l.top + lH > el.top) {
                    selecionarElemento($(this), true);
                }
            });
            lasso.remove();
        }
    });
}

/**
 * Calcula o tamanho de fonte ideal para caber dentro de W e H.
 * Garante que o texto não vaze nem na largura nem na altura.
 */
function autoAjustarTexto(el, containerW, containerH) {
    let texto = el.text();
    // 1. Estimativa baseada na altura (geralmente altura da caixa * 0.7 dá uma boa margem para acentos)
    let fontSizePorAltura = containerH * 0.75;

    // 2. Verificação da Largura usando Canvas (preciso e rápido)
    // Criamos um contexto canvas virtual apenas para medir pixels
    let canvas = document.createElement('canvas');
    let ctx = canvas.getContext("2d");

    // Define a fonte baseada na altura primeiro
    ctx.font = "bold " + fontSizePorAltura + "px Arial";
    // Se o elemento não for negrito, tire o "bold" acima. Para ser perfeito, teríamos que checar o CSS.
    // Mas para performance, assumir um padrão ou ler uma vez é melhor.
    if (el.css('font-weight') < 400) ctx.font = fontSizePorAltura + "px Arial";

    let larguraTextoCalculada = ctx.measureText(texto).width;

    // 3. O Fator de Ajuste
    let finalFontSize = fontSizePorAltura;

    // Se a largura do texto for maior que a largura da caixa, temos que diminuir a fonte
    if (larguraTextoCalculada > containerW) {
        let ratio = containerW / larguraTextoCalculada;
        finalFontSize = fontSizePorAltura * ratio;
    }

    // 4. Limites de segurança (mínimo 6px para não sumir)
    if (finalFontSize < 6) finalFontSize = 6;

    // Aplica
    el.css('font-size', finalFontSize + 'px');
    el.css('line-height', containerH + 'px'); // Centraliza verticalmente
    el.css('white-space', 'nowrap'); // Garante que não quebre linha
}