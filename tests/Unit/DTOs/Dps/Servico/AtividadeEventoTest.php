<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento::class);
use Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoSimples;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when both idAtvEvt and end are set', function () {
    expect(fn () => new AtividadeEvento(
        xNome: 'Evento Teste',
        dtIni: '2026-01-01',
        dtFim: '2026-01-02',
        idAtvEvt: 'EVT001',
        end: new EnderecoSimples(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            CEP: '01001000',
        ),
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when neither idAtvEvt nor end is set', function () {
    expect(fn () => new AtividadeEvento(
        xNome: 'Evento Teste',
        dtIni: '2026-01-01',
        dtFim: '2026-01-02',
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});
