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
})->with(['nfse-autorizada.xml', 'nfse-homologacao.xml']);
