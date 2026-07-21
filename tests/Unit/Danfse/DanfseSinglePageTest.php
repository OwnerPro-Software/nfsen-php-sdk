<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;
use OwnerPro\Nfsen\Danfse\DanfseConfig;

/**
 * O DANFSe tem de caber numa página A4.
 *
 * NT 008, item 2.2: "O DANFSe deverá ser impresso, obrigatoriamente, em uma única
 * página"; item 2.2.1: formulário de tamanho mínimo A4, em modo retrato.
 *
 * Não é detalhe estético. O layout do item 2.4.5 ocupa 28,77cm dos 29,30cm úteis
 * de uma A4 — 0,53cm de folga para o documento inteiro. Cada bloco acrescentado
 * consome parte dessa folga, e nada no resto da suíte percebe quando ela acaba:
 * o PDF simplesmente ganha uma segunda página. Foi o que aconteceu ao acrescentar
 * os blocos de destinatário e IBS/CBS.
 */
function nfsenRenderizaPdf(string $xml): string
{
    $data = (new DanfseDataBuilder)->build($xml);
    $html = (new DanfseHtmlRenderer(new BaconQrCodeGenerator, new DanfseConfig(logoPath: false)))->render($data);

    return (new DompdfHtmlToPdfConverter)->convert($html);
}

/**
 * Páginas de um PDF.
 *
 * Conta os objetos `/Type /Page` — o `[^s]` evita casar com `/Type /Pages`, que é
 * o nó raiz da árvore de páginas e apareceria uma vez em qualquer documento.
 */
