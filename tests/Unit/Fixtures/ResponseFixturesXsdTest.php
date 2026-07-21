<?php

/**
 * As fixtures têm de validar contra os XSDs que este repositório distribui.
 *
 * Uma fixture escrita para casar com o código, em vez de com o schema, transforma a
 * suíte em ratificadora do defeito: foi assim que `DanfseDataBuilder` leu cinco campos
 * do nó errado com 100% de cobertura, mutação e tipos, tudo verde. Aqui o schema é
 * quem julga.
 */
$xsdPorRaiz = [
    'NFSe' => 'NFSe_v1.01.xsd',
    'DPS' => 'DPS_v1.01.xsd',
    'evento' => 'evento_v1.01.xsd',
    'pedRegEvento' => 'pedRegEvento_v1.01.xsd',
];

/**
 * Qual resposta da API cada fixture representa.
 *
 * Só declarar o nome da definition: quais campos ela carrega é o swagger que diz.
 * Uma expectativa derivada da própria fixture não é expectativa — é eco.
 */
$definicaoPorFixture = [
    'emitir_sucesso.json' => 'NFSePostResponseSucesso',
    'emitir_rejeicao.json' => 'NFSePostResponseErro',
    'consultar_nfse.json' => 'NFSeGetResponseSucesso',
    'consultar_dps.json' => 'DpsGetResponse',
    'consultar_eventos.json' => 'EventosPostResponseSucesso',
    'cancelar_sucesso.json' => 'EventosPostResponseSucesso',
    'cancelar_rejeicao.json' => 'ResponseErro',
];

/** @param  array<string, string>  $xsdPorRaiz */
function validateAgainstXsd(string $xml, array $xsdPorRaiz): void
{
    $doc = new DOMDocument;

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    try {
        expect($doc->loadXML($xml))->toBeTrue('XML malformado.');

        $raiz = (string) $doc->documentElement?->localName;
        expect(array_key_exists($raiz, $xsdPorRaiz))->toBeTrue("Raiz <{$raiz}> não tem XSD mapeado.");

        libxml_clear_errors();
        $valido = $doc->schemaValidate(__DIR__.'/../../../storage/schemes/'.$xsdPorRaiz[$raiz]);

        $erros = array_map(
            fn (LibXMLError $e): string => trim($e->message),
            libxml_get_errors(),
        );

        expect($valido)->toBeTrue(sprintf(
            "<%s> não valida contra %s:\n  %s",
            $raiz,
            $xsdPorRaiz[$raiz],
            implode("\n  ", $erros),
        ));
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/**
 * Campos que carregam documento fiscal, lidos do swagger — não da fixture.
 *
 * É o que torna a contagem abaixo uma asserção de verdade: renomear
 * `nfseXmlGZipB64` na fixture faz o esperado continuar 1 e o encontrado virar 0.
 *
 * @return list<string>
 */
function camposGZipB64DoSwagger(string $definicao): array
{
    /** @var array{definitions: array<string, array{properties?: array<string, mixed>}>} $swagger */
    $swagger = json_decode(
        (string) file_get_contents(__DIR__.'/../../../storage/schemes/SefinNacional-swagger.json'),
        true,
    );

    expect(array_key_exists($definicao, $swagger['definitions']))
        ->toBeTrue("Definition '{$definicao}' não existe no swagger da SEFIN.");

    return array_values(array_filter(
        array_keys($swagger['definitions'][$definicao]['properties'] ?? []),
        fn (string $campo): bool => str_ends_with($campo, 'GZipB64'),
    ));
}

it('validates every DANFSe fixture against its XSD', function (string $arquivo) use ($xsdPorRaiz) {
    validateAgainstXsd((string) file_get_contents($arquivo), $xsdPorRaiz);
})->with(fn (): array => glob(__DIR__.'/../../fixtures/danfse/*.xml') ?: []);

it('validates every XML embedded in a response fixture against its XSD', function (string $arquivo) use ($xsdPorRaiz, $definicaoPorFixture) {
    $nome = basename($arquivo);
    expect(array_key_exists($nome, $definicaoPorFixture))
        ->toBeTrue("Fixture {$nome} não declara qual resposta da API representa.");

    $esperados = camposGZipB64DoSwagger($definicaoPorFixture[$nome]);

    $dados = json_decode((string) file_get_contents($arquivo), true);
    expect($dados)->toBeArray();

    // O swagger dita quais campos têm de estar lá; a fixture não opina.
    $presentes = array_values(array_filter(
        array_keys($dados),
        fn ($chave): bool => str_ends_with((string) $chave, 'GZipB64'),
    ));

    sort($esperados);
    sort($presentes);
    expect($presentes)->toBe($esperados, sprintf(
        '%s carrega [%s], mas %s declara [%s].',
        $nome,
        implode(', ', $presentes),
        $definicaoPorFixture[$nome],
        implode(', ', $esperados),
    ));

    foreach ($esperados as $campo) {
        $bruto = base64_decode((string) $dados[$campo], true);
        expect($bruto)->not->toBeFalse("Campo {$campo} não é base64 válido.");

        $xml = @gzdecode((string) $bruto);
        expect($xml)->not->toBeFalse("Campo {$campo} não descomprime com gzip.");

        validateAgainstXsd((string) $xml, $xsdPorRaiz);
    }
})->with(fn (): array => glob(__DIR__.'/../../fixtures/responses/*.json') ?: []);
