<?php

use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

covers(DanfseHtmlRenderer::class);

// Helpers `fakeQrGen()`, `sampleParte()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

it('produces HTML containing chave de acesso', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->toContain('3303302112233450000195000000000000100000000001');
});

it('embeds QR code pointing to production consulta URL when ambiente is PRODUCAO', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(NfseAmbiente::PRODUCAO));

    expect($html)->toContain('FAKEQR_');
    $expectedPayload = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=3303302112233450000195000000000000100000000001';
    expect($html)->toContain(base64_encode($expectedPayload));
});

it('embeds QR code pointing to homologacao consulta URL when ambiente is HOMOLOGACAO', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    $expectedPayload = 'https://hom.nfse.fazenda.gov.br/ConsultaPublica/?tpc=1&chave=3303302112233450000195000000000000100000000001';
    expect($html)->toContain(base64_encode($expectedPayload));
});

it('shows watermark only in homologacao', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $prod = $r->render(sampleData(NfseAmbiente::PRODUCAO));
    $homo = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    expect($prod)->not->toContain('SEM VALIDADE JURÍDICA');
    expect($homo)->toContain('SEM VALIDADE JURÍDICA');
});

it('shows "NÃO IDENTIFICADO" when there is no intermediario', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(interm: null));

    expect($html)->toContain('NÃO IDENTIFICADO');
});

it('shows intermediario block when present', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $interm = sampleParte('INTERMEDIÁRIO LTDA');
    $html = $r->render(sampleData(interm: $interm));

    expect($html)->toContain('INTERMEDIÁRIO LTDA');
});

it('includes municipality branding in header when provided', function (): void {
    $branding = new MunicipalityBranding(
        name: 'Prefeitura de Teste',
        department: 'Secretaria de Fazenda',
        email: 'iss@teste.gov.br',
    );
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false, municipality: $branding));

    $html = $r->render(sampleData());

    expect($html)->toContain('Prefeitura de Teste');
    expect($html)->toContain('Secretaria de Fazenda');
    expect($html)->toContain('iss@teste.gov.br');
});

it('renders empty municipality cell when branding is absent', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->not->toContain('Prefeitura de Teste');
});

it('uses default NFSe logo when no custom logo configured', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig);

    $html = $r->render(sampleData());

    expect($html)->toContain('data:image/');
});

it('omits logo when logoPath is false', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    // Deve ter só o QR code como data URI, não o logo.
    $dataUriCount = substr_count($html, 'data:image/');
    expect($dataUriCount)->toBe(1); // apenas o QR
});

it('escapes HTML in data fields (XSS prevention)', function (): void {
    $malicious = sampleParte("<script>alert('xss')</script>");
    $data = new NfseData(
        chaveAcesso: 'X', numeroNfse: '1', competencia: '-', emissaoNfse: '-',
        numeroDps: '1', serieDps: '1', emissaoDps: '-',
        ambiente: NfseAmbiente::PRODUCAO,
        emitente: $malicious, tomador: sampleParte(), intermediario: null,
        servico: new DanfseServico('-', '-', '-', '-', '-', '-', '-'),
        tribMun: new DanfseTributacaoMunicipal('-', '-', '-', '-', '-', '-', '-', '-'),
        tribFed: new DanfseTributacaoFederal('-', '-', '-', '-', '-'),
        totais: new DanfseTotais('-', '-', '-', '-', '-', '-', '-'),
        totaisTributos: new DanfseTotaisTributos('-', '-', '-'),
        informacoesComplementares: '',
    );

    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));
    $html = $r->render($data);

    expect($html)->not->toContain("<script>alert('xss')</script>");
    expect($html)->toContain('&lt;script&gt;');
    // Aspas simples também devem ser escapadas (garante ENT_QUOTES, não só ENT_COMPAT).
    expect($html)->toContain('&#039;xss&#039;');
});
