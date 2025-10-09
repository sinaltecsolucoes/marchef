<?php
// /src/Core/RelatorioService.php
namespace App\Core;

// Importa as classes do DomPDF
use Dompdf\Dompdf;
use Dompdf\Options;

class RelatorioService
{
    /**
     * Gera o conteúdo binário do PDF a partir de uma string HTML, usando DomPDF.
     *
     * @param string $htmlContent Conteúdo HTML completo do relatório.
     * @return string Conteúdo binário do PDF.
     * @throws \Exception
     */
    public function generatePdfContent(string $htmlContent): string
    {
        if (empty($htmlContent)) {
            throw new \Exception("Conteúdo HTML vazio, não é possível gerar o PDF.");
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Permite carregar imagens via URL (logos, fotos)
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}