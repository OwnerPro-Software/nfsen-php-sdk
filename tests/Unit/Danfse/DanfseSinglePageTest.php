<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;

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
    $html = (new DanfseHtmlRenderer(new BaconQrCodeGenerator))->render($data);

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
    expect(mb_strlen($data->informacoesComplementares))->toBeGreaterThan(1900);

    expect(nfsenContaPaginas(nfsenRenderizaPdf($xml)))->toBe(1);
});

it('keeps the fixed totals line on the page even at the worst case', function () {
    // Nota 10: o corte do texto livre é "sem prejuízo" desta linha. Ela é a última
    // coisa impressa, então é a primeira a ser empurrada para uma segunda página —
    // e com uma página só, tudo que está no HTML está nela.
    $xml = nfsenXmlNoLimite();
    $data = (new DanfseDataBuilder)->build($xml);
    $html = (new DanfseHtmlRenderer(new BaconQrCodeGenerator))->render($data);

    expect($html)->toContain('Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012');
    expect(nfsenContaPaginas(nfsenRenderizaPdf($xml)))->toBe(1);
});

it('marks the complementary information it had to cut', function () {
    // Corte silencioso num documento fiscal é pior que corte visível: as
    // reticências avisam o leitor de que há texto além do impresso.
    $xml = (string) preg_replace(
        '|<xInfComp>[^<]*</xInfComp>|',
        '<xInfComp>'.str_repeat('a', 2500).'</xInfComp>',
        (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml'),
    );
    $data = (new DanfseDataBuilder)->build($xml);

    // Sem espaços para recuar, o corte cai exatamente no teto mais as reticências.
    expect(mb_strlen($data->informacoesComplementares))->toBe(2000);
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
