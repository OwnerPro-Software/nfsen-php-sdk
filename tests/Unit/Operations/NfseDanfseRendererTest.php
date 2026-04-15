<?php

declare(strict_types=1);

use Dompdf\Exception as DompdfException;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Operations\NfseDanfseRenderer;
use OwnerPro\Nfsen\Responses\DanfseResponse;

covers(NfseDanfseRenderer::class);

// Helpers `stubBuilder()`, `stubHtmlRenderer()`, `stubPdfConverter()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

beforeEach(function () {
    $this->xml = '<xml>irrelevant (builder stubbed)</xml>';
});

it('toPdf returns successful DanfseResponse', function () {
    $op = new NfseDanfseRenderer(stubBuilder(sampleData()), stubHtmlRenderer(), stubPdfConverter());

    $resp = $op->toPdf($this->xml);

    expect($resp)->toBeInstanceOf(DanfseResponse::class);
    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBe('%PDF-1.4');
    expect($resp->erros)->toBe([]);
});

it('toPdf wraps XmlParseException into DanfseResponse', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(new XmlParseException('xml foo')),
        stubHtmlRenderer(),
        stubPdfConverter(),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros)->toHaveCount(1);
    expect($resp->erros[0]->descricao)->toBe('XML da NFS-e inválido ou malformado.');
    expect($resp->erros[0]->complemento)->toBe('xml foo');
    expect($resp->pdf)->toBeNull();
});

it('toPdf wraps Dompdf exception', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(),
        stubPdfConverter(new DompdfException('pdf broke')),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros[0]->descricao)->toBe('Falha ao renderizar o PDF.');
    expect($resp->erros[0]->complemento)->toBe('pdf broke');
});

it('toPdf wraps generic Throwable', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(new RuntimeException('boom')),
        stubPdfConverter(),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros[0]->descricao)->toBe('Erro inesperado ao gerar DANFSE.');
    expect($resp->erros[0]->complemento)->toBe('boom');
});

it('toHtml returns the html on success', function () {
    $op = new NfseDanfseRenderer(stubBuilder(sampleData()), stubHtmlRenderer('<html>X</html>'), stubPdfConverter());

    expect($op->toHtml($this->xml))->toBe('<html>X</html>');
});

it('toHtml propagates XmlParseException', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(new XmlParseException('bad')),
        stubHtmlRenderer(),
        stubPdfConverter(),
    );

    expect(fn () => $op->toHtml($this->xml))->toThrow(XmlParseException::class, 'bad');
});

it('toHtml propagates generic exception', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(new RuntimeException('render boom')),
        stubPdfConverter(),
    );

    expect(fn () => $op->toHtml($this->xml))->toThrow(RuntimeException::class, 'render boom');
});
