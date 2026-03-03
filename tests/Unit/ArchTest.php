<?php

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();
arch()->preset()->strict()
    ->ignoring('Pulsar\NfseNacional\Exceptions\NfseException')
    ->ignoring('Pulsar\NfseNacional\Facades\NfseNacional')
    ->ignoring('Pulsar\NfseNacional\Support\TempFileFactory')
    ->ignoring('Pulsar\NfseNacional\Support\FileReader')
    ->ignoring('Pulsar\NfseNacional\Support\GzipCompressor')
    ->ignoring('Pulsar\NfseNacional\Support\XmlDocumentLoader');

// Hexagonal boundary: core must not depend on infrastructure adapters
arch('operations do not depend on infrastructure adapters')
    ->expect('Pulsar\NfseNacional\Operations')
    ->not->toUse([
        'Pulsar\NfseNacional\Adapters',
    ]);

arch('pipeline does not depend on infrastructure adapters')
    ->expect('Pulsar\NfseNacional\Pipeline')
    ->not->toUse([
        'Pulsar\NfseNacional\Adapters',
    ]);

arch('xml builders do not depend on infrastructure adapters')
    ->expect('Pulsar\NfseNacional\Xml')
    ->not->toUse([
        'Pulsar\NfseNacional\Adapters',
    ]);
