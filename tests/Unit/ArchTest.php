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
arch('handlers do not depend on infrastructure adapters')
    ->expect('Pulsar\NfseNacional\Handlers')
    ->not->toUse([
        'Pulsar\NfseNacional\Http',
        'Pulsar\NfseNacional\Signing',
        'Pulsar\NfseNacional\Services',
        'Pulsar\NfseNacional\Certificates',
    ]);

arch('consulta does not depend on infrastructure adapters')
    ->expect('Pulsar\NfseNacional\Consulta')
    ->not->toUse([
        'Pulsar\NfseNacional\Http',
        'Pulsar\NfseNacional\Signing',
        'Pulsar\NfseNacional\Services',
        'Pulsar\NfseNacional\Certificates',
    ]);
