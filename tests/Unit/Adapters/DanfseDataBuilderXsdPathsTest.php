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
 * O par cruzado é: caminhos derivados do XSD × caminhos extraídos do fonte do
 * builder. Nenhum dos dois lados vem de uma fixture.
 */
it('navigates only paths that exist in the official NFS-e schema', function () {
    $leitura = nfsenDanfseBuilderPaths();

    expect($leitura['problemas'])->toBe([], "\n".implode("\n", $leitura['problemas']));

    // Âncoras: os cinco campos do defeito 3.0.0. Se alguém os mover de volta para
    // tribMun, o caminho deixa de existir no XSD e a checagem acima pega — mas se
    // for movido para outro nó igualmente válido, só estas asserções pegam.
    expect($leitura['caminhos'])->toContain(
        'NFSe/infNFSe/valores/vBC',
        'NFSe/infNFSe/valores/vISSQN',
        'NFSe/infNFSe/valores/pAliqAplic',
        'NFSe/infNFSe/DPS/infDPS/valores/vDescCondIncond/vDescCond',
        'NFSe/infNFSe/DPS/infDPS/valores/vDescCondIncond/vDescIncond',
    );

    // Piso, não igualdade: igualdade viraria ruído a cada campo novo. O piso existe
    // para que um refactor que quebre o extrator falhe alto, em vez de passar
    // verificando zero acesso.
    expect($leitura['acessos'])->toBeGreaterThanOrEqual(100);
    expect(count($leitura['caminhos']))->toBeGreaterThanOrEqual(95);
});
