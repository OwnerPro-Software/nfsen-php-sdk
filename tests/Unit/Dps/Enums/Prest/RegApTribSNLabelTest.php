<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;

covers(RegApTribSN::class, HasLabelOf::class);

/**
 * Extrai os pares `N - Rótulo;` do `<xs:documentation>` de um tipo simples do XSD.
 *
 * A versão anterior deste arquivo repetia as strings do enum, o que faz o teste passar
 * qualquer que seja o rótulo — foi assim que "por fora do SN" virou "pela NFS-e" sem
 * ninguém notar. Derivar do XSD é o que dá ao teste algum poder de recusa.
 *
 * @return array<string, string>
 */
function labelsDoXsd(string $tipo): array
{
    $doc = new DOMDocument;
    $doc->load(__DIR__.'/../../../../../storage/schemes/tiposSimples_v1.01.xsd');

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $nodes = $xpath->query(sprintf('//xs:simpleType[@name="%s"]//xs:documentation', $tipo));
    expect($nodes)->not->toBeFalse();
    expect($nodes->length)->toBeGreaterThan(0, "Tipo {$tipo} não encontrado no XSD.");

    $texto = (string) $nodes->item(0)?->textContent;

    $labels = [];
    // O XSD usa travessão (–), não hífen, e sobra espaço duplo em alguns rótulos.
    foreach (explode(';', $texto) as $linha) {
        if (preg_match('/(\d+)\s*[-–]\s*(.+)$/su', trim($linha), $m) === 1) {
            $labels[$m[1]] = (string) preg_replace('/\s+/u', ' ', trim($m[2]));
        }
    }

    return $labels;
}

it('matches every label to the XSD documentation', function () {
    $doXsd = labelsDoXsd('TSRegimeApuracaoSimpNac');

    expect($doXsd)->toHaveCount(count(RegApTribSN::cases()));

    foreach (RegApTribSN::cases() as $case) {
        expect($doXsd)->toHaveKey($case->value);
        expect($case->label())->toBe($doXsd[$case->value]);
    }
});

it('labelOf resolves a raw XSD value to its label', function () {
    $doXsd = labelsDoXsd('TSRegimeApuracaoSimpNac');

    expect(RegApTribSN::labelOf('2'))->toBe($doXsd['2']);
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegApTribSN::labelOf(null))->toBe('-');
    expect(RegApTribSN::labelOf('99'))->toBe('-');
});
