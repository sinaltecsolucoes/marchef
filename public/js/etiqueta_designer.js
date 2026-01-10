/**
 * Designer de Etiquetas Marchef v5.0 (Correção Lasso, Scroll e Resize Texto)
 */
const MM_TO_PX = 3.78;
var contadorId = 0;

// Variáveis para Seleção em Área (Lasso)
var isLassoSelecting = false;
var lassoStartX, lassoStartY;

$(document).ready(function () {
    atualizarTamanhoCanvas(100, 150);

    // Configuração Inicial
    $('#config-largura, #config-altura').on('change', function () {
        atualizarTamanhoCanvas($('#config-largura').val(), $('#config-altura').val());
    });

    // Inputs de propriedade (tempo real)
    $('#painel-propriedades input, #painel-propriedades textarea').on('input change', function () {
        $('.elemento-etiqueta.selecionado').each(function () {
            aplicarMudancasVisual($(this));
        });
    });

    // Delete via teclado
    $(document).keydown(function (e) {
        if (e.key === "Delete") removerSelecionados();
    });

    // Upload Imagem
    $('#input-img-upload').on('change', function (e) {
        let file = e.target.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function (readerEvent) { criarElementoImagem(readerEvent.target.result); }
            reader.readAsDataURL(file);
        }
        $(this).val('');
    });

    // --- CORREÇÃO DO LASSO (Considerando Scroll) ---
    $('.canvas-area').on('mousedown', function (e) {
        // Se clicou num elemento ou na toolbar, ignora o lasso
        if ($(e.target).closest('.elemento-etiqueta, .toolbar-alinhamento').length > 0) return;

        if (!e.ctrlKey) deselecionarTudo();

        isLassoSelecting = true;

        // CORREÇÃO MATEMÁTICA: Posição Mouse - Offset Container + Scroll Container
        let container = $(this);
        let offset = container.offset();
        lassoStartX = e.pageX - offset.left + container.scrollLeft();
        lassoStartY = e.pageY - offset.top + container.scrollTop();

        $('<div class="lasso-box"></div>').appendTo('.canvas-area').css({
            left: lassoStartX, top: lassoStartY, width: 0, height: 0
        });
    });

    $(document).on('mousemove', function (e) {
        if (!isLassoSelecting) return;

        let container = $('.canvas-area');
        let offset = container.offset();

        // Mesma correção aqui para o movimento
        let currentX = e.pageX - offset.left + container.scrollLeft();
        let currentY = e.pageY - offset.top + container.scrollTop();

        let width = Math.abs(currentX - lassoStartX);
        let height = Math.abs(currentY - lassoStartY);
        let newLeft = (currentX < lassoStartX) ? currentX : lassoStartX;
        let newTop = (currentY < lassoStartY) ? currentY : lassoStartY;

        $('.lasso-box').css({ left: newLeft, top: newTop, width: width, height: height });
    });

    $(document).on('mouseup', function (e) {
        if (!isLassoSelecting) return;
        isLassoSelecting = false;

        let lasso = $('.lasso-box');
        if (lasso.length) {
            detectarColisaoLasso(lasso);
            lasso.remove();
        }
    });
});

// --- LÓGICA DE SELEÇÃO E ALINHAMENTO ---

function detectarColisaoLasso(lasso) {
    // Usamos offset global para comparar posições na tela
    let l = lasso.offset();
    let lLeft = l.left;
    let lTop = l.top;
    let lRight = lLeft + lasso.width();
    let lBottom = lTop + lasso.height();

    $('.elemento-etiqueta').each(function () {
        let el = $(this);
        let e = el.offset();
        let eRight = e.left + el.outerWidth();
        let eBottom = e.top + el.outerHeight();

        // Se houver qualquer intersecção
        if (!(lLeft > eRight || lRight < e.left || lTop > eBottom || lBottom < e.top)) {
            selecionarElemento(el, true);
        }
    });
    atualizarUISelecao();
}

function selecionarElemento(el, isMulti = false) {
    if (!isMulti) deselecionarTudo();
    el.addClass('selecionado');
    atualizarUISelecao();
}

function deselecionarTudo() {
    $('.elemento-etiqueta').removeClass('selecionado');
    atualizarUISelecao();
}

