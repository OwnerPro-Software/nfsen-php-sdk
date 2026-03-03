<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Illuminate\Support\ServiceProvider;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use RuntimeException;

final class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function (): NfseClient {
            /**
             * @var array{
             *     ambiente: int|string,
             *     prefeitura: string|null,
             *     certificado: array{
             *         path: string|null,
             *         senha: string|null,
             *     },
             *     timeout: int,
             *     connect_timeout: int,
             *     signing_algorithm: string,
             *     ssl_verify: bool,
             * } $config
             */
            $config = config('nfse-nacional');
            $certPath = $config['certificado']['path'];
            $certSenha = (string) $config['certificado']['senha'];
            $prefeitura = (string) $config['prefeitura'];

            if (! $certPath || ! $certSenha || ! $prefeitura || ! is_file($certPath)) {
                throw new NfseException(
                    'NfseClient não configurado. Use NfseClient::for() ou configure certificado/prefeitura no config/nfse-nacional.php.'
                );
            }

            $certContent = file_get_contents($certPath);

            if ($certContent === false || $certContent === '') {
                throw new RuntimeException('Falha ao ler arquivo de certificado digital.');
            }

            return NfseClient::forStandalone(
                pfxContent: $certContent,
                senha: $certSenha,
                prefeitura: $prefeitura,
                ambiente: NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                connectTimeout: $config['connect_timeout'],
            );
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
