<?php

use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpImunidade;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpSusp;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Enums\SituacaoNfse;
use OwnerPro\Nfsen\Enums\TipoBeneficioMunicipal;

/**
 * Cada `label()` tem de repetir o rótulo que o XSD escreve para aquele código.
 *
 * O texto sai impresso no documento fiscal. Um par trocado — código certo, rótulo do
 * vizinho — imprime a informação errada sem sintoma nenhum: nenhum tipo, linter ou
 * mutante enxerga isso. Foi a forma do defeito 2.7.0, onde os códigos de `TipoEvento`
 * estavam certos e os nomes deslocados.
 *
 * A comparação é contra `<xs:documentation>`, não contra uma cópia dos rótulos: um
 * teste que reescreve a declaração do enum é tautológico e passa sempre.
 */
$TIPO_XSD_POR_ENUM = [
    TribISSQN::class => 'TSTribISSQN',
    RegEspTrib::class => 'TSRegEspTrib',
    OpSimpNac::class => 'TSOpSimpNac',
    RegApTribSN::class => 'TSRegimeApuracaoSimpNac',
    TpRetISSQN::class => 'TSTipoRetISSQN',
    CNaoNIF::class => 'TSCodNaoNIF',
    NfseAmbiente::class => 'TSTipoAmbiente',
    SituacaoNfse::class => 'TStat',
    FinNFSe::class => 'TSRTCFinNFSe',
    TpEmit::class => 'TSEmitenteDPS',
    TpImunidade::class => 'TSTipoImunidadeISSQN',
    TpSusp::class => 'TSOpExigSuspensa',
    TipoBeneficioMunicipal::class => 'TBMISSQN',
];

/**
 * Pares `N - Rótulo;` da `<xs:documentation>` de um simpleType.
 *
 * O XSD não é consistente no separador: a maioria usa hífen ASCII, `TSRegimeApuracaoSimpNac`
 * usa travessão Unicode e `TBMISSQN` usa parêntese (`1) Isenção;`). Aceitar só um deles
 * faria a extração devolver zero pares e o teste passar por vacuidade, verificando nada
 * — e é justamente o tipo que tem os rótulos mais longos.
 *
 * @return array<string, string> código => rótulo
 */
function nfsenXsdLabelPairs(string $tipo): array
{
    $doc = new DOMDocument;
    $doc->load(__DIR__.'/../../../storage/schemes/tiposSimples_v1.01.xsd');

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $simpleType = $xpath->query(sprintf('//xs:simpleType[@name="%s"]', $tipo))->item(0);
    expect($simpleType)->toBeInstanceOf(DOMElement::class, "simpleType {$tipo} não existe no XSD.");

    $texto = '';
    foreach ($xpath->query('.//xs:documentation', $simpleType) as $documentation) {
        $texto .= "\n".$documentation->textContent;
    }

    preg_match_all(
        '/(?:^|[;\n])\s*([0-9]+)\s*[-)\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2015}]\s*([^;]+?)\s*(?=;|$)/mu',
        $texto,
        $ocorrencias,
        PREG_SET_ORDER,
    );

    $pares = [];
    foreach ($ocorrencias as $ocorrencia) {
        $pares[$ocorrencia[1]] = (string) preg_replace('/\s+/u', ' ', trim($ocorrencia[2]));
    }

    return $pares;
}

/**
 * Compara ignorando caixa, acento e pontuação — o XSD não é consistente nisso.
 *
 * Mapa explícito em vez de `transliterator_transliterate()`: ext-intl não está no
 * `composer.json`, e um teste que só roda onde a extensão existe não é teste.
 */
function nfsenNormalizeLabel(string $texto): string
{
    $texto = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($texto)), 'UTF-8');

    $semAcento = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);

    return (string) preg_replace('/[^a-z0-9 ]/', '', $semAcento);
}

it('prints the label the XSD defines for each code', function (string $enum, string $tipoXsd) {
    $pares = nfsenXsdLabelPairs($tipoXsd);

    // Sem isto, um simpleType cuja documentação mude de formato faria o laço abaixo
    // não iterar e o teste passar sem comparar nada.
    expect($pares)->not->toBeEmpty("Nenhum par 'N - Rótulo' extraído de {$tipoXsd}.");

    $divergencias = [];
    foreach ($enum::cases() as $case) {
        $rotuloXsd = $pares[$case->value] ?? null;

        if ($rotuloXsd === null) {
            $divergencias[] = sprintf('%s: código "%s" não tem rótulo em %s.', $case->name, $case->value, $tipoXsd);

            continue;
        }

        if (nfsenNormalizeLabel($case->label()) === nfsenNormalizeLabel($rotuloXsd)) {
            continue;
        }

        $divergencias[] = sprintf(
            "%s [%s]:\n     código: %s\n     XSD   : %s",
            $case->name,
            $case->value,
            $case->label(),
            $rotuloXsd,
        );
    }

    expect($divergencias)->toBe([], "\n".implode("\n", $divergencias));
})->with(fn (): array => array_map(
    fn (string $enum, string $tipo): array => [$enum, $tipo],
    array_keys($TIPO_XSD_POR_ENUM),
    array_values($TIPO_XSD_POR_ENUM),
));

it('covers every enum that exposes a label', function () use ($TIPO_XSD_POR_ENUM) {
    $comLabel = [];

    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../../src'));
    foreach ($arquivos as $arquivo) {
        if (! $arquivo instanceof SplFileInfo || $arquivo->getExtension() !== 'php') {
            continue;
        }

        $fonte = (string) file_get_contents($arquivo->getPathname());
        if (! preg_match('/^enum\s+(\w+)/m', $fonte, $nome)) {
            continue;
        }

        preg_match('/^namespace\s+([^;]+);/m', $fonte, $namespace);
        $classe = $namespace[1].'\\'.$nome[1];

        if (enum_exists($classe) && method_exists($classe, 'label')) {
            $comLabel[] = $classe;
        }
    }

    sort($comLabel);
    $mapeados = array_keys($TIPO_XSD_POR_ENUM);
    sort($mapeados);

    // Um enum novo com label() entra aqui sozinho e falha até ser mapeado — é o que
    // impede este teste de ir ficando cego conforme o SDK cresce.
    expect($comLabel)->toBe($mapeados);
});