function atualizarUISelecao() {
    let selecionados = $('.elemento-etiqueta.selecionado');

    // Toolbar de Alinhamento
    if (selecionados.length > 1) {
        $('.toolbar-alinhamento').fadeIn().css('display', 'flex');
    } else {
        $('.toolbar-alinhamento').fadeOut();
    }

    // Painel de Propriedades
    if (selecionados.length === 1) {
        let el = $(selecionados[0]);
        let tipo = el.data('tipo');

        if (tipo === 'texto_fixo' || tipo === 'variavel') {
            $('.prop-texto-only').show();
            $('.prop-box-only').hide();
            $('#prop-conteudo').val(el.text().trim());
            $('#prop-tamanho').val(parseInt(el.css('font-size')));
            $('#prop-negrito').prop('checked', el.css('font-weight') == 'bold' || parseInt(el.css('font-weight')) >= 700);
        } else {
            $('.prop-texto-only').hide();
            $('.prop-box-only').show();
            if (tipo === 'barcode' || tipo === 'qrcode') {
                $('.prop-conteudo-box').show();
                $('#prop-conteudo').val(el.attr('data-valor-original'));
            } else {
                $('.prop-conteudo-box').hide();
            }
            $('#prop-largura').val(parseInt(el.css('width')));
            $('#prop-altura').val(parseInt(el.css('height')));
        }
        $('#painel-propriedades').fadeIn();
    } else {
        $('#painel-propriedades').fadeOut();
    }
}

function alinhar(tipo) {
    let sel = $('.elemento-etiqueta.selecionado');
    if (sel.length < 2) return;

    let minLeft = 99999, maxRight = 0, minTop = 99999, maxBottom = 0;

    sel.each(function () {
        let p = $(this).position(); // Posição relativa ao pai (etiqueta-canvas)
        let w = $(this).outerWidth();
        let h = $(this).outerHeight();
        if (p.left < minLeft) minLeft = p.left;
        if ((p.left + w) > maxRight) maxRight = p.left + w;
        if (p.top < minTop) minTop = p.top;
        if ((p.top + h) > maxBottom) maxBottom = p.top + h;
    });

    sel.each(function () {
        let el = $(this);
        let w = el.outerWidth();
        let h = el.outerHeight();

        switch (tipo) {
            case 'esquerda': el.css('left', minLeft); break;
            case 'direita': el.css('left', maxRight - w); break;
            case 'topo': el.css('top', minTop); break;
            case 'base': el.css('top', maxBottom - h); break;
            case 'centro-h': el.css('left', ((minLeft + maxRight) / 2) - (w / 2)); break;
            case 'centro-v': el.css('top', ((minTop + maxBottom) / 2) - (h / 2)); break;
        }
    });
}

// --- CRIAR E CONFIGURAR ELEMENTOS ---

function criarElemento(tipo, conteudo, tamanho, xMm, yMm) {
    contadorId++;
    let id = 'el_' + contadorId;
    let styleExtra = `top: ${yMm * MM_TO_PX}px; left: ${xMm * MM_TO_PX}px;`;
    if (tipo === 'texto_fixo' || tipo === 'variavel') styleExtra += ` font-size: ${tamanho}px;`;

    let html = `<div id="${id}" class="elemento-etiqueta" data-tipo="${tipo}" style="${styleExtra}">${conteudo}</div>`;
    $('#etiqueta-canvas').append(html);
    let novoEl = $(`#${id}`);

    // Inicialização visual de Barcodes/QR
    if (tipo === 'barcode' || tipo === 'qrcode') {
        let initW = parseInt(novoEl.css('width'));
        let initH = parseInt(novoEl.css('height'));
        novoEl.find('i').css('font-size', (Math.min(initW, initH) * 0.8) + 'px');
    }

    novoEl.draggable({
        containment: ".canvas-area", // Permite arrastar um pouco fora se precisar, ou use "#etiqueta-canvas"
        cursor: "move",
        start: function (e, ui) {
            if (!$(this).hasClass('selecionado')) {
                selecionarElemento($(this), e.ctrlKey);
            }
        }
    });

    let aspect = (tipo === 'qrcode');

    novoEl.resizable({
        containment: "document", // Deixa redimensionar livremente
        handles: "n, e, s, w, se, sw, ne, nw",
        aspectRatio: aspect,
        resize: function (event, ui) {
            let el = $(this);
            let tipo = el.data('tipo');

            // --- LÓGICA DE RESIZE INTELIGENTE ---

            if (tipo === 'texto_fixo' || tipo === 'variavel') {
                // Se for texto, a altura da caixa define o tamanho da fonte
                let novaFonte = ui.size.height * 0.7; // Ajuste empírico (70% da altura)
                el.css('font-size', novaFonte + 'px');
                el.css('width', 'auto'); // Deixa a largura automática para o texto caber
            }
            else if (tipo === 'barcode' || tipo === 'qrcode') {
                // Se for Barcode, aumenta o ícone
                let w = ui.size.width;
                let h = ui.size.height;
                let novoTamanho = Math.min(w, h) * 0.8;
                el.find('i').css('font-size', novoTamanho + 'px');
            }
        },
        stop: function () { atualizarUISelecao(); }
    });

    novoEl.on('mousedown', function (e) {
        e.stopPropagation();
        if (!e.ctrlKey && !$(this).hasClass('selecionado')) {
            selecionarElemento($(this), false);
        } else if (e.ctrlKey) {
            $(this).toggleClass('selecionado');
            atualizarUISelecao();
        }
    });

    selecionarElemento(novoEl);
    return novoEl;
}

