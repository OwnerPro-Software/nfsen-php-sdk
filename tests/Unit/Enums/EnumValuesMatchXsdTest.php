<?php

/**
 * O conjunto de valores de cada enum tem de ser idêntico ao `<xs:enumeration>` do
 * tipo que o XSD associa àquele elemento — sem faltar nem sobrar.
 *
 * Faltando: `from()` lança em documento legítimo. Sobrando: o SDK aceita e monta um
 * XML que o fisco rejeita.
 *
 * O mapeamento enum → tipo XSD é *derivado do schema*: procura-se o elemento de mesmo
 * nome e lê-se o `@type` dele. Uma tabela escrita à mão poderia apontar o enum para o
 * tipo errado e o teste passaria comparando a coisa errada com ela mesma.
 */
$XS = 'http://www.w3.org/2001/XMLSchema';

/**
 * Enums cujo valor não vem de `<xs:enumeration>`. Declarar aqui é uma afirmação —
 * o teste confere que ela continua verdadeira.
 */
$FORA_DO_XSD = [
    // Distribuição é API REST (ADN), não documento fiscal: os valores são enums do
    // swagger, cobertos por outro caminho.
    'OwnerPro\Nfsen\Enums\StatusDistribuicao' => 'enum do ADN swagger',
    'OwnerPro\Nfsen\Enums\TipoDocumentoFiscal' => 'enum do ADN swagger',
    'OwnerPro\Nfsen\Enums\TipoEventoDistribuicao' => 'enum do ADN swagger',
    // Códigos de evento: o swagger enumera 18, o XSD descreve 16 (467201 e 907201 não
    // têm elemento eNNNNNN). A fonte aqui é o swagger.
    'OwnerPro\Nfsen\Enums\TipoEvento' => 'enum do parâmetro tipoEvento no swagger da SEFIN',
];

/**
 * Enums cujo nome não resolve para exatamente um tipo no schema.
 *
 * `<CST>` aparece com dois (`TSTipoCST` para PIS/COFINS e `TSRTCCodSitTrib`, um
 * pattern sem enumeração, para IBS/CBS). Os de justificativa e ambiente são nomeados
 * em português no SDK e não batem com o nome do elemento.
 */
$TIPO_EXPLICITO = [
    'OwnerPro\Nfsen\Dps\Enums\Valores\CST' => 'TSTipoCST',
    'OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento' => 'TSCodJustCanc',
    'OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao' => 'TSCodJustSubst',
    'OwnerPro\Nfsen\Enums\NfseAmbiente' => 'TSTipoAmbiente',
    'OwnerPro\Nfsen\Enums\SituacaoNfse' => 'TStat',
    'OwnerPro\Nfsen\Enums\TipoBeneficioMunicipal' => 'TBMISSQN',
    'OwnerPro\Nfsen\Enums\AmbienteGerador' => 'TSAmbGeradorNFSe',
];

/**
 * Elemento/atributo do XSD → tipos declarados para aquele nome.
 *
 * @return array<string, list<string>> nome minúsculo => tipos distintos
 */
function nfsenXsdTypesByElementName(): array
{
    $schemes = __DIR__.'/../../../storage/schemes';
    $xs = 'http://www.w3.org/2001/XMLSchema';

    $porNome = [];
    foreach (['DPS_v1.01.xsd', 'NFSe_v1.01.xsd', 'tiposComplexos_v1.01.xsd', 'tiposEventos_v1.01.xsd', 'evento_v1.01.xsd', 'pedRegEvento_v1.01.xsd'] as $arquivo) {
        $doc = new DOMDocument;
        $doc->load($schemes.'/'.$arquivo);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('xs', $xs);

        foreach ($xpath->query('//xs:element[@name][@type]|//xs:attribute[@name][@type]') as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }

            $nome = mb_strtolower($no->getAttribute('name'));
            $tipo = (string) preg_replace('/^.*:/', '', $no->getAttribute('type'));

            if (! in_array($tipo, $porNome[$nome] ?? [], true)) {
                $porNome[$nome][] = $tipo;
            }
        }
    }

    return $porNome;
}

/**
 * Valores de `<xs:enumeration>` de um simpleType.
 *
 * @return list<string>
 */
function nfsenXsdEnumerationValues(string $tipo): array
{
    $doc = new DOMDocument;
    $doc->load(__DIR__.'/../../../storage/schemes/tiposSimples_v1.01.xsd');

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $valores = [];
    foreach ($xpath->query(sprintf('//xs:simpleType[@name="%s"]//xs:enumeration/@value', $tipo)) as $atributo) {
        $valores[] = $atributo->nodeValue ?? '';
    }

    return $valores;
}

/** @return list<class-string> */
function nfsenAllBackedEnums(): array
{
    $classes = [];

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

        if (enum_exists($classe)) {
            /** @var class-string $classe */
            $classes[] = $classe;
        }
    }

    sort($classes);

    return $classes;
}

it('declares exactly the values the XSD enumerates', function () use ($FORA_DO_XSD, $TIPO_EXPLICITO) {
    $porNome = nfsenXsdTypesByElementName();
    $divergencias = [];
    $comparados = 0;

    foreach (nfsenAllBackedEnums() as $enum) {
        if (array_key_exists($enum, $FORA_DO_XSD)) {
            continue;
        }

        $curto = (string) substr((string) strrchr($enum, '\\'), 1);
        $tipos = $porNome[mb_strtolower($curto)] ?? [];

        $tipo = $TIPO_EXPLICITO[$enum] ?? (count($tipos) === 1 ? $tipos[0] : null);

        if ($tipo === null) {
            $divergencias[] = sprintf(
                '%s: elemento <%s> resolve para [%s] — declare o tipo em $TIPO_EXPLICITO ou o motivo em $FORA_DO_XSD.',
                $curto,
                $curto,
                $tipos === [] ? 'nenhum' : implode('|', $tipos),
            );

            continue;
        }

        $doXsd = nfsenXsdEnumerationValues($tipo);

        if ($doXsd === []) {
            $divergencias[] = sprintf('%s: tipo %s não tem <xs:enumeration> em tiposSimples_v1.01.xsd.', $curto, $tipo);

            continue;
        }

        $doCodigo = array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        sort($doXsd);
        sort($doCodigo);
        $comparados++;

        if ($doXsd === $doCodigo) {
            continue;
        }

        $divergencias[] = sprintf(
            "%s vs %s:\n     faltando no código: %s\n     sobrando no código: %s",
            $curto,
            $tipo,
            implode(',', array_diff($doXsd, $doCodigo)) ?: '-',
            implode(',', array_diff($doCodigo, $doXsd)) ?: '-',
        );
    }

    expect($divergencias)->toBe([], "\n".implode("\n", $divergencias));

    // Piso: se a varredura parar de encontrar enums, o laço acima não compara nada e
    // o teste passaria vazio.
    expect($comparados)->toBeGreaterThanOrEqual(25);
});

it('keeps the out-of-XSD exemption list honest', function () use ($FORA_DO_XSD) {
    $enums = nfsenAllBackedEnums();

    // Uma isenção que sobrou depois do enum sumir esconde o próximo enum com o mesmo
    // nome — que entraria isento sem ninguém decidir isso.
    foreach (array_keys($FORA_DO_XSD) as $isento) {
        expect(in_array($isento, $enums, true))
            ->toBeTrue("Isenção obsoleta: {$isento} não existe mais.");
    }
});
