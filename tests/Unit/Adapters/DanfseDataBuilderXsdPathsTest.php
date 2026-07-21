<?php

/**
 * Todo caminho que o `DanfseDataBuilder` navega tem de existir no XSD.
 *
 * SimpleXML não reclama de caminho errado: devolve null, o builder normaliza para
 * '-' e o PDF sai com o campo vazio. Foi assim que vBC, vISSQN, pAliqAplic e os dois
 * descontos saíram de `valores/trib/tribMun` — nó válido, campo errado — em todo
 * DANFSe real, com a suíte verde. A fixture ratificava o defeito; aqui é o schema
 * que julga.
 *
 * O par que este teste cruza é: caminhos derivados do XSD × caminhos extraídos do
 * fonte do builder. Nenhum dos dois lados vem da fixture.
 */
$XS = 'http://www.w3.org/2001/XMLSchema';

$INF_NFSE = 'NFSe/infNFSe';
$INF_DPS = $INF_NFSE.'/DPS/infDPS';

/**
 * Variável do builder → caminho absoluto do nó que ela guarda.
 *
 * Escrito à mão a partir das atribuições do builder — e é justamente por isso que
 * uma variável fora deste mapa faz o teste FALHAR em vez de ser ignorada. Ignorar
 * silenciosamente é como a auditoria fica cega conforme o builder cresce.
 */
$ALIASES = [
    'root' => 'NFSe',
    'children' => 'NFSe',
    'inf' => $INF_NFSE,
    'emit' => $INF_NFSE.'/emit',
    'ender' => $INF_NFSE.'/emit/enderNac',
    'valNfse' => $INF_NFSE.'/valores',
    'infDps' => $INF_DPS,
    'prest' => $INF_DPS.'/prest',
    'regTrib' => $INF_DPS.'/prest/regTrib',
    'serv' => $INF_DPS.'/serv',
    'cServ' => $INF_DPS.'/serv/cServ',
    'locPrest' => $INF_DPS.'/serv/locPrest',
    'toma' => $INF_DPS.'/toma',
    'interm' => $INF_DPS.'/interm',
    'valores' => $INF_DPS.'/valores',
    'desc' => $INF_DPS.'/valores/vDescCondIncond',
    'trib' => $INF_DPS.'/valores/trib',
    'tribMun' => $INF_DPS.'/valores/trib/tribMun',
    'tribFed' => $INF_DPS.'/valores/trib/tribFed',
    'pc' => $INF_DPS.'/valores/trib/tribFed/piscofins',
    'totTrib' => $INF_DPS.'/valores/trib/totTrib',
    'p' => $INF_DPS.'/valores/trib/totTrib/pTotTrib',
];

/**
 * `$end` e `$endNac` existem em dois métodos, apontando para participantes
 * diferentes. Resolver pelo método evita dar a um o nó do outro.
 */
$ALIASES_POR_METODO = [
    'buildTomador' => [
        'end' => $INF_DPS.'/toma/end',
        'endNac' => $INF_DPS.'/toma/end/endNac',
    ],
    'buildIntermediario' => [
        'end' => $INF_DPS.'/interm/end',
        'endNac' => $INF_DPS.'/interm/end/endNac',
    ],
];

/**
 * Expande o modelo de conteúdo do XSD em caminhos absolutos.
 *
 * A travessia é estrita: desce por sequence/choice/all/extension/group, mas nunca
 * entra no `complexType` de um elemento filho por atalho. Um `.//xs:element` aqui
 * achataria os complexTypes inline e aceitaria como válido um caminho que pula
 * nível — exatamente o erro que o teste existe para pegar.
 *
 * @return array<string, string> caminho => nome do tipo XSD
 */
