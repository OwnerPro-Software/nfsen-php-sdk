<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Http;

use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;

class NfseHttpClient
{
    public function __construct(
        private readonly Certificate $certificate,
        private readonly int $timeout = 30,
        private readonly bool $sslVerify = true,
    ) {}

    /**
     * @param array<string, mixed> $payload
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload): array
    {
        $certHandle = tmpfile();
        $keyHandle  = tmpfile();

        // @codeCoverageIgnoreStart
        if ($certHandle === false || $keyHandle === false) {
            throw new NfseException('Falha ao criar arquivos temporários para o certificado.');
        }

        // @codeCoverageIgnoreEnd

        try {
            fwrite($certHandle, (string) $this->certificate);
            fwrite($keyHandle, (string) $this->certificate->privateKey);

            $certPath = stream_get_meta_data($certHandle)['uri']; // @phpstan-ignore offsetAccess.notFound (tmpfile always has uri)
            $keyPath  = stream_get_meta_data($keyHandle)['uri']; // @phpstan-ignore offsetAccess.notFound (tmpfile always has uri)

            $pending = Http::timeout($this->timeout)
                ->acceptJson()
                ->withOptions([
                    'verify'  => $this->sslVerify,
                    'cert'    => $certPath,
                    'ssl_key' => $keyPath,
                ]);

            $response = $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url);

            if ($response->serverError() || $response->clientError()) {
                throw new HttpException(
                    'HTTP error: ' . $response->status(),
                    $response->status()
                );
            }

            /** @var array<string, mixed> $json */
            $json = (array) ($response->json() ?? []);
            return $json;
        } finally {
            fclose($certHandle);
            fclose($keyHandle);
        }
    }
}
