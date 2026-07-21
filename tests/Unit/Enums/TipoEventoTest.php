<?php

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;

covers(TipoEvento::class);

it('has all 18 expected values', function () {
    expect(TipoEvento::cases())->toHaveCount(18);
});

it('maps correct integer values', function (TipoEvento $case, int $expectedValue) {
    expect($case->value)->toBe($expectedValue);
})->with([
    [TipoEvento::Cancelamento, 101101],
    [TipoEvento::SolicitacaoCancelamentoAnaliseFiscal, 101103],
    [TipoEvento::CancelamentoPorSubstituicao, 105102],
    [TipoEvento::CancelamentoDeferidoAnaliseFiscal, 105104],
    [TipoEvento::CancelamentoIndeferidoAnaliseFiscal, 105105],
    [TipoEvento::ConfirmacaoPrestador, 202201],
    [TipoEvento::RejeicaoPrestador, 202205],
    [TipoEvento::ConfirmacaoTomador, 203202],
    [TipoEvento::RejeicaoTomador, 203206],
    [TipoEvento::ConfirmacaoIntermediario, 204203],
    [TipoEvento::RejeicaoIntermediario, 204207],
    [TipoEvento::ConfirmacaoTacita, 205204],
    [TipoEvento::AnulacaoRejeicao, 205208],
    [TipoEvento::CancelamentoPorOficio, 305101],
    [TipoEvento::BloqueioPorOficio, 305102],
    [TipoEvento::DesbloqueioPorOficio, 305103],
    [TipoEvento::InclusaoNfseDan, 467201],
    [TipoEvento::TributosNfseRecolhidos, 907201],
]);

it('covers exactly the tipoEvento codes the SEFIN API accepts', function () {
    /** @var array{paths: array<string, array{get: array{parameters: list<array{name: string, enum?: list<int>}>}}>} $swagger */
    $swagger = json_decode(
        (string) file_get_contents(__DIR__.'/../../../storage/schemes/SefinNacional-swagger.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $parameters = $swagger['paths']['/nfse/{chaveAcesso}/eventos/{tipoEvento}/{numSeqEvento}']['get']['parameters'];
    $codigos = array_values(array_filter($parameters, fn (array $p): bool => $p['name'] === 'tipoEvento'))[0]['enum'] ?? [];

    expect(array_column(TipoEvento::cases(), 'value'))->toBe($codigos);
});

it('names each code after the event the XSD documents for it', function (TipoEvento $case, string $trechoDaDocumentacao) {
    // Este é o teste que travaria o defeito corrigido na 3.0.0: nome apontando para o
    // código de outro evento. A documentação do XSD é prosa livre, então a âncora de
    // cada código é escolhida à mão — o suficiente para provar que o nome do caso
    // descreve o mesmo evento que o schema associa àquele código.
    $xsd = new DOMDocument;
    $xsd->load(__DIR__.'/../../../storage/schemes/tiposEventos_v1.01.xsd');

    $xpath = new DOMXPath($xsd);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $documentacao = $xpath->query(sprintf(
        '//xs:element[@name="e%d"]/xs:annotation/xs:documentation',
        $case->value,
    ));

    expect($documentacao->length)->toBe(1)
        ->and(trim((string) $documentacao->item(0)?->textContent))->toContain($trechoDaDocumentacao);
})->with([
    // 467201 e 907201 ficam de fora: não constam em nenhum XSD, só no swagger.
    [TipoEvento::Cancelamento, 'Evento de cancelamento'],
    [TipoEvento::SolicitacaoCancelamentoAnaliseFiscal, 'Solicitação de Análise Fiscal para Cancelamento'],
    [TipoEvento::CancelamentoPorSubstituicao, 'cancelamento por substituição'],
    [TipoEvento::CancelamentoDeferidoAnaliseFiscal, 'Cancelamento de NFS-e Deferido por Análise Fiscal'],
    [TipoEvento::CancelamentoIndeferidoAnaliseFiscal, 'Cancelamento de NFS-e Indeferido por Análise Fiscal'],
    [TipoEvento::ConfirmacaoPrestador, 'Confirmação do Prestador'],
    [TipoEvento::RejeicaoPrestador, 'Rejeição do Prestador'],
    [TipoEvento::ConfirmacaoTomador, 'Confirmação do Tomador'],
    [TipoEvento::RejeicaoTomador, 'Rejeição do Tomador'],
    [TipoEvento::ConfirmacaoIntermediario, 'Confirmação do Intermediário'],
    [TipoEvento::RejeicaoIntermediario, 'Rejeição do Intermediário'],
    [TipoEvento::ConfirmacaoTacita, 'Confirmação Tácita'],
    [TipoEvento::AnulacaoRejeicao, 'Anulação da Rejeição'],
    [TipoEvento::CancelamentoPorOficio, 'Cancelamento de NFS-e por Ofício'],
    [TipoEvento::BloqueioPorOficio, 'Bloqueio de NFS-e por Ofício'],
    [TipoEvento::DesbloqueioPorOficio, 'Desbloqueio de NFS-e por Ofício'],
]);

it('shares its vocabulary with TipoEventoDistribuicao', function () {
    // Os dois enums descrevem os mesmos eventos por canais diferentes (consulta por
    // código vs. distribuição por string). Nomes divergentes entre eles foram o que
    // mascarou o mapeamento invertido até a 3.0.0.
    expect(array_column(TipoEvento::cases(), 'name'))
        ->toBe(array_column(TipoEventoDistribuicao::cases(), 'name'));
});

it('creates from valid integer', function () {
    $evento = TipoEvento::from(101101);

    expect($evento)->toBe(TipoEvento::Cancelamento);
});

it('throws ValueError for invalid integer', function () {
    expect(fn () => TipoEvento::from(999999))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid integer', function () {
    expect(TipoEvento::tryFrom(999999))->toBeNull();
});
