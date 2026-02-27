<?php

use Pulsar\NfseNacional\DTOs\DpsData;

it('stores all groups', function () {
    $infDps    = new stdClass();
    $prestador = new stdClass();
    $tomador   = new stdClass();
    $servico   = new stdClass();
    $valores   = new stdClass();

    $data = new DpsData($infDps, $prestador, $tomador, $servico, $valores);

    expect($data->infDps)->toBe($infDps);
    expect($data->prestador)->toBe($prestador);
    expect($data->tomador)->toBe($tomador);
    expect($data->servico)->toBe($servico);
    expect($data->valores)->toBe($valores);
});
