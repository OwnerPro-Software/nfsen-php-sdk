<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Serv\AtvEvento::class);
use Pulsar\NfseNacional\Dps\DTO\Serv\AtvEvento;
use Pulsar\NfseNacional\Dps\DTO\Serv\EndSimples;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when both idAtvEvt and end are set', function () {
    expect(fn () => new AtvEvento(
        xNome: 'Evento Teste',
        dtIni: '2026-01-01',
        dtFim: '2026-01-02',
        idAtvEvt: 'EVT001',
        end: new EndSimples(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            CEP: '01001000',
        ),
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when neither idAtvEvt nor end is set', function () {
    expect(fn () => new AtvEvento(
        xNome: 'Evento Teste',
        dtIni: '2026-01-01',
        dtFim: '2026-01-02',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});
