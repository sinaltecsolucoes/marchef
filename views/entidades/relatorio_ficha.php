<?php
// views/entidades/relatorio_ficha.php
// Relatório de Ficha Cadastral com Cache Inteligente

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Entidades\EntidadeRepository;
use App\Core\RelatorioService;

// --- 1. FUNÇÕES AUXILIARES ---
function limparStringParaArquivo($string)
{
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a a e e i i o o u u n n"), $string);
    $string = preg_replace('/[^a-z0-9\-]/', '_', $string);
    $string = preg_replace('/_+/', '_', $string);
    return trim($string, '_');
}

if (!function_exists('formatarCpfCnpj')) {
    function formatarCpfCnpj($valor)
    {
        $valor = preg_replace('/\D/', '', $valor);
        if (strlen($valor) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $valor);
        elseif (strlen($valor) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $valor);
        return $valor;
    }
}

if (!function_exists('formatarCep')) {
    function formatarCep($valor)
    {
        $valor = preg_replace('/\D/', '', $valor);
        return (strlen($valor) === 8) ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', $valor) : $valor;
    }
}

// --- 2. SETUP E BUSCA DE DADOS ---
if (!isset($_SESSION['codUsuario'])) die("Acesso negado.");
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$modo = filter_input(INPUT_GET, 'modo', FILTER_DEFAULT) ?? 'html';
if (!$id) die("Erro: ID não fornecido.");

