<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Operations\NfseDistributor;
use OwnerPro\Nfsen\Responses\HttpResponse;

covers(NfseDistributor::class);

function makeFakeDistribuicaoJson(string $status = 'DOCUMENTOS_LOCALIZADOS', ?array $lote = null): array
{
    $xml = '<NFSe/>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    return [
        'StatusProcessamento' => $status,
        'LoteDFe' => $lote ?? [
            ['NSU' => 1, 'ChaveAcesso' => makeChaveAcesso(), 'TipoDocumento' => 'NFSE', 'ArquivoXml' => $gzipB64, 'DataHoraGeracao' => '2026-04-08T14:30:00'],
        ],
        'Alertas' => [],
        'Erros' => [],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
}

function makeFakeRawHttpClient(int $statusCode, array $json, ?string $body = null): SendsRawHttpRequests
{
    return new class($statusCode, $json, $body) implements SendsRawHttpRequests
    {
        /** @var list<string> */
        public array $urls = [];

        public function __construct(
            private readonly int $statusCode,
            private readonly array $json,
            private readonly ?string $body,
        ) {}

        public function getResponse(string $url): HttpResponse
        {
            $this->urls[] = $url;

            return new HttpResponse(
                $this->statusCode,
                $this->json,
                $this->body ?? json_encode($this->json, JSON_THROW_ON_ERROR),
            );
        }
    };
}

function makeDistributor(SendsRawHttpRequests $httpClient): NfseDistributor
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base', '12345678000195');
}

// --- URL construction tests ---

it('documentos sends GET with lote=true and default cnpjConsulta', function () {
    $json = makeFakeDistribuicaoJson();
    $httpClient = makeFakeRawHttpClient(200, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeTrue();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::DocumentosLocalizados);
    expect($response->lote)->toHaveCount(1);
    expect($response->lote[0]->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse);
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=12345678000195&lote=true');
});

it('documentos uses provided cnpjConsulta over default', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documentos(0, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=99999999000100&lote=true');
});

it('documento sends GET with lote=false', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documento(42);

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=12345678000195&lote=false');
});

it('documento uses provided cnpjConsulta', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documento(42, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=99999999000100&lote=false');
});

it('eventos sends GET with chave in URL', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);
    $chave = makeChaveAcesso();

    $response = $distributor->eventos($chave);

    expect($response->sucesso)->toBeTrue();
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/NFSe/'.$chave.'/Eventos');
});

it('eventos throws InvalidArgumentException for invalid chave', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    expect(fn () => $distributor->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

// --- Status handling tests ---

it('handles NENHUM_DOCUMENTO_LOCALIZADO status', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson('NENHUM_DOCUMENTO_LOCALIZADO', []));
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(999);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::NenhumDocumentoLocalizado);
    expect($response->lote)->toBeEmpty();
});

it('handles REJEICAO status with errors', function () {
    $json = [
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
    $httpClient = makeFakeRawHttpClient(200, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

// --- HTTP error handling tests ---

it('handles HTTP 500 with non-JSON body', function () {
    $httpClient = makeFakeRawHttpClient(500, [], 'Server Error');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_500')
        ->complemento->toBe('Server Error');
});

it('handles HTTP 500 with structured ADN JSON body', function () {
    $json = [
        'StatusProcessamento' => 'REJEICAO',
        'Erros' => [['Codigo' => 'E500', 'Descricao' => 'Erro interno']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
    $httpClient = makeFakeRawHttpClient(500, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->descricao)->toBe('Erro interno');
});

it('handles HTTP 500 with JSON missing StatusProcessamento', function () {
    $json = ['message' => 'Internal Server Error'];
    $httpClient = makeFakeRawHttpClient(500, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->codigo)->toBe('HTTP_500');
});

it('handles HTTP 429 rate limiting', function () {
    $httpClient = makeFakeRawHttpClient(429, [], 'Too Many Requests');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])
        ->codigo->toBe('HTTP_429')
        ->complemento->toBe('Too Many Requests');
});

it('handles HTTP 200 with empty body', function () {
    $httpClient = makeFakeRawHttpClient(200, [], '');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])
        ->codigo->toBe('EMPTY_RESPONSE')
        ->descricao->toBe('A API retornou HTTP 200 com corpo vazio.');
});

// --- URL construction edge cases ---

it('buildUrl trims leading slash from path', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['distribute_documents' => '/contribuintes/DFe/{NSU}']],
    ]));

    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $distributor = new NfseDistributor($httpClient, $resolver, '9999998', 'https://adn.base', '12345678000195');
        $distributor->documentos(0);

        expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
    } finally {
        unlink($tmpJson);
    }
});

it('buildUrl returns baseUrl when path is empty', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['distribute_documents' => '']],
    ]));

    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $distributor = new NfseDistributor($httpClient, $resolver, '9999998', 'https://adn.base', '12345678000195');
        $distributor->documentos(0);

        expect($httpClient->urls[0])->toStartWith('https://adn.base?');
    } finally {
        unlink($tmpJson);
    }
});

it('buildUrl trims trailing slash from baseUrl', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $distributor = new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base/', '12345678000195');

    $distributor->documentos(0);

    expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
});
