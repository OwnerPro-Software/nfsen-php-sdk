<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Http;

use Closure;
use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Contracts\Ports\Driven\SendsHttpRequests;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\TempFileFactory;

final readonly class NfseHttpClient implements SendsHttpRequests
{
    public function __construct(
        private Certificate $certificate,
        private int $timeout = 30,
        private int $connectTimeout = 10,
        private bool $sslVerify = true,
        private TempFileFactory $tempFileFactory = new TempFileFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload): array
    {
        return $this->request('post', $url, $payload);
    }

    /** @return array<string, mixed> */
    public function get(string $url): array
    {
        return $this->request('get', $url, []);
    }

    public function head(string $url): int
    {
        return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($url): int {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                    'allow_redirects' => false,
                ])
                ->head($url);

            if ($response->serverError()) {
                throw HttpException::fromResponse($response->status(), $response->body());
            }

            return $response->status();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload): array
    {
        return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($method, $url, $payload): array {
            $pending = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                    'allow_redirects' => false,
                ]);

            $response = $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url);

            /** @var array<string, mixed> $json */
            $json = (array) ($response->json() ?? []);

            if ($json === [] && $response->serverError()) {
                throw HttpException::fromResponse($response->status(), $response->body());
            }

            return $json;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(string, string): T  $callback
     * @return T
     */
    private function withCertificateFiles(Closure $callback): mixed
    {
        $certHandle = ($this->tempFileFactory)();
        $keyHandle = ($this->tempFileFactory)();

        if ($certHandle === false || $keyHandle === false) {
            if ($certHandle !== false) {
                fclose($certHandle);
            }

            if ($keyHandle !== false) {
                fclose($keyHandle);
            }

            throw new NfseException('Falha ao criar arquivos temporários para o certificado.');
        }

        try {
            $certData = (string) $this->certificate;
            $keyData = (string) $this->certificate->privateKey;

            if (fwrite($certHandle, $certData) !== strlen($certData)
                || fwrite($keyHandle, $keyData) !== strlen($keyData)
            ) {
                throw new NfseException('Falha ao escrever certificado em arquivo temporário.');
            }

            $certPath = stream_get_meta_data($certHandle)['uri']; // @phpstan-ignore offsetAccess.notFound (tmpfile always has uri)
            $keyPath = stream_get_meta_data($keyHandle)['uri']; // @phpstan-ignore offsetAccess.notFound (tmpfile always has uri)

            chmod($certPath, 0600);
            chmod($keyPath, 0600);

            return $callback($certPath, $keyPath);
        } finally {
            fclose($certHandle);
            fclose($keyHandle);
        }
    }
}
