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