// --- AUXILIARES ---
function atualizarTamanhoCanvas(w, h) { $('#etiqueta-canvas').css({ width: w * MM_TO_PX + 'px', height: h * MM_TO_PX + 'px' }); }
function selecionarVariavel(campo) { criarElemento('variavel', '{' + campo + '}', 14, 10, 10); $('#modalVariaveis').modal('hide'); }
function removerSelecionados() { $('.elemento-etiqueta.selecionado').remove(); atualizarUISelecao(); }
function addTexto() { criarElemento('texto_fixo', 'Texto', 16, 5, 5); }
function addLinha() {
    let el = criarElemento('linha', '', 0, 5, 60);
    el.css({ width: '100px', height: '3px', background: '#000000' });
}
function addBarcode() {
    let el = criarElemento('barcode', '12345678', 0, 5, 20);
    el.css({ width: '200px', height: '60px', background: '#f0f0f0' });
    el.attr('data-valor-original', '12345678');
    el.html('<i class="fas fa-barcode"></i>');
}
function addQRCode() {
    let el = criarElemento('qrcode', 'QR', 0, 5, 40);
    el.css({ width: '80px', height: '80px', background: '#f0f0f0' });
    el.attr('data-valor-original', 'QR123');
    el.html('<i class="fas fa-qrcode"></i>');
}
function triggerUploadImagem() { $('#input-img-upload').click(); }
function criarElementoImagem(base64Img) {
    let el = criarElemento('imagem', '', 0, 10, 10);
    el.css({ width: '100px', height: '100px', backgroundImage: `url('${base64Img}')`, backgroundSize: 'cover', backgroundPosition: 'center', backgroundRepeat: 'no-repeat' });
    el.attr('data-base64', base64Img);
}
function aplicarMudancasVisual(el) {
    let tipo = el.data('tipo');
    if (tipo === 'texto_fixo' || tipo === 'variavel') {
        el.text($('#prop-conteudo').val());
        el.css('font-size', $('#prop-tamanho').val() + 'px');
        el.css('font-weight', $('#prop-negrito').is(':checked') ? 'bold' : 'normal');
    } else {
        el.css('width', $('#prop-largura').val() + 'px');
        el.css('height', $('#prop-altura').val() + 'px');
        if (tipo === 'barcode' || tipo === 'qrcode') {
            el.attr('data-valor-original', $('#prop-conteudo').val());
            let w = parseInt(el.css('width'));
            let h = parseInt(el.css('height'));
            el.find('i').css('font-size', (Math.min(w, h) * 0.8) + 'px');
        }
    }
}
function salvarLayout() {
    let nomeLayout = $('#nome-layout').val();
    if (!nomeLayout) { alert('Dê um nome para o layout!'); return; }
    let elementos = [];
    $('.elemento-etiqueta').each(function () {
        let el = $(this);
        let pos = el.position(); // Pega X, Y relativo ao pai (#etiqueta-canvas)
        let tipo = el.data('tipo');
        let conteudo = '';
        if (tipo === 'barcode' || tipo === 'qrcode') conteudo = el.attr('data-valor-original');
        else if (tipo === 'imagem') conteudo = el.attr('data-base64');
        else conteudo = el.text().trim();

        elementos.push({
            tipo: tipo, conteudo: conteudo,
            x_mm: (pos.left / MM_TO_PX).toFixed(2), y_mm: (pos.top / MM_TO_PX).toFixed(2),
            largura_mm: (el.outerWidth() / MM_TO_PX).toFixed(2), altura_mm: (el.outerHeight() / MM_TO_PX).toFixed(2),
            tamanho_fonte: parseInt(el.css('font-size')),
            negrito: el.css('font-weight') == 'bold' || parseInt(el.css('font-weight')) >= 700
        });
    });
    let layoutObj = {
        nome: nomeLayout, largura: $('#config-largura').val(), altura: $('#config-altura').val(), elementos: elementos
    };
    console.log("JSON FINAL:", JSON.stringify(layoutObj, null, 2));
    alert('Pronto para salvar no banco!');
}