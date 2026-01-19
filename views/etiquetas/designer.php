<?php
// views/etiquetas/designer.php
?>
<div class="container-fluid mt-3">

    <div class="row mb-3">
        <div class="col-md-6">
            <h2><i class="fa fa-paint-brush"></i> Designer de Etiquetas</h2>
        </div>
        <div class="col-md-6 text-right text-end">
            <button class="btn btn-outline-secondary" onclick="$('#input-import-prn').click()">
                <i class="fa fa-upload"></i> Importar ZPL/PRN
            </button>
            <input type="file" id="input-import-prn" style="display:none" accept=".prn,.txt,.zpl">

            <button class="btn btn-success" onclick="salvarLayout()">
                <i class="fa fa-save"></i> Salvar Layout
            </button>
        </div>
    </div>

    <div class="designer-container border rounded">

        <div class="sidebar-ferramentas">
            <div class="mb-3">
                <div class="sidebar-title"><i class="fas fa-ruler-combined"></i> Dimensões da Etiqueta</div>
                <div class="row g-1">
                    <div class="col-6">
                        <label class="small text-muted">Largura (mm)</label>
                        <input type="number" id="config-largura" class="form-control form-control-sm" value="100">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted">Altura (mm)</label>
                        <input type="number" id="config-altura" class="form-control form-control-sm" value="150">
                    </div>
                </div>
            </div>
            <hr>

            <div class="mb-3">
                <div class="sidebar-title mb-2">Adicionar Elemento</div>
                <div class="row g-2">
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-secondary w-100 py-2" onclick="addTexto()" title="Adicionar Texto">
                            <i class="fas fa-font fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Texto</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-primary w-100 py-2" onclick="$('#modalVariaveis').modal('show')" title="Campo do Banco">
                            <i class="fas fa-database fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Dados</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-dark w-100 py-2" onclick="addBarcode()" title="Código de Barras">
                            <i class="fas fa-barcode fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Barras</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-dark w-100 py-2" onclick="addQRCode()" title="QR Code">
                            <i class="fas fa-qrcode fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">QR Code</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-success w-100 py-2" onclick="triggerUploadImagem()" title="Imagem/Logo">
                            <i class="far fa-image fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Img</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-secondary w-100 py-2" onclick="addLinha()" title="Linha">
                            <i class="fas fa-minus fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Linha</div>
                        </button>
                    </div>
                    <div class="col-4 text-center">
                        <button class="btn btn-outline-secondary w-100 py-2" onclick="addRetangulo()" title="Retângulo">
                            <i class="far fa-square fa-lg"></i>
                            <div style="font-size: 10px; margin-top: 5px">Retâng.</div>
                        </button>
                    </div>
                </div>
            </div>
            <hr>

            <div class="mb-3">
                <div class="sidebar-title mb-2">Alinhamento (Selecione 2+)</div>
                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('esquerda')" title="Esquerda"><i class="fas fa-align-left"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('centro-h')" title="Centro H"><i class="fas fa-arrows-alt-h"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('direita')" title="Direita"><i class="fas fa-align-right"></i></button>
                </div>
                <div class="btn-group w-100 mt-1" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('topo')" title="Topo"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('centro-v')" title="Centro V"><i class="fas fa-arrows-alt-v"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alinhar('base')" title="Base"><i class="fas fa-arrow-down"></i></button>
                </div>
            </div>
            <hr>

            <div id="painel-propriedades" style="display:none;" class="card bg-light border-0">
                <div class="card-body p-2">
                    <h6 class="card-title text-primary" style="font-size: 0.9rem;"><i class="fas fa-sliders-h"></i> Propriedades</h6>

                    <div class="mb-2 prop-conteudo-box">
                        <label class="small">Conteúdo / Texto</label>
                        <textarea id="prop-conteudo" class="form-control form-control-sm" rows="2"></textarea>
                    </div>

                    <div class="row g-1 mb-2 prop-texto-only">
                        <div class="col-6">
                            <label class="small">Fonte (px)</label>
                            <input type="number" id="prop-tamanho" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" id="prop-negrito" class="form-check-input">
                                <label for="prop-negrito" class="form-check-label small">Negrito</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-1 mb-2 prop-dimensao-box">
                        <div class="col-6">
                            <label class="small">Largura</label>
                            <input type="number" id="prop-largura" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="small">Altura</label>
                            <input type="number" id="prop-altura" class="form-control form-control-sm">
                        </div>
                    </div>

                    <button class="btn btn-danger btn-sm w-100 mt-2" onclick="removerSelecionados()">
                        <i class="fas fa-trash"></i> Remover Item
                    </button>
                </div>
            </div>

            <div class="mt-auto pt-3">
                <label class="small text-muted">Nome do Layout</label>
                <input type="text" id="nome-layout" class="form-control mb-2" placeholder="Ex: Etiqueta Padrão">
                <button class="btn btn-success w-100" onclick="salvarLayout()">
                    <i class="fa fa-save"></i> SALVAR LAYOUT
                </button>
            </div>
        </div>

        <div class="canvas-area">
            <div id="etiqueta-canvas" class="bg-white shadow-sm">
            </div>
        </div>

        <div class="modal fade" id="modalVariaveis" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title">Escolher Dado</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group list-group-flush">
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('nome_produto')">Nome do Produto</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('pecas_produto')">Total de Peças</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('lote')">Lote Completo</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('datas_produto')">Data Fab. / Val. </button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('data_fabricacao')">Data Fabricação</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('data_validade')">Data Validade</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('peso_liquido')">Peso Líquido</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('especie_origem')">Espécie e Origem</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('nome_cliente')">Cliente</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('endereco_cliente')">Endereço</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('cidade_cliente')">Cidade</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('cnpj_cliente')">CNPJ / IE</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('codigo_produto')">Cód. Interno</button>
                            <button class="list-group-item list-group-item-action" onclick="selecionarVariavel('nome_fantasia')">Nome Fantasia</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <input type="file" id="input-img-upload" accept="image/*" style="display: none;">
    </div>
</div>