<?php

use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;

it('NfseRequested carries operacao', function () {
    $event = new NfseRequested('emitir', ['payload']);
    expect($event->operacao)->toBe('emitir');
});

it('NfseEmitted carries chave', function () {
    $event = new NfseEmitted('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseCancelled carries chave', function () {
    $event = new NfseCancelled('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseQueried carries operacao', function () {
    $event = new NfseQueried('nfse');
    expect($event->operacao)->toBe('nfse');
});

it('NfseFailed carries operacao and message', function () {
    $event = new NfseFailed('emitir', 'Connection timeout');
    expect($event->message)->toBe('Connection timeout');
});

it('NfseRejected carries operacao and codigo', function () {
    $event = new NfseRejected('emitir', 'E001');
    expect($event->codigoErro)->toBe('E001');
});
