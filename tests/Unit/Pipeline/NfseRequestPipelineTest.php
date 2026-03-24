<?php

covers(\OwnerPro\Nfsen\Pipeline\NfseRequestPipeline::class);

use OwnerPro\Nfsen\Contracts\Driven\ExtractsAuthorIdentity;
use OwnerPro\Nfsen\Contracts\Driven\ResolvesPrefeituras;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driven\SignsXml;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * @param  array<string, mixed>  $postResult
 * @param  ?array{cnpj: ?string, cpf: ?string}  $identity
 * @return object{pipeline: NfseRequestPipeline, postedUrl: ?string, postedPayload: ?array<string, string>}
 */
function buildRequestPipeline(
    string $seFinUrl = 'https://sefin.example.com',
    string $opPath = 'api/nfse',
    ?GzipCompressor $gzipCompressor = null,
    string $signResult = '<Signed/>',
    array $postResult = ['status' => 'ok'],
    ?array $identity = null,
): object {
    $ctx = new stdClass;
    $ctx->postedUrl = null;
    $ctx->postedPayload = null;

    $signer = new class($signResult) implements SignsXml
    {
        public function __construct(private readonly string $result) {}

        public function sign(string $xml, string $tagname, string $rootname): string
        {
            return $this->result;
        }
    };

    $prefeituraResolver = new class($seFinUrl, $opPath) implements ResolvesPrefeituras
    {
        public function __construct(private readonly string $url, private readonly string $path) {}

        public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string
        {
            return $this->url;
        }

        public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string
        {
            return '';
        }

        /** @param array<string, int|string> $params */
        public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string
        {
            return $this->path;
        }
    };

    $authorIdentity = new class($identity ?? ['cnpj' => '12345678000195', 'cpf' => null]) implements ExtractsAuthorIdentity
    {
        /** @param array{cnpj: ?string, cpf: ?string} $id */
        public function __construct(private readonly array $id) {}

        /** @return array{cnpj: ?string, cpf: ?string} */
        public function extract(): array
        {
            return $this->id;
        }
    };

    $httpClient = new class($postResult, $ctx) implements SendsHttpRequests
    {
        /** @param array<string, mixed> $result */
        public function __construct(private readonly array $result, private readonly object $ctx) {}

        /** @param array<string, string> $payload */
        public function post(string $url, array $payload): array
        {
            $this->ctx->postedUrl = $url;
            $this->ctx->postedPayload = $payload;

            return $this->result;
        }

        public function get(string $url): array
        {
            return [];
        }

        public function getBytes(string $url): string
        {
            return '';
        }

        public function head(string $url): int
        {
            return 200;
        }
    };

    $ctx->pipeline = new NfseRequestPipeline(
        ambiente: NfseAmbiente::HOMOLOGACAO,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: $gzipCompressor ?? new GzipCompressor,
        signer: $signer,
        authorIdentity: $authorIdentity,
        prefeitura: '9999999',
        httpClient: $httpClient,
    );

    return $ctx;
}

// --- signCompressSend ---

it('prepends xml declaration to signed content before compression', function (): void {
    $ctx = buildRequestPipeline(signResult: '<Root/>');

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    $compressed = base64_decode($ctx->postedPayload['key']);
    $decompressed = gzdecode($compressed);

    expect($decompressed)->toBe('<?xml version="1.0" encoding="UTF-8"?><Root/>');
});

it('throws NfseException when compression fails', function (): void {
    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $ctx = buildRequestPipeline(gzipCompressor: $compressor);

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');
})->throws(NfseException::class, 'Falha ao comprimir XML.');

it('sends base64 encoded compressed payload with the given key', function (): void {
    $ctx = buildRequestPipeline();

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'myPayloadKey', 'op');

    expect($ctx->postedPayload)
        ->toHaveKey('myPayloadKey')
        ->toHaveCount(1);

    $decompressed = gzdecode(base64_decode($ctx->postedPayload['myPayloadKey']));
    expect($decompressed)->toBeString();
});

it('joins sefin url and operation path with slash', function (): void {
    $ctx = buildRequestPipeline(seFinUrl: 'https://sefin.example.com', opPath: 'api/nfse');

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    expect($ctx->postedUrl)->toBe('https://sefin.example.com/api/nfse');
});

it('uses sefin url directly when operation path is empty', function (): void {
    $ctx = buildRequestPipeline(seFinUrl: 'https://sefin.example.com', opPath: '');

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    expect($ctx->postedUrl)->toBe('https://sefin.example.com');
});

it('trims trailing slash from sefin url when joining', function (): void {
    $ctx = buildRequestPipeline(seFinUrl: 'https://sefin.example.com/', opPath: 'api/nfse');

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    expect($ctx->postedUrl)->toBe('https://sefin.example.com/api/nfse');
});

it('trims leading slash from operation path when joining', function (): void {
    $ctx = buildRequestPipeline(seFinUrl: 'https://sefin.example.com', opPath: '/api/nfse');

    $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    expect($ctx->postedUrl)->toBe('https://sefin.example.com/api/nfse');
});

it('returns http client post result', function (): void {
    $ctx = buildRequestPipeline(postResult: ['chave' => 'ABC123']);

    $result = $ctx->pipeline->signCompressSend('<xml/>', 'tag', 'root', 'key', 'op');

    expect($result)->toBe(['chave' => 'ABC123']);
});

// --- extractAuthorIdentity ---

it('returns identity when cnpj is present', function (): void {
    $ctx = buildRequestPipeline(identity: ['cnpj' => '12345678000195', 'cpf' => null]);

    $result = $ctx->pipeline->extractAuthorIdentity('emitir');

    expect($result)->toBe(['cnpj' => '12345678000195', 'cpf' => null]);
});

it('returns identity when cpf is present', function (): void {
    $ctx = buildRequestPipeline(identity: ['cnpj' => null, 'cpf' => '12345678901']);

    $result = $ctx->pipeline->extractAuthorIdentity('cancelar');

    expect($result)->toBe(['cnpj' => null, 'cpf' => '12345678901']);
});

it('throws when both cnpj and cpf are null', function (): void {
    $ctx = buildRequestPipeline(identity: ['cnpj' => null, 'cpf' => null]);

    $ctx->pipeline->extractAuthorIdentity('cancelar');
})->throws(NfseException::class, 'cancelar');
