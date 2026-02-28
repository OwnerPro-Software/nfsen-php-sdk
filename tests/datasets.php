<?php

use Pulsar\NfseNacional\DTOs\DpsData;

dataset('dpsData', [
    'basico' => function (): DpsData {
        $infDps = new stdClass;
        $infDps->tpamb = 2;
        $infDps->dhemi = '2026-02-27T10:00:00-03:00';
        $infDps->veraplic = '1.0';
        $infDps->serie = '1';
        $infDps->ndps = 1;
        $infDps->dcompet = '2026-02-27';
        $infDps->tpemit = 1;
        $infDps->clocemi = '3501608';

        $prestador = new stdClass;
        $prestador->cnpj = '12345678000195';
        $prestador->xnome = 'Empresa';
        $regTrib = new stdClass;
        $regTrib->opsimpnac = 1;
        $regTrib->regesptrib = 0;
        $prestador->regtrib = $regTrib;

        $tomador = new stdClass;
        $servico = new stdClass;

        $locPrest = new stdClass;
        $locPrest->clocprestacao = '3501608';
        $servico->locprest = $locPrest;

        $cServ = new stdClass;
        $cServ->ctribnac = '010101';
        $cServ->xdescserv = 'Serviço de Teste';
        $cServ->cnbs = '123456789';
        $servico->cserv = $cServ;

        $valores = new stdClass;
        $valores->vservprest = new stdClass;
        $valores->vservprest->vserv = '100.00';

        $valores->trib = new stdClass;
        $valores->trib->tribmun = new stdClass;
        $valores->trib->tribmun->tribissqn = '1';
        $valores->trib->tribmun->tpretissqn = '1';
        $valores->trib->totaltrib = new stdClass;
        $valores->trib->totaltrib->indtottrib = '0';

        return new DpsData($infDps, $prestador, $tomador, $servico, $valores);
    },
]);
