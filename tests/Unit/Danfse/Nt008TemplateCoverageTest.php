<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;

/**
 * O outro lado do `Nt008FieldCoverageTest`: o campo chega ao papel, e com o rótulo da NT.
 *
 * Aquele teste prova que o `DanfseDataBuilder` LÊ todos os caminhos do XML que a seção
 * 2.4.5 exige. Nada provava que o valor lido é impresso — e foi por essa fresta que
 * `Descrição Contrib. Sociais - Retidas` passou: lida pelo builder, tipada no DTO, coberta
 * por teste, invisível no PDF, com linha, tipo e mutação em 100%.
 *
 * A comparação é literal porque o item 2.4.5 dá o texto exato de cada rótulo, e o item
 * 2.2.4 manda dispor tudo conforme o Anexo I. Casar por aproximação devolveria a fresta.
 */
$FIXTURE = __DIR__.'/../../fixtures/nt008/campos-2.4.5.json';

/**
 * Campos da tabela 2.4.5 que não têm rótulo impresso, por motivo.
 *
 * O teste exige que esta lista seja exatamente o complemento do que o template imprime:
 * rotular um destes sem tirá-lo daqui quebra a suíte. Isenção sem motivo declarado é como
 * o inventário passa a mentir — mesma disciplina de `$TAGS_SEM_ELEMENTO`.
 */
$SEM_ROTULO = [
    'SERVIÇO PRESTADO :: DESCRIÇÃO DO CÓDIGO DE TRIBUTAÇÃO NACIONAL / MUNICIPAL' => 'a observação da própria linha diz "Não há título (label) deste campo no DANFSe"',
    'TRIBUTAÇÃO IBS / CBS :: EXCLUSÕES E REDUÇÕES DA BÁSE DE CLÁCULO' => 'erro de digitação na NT; o DANFSe grafa "Base de Cálculo", e assim deve ficar',
    'INDENTIFICAÇÃO E ASSINATURA :: Nº NFS-E / CHAVE NFS-E' => 'canhoto, declarado opcional pela nota 11 do item 2.4.5',
];

/** Ignora caixa, acento e pontuação — o que sobra é o texto do rótulo. */
function nfsenRotuloNormalizado(string $texto): string
{
    $texto = strtr(mb_strtolower(html_entity_decode(strip_tags($texto))), [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
        'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
    ]);

    return (string) preg_replace('/[^a-z0-9]/', '', $texto);
}

/**
 * Rótulos impressos pelo DANFSe, normalizados.
 *
 * Dois formatos, porque o quadro de identificação do cabeçalho não cabe na grade de
 * quatro colunas do resto do documento: lá o rótulo e o valor dividem a mesma linha,
 * separados por dois-pontos.
 *
 * @return list<string>
 */
function nfsenRotulosImpressos(string $html): array
{
    preg_match_all('#<span class="(?:label|section-title)">(.*?)</span>#s', $html, $emColuna);
    preg_match_all('#<div class="header-(?:municipio|ambiente)">([^:<]*):#s', $html, $emLinha);

    return array_map(nfsenRotuloNormalizado(...), [...$emColuna[1], ...$emLinha[1]]);
}

it('prints every field of the notice table under the label the notice gives it', function () use ($FIXTURE, $SEM_ROTULO) {
    /** @var array{campos: list<array{bloco: string, campo: string}>} $fixture */
    $fixture = json_decode((string) file_get_contents($FIXTURE), true);

    // O pior caso da norma, não a fixture simples: destinatário e IBS/CBS são blocos
    // condicionais, e sem eles no HTML seus rótulos "passariam" por colidirem com os de
    // outro bloco — a mesma frouxidão que deixou um campo inteiro fora do papel.
    $html = (new DanfseHtmlRenderer(new BaconQrCodeGenerator))->render((new DanfseDataBuilder)->build(nfsenXmlNoLimite()));
    $impressos = nfsenRotulosImpressos($html);
    $rotulos = array_flip($impressos);

    // Sem isto, um seletor que parasse de casar deixaria o teste "passando" com a lista
    // de isenções inteira — ou, pior, com uma fixture truncada.
    expect(count($impressos))->toBeGreaterThanOrEqual(90);
    expect(count($fixture['campos']))->toBeGreaterThanOrEqual(90);

    $ausentes = [];
    foreach ($fixture['campos'] as $campo) {
        if (! isset($rotulos[nfsenRotuloNormalizado($campo['campo'])])) {
            $ausentes[] = $campo['bloco'].' :: '.$campo['campo'];
        }
    }

    sort($ausentes);
    $esperados = array_keys($SEM_ROTULO);
    sort($esperados);

    // Igualdade nos dois sentidos, como no inventário de leitura: um campo rotulado tem de
    // sair de $SEM_ROTULO, e um rótulo que suma tem de entrar — senão a regressão passa
    // como "já era pendente".
    expect($ausentes)->toBe($esperados);
});
