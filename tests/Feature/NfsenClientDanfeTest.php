<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseDanfseRenderer;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use Smalot\PdfParser\Parser as PdfParser;

covers(
    NfsenClient::class,
    NfseDanfseRenderer::class,
    DanfseDataBuilder::class,
    DanfseHtmlRenderer::class,
    DompdfHtmlToPdfConverter::class,
);

beforeEach(function () {
    $this->xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');

    // Usa o helper makeNfsenClient() (tests/helpers.php) — cria NfsenClient pronto
    // com cert fake + senha correta. Prefeitura é irrelevante para danfe() (que só
    // consome XML; não bate em nenhum endpoint).
    $this->client = makeNfsenClient();
});

it('generates DANFSE PDF end-to-end', function () {
    $resp = $this->client->danfe()->toPdf($this->xml);

    expect($resp)->toBeInstanceOf(DanfseResponse::class);
    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toStartWith('%PDF-');
});

it('generated PDF contains chave de acesso and emitente', function () {
    $resp = $this->client->danfe()->toPdf($this->xml);

    $text = (new PdfParser)->parseContent($resp->pdf)->getText();

    expect($text)->toContain('3303302112233450000195000000000000100000000001');
    expect($text)->toContain('EMPRESA EXEMPLO DESENVOLVIMENTO');
});

it('returns failure DanfseResponse for malformed XML', function () {
    $resp = $this->client->danfe()->toPdf('<not-xml');

    expect($resp->sucesso)->toBeFalse();
    expect($resp->pdf)->toBeNull();
    expect($resp->erros[0]->descricao)->toBe('XML da NFS-e inválido ou malformado.');
});

it('toHtml returns HTML string', function () {
    $html = $this->client->danfe()->toHtml($this->xml);

    expect($html)->toContain('DANFSe');
    expect($html)->toContain('3303302112233450000195000000000000100000000001');
});

it('toHtml throws on malformed XML', function () {
    expect(fn () => $this->client->danfe()->toHtml('<not-xml'))
        ->toThrow(XmlParseException::class);
});

it('escapes HTML entities in XML fields end-to-end (XSS prevention)', function () {
    // Injeta payload malicioso em xNome (emitente) e xInfComp (informações complementares).
    // Substitui xNome do emitente (primeira ocorrência) pelo payload já HTML-encoded
    // no XML (o XML parser decodifica entities, então o builder recebe `<script>...`).
    $xml = preg_replace(
        '|<xNome>EMPRESA EXEMPLO DESENVOLVIMENTO LTDA</xNome>|',
        '<xNome>&lt;script&gt;alert(&apos;pwn&apos;)&lt;/script&gt;</xNome>',
        $this->xml,
        1,
    );
    $xml = preg_replace(
        '|<xInfComp>[^<]+</xInfComp>|',
        '<xInfComp>"onerror=alert(1)</xInfComp>',
        $xml,
    );

    $html = $this->client->danfe()->toHtml($xml);

    // Raw payload must NOT survive; escaped version must appear.
    expect($html)->not->toContain("<script>alert('pwn')</script>");
    expect($html)->toContain('&lt;script&gt;');
    expect($html)->toContain('&#039;pwn&#039;');
    expect($html)->not->toContain('"onerror=alert(1)');
    expect($html)->toContain('&quot;onerror=alert(1)');
});

it('applies MunicipalityBranding in rendered PDF', function () {
    $config = new DanfseConfig(
        municipality: new MunicipalityBranding(
            name: 'Município de Teste',
            department: 'Secretaria X',
            email: 'teste@example.com',
        ),
    );

    $html = $this->client->danfe($config)->toHtml($this->xml);

    expect($html)->toContain('Município de Teste');
    expect($html)->toContain('Secretaria X');
});
