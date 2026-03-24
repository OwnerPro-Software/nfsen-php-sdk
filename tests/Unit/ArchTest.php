<?php

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();
arch()->preset()->strict()
    ->ignoring('OwnerPro\Nfsen\Exceptions\NfseException')
    ->ignoring('OwnerPro\Nfsen\Facades\NfseNacional')
    ->ignoring('OwnerPro\Nfsen\Support\TempFileFactory')
    ->ignoring('OwnerPro\Nfsen\Support\FileReader')
    ->ignoring('OwnerPro\Nfsen\Support\GzipCompressor')
    ->ignoring('OwnerPro\Nfsen\Support\XmlDocumentLoader');

// Hexagonal boundary: core must not depend on infrastructure adapters
arch('operations do not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Operations')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);

arch('pipeline does not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Pipeline')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);

arch('xml builders do not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Xml')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);
