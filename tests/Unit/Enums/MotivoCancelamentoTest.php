<?php

use Pulsar\NfseNacional\Enums\MotivoCancelamento;

it('has erro emissao value', function () {
    expect(MotivoCancelamento::ErroEmissao->value)->toBe('e101101');
});

it('has outros value', function () {
    expect(MotivoCancelamento::Outros->value)->toBe('e105102');
});