function nfsenExpandXsdPaths(): array
{
    $schemes = __DIR__.'/../../../storage/schemes';
    $xs = 'http://www.w3.org/2001/XMLSchema';

    $tipos = [];
    $grupos = [];
    $elementosGlobais = [];

    foreach (['NFSe_v1.01.xsd', 'tiposComplexos_v1.01.xsd', 'DPS_v1.01.xsd'] as $arquivo) {
        $doc = new DOMDocument;
        $doc->load($schemes.'/'.$arquivo);

        foreach ($doc->documentElement?->childNodes ?? [] as $filho) {
            if (! $filho instanceof DOMElement || $filho->namespaceURI !== $xs) {
                continue;
            }

            $nome = $filho->getAttribute('name');
            if ($nome === '') {
                continue;
            }

            match ($filho->localName) {
                'complexType' => $tipos[$nome] ??= $filho,
                'group' => $grupos[$nome] ??= $filho,
                'element' => $elementosGlobais[$nome] ??= $filho,
                default => null,
            };
        }
    }

    $caminhos = [];
    $semPrefixo = fn (string $qname): string => (string) preg_replace('/^.*:/', '', $qname);

    /** @var Closure(?DOMElement, string, list<string>): void $percorreTipo */
    $percorreTipo = function (?DOMElement $tipo, string $prefixo, array $pilha) use (
        &$percorreTipo, &$caminhos, $tipos, $grupos, $xs, $semPrefixo
    ): void {
        if (! $tipo instanceof DOMElement) {
            return;
        }

        $nome = $tipo->getAttribute('name');
        if ($nome !== '') {
            if (in_array($nome, $pilha, true)) {
                return; // tipo recursivo
            }
            $pilha[] = $nome;
        }

        /** @var Closure(DOMElement): void $percorreModelo */
        $percorreModelo = function (DOMElement $no) use (
            &$percorreModelo, &$percorreTipo, &$caminhos, $prefixo, $pilha, $tipos, $grupos, $xs, $semPrefixo
        ): void {
            foreach ($no->childNodes as $filho) {
                if (! $filho instanceof DOMElement || $filho->namespaceURI !== $xs) {
                    continue;
                }

                if (in_array($filho->localName, ['sequence', 'choice', 'all', 'complexContent', 'simpleContent', 'extension', 'restriction'], true)) {
                    $percorreModelo($filho);

                    continue;
                }

                if ($filho->localName === 'group') {
                    $ref = $semPrefixo($filho->getAttribute('ref'));
                    if ($ref !== '' && isset($grupos[$ref])) {
                        $percorreModelo($grupos[$ref]);
                    }

                    continue;
                }

                if ($filho->localName !== 'element') {
                    continue;
                }

                $nomeElemento = $filho->getAttribute('name');
                if ($nomeElemento === '') {
                    continue;
                }

                $caminho = $prefixo.'/'.$nomeElemento;
                $tipoRef = $filho->getAttribute('type');
                $caminhos[$caminho] = $tipoRef !== '' ? $semPrefixo($tipoRef) : '(inline)';

                if ($tipoRef !== '' && isset($tipos[$semPrefixo($tipoRef)])) {
                    $percorreTipo($tipos[$semPrefixo($tipoRef)], $caminho, $pilha);

                    continue;
                }

                foreach ($filho->childNodes as $interno) {
                    if ($interno instanceof DOMElement && $interno->namespaceURI === $xs && $interno->localName === 'complexType') {
                        $percorreTipo($interno, $caminho, $pilha);
                    }
                }
            }
        };

        $percorreModelo($tipo);
    };

    $raiz = $elementosGlobais['NFSe'] ?? null;
    expect($raiz)->toBeInstanceOf(DOMElement::class, 'Elemento global <NFSe> não encontrado no XSD.');

    $tipoRaiz = $semPrefixo($raiz?->getAttribute('type') ?? '');
    expect($tipos)->toHaveKey($tipoRaiz);

    $percorreTipo($tipos[$tipoRaiz], 'NFSe', []);

    return $caminhos;
}

