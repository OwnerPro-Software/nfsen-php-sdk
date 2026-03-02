<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Http;

use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\TempFileFactory;

final readonly class NfseHttpClient
{
    public function __construct(
        private Certificate $certificate,
        private int $timeout = 30,
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload): array
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

            $pending = Http::timeout($this->timeout)
                ->acceptJson()
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                ]);

            $response = $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url);

            if ($response->serverError()) {
                $body = substr($response->body(), 0, 500);
                $message = 'HTTP error: '.$response->status();

                if ($body !== '') {
                    $message .= ' — '.$body;
                }

                throw new HttpException($message, $response->status());
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
