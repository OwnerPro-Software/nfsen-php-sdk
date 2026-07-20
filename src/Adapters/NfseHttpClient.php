<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Closure;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Support\TempFileFactory;
use SensitiveParameter;

final readonly class NfseHttpClient implements SendsHttpRequests, SendsRawHttpRequests
{
    public function __construct(
        #[SensitiveParameter] private Certificate $certificate,
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
            $response = $this->guardTransport(fn (): Response => Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                    'allow_redirects' => false,
                ])
                ->head($url));

            if ($response->serverError()) {
                throw HttpException::fromResponse($response->status(), $response->body());
            }

            return $response->status();
        });
    }

    public function getBytes(string $url): string
    {
        return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($url): string {
            $response = $this->guardTransport(fn (): Response => Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                    'allow_redirects' => false,
                ])
                ->get($url));

            if (! $response->successful()) {
                throw HttpException::fromResponse($response->status(), $response->body());
            }

            return $response->body();
        });
    }

    public function getResponse(string $url): HttpResponse
    {
        return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($url): HttpResponse {
            $response = $this->guardTransport(fn (): Response => Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withOptions([
                    'verify' => $this->sslVerify,
                    'cert' => $certPath,
                    'ssl_key' => $keyPath,
                    'allow_redirects' => false,
                ])
                ->get($url));

            $decoded = json_decode($response->body(), true);

            // Mesma regra de request(): 2xx sem JSON legível nunca vira
            // resposta "vazia" — é estado indeterminado.
            if ($response->successful() && ! is_array($decoded)) {
                throw IndeterminateResultException::fromUnreadableResponse($response->status(), $response->body());
            }

            /** @var array<string, mixed> $json */
            $json = is_array($decoded) ? $decoded : [];

            return new HttpResponse($response->status(), $json, $response->body());
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

            $response = $this->guardTransport(fn (): Response => $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url));

            $decoded = json_decode($response->body(), true);

            // 2xx sem JSON legível: o servidor confirmou o processamento mas o
            // resultado não pôde ser interpretado — estado indeterminado, nunca
            // um sucesso silencioso.
            if ($response->successful()) {
                if (! is_array($decoded)) {
                    throw IndeterminateResultException::fromUnreadableResponse($response->status(), $response->body());
                }

                /** @var array<string, mixed> $decoded */
                return $decoded;
            }

            if (is_array($decoded) && $decoded !== []) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }

            throw HttpException::fromResponse($response->status(), $response->body());
        });
    }

    /**
     * Envia a requisição convertendo falha de transporte em
     * IndeterminateResultException — falha antes de qualquer resposta
     * (timeout, DNS, recusa, TLS) e falha no meio da transferência (conexão
     * resetada, corpo truncado — cURL 18/56/92).
     *
     * Os três catches cobrem as variações de marshalling entre versões do
     * Laravel: ConnectionException (falha de conexão em todas; no Laravel 13+
     * também RequestException do Guzzle sem response), RequestException do
     * Laravel (13+: falha do Guzzle com response parcial ≥ 400 — nunca
     * produzida por status HTTP aqui, pois o SDK não usa throw()) e
     * TransferException do Guzzle (11/12: falha na transferência propagada
     * crua).
     *
     * @param  Closure(): Response  $send
     */
    private function guardTransport(Closure $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException|RequestException|TransferException $transportFailure) {
            throw IndeterminateResultException::fromTransportFailure($transportFailure);
        }
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
