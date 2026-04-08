<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Operations\NfseDistributor;

covers(NfseDistributor::class);

function makeFakeDistribuicaoResponse(string $status = 'DOCUMENTOS_LOCALIZADOS', ?array $lote = null): array
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

function makeFakeHttpClient(array $response): SendsHttpRequests
{
    return new class($response) implements SendsHttpRequests
    {
        /** @var list<string> */
        public array $urls = [];

        public function __construct(private readonly array $response) {}

        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            $this->urls[] = $url;

            return $this->response;
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
}

function makeNfseDistributor(SendsHttpRequests $httpClient): NfseDistributor
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base', '12345678000195');
}

it('documentos sends GET with lote=true and default cnpjConsulta', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeTrue();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::DocumentosLocalizados);
    expect($response->lote)->toHaveCount(1);
    expect($response->lote[0]->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse);
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=12345678000195&lote=true');
});

it('documentos uses provided cnpjConsulta over default', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documentos(0, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=99999999000100&lote=true');
});

it('documento sends GET with lote=false', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documento(42);

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=12345678000195&lote=false');
});

it('documento uses provided cnpjConsulta', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documento(42, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=99999999000100&lote=false');
});

it('eventos sends GET with chave in URL', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);
    $chave = makeChaveAcesso();

    $response = $distributor->eventos($chave);

    expect($response->sucesso)->toBeTrue();
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/NFSe/'.$chave.'/Eventos');
});

it('eventos throws InvalidArgumentException for invalid chave', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    expect(fn () => $distributor->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('handles NENHUM_DOCUMENTO_LOCALIZADO status', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse('NENHUM_DOCUMENTO_LOCALIZADO', []));
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(999);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::NenhumDocumentoLocalizado);
    expect($response->lote)->toBeEmpty();
});

it('handles REJEICAO status with errors', function () {
    $httpClient = makeFakeHttpClient([
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ]);
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

it('handles HttpException with structured body', function () {
    $httpClient = new class implements SendsHttpRequests
    {
        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            throw HttpException::fromResponse(500, json_encode([
                'StatusProcessamento' => 'REJEICAO',
                'Erros' => [['Codigo' => 'E500', 'Descricao' => 'Erro interno']],
                'TipoAmbiente' => 'HOMOLOGACAO',
                'DataHoraProcessamento' => '2026-04-08T15:00:00',
            ]));
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

    $distributor = makeNfseDistributor($httpClient);
    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->descricao)->toBe('Erro interno');
});

it('handles HttpException with non-JSON body', function () {
    $httpClient = new class implements SendsHttpRequests
    {
        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            throw HttpException::fromResponse(500, 'Server Error');
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

    $distributor = makeNfseDistributor($httpClient);
    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 500');
    expect($response->erros[0]->codigo)->toBe('500');
    expect($response->erros[0]->descricao)->toBe('Server Error');
});

it('handles HttpException with JSON body missing StatusProcessamento', function () {
    $httpClient = new class implements SendsHttpRequests
    {
        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            throw HttpException::fromResponse(500, json_encode(['message' => 'Internal Server Error']));
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

    $distributor = makeNfseDistributor($httpClient);
    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 500');
});

it('buildUrl trims leading slash from path', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['distribute_documents' => '/contribuintes/DFe/{NSU}']],
    ]));

    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());

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

    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());

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
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $distributor = new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base/', '12345678000195');

    $distributor->documentos(0);

    expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
});
