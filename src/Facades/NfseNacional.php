<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\NfseClient;

/**
 * @method static NfseClient for(string $pfxContent, string $senha, string $prefeitura)
 */
final class NfseNacional extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfseClient::class;
    }
}
