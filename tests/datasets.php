<?php

use Pulsar\NfseNacional\DTOs\DpsData;

dataset('dpsData', [
    'basico' => function (): DpsData {
        $infDps           = new stdClass();
        $infDps->tpamb    = 2;
        $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
        $infDps->veraplic = '1.0';
        $infDps->serie    = 'E';
        $infDps->ndps     = 1;
        $infDps->dcompet  = '2026-02';
        $infDps->tpemit   = 1;
        $infDps->clocemi  = '3501608';

        $prestador        = new stdClass();
        $prestador->cnpj  = '12345678000195';
        $prestador->xnome = 'Empresa';
        $regTrib             = new stdClass();
        $regTrib->opsimpnac  = 1;
        $regTrib->regesptrib = 0;
        $prestador->regtrib  = $regTrib;

        $tomador  = new stdClass();
        $servico  = new stdClass();

        $locPrest                    = new stdClass();
        $locPrest->clocprestacao     = '3501608';
        $servico->locprest           = $locPrest;

        $cServ               = new stdClass();
        $cServ->ctribnac     = '01.01.01.000';
        $cServ->xdescserv    = 'Serviço de Teste';
        $servico->cserv      = $cServ;

        $valores = new stdClass();

        return new DpsData($infDps, $prestador, $tomador, $servico, $valores);
    },
]);
