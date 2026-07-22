<?php

use OwnerPro\Nfsen\Exceptions\NfseException;

// As fixtures de DANFSE só valem como espelho de uma NFS-e real se casarem com o
// schema oficial. Sem esta guarda, uma fixture escrita para o código (e não para o
// XSD) mascara bugs de leitura — foi o que aconteceu com vBC/vISSQN/descontos, que
// o builder lia de tribMun em vez de infNFSe/valores e vDescCondIncond.
it('validates DANFSE fixtures against the official NFS-e schema', function (string $fixture) {
    $xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/'.$fixture);

    // XsdValidator prefixa a declaração XML; a fixture já traz a sua.
    $fragment = (string) preg_replace('|^<\?xml.*?\?>\s*|', '', $xml);

    expect(fn () => makeXsdValidator()->validate($fragment, 'NFSe_v1.01.xsd'))->not->toThrow(NfseException::class);
})->with(['nfse-autorizada.xml', 'nfse-homologacao.xml', 'nfse-ibscbs.xml']);

// O pior caso da página única é derivado em código, e derivação escapava da guarda
// acima: o helper montava o grupo IBSCBS à mão, com o de infNFSe depois do DPS e sem o
// finNFSe obrigatório, além de nProcesso e nBM fora dos padrões de dígitos. Nada disso
// falhava, porque nenhum teste submetia o resultado ao schema.
it('keeps the worst-case NFS-e within the official schema', function () {
    $fragment = (string) preg_replace('|^<\?xml.*?\?>\s*|', '', nfsenXmlNoLimite());

    expect(fn () => makeXsdValidator()->validate($fragment, 'NFSe_v1.01.xsd'))->not->toThrow(NfseException::class);
});
