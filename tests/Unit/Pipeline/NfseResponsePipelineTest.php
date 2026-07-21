<?php

use Illuminate\Support\Facades\Event;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Events\NfseFailed;
use OwnerPro\Nfsen\Events\NfseQueried;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;
use OwnerPro\Nfsen\Responses\HttpResponse;

covers(NfseResponsePipeline::class);

/**
 * @param  ?array<string, mixed>  $getResult
 */
function buildResponsePipeline(
    ?array $getResult = null,
    ?int $headResult = null,
    ?Throwable $throwOnGet = null,
    ?Throwable $throwOnHead = null,
    ?string $getBytesResult = null,
    ?Throwable $throwOnGetBytes = null,
    ?HttpResponse $rawResult = null,
): NfseResponsePipeline {
    $httpClient = new class($getResult ?? [], $headResult ?? 200, $throwOnGet, $throwOnHead, $getBytesResult, $throwOnGetBytes, $rawResult) implements SendsHttpRequests, SendsRawHttpRequests
    {
        public function __construct(
            private readonly array $getResult,
            private readonly int $headResult,
            private readonly ?Throwable $throwOnGet,
            private readonly ?Throwable $throwOnHead,
            private readonly ?string $getBytesResult,
            private readonly ?Throwable $throwOnGetBytes,
            private readonly ?HttpResponse $rawResult,
        ) {}

        public function getResponse(string $url): HttpResponse
        {
            return $this->rawResult ?? new HttpResponse(200, [], '');
        }

        /** @param array<string, string> $payload */
        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            if ($this->throwOnGet) {
                throw $this->throwOnGet;
            }

            return $this->getResult;
        }

        public function getBytes(string $url): string
        {
            if ($this->throwOnGetBytes) {
                throw $this->throwOnGetBytes;
            }

            return $this->getBytesResult ?? '';
        }

        public function head(string $url): int
        {
            if ($this->throwOnHead) {
                throw $this->throwOnHead;
            }

            return $this->headResult;
        }
    };

    return new NfseResponsePipeline($httpClient);
}

// --- executeAndDecompress ---

it('returns successful response with decompressed xml and metadata on executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'chaveAcesso' => 'CHAVE123',
        'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>')),
        'tipoAmbiente' => 2,
        'versaoAplicativo' => '1.0.0',
        'dataHoraProcessamento' => '2024-01-01T00:00:00',
    ]);

    $response = $pipeline->executeAndDecompress('https://example.com/nfse');

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('CHAVE123')
        ->xml->toBe('<NFSe/>')
        ->tipoAmbiente->toBe(2)
        ->versaoAplicativo->toBe('1.0.0')
        ->dataHoraProcessamento->toBe('2024-01-01T00:00:00');
});

it('dispatches NfseRequested and NfseQueried on successful executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: ['chaveAcesso' => 'ABC']);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class, fn (NfseQueried $e): bool => $e->operacao === 'consultar');
});

it('returns error response when erros key is present on executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['codigo' => 'E01', 'mensagem' => 'Validation error']],
        'tipoAmbiente' => 1,
        'versaoAplicativo' => '2.0',
        'dataHoraProcessamento' => '2024-06-15',
    ]);

    $response = $pipeline->executeAndDecompress('https://example.com/nfse');

    expect($response)
        ->sucesso->toBeFalse()
        ->erros->toHaveCount(1)
        ->tipoAmbiente->toBe(1)
        ->versaoAplicativo->toBe('2.0')
        ->dataHoraProcessamento->toBe('2024-06-15');
});

it('returns error response when singular erro key is present on executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erro' => ['codigo' => 'E02', 'mensagem' => 'Single error'],
    ]);

    $response = $pipeline->executeAndDecompress('https://example.com/nfse');

    expect($response)
        ->sucesso->toBeFalse()
        ->erros->toHaveCount(1);
});

it('dispatches NfseRejected with error code on executeAndDecompress error', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['codigo' => 'ERR_42']],
    ]);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->operacao === 'consultar' && $e->codigoErro === 'ERR_42');
});

