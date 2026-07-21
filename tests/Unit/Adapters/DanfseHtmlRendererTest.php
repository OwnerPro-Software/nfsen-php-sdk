<?php

use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoIbsCbs;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

covers(DanfseHtmlRenderer::class);

// Helpers `fakeQrGen()`, `sampleParticipante()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

it('produces HTML containing chave de acesso', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->toContain('33033021211222333000181000000000001026010000010000');
});

it('embeds QR code pointing to production consulta URL when ambiente is PRODUCAO', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(NfseAmbiente::PRODUCAO));

    expect($html)->toContain('FAKEQR_');
    $expectedPayload = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=33033021211222333000181000000000001026010000010000';
    expect($html)->toContain(base64_encode($expectedPayload));
});

it('embeds QR code pointing to homologacao consulta URL when ambiente is HOMOLOGACAO', function (): void {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    $expectedPayload = 'https://hom.nfse.fazenda.gov.br/ConsultaPublica/?tpc=1&chave=33033021211222333000181000000000001026010000010000';
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

    $interm = sampleParticipante('INTERMEDIÁRIO LTDA');
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

it('renders single dash for codigoTribMunicipal when codigo and desc are both empty', function (): void {
    // Sem cTribMun nem xTribMun, template não deve renderizar "- -" (traço duplo com espaço).
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));
    $base = sampleData();
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        emitente: $base->emitente, tomador: $base->tomador,
        intermediario: $base->intermediario, destinatario: $base->destinatario, destinatarioEhTomador: $base->destinatarioEhTomador,
        servico: new DanfseServico(
            codigoTribNacional: '01.07.00', descTribNacional: 'Desenvolvimento',
            codigoTribMunicipal: '-', descTribMunicipal: '-',
            localPrestacao: 'São Paulo', paisPrestacao: '-', descricao: 'X', codigoNbs: '-',
        ),
        tribMun: $base->tribMun, tribFed: $base->tribFed, tribIbsCbs: $base->tribIbsCbs, totais: $base->totais,
        totaisTributos: $base->totaisTributos, informacoesComplementares: $base->informacoesComplementares,
    );

    $html = $r->render($data);

    expect($html)->not->toContain('- -');
});

it('prints the NBS code in the SERVIÇO PRESTADO block, where the notice puts it', function (): void {
    // Item 2.4.5: "CÓDIGO DA NBS" é campo do bloco de serviço, ao lado do código de
    // tributação. Ficava em "Informações Complementares", que a NT reserva à união
    // de outros dez campos.
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(codigoNbs: '111032200'));

    $servico = substr($html, (int) strpos($html, 'SERVIÇO PRESTADO'), (int) strpos($html, 'TRIBUTAÇÃO MUNICIPAL') - (int) strpos($html, 'SERVIÇO PRESTADO'));

    expect($servico)->toContain('Código da NBS')->toContain('111032200');
});

it('prints a dash for the NBS code when the NFS-e has none', function (): void {
    // Nota 12: campo sem informação no XML sai com traço, não sumindo.
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(codigoNbs: '-'));

    expect($html)->toContain('Código da NBS')->not->toContain('111032200');
});

