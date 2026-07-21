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

it('validates every DANFSe fixture against its XSD', function (string $arquivo) use ($xsdPorRaiz) {
    validateAgainstXsd((string) file_get_contents($arquivo), $xsdPorRaiz);
})->with(fn (): array => glob(__DIR__.'/../../fixtures/danfse/*.xml') ?: []);

it('validates every XML embedded in a response fixture against its XSD', function (string $arquivo) use ($xsdPorRaiz) {
    $dados = json_decode((string) file_get_contents($arquivo), true);
    expect($dados)->toBeArray();

    $validados = 0;
    foreach ($dados as $chave => $valor) {
        // Os campos que carregam documento fiscal terminam em GZipB64 por convenção da API.
        if (! is_string($valor) || ! str_ends_with((string) $chave, 'GZipB64')) {
            continue;
        }

        $bruto = base64_decode($valor, true);
        expect($bruto)->not->toBeFalse("Campo {$chave} não é base64 válido.");

        $xml = @gzdecode((string) $bruto);
        expect($xml)->not->toBeFalse("Campo {$chave} não descomprime com gzip.");

        validateAgainstXsd((string) $xml, $xsdPorRaiz);
        $validados++;
    }

    // Sem isto, um campo renomeado faria o teste passar por vacuidade — o modo de falha
    // que esta auditoria encontrou duas vezes nos próprios scripts de verificação.
    expect($validados)->toBe(countGZipB64Fields($arquivo));
})->with(fn (): array => glob(__DIR__.'/../../fixtures/responses/*.json') ?: []);

function countGZipB64Fields(string $arquivo): int
{
    $dados = json_decode((string) file_get_contents($arquivo), true);

    return count(array_filter(
        is_array($dados) ? array_keys($dados) : [],
        fn ($chave): bool => str_ends_with((string) $chave, 'GZipB64'),
    ));
}
