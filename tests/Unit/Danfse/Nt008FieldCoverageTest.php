<?php

/**
 * O que o DANFSe tem de imprimir, e de qual tag do XML, segundo a NT 008.
 *
 * A seção 2.4.5 da nota técnica é a única fonte que amarra cada campo impresso a um
 * caminho do XML. Ela vive num PDF; a suíte não pode lê-lo, então o dado entra como
 * fixture derivada — e fixture derivada é precisamente o que ratificou o defeito
 * 3.0.0. Por isso o primeiro teste aqui confere a fixture contra o XSD a cada
 * execução: uma fixture desatualizada ou adulterada quebra a suíte em vez de virar
 * requisito fantasma.
 *
 * Regerar: veja as instruções em `tools/extract-nt008.py`.
 */
$FIXTURE = __DIR__.'/../../fixtures/nt008/campos-2.4.5.json';

/**
 * Tags da NT que não resolvem para um elemento do XSD, com o motivo.
 *
 * Cada isenção é uma afirmação sobre a nota técnica, não uma desculpa: o segundo
 * teste falha se alguma delas passar a resolver, para que a lista não envelheça.
 */
$TAGS_SEM_ELEMENTO = [
    'id' => 'atributo Id de infNFSe, não elemento — a expansão do XSD só cataloga elementos',
    'vPIS' => 'tag do bloco IBS/CBS ausente do XSD v1.01 distribuído neste repositório',
    'vCOFINS' => 'idem',
];

/**
 * Linhas cuja célula "Caminho no XML" o extrator não remonta por inteiro.
 *
 * Causa diferente de `$TAGS_SEM_ELEMENTO`: aqui a tag existe no XSD, mas a
 * alternativa de caminho que a contém se perdeu na quebra de linha do PDF. Fica
 * anotado em vez de virar mais uma heurística no extrator — foi calibrando
 * heurística contra o resultado que esta auditoria começou.
 */
$CAMINHOS_INCOMPLETOS = [
    'TRIBUTAÇÃO IBS / CBS :: RED. ALÍQUOTA IBS / RED. ALÍQUOTA CBS' => 'a alternativa que carrega pRedAliqCBS quebra em três linhas na tabela',
    'INTERMEDIÁRIO DA OPERAÇÃO :: MUNICÍPIO / SIGLA UF' => 'a alternativa .../interm/end/endExt/, que carrega xCidade, se perde na quebra',
];

/** @return list<string> tags atômicas de uma célula "Campo" da NT */
function nt008Tags(string $celula): array
{
    $partes = preg_split('/[|\/,+]/u', $celula) ?: [];

    return array_values(array_filter(
        array_map(fn (string $t): string => trim($t), $partes),
        fn (string $t): bool => $t !== '' && preg_match('/^\w+$/', $t) === 1,
    ));
}

/** @return list<string> caminhos alternativos de uma célula "Caminho no XML" da NT */
function nt008Caminhos(string $celula): array
{
    $partes = preg_split('/[|+]/u', $celula) ?: [];

    return array_values(array_filter(
        array_map(fn (string $c): string => trim(trim($c), '/'), $partes),
        fn (string $c): bool => str_starts_with($c, 'NFSe/'),
    ));
}

it('carries a field table that still matches the XSD', function () use ($FIXTURE, $TAGS_SEM_ELEMENTO, $CAMINHOS_INCOMPLETOS) {
    /** @var list<array{bloco: string, campo: string, caminho: string, tag: string}> $campos */
    $campos = json_decode((string) file_get_contents($FIXTURE), true);
    $xsd = nfsenXsdPaths();

    // Sem isto, uma fixture vazia ou truncada passaria verificando nada.
    expect(count($campos))->toBeGreaterThanOrEqual(90);

    $orfaos = [];
    $isentasQueResolvem = [];

    foreach ($campos as $campo) {
        $caminhos = nt008Caminhos($campo['caminho']);
        $incompleto = array_key_exists($campo['bloco'].' :: '.$campo['campo'], $CAMINHOS_INCOMPLETOS);

        foreach (nt008Tags($campo['tag']) as $tag) {
            $resolve = false;
            foreach ($caminhos as $caminho) {
                if (array_key_exists($caminho.'/'.$tag, $xsd)) {
                    $resolve = true;
                    break;
                }
            }

            if ($resolve && array_key_exists($tag, $TAGS_SEM_ELEMENTO)) {
                $isentasQueResolvem[$tag] = true;
            }

            if (! $resolve && ! $incompleto && ! array_key_exists($tag, $TAGS_SEM_ELEMENTO)) {
                $orfaos[] = sprintf('%s / %s: <%s> não existe sob %s',
                    $campo['bloco'], $campo['campo'], $tag, implode(' | ', $caminhos) ?: '(sem caminho)');
            }
        }
    }

    expect($orfaos)->toBe([], "\nA fixture divergiu do XSD — regenere com tools/extract-nt008.py:\n".implode("\n", $orfaos));
    expect(array_keys($isentasQueResolvem))->toBe([], 'Isenção obsoleta: estas tags já resolvem no XSD e devem sair de $TAGS_SEM_ELEMENTO.');
});

/**
 * Campos da NT que o `DanfseDataBuilder` ainda não lê, por motivo.
 *
 * Não é uma lista de tarefas decorativa: o teste abaixo exige que ela seja
 * exatamente o complemento do que o builder lê. Implementar um campo sem tirá-lo
 * daqui quebra a suíte, e é assim que o inventário não envelhece sozinho.
 */
