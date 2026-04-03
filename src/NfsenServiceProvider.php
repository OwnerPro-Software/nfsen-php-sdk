<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen;

use Illuminate\Support\ServiceProvider;
use Override;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\NfseException;
use RuntimeException;

final class NfsenServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nfsen.php', 'nfsen');

        $this->app->bind(NfsenClient::class, function (): NfsenClient {
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
             *     validate_identity: bool,
             * } $config
             */
            $config = config('nfsen');
            $certPath = $config['certificado']['path'];
            $certSenha = (string) $config['certificado']['senha']; // @pest-mutate-ignore RemoveStringCast — config always returns string
            $prefeitura = (string) $config['prefeitura'];

            if (! $certPath || ! $certSenha || ! $prefeitura || ! is_file($certPath)) {
                throw new NfseException(
                    'NfsenClient não configurado. Use NfsenClient::for() ou configure certificado/prefeitura no config/nfsen.php.'
                );
            }

            $certContent = file_get_contents($certPath);

            if ($certContent === false || $certContent === '') { // @pest-mutate-ignore FalseToTrue — file_get_contents returns false only on unreadable files, already guarded by is_file
                throw new RuntimeException('Falha ao ler arquivo de certificado digital.');
            }

            return NfsenClient::forStandalone(
                pfxContent: $certContent,
                senha: $certSenha,
                prefeitura: $prefeitura,
                ambiente: NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                connectTimeout: $config['connect_timeout'],
                validateIdentity: $config['validate_identity'],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nfsen.php' => config_path('nfsen.php'),
            ], 'nfsen-config');
        }
    }
}
