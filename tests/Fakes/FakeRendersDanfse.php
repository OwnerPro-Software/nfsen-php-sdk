<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

final class FakeRendersDanfse implements RendersDanfse
{
    public int $toPdfCalls = 0;

    /** @var list<string> */
    public array $xmlsReceived = [];

    public function __construct(
        private readonly DanfseResponse $nextResponse = new DanfseResponse(
            sucesso: true,
            pdf: '%PDF-1.4 fake',
        ),
    ) {}

    public function toPdf(string $xmlNfse): DanfseResponse
    {
        $this->toPdfCalls++;
        $this->xmlsReceived[] = $xmlNfse;

        return $this->nextResponse;
    }

    public function toHtml(string $xmlNfse): string
    {
        return '<html>fake</html>';
    }

    public static function failing(string $descricao = 'render falhou'): self
    {
        return new self(new DanfseResponse(
            sucesso: false,
            erros: [new ProcessingMessage(descricao: $descricao)],
        ));
    }
}