$NAO_LIDOS = [
    // Campos que o SDK simplesmente não coleta.
    'QUADRO DA IDENT. MUNICÍPIO/AMBIENTE :: AMBIENTE GERADOR',
    'DADOS DA NFS-e :: EMITENTE DA NFS-E',
    'DADOS DA NFS-e :: SITUAÇÃO DA NFS-E',
    'DADOS DA NFS-e :: FINALIDADE',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: TIPO DE IMUNIDADE DO ISSQN',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: SUSPENSÃO DA EXIGIBILIDADE DO ISSQN',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: NÚMERO PROCESSO SUSPENSÃO',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: BENEFÍCIO MUNICIPAL',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: CÁLCULO DO BM',
    'TRIBUTAÇÃO MUNICIPAL (ISSQN) :: TOTAL DEDUÇÕES/REDUÇÕES',
    'TRIBUTAÇÃO FEDERAL (EXCETO CBS) :: DESCRIÇÃO CONTRIB. SOCIAIS - RETIDAS',
    'VALOR TOTAL DA NFS-E :: TOTAL DO IBS/CBS',
    'VALOR TOTAL DA NFS-E :: VALOR LÍQUIDO DA NFS-e + IBS/CBS',

    // Bloco inteiro ausente: o destinatário da operação (IBSCBS/dest).
    'DESTINÁRIO DA OPERAÇÃO :: CNPJ / CPF / NIF',
    'DESTINÁRIO DA OPERAÇÃO :: TELEFONE',
    'DESTINÁRIO DA OPERAÇÃO :: NOME / NOME EMPRESARIAL',
    'DESTINÁRIO DA OPERAÇÃO :: MUNICÍPIO / SIGLA UF',
    'DESTINÁRIO DA OPERAÇÃO :: CÓDIGO IBGE / CEP',
    'DESTINÁRIO DA OPERAÇÃO :: ENDEREÇO',
    'DESTINÁRIO DA OPERAÇÃO :: E-MAIL',

    // Bloco inteiro ausente: tributação IBS/CBS (reforma tributária).
    'TRIBUTAÇÃO IBS / CBS :: CST / CCLASSTRIB',
    'TRIBUTAÇÃO IBS / CBS :: INDICADOR DE OPERAÇÃO / CÓDIGO IBGE INCIDÊNCIA / MUNICÍPIO INCIDÊNCIA / SIGLA UF',
    'TRIBUTAÇÃO IBS / CBS :: BASE DE CÁLCULO APÓS EXCLUSÕES E REDUÇÕES',
    'TRIBUTAÇÃO IBS / CBS :: RED. ALÍQUOTA IBS / RED. ALÍQUOTA CBS',
    'TRIBUTAÇÃO IBS / CBS :: ALÍQUOTA - IBS UF / IBS MUN',
    'TRIBUTAÇÃO IBS / CBS :: ALÍQ. EFETIVA MUNICIPAL - IBS',
    'TRIBUTAÇÃO IBS / CBS :: VALOR APURADO MUNICIPAL - IBS',
    'TRIBUTAÇÃO IBS / CBS :: ALÍQ. EFETIVA ESTADUAL - IBS',
    'TRIBUTAÇÃO IBS / CBS :: VALOR APURADO ESTADUAL - IBS',
    'TRIBUTAÇÃO IBS / CBS :: VALOR TOTAL APURADO - IBS',
    'CBS :: ALÍQUOTA - CBS',
    'CBS :: ALÍQUOTA EFETIVA - CBS',
    'CBS :: VALOR TOTAL APURADO - CBS',

    // Lidos, mas invisíveis para o extrator estático de caminhos:
    // a chave vem do atributo Id (`$inf->attributes()->Id`), e o total de retenções
    // é somado em código a partir de vRetIRRF/vRetCP/vRetCSLL.
    'DADOS DA NFS-e :: CHAVE DE ACESSO DA NFS-E',
    'VALOR TOTAL DA NFS-E :: TOTAL DAS RETENÇÕES (ISSQN / FEDERAIS)',
];

it('knows exactly which notice fields the builder still does not read', function () use ($FIXTURE, $NAO_LIDOS) {
    /** @var list<array{bloco: string, campo: string, caminho: string, tag: string}> $campos */
    $campos = json_decode((string) file_get_contents($FIXTURE), true);
    $lidos = array_flip(nfsenDanfseBuilderPaths()['caminhos']);

    $ausentes = [];
    foreach ($campos as $campo) {
        $caminhos = nt008Caminhos($campo['caminho']);

        $lido = false;
        foreach (nt008Tags($campo['tag']) as $tag) {
            foreach ($caminhos as $caminho) {
                if (isset($lidos[$caminho.'/'.$tag])) {
                    $lido = true;
                    break 2;
                }
            }
        }

        if (! $lido) {
            $ausentes[] = $campo['bloco'].' :: '.$campo['campo'];
        }
    }

    sort($ausentes);
    $esperados = $NAO_LIDOS;
    sort($esperados);

    // Igualdade nos dois sentidos, de propósito: um campo implementado tem de sair
    // da lista (senão o inventário mente para baixo), e um campo que pare de ser
    // lido tem de entrar (senão uma regressão passa como "já era pendente").
    expect($ausentes)->toBe($esperados);
});