it('dispatches NfseRejected with mensagemErro and correcao on executeAndDecompress error', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [[
            'codigo' => 'ERR_42',
            'descricao' => 'CNPJ inválido',
            'complemento' => 'Verifique o CNPJ do prestador',
        ]],
    ]);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->mensagemErro === 'CNPJ inválido' && $e->correcao === 'Verifique o CNPJ do prestador');
});

it('falls back to mensagem when descricao is missing on executeAndDecompress error', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['codigo' => 'ERR_42', 'mensagem' => 'Apenas mensagem']],
    ]);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->mensagemErro === 'Apenas mensagem');
});

it('uses UNKNOWN as fallback error code when codigo is missing on executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['mensagem' => 'Error without code']],
    ]);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'UNKNOWN');
});

// --- executeRaw ---

it('returns raw HttpResponse and dispatches NfseQueried on 200 executeRaw', function (): void {
    Event::fake();

    $raw = new HttpResponse(200, ['chaveAcesso' => 'CHAVE123'], '{"chaveAcesso":"CHAVE123"}');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/dps/DPS1');

    expect($response)->toBe($raw);
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
    Event::assertNotDispatched(NfseRejected::class);
});

it('returns raw HttpResponse and dispatches NfseQueried on 201 executeRaw', function (): void {
    Event::fake();

    $raw = new HttpResponse(201, ['chaveAcesso' => 'CHAVE123'], '{"chaveAcesso":"CHAVE123"}');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/dps/DPS1');

    expect($response)->toBe($raw);
    Event::assertDispatched(NfseQueried::class);
    Event::assertNotDispatched(NfseRejected::class);
});

it('returns raw HttpResponse without result events on 404 without error body on executeRaw', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(rawResult: new HttpResponse(404, [], ''));

    $response = $pipeline->executeRaw('https://example.com/dps/DPS1');

    expect($response->statusCode)->toBe(404);
    Event::assertDispatched(NfseRequested::class);
    Event::assertNotDispatched(NfseQueried::class);
    Event::assertNotDispatched(NfseRejected::class);
});

it('dispatches NfseRejected on 404 with structured error body on executeRaw', function (): void {
    Event::fake();

    $raw = new HttpResponse(404, ['erros' => [['codigo' => 'E404', 'descricao' => 'DPS inexistente']]], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $pipeline->executeRaw('https://example.com/dps/DPS1');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'E404');
    Event::assertNotDispatched(NfseQueried::class);
});

it('throws IndeterminateResultException and dispatches NfseFailed when requiredField is missing on executeRaw', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(rawResult: new HttpResponse(200, [], '{}'));

    expect(fn () => $pipeline->executeRaw('https://example.com/eventos', 'eventoXmlGZipB64'))
        ->toThrow(IndeterminateResultException::class, 'eventoXmlGZipB64');

    Event::assertDispatched(NfseFailed::class);
    Event::assertNotDispatched(NfseQueried::class);
});

it('throws IndeterminateResultException when requiredField is empty or not a string on executeRaw', function (mixed $value): void {
    $pipeline = buildResponsePipeline(rawResult: new HttpResponse(200, ['eventoXmlGZipB64' => $value], ''));

    expect(fn () => $pipeline->executeRaw('https://example.com/eventos', 'eventoXmlGZipB64'))
        ->toThrow(IndeterminateResultException::class, 'eventoXmlGZipB64');
})->with([
    'empty string' => [''],
    'int' => [123],
    'null' => [null],
]);

