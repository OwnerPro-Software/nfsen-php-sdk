<?php

use Illuminate\Support\Facades\Event;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Events\NfseFailed;
use OwnerPro\Nfsen\Events\NfseQueried;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;

covers(NfseResponsePipeline::class);

/**
 * @param  ?array<string, mixed>  $getResult
 */
function buildResponsePipeline(
    ?array $getResult = null,
    ?int $headResult = null,
    ?\Throwable $throwOnGet = null,
    ?\Throwable $throwOnHead = null,
    ?string $getBytesResult = null,
    ?\Throwable $throwOnGetBytes = null,
): NfseResponsePipeline {
    $httpClient = new class($getResult ?? [], $headResult ?? 200, $throwOnGet, $throwOnHead, $getBytesResult, $throwOnGetBytes) implements SendsHttpRequests
    {
        public function __construct(
            private readonly array $getResult,
            private readonly int $headResult,
            private readonly ?\Throwable $throwOnGet,
            private readonly ?\Throwable $throwOnHead,
            private readonly ?string $getBytesResult,
            private readonly ?\Throwable $throwOnGetBytes,
        ) {}

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

it('uses UNKNOWN as fallback error code when codigo is missing on executeAndDecompress', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['mensagem' => 'Error without code']],
    ]);

    $pipeline->executeAndDecompress('https://example.com/nfse');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'UNKNOWN');
});

// --- execute ---

it('returns raw result and dispatches NfseQueried on successful execute', function (): void {
    Event::fake();

    $expected = ['chaveAcesso' => 'CHAVE123', 'idDps' => 'DPS456'];
    $pipeline = buildResponsePipeline(getResult: $expected);

    $result = $pipeline->execute('https://example.com/dps');

    expect($result)->toBe($expected);
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
    Event::assertNotDispatched(NfseRejected::class);
});

it('returns raw result and dispatches NfseRejected on execute error', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['codigo' => 'RAW_ERR', 'mensagem' => 'Raw error']],
    ]);

    $pipeline->execute('https://example.com/dps');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'RAW_ERR');
    Event::assertNotDispatched(NfseQueried::class);
});

it('uses UNKNOWN as fallback error code on execute error without codigo', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getResult: [
        'erros' => [['mensagem' => 'No code']],
    ]);

    $pipeline->execute('https://example.com/dps');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->codigoErro === 'UNKNOWN');
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

it('returns non-200 status without dispatching NfseQueried on executeHead', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(headResult: 404);

    $status = $pipeline->executeHead('https://example.com/nfse');

    expect($status)->toBe(404);
    Event::assertDispatched(NfseRequested::class);
    Event::assertNotDispatched(NfseQueried::class);
});

// --- Exception propagation ---

it('dispatches NfseFailed when executeAndDecompress throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnGet: new RuntimeException('Connection failed'));

    try {
        $pipeline->executeAndDecompress('https://example.com/nfse');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->message === 'Connection failed');
});

it('dispatches NfseFailed when executeHead throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnHead: new RuntimeException('Timeout'));

    try {
        $pipeline->executeHead('https://example.com/nfse');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->message === 'Timeout');
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

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->message === 'Connection failed');
});