function nfsenContaPaginas(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

/**
 * NFS-e no limite: todos os blocos preenchidos e os dois campos livres no tamanho
 * máximo que a NT admite — 1300 caracteres de descrição e 2000 de informações
 * complementares, ambos em caixa alta, que é mais larga e quebra linha antes.
 *
 * O pior caso vem da especificação, não de um número escolhido a dedo.
 *
 * Cabe porque o builder corta as informações complementares em 1000 caracteres.
 * Esse corte é uma escolha entre duas regras da própria NT que não cabem juntas
 * neste template: o campo de 2000 caracteres e a página única do item 2.2. Ver a
 * justificativa em DanfseDataBuilder::fromInf().
 */
function nfsenXmlNoLimite(): string
{
    $xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml');

    $xml = str_replace(
        '</tribMun>',
        '<tpImunidade>5</tpImunidade>'
        .'<exigSusp><tpSusp>2</tpSusp><nProcesso>0012345-67.2026.8.19.0002</nProcesso></exigSusp>'
        .'<BM><nBM>99</nBM><vRedBCBM>90.00</vRedBCBM></BM></tribMun>',
        $xml,
    );

    $xml = str_replace(
        '</infDPS>',
        '<IBSCBS><cIndOp>000001</cIndOp><indDest>1</indDest>'
        .'<dest><CNPJ>91712343000134</CNPJ><xNome>DESTINATARIO DA OPERACAO SOCIEDADE ANONIMA</xNome>'
        .'<end><endNac><cMun>3550308</cMun><CEP>01310100</CEP></endNac>'
        .'<xLgr>Avenida Brigadeiro Faria Lima</xLgr><nro>5000</nro><xCpl>Conjunto 1801</xCpl>'
        .'<xBairro>Itaim Bibi</xBairro></end>'
        .'<fone>1155554444</fone><email>destinatario@example.com</email></dest>'
        .'<valores><trib><gIBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib></gIBSCBS></trib></valores>'
        .'</IBSCBS></infDPS>',
        $xml,
    );

    $xml = str_replace(
        '</infNFSe>',
        '<IBSCBS><cLocalidadeIncid>3550308</cLocalidadeIncid><valores><vBC>1000.00</vBC>'
        .'<uf><pIBSUF>10.00</pIBSUF><pRedAliqUF>1.00</pRedAliqUF><pAliqEfetUF>9.00</pAliqEfetUF></uf>'
        .'<mun><pIBSMun>2.00</pIBSMun><pRedAliqMun>0.50</pRedAliqMun><pAliqEfetMun>1.80</pAliqEfetMun></mun>'
        .'<fed><pCBS>8.80</pCBS><pRedAliqCBS>0.80</pRedAliqCBS><pAliqEfetCBS>8.00</pAliqEfetCBS></fed>'
        .'</valores><totCIBS>'
        .'<gIBS><gIBSUFTot><vIBSUF>90.00</vIBSUF></gIBSUFTot>'
        .'<gIBSMunTot><vIBSMun>18.00</vIBSMun></gIBSMunTot><vIBSTot>108.00</vIBSTot></gIBS>'
        .'<gCBS><vCBS>80.00</vCBS></gCBS><vTotNF>1188.00</vTotNF></totCIBS></IBSCBS></infNFSe>',
        $xml,
    );

    $xml = (string) preg_replace(
        '|<xDescServ>[^<]*</xDescServ>|',
        '<xDescServ>'.str_repeat('DESCRICAO EXTENSA DO SERVICO PRESTADO NO LIMITE DA NORMA. ', 23).'</xDescServ>',
        $xml,
    );

    return (string) preg_replace(
        '|<xInfComp>[^<]*</xInfComp>|',
        '<xInfComp>'.str_repeat('INFORMACAO COMPLEMENTAR RELEVANTE PARA O TOMADOR. ', 41).'</xInfComp>',
        $xml,
    );
}

it('prints a plain NFS-e on a single A4 page', function () {
    $pdf = nfsenRenderizaPdf((string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml'));

    expect(nfsenContaPaginas($pdf))->toBe(1);
    // A4 retrato, em pontos: 595,28 x 841,89 (item 2.2.1).
    expect($pdf)->toMatch('/MediaBox\s*\[\s*0[.0]* 0[.0]* 595\.\d+ 841\.\d+/');
});

it('keeps the fullest NFS-e the norm allows on a single page', function () {
    $xml = nfsenXmlNoLimite();
    $data = (new DanfseDataBuilder)->build($xml);

    // O caso só prova algo se os dois campos livres estiverem mesmo no limite. O
    // comprimento exato varia: limit() recua até o último espaço antes de cortar,
    // então o que se afirma é que ambos foram truncados no teto.
    expect($data->servico->descricao)->toEndWith('...');
    expect(mb_strlen($data->servico->descricao))->toBeGreaterThan(1250);
    expect($data->informacoesComplementares)->toEndWith('...');
    expect(mb_strlen($data->informacoesComplementares))->toBeGreaterThan(950);

    expect(nfsenContaPaginas(nfsenRenderizaPdf($xml)))->toBe(1);
});

it('marks the complementary information it had to cut', function () {
    // Corte silencioso num documento fiscal é pior que corte visível: as
    // reticências avisam o leitor de que há texto além do impresso.
    $xml = (string) preg_replace(
        '|<xInfComp>[^<]*</xInfComp>|',
        '<xInfComp>'.str_repeat('a', 1500).'</xInfComp>',
        (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml'),
    );
    $data = (new DanfseDataBuilder)->build($xml);

    // Sem espaços para recuar, o corte cai exatamente no teto mais as reticências.
    expect(mb_strlen($data->informacoesComplementares))->toBe(1003);
    expect($data->informacoesComplementares)->toEndWith('...');
});

it('truncates the free-text fields at the lengths the notice sets', function () {
    // Sem o corte, uma descrição no limite do XSD empurra o documento para a
    // segunda página — e nada mais na suíte perceberia.
    $xml = (string) preg_replace(
        '|<xDescServ>[^<]*</xDescServ>|',
        '<xDescServ>'.str_repeat('a', 1500).'</xDescServ>',
        (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml'),
    );
    $data = (new DanfseDataBuilder)->build($xml);

    expect(mb_strlen($data->servico->descricao))->toBe(1300);
    expect($data->servico->descricao)->toEndWith('...');
});
