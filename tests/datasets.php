<?php

use Pulsar\NfseNacional\DTOs\DpsData;

dataset('dpsData', [
    'basico' => function (): DpsData {
        $infDps = new stdClass;
        $infDps->tpAmb = 2;
        $infDps->dhEmi = '2026-02-27T10:00:00-03:00';
        $infDps->verAplic = '1.0';
        $infDps->serie = '1';
        $infDps->nDPS = 1;
        $infDps->dCompet = '2026-02-27';
        $infDps->tpEmit = 1;
        $infDps->cLocEmi = '3501608';

        $prestador = new stdClass;
        $prestador->CNPJ = '12345678000195';
        $prestador->xNome = 'Empresa';
        $regTrib = new stdClass;
        $regTrib->opSimpNac = 1;
        $regTrib->regEspTrib = 0;
        $prestador->regTrib = $regTrib;

        $tomador = new stdClass;
        $servico = new stdClass;

        $locPrest = new stdClass;
        $locPrest->cLocPrestacao = '3501608';
        $servico->locPrest = $locPrest;

        $cServ = new stdClass;
        $cServ->cTribNac = '010101';
        $cServ->xDescServ = 'Serviço de Teste';
        $cServ->cNBS = '123456789';
        $servico->cServ = $cServ;

        $valores = new stdClass;
        $valores->vServPrest = new stdClass;
        $valores->vServPrest->vServ = '100.00';

        $valores->trib = new stdClass;
        $valores->trib->tribMun = new stdClass;
        $valores->trib->tribMun->tribISSQN = '1';
        $valores->trib->tribMun->tpRetISSQN = '1';
        $valores->trib->totTrib = new stdClass;
        $valores->trib->totTrib->indTotTrib = '0';

        return new DpsData($infDps, $prestador, $tomador, $servico, $valores);
    },
]);
