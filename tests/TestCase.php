<?php

namespace Pulsar\NfseNacional\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pulsar\NfseNacional\NfseNacionalServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NfseNacionalServiceProvider::class];
    }
}
