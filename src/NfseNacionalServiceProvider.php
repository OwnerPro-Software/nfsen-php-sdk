<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Xml\DpsBuilder;
use RuntimeException;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function (Application $app): NfseClient {
            /**
             * @var array{
             *     ambiente: int|string,
             *     prefeitura: string|null,
             *     certificado: array{
             *         path: string|null,
             *         senha: string|null,
             *     },
             *     timeout: int,
             *     signing_algorithm: string,
             *     ssl_verify: bool,
             * } $config
             */
            $config = $app['config']['nfse-nacional'];
            $jsonPath = __DIR__.'/../storage/prefeituras.json';

            $client = new NfseClient(
                ambiente: NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                prefeituraResolver: new PrefeituraResolver($jsonPath),
                dpsBuilder: new DpsBuilder(__DIR__.'/../storage/schemes'),
            );

            $certPath = $config['certificado']['path'];
            $certSenha = (string) $config['certificado']['senha'];
            $prefeitura = (string) $config['prefeitura'];

            if ($certPath && $certSenha && $prefeitura && file_exists($certPath)) {
                $certContent = @file_get_contents($certPath);

                if ($certContent === false || $certContent === '') {
                    throw new RuntimeException('Falha ao ler certificado: '.$certPath);
                }

                $client->configure($certContent, $certSenha, $prefeitura);
            }

            return $client;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nfse-nacional.php' => config_path('nfse-nacional.php'),
            ], 'nfse-nacional-config');
        }
    }
}
