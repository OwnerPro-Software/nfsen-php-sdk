<?php

namespace Pulsar\NfseNacional;

use Illuminate\Support\ServiceProvider;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function (\Illuminate\Foundation\Application $app) {
            $config   = $app['config']['nfse-nacional'];
            $jsonPath = __DIR__ . '/../storage/prefeituras.json';

            $client = new NfseClient(
                ambiente:           NfseAmbiente::fromConfig($config['ambiente']),
                timeout:            (int) $config['timeout'],
                signingAlgorithm:   $config['signing_algorithm'],
                sslVerify:          (bool) $config['ssl_verify'],
                prefeituraResolver: new PrefeituraResolver($jsonPath),
                dpsBuilder:         new DpsBuilder(__DIR__ . '/../storage/schemes'),
            );

            $certPath    = $config['certificado']['path'] ?? null;
            $certSenha   = $config['certificado']['senha'] ?? null;
            $prefeitura  = $config['prefeitura'] ?? null;

            if ($certPath && $certSenha && $prefeitura && file_exists($certPath)) {
                $client->configure(file_get_contents($certPath), $certSenha, $prefeitura);
            }

            return $client;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nfse-nacional.php' => config_path('nfse-nacional.php'),
            ], 'nfse-nacional-config');
        }
    }
}
