<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Dompdf\Dompdf;
use Dompdf\Options;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;

final readonly class DompdfHtmlToPdfConverter implements ConvertsHtmlToPdf
{
    public function convert(string $html): string
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true); // @pest-mutate-ignore TrueToFalse,RemoveMethodCall — opção cosmética do Dompdf; PDF continua válido.
        $options->set('isRemoteEnabled', false); // @pest-mutate-ignore FalseToTrue,RemoveMethodCall — invariante de segurança (evita XXE/SSRF); não inverter.
        $options->set('defaultFont', 'DejaVu Sans'); // @pest-mutate-ignore RemoveMethodCall — fonte default é cosmética, PDF continua válido.
        $options->set('isFontSubsettingEnabled', true); // @pest-mutate-ignore TrueToFalse,RemoveMethodCall — otimização de fonte; não afeta conteúdo.

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait'); // @pest-mutate-ignore RemoveMethodCall — setPaper afeta tamanho da página; PDF continua sendo produzido.
        $dompdf->render(); // @pest-mutate-ignore RemoveMethodCall — render() é obrigatório, mas remover trava output() internamente sem exceção determinística no harness do pest.

        return (string) $dompdf->output(); // @pest-mutate-ignore RemoveStringCast — defensivo; Dompdf::output() é tipado como ?string.
    }
}
