<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use Dompdf\Exception as DompdfException;
use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use Throwable;

/**
 * Orquestra a renderização local do DANFSE.
 *
 * @api
 */
final readonly class NfseDanfseRenderer implements RendersDanfse
{
    public function __construct(
        private BuildsDanfseData $builder,
        private RendersDanfseHtml $htmlRenderer,
        private ConvertsHtmlToPdf $pdfConverter,
    ) {}

    public function toPdf(string $xmlNfse): DanfseResponse
    {
        try {
            $data = $this->builder->build($xmlNfse);
            $html = $this->htmlRenderer->render($data);
            $pdf = $this->pdfConverter->convert($html);

            return new DanfseResponse(sucesso: true, pdf: $pdf);
        } catch (XmlParseException $e) {
            return $this->failure('XML da NFS-e inválido ou malformado.', $e);
        } catch (DompdfException $e) {
            return $this->failure('Falha ao renderizar o PDF.', $e);
        } catch (Throwable $e) {
            return $this->failure('Erro inesperado ao gerar DANFSE.', $e);
        }
    }

    public function toHtml(string $xmlNfse): string
    {
        $data = $this->builder->build($xmlNfse);

        return $this->htmlRenderer->render($data);
    }

    private function failure(string $descricao, Throwable $e): DanfseResponse
    {
        return new DanfseResponse(
            sucesso: false,
            erros: [new ProcessingMessage(descricao: $descricao, complemento: $e->getMessage())],
        );
    }
}