/**
 * Extrai do fonte do builder toda cadeia `$var->a->b`, por método.
 *
 * O segmento final é descartado quando vem seguido de `(` — aí é chamada de método
 * (`->count()`, `->getMessage()`), não navegação de elemento.
 *
 * @return list<array{metodo: string, var: string, cadeia: list<string>}>
 */
function nfsenExtractBuilderAccesses(): array
{
    $fonte = (string) file_get_contents(__DIR__.'/../../../src/Adapters/DanfseDataBuilder.php');

    $blocos = preg_split('/(?=\n    (?:private|public) function )/', $fonte) ?: [];

    $acessos = [];
    foreach ($blocos as $bloco) {
        preg_match('/function (\w+)/', $bloco, $m);
        $metodo = $m[1] ?? '(topo)';

        preg_match_all('/\$(\w+)((?:\s*\??->\s*\w+)+)\s*(\()?/', $bloco, $ocorrencias, PREG_SET_ORDER);

        foreach ($ocorrencias as $ocorrencia) {
            $var = $ocorrencia[1];
            if ($var === 'this') {
                continue; // colaborador do builder, não nó XML
            }

            $cadeia = array_values(array_filter(
                array_map('trim', preg_split('/\??->/', trim($ocorrencia[2])) ?: []),
                fn (string $segmento): bool => $segmento !== '',
            ));

            if (($ocorrencia[3] ?? '') === '(') {
                array_pop($cadeia); // último segmento é chamada de método
            }

            if ($cadeia === []) {
                continue;
            }

            $acessos[] = ['metodo' => $metodo, 'var' => $var, 'cadeia' => $cadeia];
        }
    }

    return $acessos;
}

it('navigates only paths that exist in the official NFS-e schema', function () use ($ALIASES, $ALIASES_POR_METODO) {
    $caminhosXsd = nfsenExpandXsdPaths();
    $acessos = nfsenExtractBuilderAccesses();

    $verificados = [];
    $problemas = [];

    foreach ($acessos as ['metodo' => $metodo, 'var' => $var, 'cadeia' => $cadeia]) {
        $base = $ALIASES_POR_METODO[$metodo][$var] ?? $ALIASES[$var] ?? null;

        if ($base === null) {
            $problemas[] = sprintf(
                '%s(): variável $%s não está no mapa de aliases — declare o nó que ela guarda '.
                '(ignorar em silêncio é como este teste fica cego).',
                $metodo,
                $var,
            );

            continue;
        }

        $atual = $base;
        foreach ($cadeia as $segmento) {
            $proximo = $atual.'/'.$segmento;

            if (! array_key_exists($proximo, $caminhosXsd)) {
                $problemas[] = sprintf(
                    '%s(): $%s->%s resolve para "%s", que não existe no XSD — SimpleXML devolveria null e o campo sairia "-" no PDF.',
                    $metodo,
                    $var,
                    implode('->', $cadeia),
                    $proximo,
                );

                continue 2;
            }

            $atual = $proximo;
        }

        $verificados[$atual] = true;
    }

    expect($problemas)->toBe([], "\n".implode("\n", $problemas));

    // Âncoras: os cinco campos do defeito 3.0.0. Se alguém os mover de volta para
    // tribMun, o caminho continua existindo no XSD e a checagem acima passa — só
    // estas asserções pegam a regressão.
    expect($verificados)->toHaveKeys([
        'NFSe/infNFSe/valores/vBC',
        'NFSe/infNFSe/valores/vISSQN',
        'NFSe/infNFSe/valores/pAliqAplic',
        'NFSe/infNFSe/DPS/infDPS/valores/vDescCondIncond/vDescCond',
        'NFSe/infNFSe/DPS/infDPS/valores/vDescCondIncond/vDescIncond',
    ]);

    // Piso, não igualdade: igualdade viraria ruído a cada campo novo. O piso existe
    // para que um refactor que quebre o extrator falhe alto, em vez de passar
    // verificando zero acesso.
    expect(count($acessos))->toBeGreaterThanOrEqual(100);
    expect(count($verificados))->toBeGreaterThanOrEqual(95);
});
