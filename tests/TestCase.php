<?php

namespace OwnerPro\Nfsen\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use OwnerPro\Nfsen\NfsenServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NfsenServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app): void
    {
        // Suppress PDO::MYSQL_ATTR_SSL_CA deprecation from testbench's
        // database.php on PHP 8.5+ (constant renamed to Pdo\Mysql\ATTR_SSL_CA).
        // Safe: this package doesn't use a database.
        $prev = set_error_handler(function (int $errno, string $errstr) use (&$prev) {
            if ($errno === E_DEPRECATED && str_contains($errstr, 'MYSQL_ATTR_SSL_CA')) {
                return true;
            }

            return $prev ? $prev($errno, $errstr) : false;
        }, E_DEPRECATED);

        parent::resolveApplicationConfiguration($app);

        restore_error_handler();
    }
}
