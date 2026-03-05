<?php

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\Serv\CServ;
use Pulsar\NfseNacional\Dps\DTO\Serv\Serv;

dataset('dpsData', [
    'basico' => function (): DpsData {
        return new DpsData(
            infDPS: makeInfDps(),
            subst: null,
            prest: makePrestadorCnpj(xNome: 'Empresa'),
            toma: null,
            interm: null,
            serv: new Serv(
                cServ: new CServ(
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