it('returns response without requiredField check when body has structured error', function (): void {
    Event::fake();

    $raw = new HttpResponse(400, ['erros' => [['codigo' => 'E1', 'descricao' => 'Rejeitada']]], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/eventos', 'eventoXmlGZipB64');

    expect($response)->toBe($raw);
    Event::assertDispatched(NfseRejected::class);
});

it('returns response when requiredField is present on executeRaw', function (): void {
    Event::fake();

    $raw = new HttpResponse(200, ['eventoXmlGZipB64' => 'Z3ppcA=='], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/eventos', 'eventoXmlGZipB64');

    expect($response)->toBe($raw);
    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseRejected and returns response when executeRaw body has erros', function (): void {
    Event::fake();

    $raw = new HttpResponse(400, ['erros' => [['codigo' => 'E42', 'descricao' => 'Rejeitada', 'complemento' => 'Corrija']]], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/dps/DPS1');

    expect($response)->toBe($raw);
    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'E42' && $e->mensagemErro === 'Rejeitada' && $e->correcao === 'Corrija');
    Event::assertNotDispatched(NfseQueried::class);
});

it('falls back to mensagem when executeRaw error has no descricao', function (): void {
    Event::fake();

    $raw = new HttpResponse(400, ['erros' => [['mensagem' => 'No code']]], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $pipeline->executeRaw('https://example.com/dps/DPS1');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'UNKNOWN' && $e->mensagemErro === 'No code');
});

it('returns response when executeRaw gets 500 with structured error body', function (): void {
    Event::fake();

    $raw = new HttpResponse(500, ['erro' => ['codigo' => 'E500', 'descricao' => 'Falha interna']], '');
    $pipeline = buildResponsePipeline(rawResult: $raw);

    $response = $pipeline->executeRaw('https://example.com/dps/DPS1');

    expect($response->statusCode)->toBe(500);
    Event::assertDispatched(NfseRejected::class);
});

it('throws HttpException and dispatches NfseFailed when executeRaw gets unexpected status without error body', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(rawResult: new HttpResponse(500, [], 'Server Error'));

    try {
        $pipeline->executeRaw('https://example.com/dps/DPS1');
        test()->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getCode())->toBe(500)
            ->and($e->getResponseBody())->toBe('Server Error');
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar');
});

it('throws HttpException when executeRaw gets redirect without error body', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(rawResult: new HttpResponse(302, [], ''));

    expect(fn () => $pipeline->executeRaw('https://example.com/dps/DPS1'))
        ->toThrow(HttpException::class, 'HTTP error: 302');
});

// --- executeHead ---

it('returns 200 and dispatches NfseQueried on executeHead', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(headResult: 200);

    $status = $pipeline->executeHead('https://example.com/nfse');

    expect($status)->toBe(200);
    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseQueried::class);
});

it('returns 404 without dispatching NfseQueried on executeHead', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(headResult: 404);

    $status = $pipeline->executeHead('https://example.com/nfse');

    expect($status)->toBe(404);
    Event::assertDispatched(NfseRequested::class);
    Event::assertNotDispatched(NfseQueried::class);
});

it('throws HttpException and dispatches NfseFailed on executeHead for any status other than 200 and 404', function (int $status): void {
    Event::fake();

    $pipeline = buildResponsePipeline(headResult: $status);

    try {
        $pipeline->executeHead('https://example.com/nfse');
        test()->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getCode())->toBe($status)
            ->and($e->getResponseBody())->toBe('');
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar');
    Event::assertNotDispatched(NfseQueried::class);
})->with([
    'unauthorized' => 401,
    'forbidden' => 403,
    'rate limit' => 429,
    'redirect' => 302,
]);

// --- Exception propagation ---

it('dispatches NfseFailed when executeAndDecompress throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnGet: new RuntimeException('Connection failed'));

    try {
        $pipeline->executeAndDecompress('https://example.com/nfse');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->mensagem === 'Connection failed');
});

it('dispatches NfseFailed when executeHead throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnHead: new RuntimeException('Timeout'));

    try {
        $pipeline->executeHead('https://example.com/nfse');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->mensagem === 'Timeout');
});

// --- executeAndDownload ---

it('returns raw bytes and dispatches events on successful executeAndDownload', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getBytesResult: 'PDF-BINARY-CONTENT');

    $result = $pipeline->executeAndDownload('https://example.com/danfse/CHAVE');

    expect($result)->toBe('PDF-BINARY-CONTENT');
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseFailed when executeAndDownload throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnGetBytes: new RuntimeException('Connection failed'));

    try {
        $pipeline->executeAndDownload('https://example.com/danfse/CHAVE');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->mensagem === 'Connection failed');
});