it('escapes HTML in data fields (XSS prevention)', function (): void {
    $malicious = sampleParticipante("<script>alert('xss')</script>");
    $data = new NfseData(
        chaveAcesso: 'X', numeroNfse: '1', competencia: '-', emissaoNfse: '-',
        numeroDps: '1', serieDps: '1', emissaoDps: '-',
        ambiente: NfseAmbiente::PRODUCAO,
        situacao: '-', finalidade: '-', emitidaPor: '-', ambienteGerador: '-',
        emitente: $malicious, tomador: sampleParticipante(), intermediario: null, destinatario: null, destinatarioEhTomador: false,
        servico: new DanfseServico('-', '-', '-', '-', '-', '-', '-', '-'),
        tribMun: new DanfseTributacaoMunicipal('-', '-', '-', '-', '-', '-', '-', '-', '-', false, false, '-', '-', '-', '-', '-'),
        tribFed: new DanfseTributacaoFederal('-', '-', '-', '-', '-', '-'),
        tribIbsCbs: new DanfseTributacaoIbsCbs('-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-'),
        totais: new DanfseTotais('-', '-', '-', '-', '-', '-', '-', '-', '-'),
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

it('states the destinatário is the tomador instead of calling it unidentified', function (): void {
    // NT 008, item 2.4.5: nota 3 para destinatário igual ao tomador, nota 2 para
    // destinatário sem dados. São frases diferentes porque dizem coisas diferentes.
    $base = sampleData();
    $data = new NfseData(
        chaveAcesso: $base->chaveAcesso, numeroNfse: $base->numeroNfse,
        competencia: $base->competencia, emissaoNfse: $base->emissaoNfse,
        numeroDps: $base->numeroDps, serieDps: $base->serieDps, emissaoDps: $base->emissaoDps,
        ambiente: $base->ambiente, situacao: $base->situacao, finalidade: $base->finalidade,
        emitidaPor: $base->emitidaPor, ambienteGerador: $base->ambienteGerador,
        emitente: $base->emitente, tomador: $base->tomador, intermediario: null,
        destinatario: null, destinatarioEhTomador: true,
        servico: $base->servico, tribMun: $base->tribMun, tribFed: $base->tribFed, tribIbsCbs: $base->tribIbsCbs,
        totais: $base->totais, totaisTributos: $base->totaisTributos,
        informacoesComplementares: $base->informacoesComplementares,
    );

    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render($data);

    expect($html)->toContain('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO');
    expect($html)->not->toContain('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO');
});

it('omits the suppressible ISSQN rows the NFS-e has no data for', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render(sampleData());

    expect($html)->not->toContain('Tipo de Imunidade do ISSQN');
    expect($html)->not->toContain('Benefício Municipal');
    // O resto do bloco continua: a supressão é por linha, não pelo bloco.
    expect($html)->toContain('BC ISSQN');
});

it('prints no marca d\'água for a vigente NFS-e', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render(sampleData());

    expect($html)->not->toContain('<div class="watermark-nt">');
});

it('prints the "CANCELADA" marca d\'água required by item 2.5.1', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))
        ->render(sampleData(marcaDagua: MarcaDagua::Cancelada));

    expect($html)->toContain('<div class="watermark-nt">CANCELADA</div>');
});

it('prints the "SUBSTITUÍDA" marca d\'água required by item 2.5.2', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))
        ->render(sampleData(marcaDagua: MarcaDagua::Substituida));

    expect($html)->toContain('<div class="watermark-nt">SUBSTITUÍDA</div>');
});

it('styles the marca d\'água with the measurements of items 2.5.1 and 2.5.2', function (): void {
    // Diagonal, formato normal, mínimo 50 pontos, Arial, cinza K35 (= #a6a6a6).
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))
        ->render(sampleData(marcaDagua: MarcaDagua::Cancelada));

    $css = substr($html, (int) strpos($html, '.watermark-nt {'));

    expect($css)->toContain('rotate(-45deg)')
        ->toContain('font-size: 50pt')
        ->toContain('font-weight: normal')
        ->toContain('color: #a6a6a6')
        ->toContain('font-family: Arial');
});

it('gives the approximate taxes no block of their own', function (): void {
    // A NT 008 não prevê esse bloco: a nota 10 do item 2.4.5 põe os totais dentro de
    // "Informações Complementares", e o item 2.2.4 manda obedecer ao Anexo I.
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render(sampleData());

    expect($html)->not->toContain('TOTAIS APROXIMADOS DOS TRIBUTOS');
});

it('prints the fixed totals line inside the complementary information block', function (): void {
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render(sampleData());

    $bloco = substr($html, (int) strpos($html, 'INFORMAÇÕES COMPLEMENTARES'));

    expect($bloco)->toContain('Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: 4.50% ; Estaduais: 0.10% ; Municipais: 2.00%');
});

it('keeps the fixed totals line outside the area that clips overflow', function (): void {
    // Nota 10: o corte do texto livre é "sem prejuízo à linha de Totais Aproximados
    // dos Tributos, que é fixa". Dentro do .expandable-text ela sumiria justamente
    // nas notas mais longas, que são as que já perdem texto.
    $html = (new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false)))->render(sampleData());

    $bloco = substr($html, (int) strpos($html, 'INFORMAÇÕES COMPLEMENTARES'));
    $abreClipado = (int) strpos($bloco, 'expandable-text');
    $fechaClipado = (int) strpos($bloco, '</div>', $abreClipado);

    expect(strpos($bloco, 'Totais Aproximados'))->toBeGreaterThan($fechaClipado);
});
