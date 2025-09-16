<?php
// /views/faturamento/relatorio_faturamento_excel.php 
// Este arquivo gera um .xls simples (tabela HTML) para exportação de DADOS.

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Database;
use App\Faturamento\FaturamentoRepository;

if (!isset($_SESSION['codUsuario'])) {
    die("Acesso negado. Por favor, faça o login.");
}

$resumoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resumoId) {
    die("Erro: ID do resumo de faturamento não fornecido.");
}

try {
    $pdo = Database::getConnection();
    $faturamentoRepo = new FaturamentoRepository($pdo);

    // 1. BUSCA OS MESMOS DADOS
    $itens = $faturamentoRepo->getDadosCompletosParaRelatorio($resumoId);

    if (empty($itens)) {
        die("Nenhum dado encontrado para este resumo.");
    }

} catch (Exception $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// --- NOME DO ARQUIVO E HEADERS ---
$filename = "faturamento_resumo_" . $resumoId . ".xls";

// 2. FORÇA O DOWNLOAD E DEFINE O TIPO DE ARQUIVO
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

/**
 * Função de formatação de texto simples para o Excel.
 * Evita que caracteres especiais quebrem o HTML/XML do Excel.
 */
function formatTextoExcel($val)
{
    if ($val === null || $val === '') {
        return ''; // Retorna célula vazia
    }
    // Apenas escapa caracteres que quebrariam o HTML
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

// 3. INICIA A SAÍDA DO ARQUIVO
// Informa ao Excel que estamos usando UTF-8
echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";

// Inicia a tabela (única)
echo "<table border='1'>";

// 4. CABEÇALHO DA TABELA
// Criamos uma única linha de cabeçalho com todas as colunas
echo "<thead>
        <tr style='background-color: #f0f0f0; font-weight: bold;'>
            <th>Fazenda</th>
            <th>Cliente (Razão Social)</th>
            <th>Cliente (Fantasia)</th>
            <th>CNPJ/CPF</th>
            <th>Endereço</th>
            <th>Cidade</th>
            <th>UF</th>
            <th>CEP</th>
            <th>N° Pedido Cliente</th>
            <th>Cond. Pagamento</th>
            <th>Obs. Pedido</th>
            <th>Produto</th>
            <th>Lote</th>
            <th>Preço Unit.</th>
            <th>Qtd. Caixas</th>
            <th>Qtd. Quilos</th>
            <th>UN Medida</th>
            <th>Valor Total</th>
        </tr>
      </thead>";

// 5. CORPO DA TABELA (OS DADOS)
echo "<tbody>";

foreach ($itens as $item) {
    // Recalcula o valor total (lógica do arquivo original)
    $qtdCaixas = (float) ($item['fati_qtd_caixas'] ?? 0);
    $qtdQuilos = (float) ($item['fati_qtd_quilos'] ?? 0);
    $precoUnit = (float) ($item['fati_preco_unitario'] ?? 0);
    $valorTotal = 0;
    if ($item['fati_preco_unidade_medida'] == 'CX') {
        $valorTotal = $qtdCaixas * $precoUnit;
    } else {
        $valorTotal = $qtdQuilos * $precoUnit;
    }

    // Constrói o endereço completo
    $endereco = $item['end_logradouro'] . ", " . $item['end_numero'] . " - " . $item['end_bairro'];

    echo "<tr>";
    // Colunas de Texto
    echo "<td>" . formatTextoExcel($item['fazenda_nome']) . "</td>";
    echo "<td>" . formatTextoExcel($item['cliente_razao_social']) . "</td>";
    echo "<td>" . formatTextoExcel($item['cliente_nome']) . "</td>";
    echo "<td>'" . formatTextoExcel($item['ent_cnpj'] ?: $item['ent_cpf']) . "</td>"; // Aspa simples antes para forçar texto (evita o Excel "sumir" com zeros)
    echo "<td>" . formatTextoExcel($endereco) . "</td>";
    echo "<td>" . formatTextoExcel($item['end_cidade']) . "</td>";
    echo "<td>" . formatTextoExcel($item['end_uf']) . "</td>";
    echo "<td>'" . formatTextoExcel($item['end_cep']) . "</td>";
    echo "<td>" . formatTextoExcel($item['fatn_numero_pedido']) . "</td>";
    echo "<td>" . formatTextoExcel($item['condicao_pag_descricao']) . "</td>";
    echo "<td>" . formatTextoExcel($item['fatn_observacao']) . "</td>";
    echo "<td>" . formatTextoExcel($item['produto_descricao']) . "</td>";
    echo "<td>" . formatTextoExcel($item['lote_completo_calculado']) . "</td>";

    // Colunas de Números
    // Enviamos como números "crus". O Excel usará a formatação local (ponto ou vírgula).
    echo "<td>" . (float) $precoUnit . "</td>";
    echo "<td>" . (float) $qtdCaixas . "</td>";
    echo "<td>" . (float) $qtdQuilos . "</td>";

    echo "<td>" . formatTextoExcel($item['fati_preco_unidade_medida']) . "</td>";
    echo "<td>" . (float) $valorTotal . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

// Finaliza o script
exit;

?>