try {
    $pdo = Database::getConnection();
    $entidadeRepo = new EntidadeRepository($pdo);
    $dados = $entidadeRepo->getDadosRelatorio($id);
    if (empty($dados) || empty($dados['entidade'])) die("Entidade não encontrada.");
    $entidade = $dados['entidade'];
    $enderecos = $dados['enderecos'];
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// --- 3. LÓGICA DE ARQUIVO E CACHE (DECISÃO CRÍTICA) ---
// Definimos o nome e caminho AGORA, para poder usar tanto no HTML (verificação) quanto no PDF (salvamento)

$nomeBase = !empty($entidade['ent_nome_fantasia']) ? $entidade['ent_nome_fantasia'] : $entidade['ent_razao_social'];
$nomeArquivo = limparStringParaArquivo($entidade['ent_tipo_entidade']) . '_' . limparStringParaArquivo($nomeBase) . '.pdf';
$pastaTipo = limparStringParaArquivo($entidade['ent_tipo_entidade']);

// Caminho Físico (Servidor)
$pastaFisica = __DIR__ . '/../../public/uploads/' . $pastaTipo;
$caminhoFisicoCompleto = $pastaFisica . '/' . $nomeArquivo;

// URL Pública (Navegador)
$urlPublica = BASE_URL . '/uploads/' . $pastaTipo . '/' . $nomeArquivo;

// Verifica se o arquivo já existe e pega a data
$arquivoExiste = file_exists($caminhoFisicoCompleto);
$dataArquivo = $arquivoExiste ? date('d/m/Y H:i', filemtime($caminhoFisicoCompleto)) : '';

// --- 4. CONFIGURAÇÃO DE LOGO ---
$pathLogo = __DIR__ . '/../../public/img/logo_marchef.png';
$logoSrc = '';
if (file_exists($pathLogo)) {
    $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($pathLogo);
    $logoSrc = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Ficha - <?= htmlspecialchars($entidade['ent_razao_social']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @page {
            margin: 20px 25px;
        }

        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #333;
            background-color: #fff;
            margin: 0px;
        }

        /* Cabeçalho */
        .header-container {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        table.header-layout {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        table.header-layout td {
            border: none;
            vertical-align: middle;
            padding: 5px;
        }

        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #0d6efd;
            margin: 0;
        }

        .report-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0;
        }

        .entity-type-header {
            font-size: 14px;
            color: #555;
            font-weight: bold;
            text-transform: uppercase;
        }

        .report-meta {
            font-size: 10px;
            color: #666;
            text-align: right;
            line-height: 1.4;
        }

        .logo-img {
            max-height: 70px;
            width: auto;
        }

        /* Dados */
        .section-header {
            background-color: #e9ecef;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 13px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 10px;
            margin-top: 15px;
        }

        .table-custom th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            vertical-align: middle;
        }

        .table-custom td {
            vertical-align: middle;
        }

        .status-active {
            color: green;
            font-weight: bold;
        }

        .status-inactive {
            color: red;
            font-weight: bold;
        }

        /* Botões */
        .actions-bar {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            text-align: right;
        }

        @media print {
            .actions-bar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php if ($modo !== 'pdf'): ?>
        <div class="actions-bar d-print-none">
            <button onclick="verificarEBaixarPdf()" class="btn btn-danger btn-sm">
                <i class="fas fa-file-pdf"></i> Baixar PDF
            </button>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ms-2">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>

        <script>
            function verificarEBaixarPdf() {
                const arquivoExiste = <?= json_encode($arquivoExiste) ?>;
                const dataArquivo = "<?= $dataArquivo ?>";
                const urlExistente = "<?= $urlPublica ?>";
                // URL para forçar nova geração
                const urlGerarNovo = "index.php?page=relatorio_entidade&id=<?= $id ?>&modo=pdf&force=true";

                if (arquivoExiste) {
                    Swal.fire({
                        title: 'Ficha já existe!',
                        html: `Encontramos uma ficha salva em <b>${dataArquivo}</b>.<br>Deseja abrir a existente ou gerar uma nova?`,
                        icon: 'question',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Abrir Existente',
                        denyButtonText: 'Gerar Nova e Salvar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#3085d6',
                        denyButtonColor: '#d33'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Abre o arquivo existente (CACHE)
                            window.open(urlExistente, '_blank');
                        } else if (result.isDenied) {
                            // Gera novo e sobrescreve
                            window.location.href = urlGerarNovo;
                        }
                    });
                } else {
                    // Se não existe, gera direto
                    window.location.href = urlGerarNovo;
                }
            }
        </script>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="header-container">
            <table class="header-layout">
                <tr>
                    <td width="25%" align="left">
                        <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt="Logo" class="logo-img"><?php endif; ?>
                    </td>
                    <td width="50%" align="center">
                        <div class="report-title">Ficha Cadastral</div>
                        <div class="entity-type-header">
                            <?= htmlspecialchars(mb_strtoupper($entidade['ent_tipo_entidade'], 'UTF-8')) ?>
                        </div>
                    </td>
                    <td width="25%" align="right">
                        <div class="report-meta">
                            <strong>Emissão:</strong> <?= date('d/m/Y') ?><br>
                            <strong>Hora:</strong> <?= date('H:i') ?><br>
                            <strong>Usuário:</strong> <?= $_SESSION['nomeUsuario'] ?? 'Sistema' ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-header">1. DADOS DE IDENTIFICAÇÃO</div>
        <table class="table table-bordered table-sm table-custom">
            <tbody>
                <tr>
                    <th width="20%">Razão Social:</th>
                    <td width="50%"><?= htmlspecialchars($entidade['ent_razao_social']) ?></td>
                    <th width="15%">Código Interno:</th>
                    <td width="15%"><?= htmlspecialchars($entidade['ent_codigo_interno'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Nome Fantasia:</th>
                    <td><?= htmlspecialchars($entidade['ent_nome_fantasia'] ?? '-') ?></td>
                    <th>Tipo:</th>
                    <td><?= htmlspecialchars($entidade['ent_tipo_entidade']) ?></td>
                </tr>
                <tr>
                    <th>Documento (CPF/CNPJ):</th>
                    <td><?= formatarCpfCnpj($entidade['ent_tipo_pessoa'] === 'J' ? $entidade['ent_cnpj'] : $entidade['ent_cpf']) ?></td>
                    <th>Situação:</th>
                    <td>
                        <span class="<?= $entidade['ent_situacao'] === 'A' ? 'status-active' : 'status-inactive' ?>">
                            <?= $entidade['ent_situacao'] === 'A' ? 'ATIVO' : 'INATIVO' ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Inscrição Estadual:</th>
                    <td colspan="3"><?= htmlspecialchars($entidade['ent_inscricao_estadual'] ?? 'Isento/Não Informado') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="section-header">2. ENDEREÇOS CADASTRADOS</div>
        <?php if (!empty($enderecos)): ?>
            <table class="table table-bordered table-striped table-sm table-custom">
                <thead>
                    <tr>
                        <th class="text-center align-middle" width="12%">Tipo</th>
                        <th class="text-center align-middle" width="28%">Logradouro</th>
                        <th class="text-center align-middle" width="8%">Nº</th>
                        <th class="text-center align-middle" width="15%">Complemento</th>
                        <th class="text-center align-middle" width="15%">Bairro</th>
                        <th class="text-center align-middle" width="14%">Cidade / UF</th>
                        <th class="text-center align-middle" width="8%">CEP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enderecos as $end): ?>
                        <tr>
                            <td class="text-nowrap text-center align-middle"><?= htmlspecialchars($end['end_tipo_endereco']) ?></td>
                            <td><?= htmlspecialchars($end['end_logradouro']) ?></td>
                            <td class="text-nowrap text-center align-middle"><?= htmlspecialchars($end['end_numero']) ?></td>
                            <td><?= htmlspecialchars($end['end_complemento'] ?? '-') ?></td>
                            <td class="text-nowrap text-center align-middle"><?= htmlspecialchars($end['end_bairro']) ?></td>
                            <td class="text-nowrap text-center align-middle"><?= htmlspecialchars($end['end_cidade']) ?> / <?= $end['end_uf'] ?></td>
                            <td class="text-nowrap text-center align-middle"><?= formatarCep($end['end_cep']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center text-muted">Nenhum endereço cadastrado.</p>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-top text-center text-muted" style="font-size: 10px;">
            Fim do Relatório. Gerado eletronicamente pelo sistema Marchef.
        </div>
    </div>
</body>

</html>
<?php
$htmlContent = ob_get_clean();

if ($modo === 'pdf') {
    try {
        // GERAÇÃO DO PDF (DomPDF)
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $outputPDF = $dompdf->output();

        // --- SALVAMENTO NO SERVIDOR ---
        // Cria pasta se não existir
        if (!is_dir($pastaFisica)) {
            mkdir($pastaFisica, 0777, true);
        }

        // Salva o arquivo (sobrescreve se existir, pois o usuário clicou em "Gerar Novo")
        file_put_contents($caminhoFisicoCompleto, $outputPDF);

        // Envia para o navegador
        header("Content-type: application/pdf");
        header("Content-Disposition: inline; filename={$nomeArquivo}");
        echo $outputPDF;
    } catch (Exception $e) {
        echo "Erro ao gerar PDF: " . $e->getMessage();
    }
} else {
    echo $htmlContent;
}
?>