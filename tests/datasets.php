<?php

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\Dps\Servico\CodigoServico;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;

dataset('dpsData', [
    'basico' => function (): DpsData {
        return new DpsData(
            infDPS: makeInfDps(),
            subst: null,
            prest: makePrestadorCnpj(xNome: 'Empresa'),
            toma: null,
            interm: null,
            serv: new Servico(
                cServ: new CodigoServico(
                    cTribNac: '010101',
                    xDescServ: 'Serviço de Teste',
                    cNBS: '123456789',
                ),
                cLocPrestacao: '3501608',
            ),
            valores: makeValoresMinimo(),
        );
    },
]);
