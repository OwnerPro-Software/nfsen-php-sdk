<?php

covers(
    \OwnerPro\Nfsen\Events\NfseRequested::class,
    \OwnerPro\Nfsen\Events\NfseEmitted::class,
    \OwnerPro\Nfsen\Events\NfseCancelled::class,
    \OwnerPro\Nfsen\Events\NfseSubstituted::class,
    \OwnerPro\Nfsen\Events\NfseQueried::class,
    \OwnerPro\Nfsen\Events\NfseFailed::class,
    \OwnerPro\Nfsen\Events\NfseRejected::class,
);

use OwnerPro\Nfsen\Events\NfseCancelled;
use OwnerPro\Nfsen\Events\NfseEmitted;
use OwnerPro\Nfsen\Events\NfseFailed;
use OwnerPro\Nfsen\Events\NfseQueried;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Events\NfseSubstituted;

it('NfseRequested carries operacao and metadata', function () {
    $event = new NfseRequested('emitir', ['payload']);
    expect($event->operacao)->toBe('emitir');
    expect($event->metadata)->toBe(['payload']);
});

it('NfseEmitted carries chave', function () {
    $event = new NfseEmitted('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseCancelled carries chave', function () {
    $event = new NfseCancelled('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseSubstituted carries chave and chaveSubstituta', function () {
    $event = new NfseSubstituted('CHAVE123', 'CHAVE456');
    expect($event->chave)->toBe('CHAVE123');
    expect($event->chaveSubstituta)->toBe('CHAVE456');
});

it('NfseQueried carries operacao', function () {
    $event = new NfseQueried('nfse');
    expect($event->operacao)->toBe('nfse');
});

it('NfseFailed carries operacao and message', function () {
    $event = new NfseFailed('emitir', 'Connection timeout');
    expect($event->operacao)->toBe('emitir');
    expect($event->message)->toBe('Connection timeout');
});

it('NfseRejected carries operacao and codigo', function () {
    $event = new NfseRejected('emitir', 'E001');
    expect($event->operacao)->toBe('emitir');
    expect($event->codigoErro)->toBe('E001');
});
