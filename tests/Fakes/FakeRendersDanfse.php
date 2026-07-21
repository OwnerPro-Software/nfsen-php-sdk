<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

final class FakeRendersDanfse implements RendersDanfse
{
    public int $toPdfCalls = 0;

    /** @var list<string> */
    public array $xmlsReceived = [];

    /** @var list<MarcaDagua|null> */
    public array $marcasReceived = [];

    public function __construct(
        private readonly DanfseResponse $nextResponse = new DanfseResponse(
            sucesso: true,
            pdf: '%PDF-1.4 fake',
        ),
    ) {}

    public function toPdf(string $xmlNfse, ?MarcaDagua $marcaDagua = null): DanfseResponse
    {
        $this->toPdfCalls++;
        $this->xmlsReceived[] = $xmlNfse;
        $this->marcasReceived[] = $marcaDagua;

        return $this->nextResponse;
    }

    public function toHtml(string $xmlNfse, ?MarcaDagua $marcaDagua = null): string
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
