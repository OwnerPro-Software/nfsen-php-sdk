<?php

covers(\Pulsar\NfseNacional\Operations\NfseConsulter::class);

use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Contracts\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\Enums\TipoEvento;
use Pulsar\NfseNacional\Operations\NfseConsulter;
use Pulsar\NfseNacional\Responses\NfseResponse;

class FakeNfseClientForConsulta implements ExecutesNfseRequests
{
    public array $calls = [];

    public int $headStatus = 200;

    public function executeAndDecompress(string $url): NfseResponse
    {
        $this->calls[] = $url;

        return new NfseResponse(true, 'chave123', '<xml/>');
    }

    public function execute(string $url): array
    {
        $this->calls[] = $url;

        return ['chaveAcesso' => null];
    }

    public function executeAndDownload(string $url): string
    {
        $this->calls[] = $url;

        return 'FAKE-PDF-BYTES';
    }

    public function executeHead(string $url): int
    {
        $this->calls[] = $url;

        return $this->headStatus;
    }
}

function makeNfseConsulter(FakeNfseClientForConsulta $fakeClient): NfseConsulter
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeAndDecompress with nfse url for nfse query', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $response = $builder->nfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave);
});

it('calls execute with dps url', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    $builder->dps('DPS456');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS456');
});

it('dps returns failure when erros key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erros: list<array{descricao: string}>} */
        public function execute(string $url): array
        {
            return ['erros' => [['descricao' => 'DPS não encontrada']]];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('DPS não encontrada');
});

it('dps returns success with idDps', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{chaveAcesso: string, idDps: string, tipoAmbiente: int, versaoAplicativo: string, dataHoraProcessamento: string} */
        public function execute(string $url): array
        {
            return ['chaveAcesso' => 'CHAVE_DPS', 'idDps' => 'DPS001', 'tipoAmbiente' => 2, 'versaoAplicativo' => '1.0.0', 'dataHoraProcessamento' => '2026-01-01T00:00:00'];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_DPS');
    expect($response->idDps)->toBe('DPS001');
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-01-01T00:00:00');
});

it('danfse returns success with pdf bytes', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            return 'PDF-BINARY-CONTENT';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->pdf)->toBe('PDF-BINARY-CONTENT');
});

it('danfse returns failure on empty response', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->codigo)->toBe('EMPTY_RESPONSE');
});

it('danfse returns failure with parsed JSON errors on HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            $e = \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(
                400,
                json_encode(['erros' => [['descricao' => 'DANFSe não encontrada', 'codigo' => '404']]]),
            );
            throw $e;
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('DANFSe não encontrada');
    expect($response->erros[0]->codigo)->toBe('404');
});

it('danfse returns failure with raw error on non-JSON HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            throw \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(500, 'Server Error');
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 500');
    expect($response->erros[0]->codigo)->toBe('500');
    expect($response->erros[0]->descricao)->toBe('Server Error');
});

it('danfse returns failure with parsed singular erro on HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            $e = \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(
                400,
                json_encode(['erro' => ['descricao' => 'Chave inválida', 'codigo' => 'E400']]),
            );
            throw $e;
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('Chave inválida');
    expect($response->erros[0]->codigo)->toBe('E400');
});

it('danfse falls back to raw error when JSON body has no erros/erro keys', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            throw \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(
                503,
                json_encode(['message' => 'Service Unavailable']),
            );
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 503');
    expect($response->erros[0]->codigo)->toBe('503');
});

it('eventos returns failure when erros key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erros: list<array{descricao: string}>} */
        public function execute(string $url): array
        {
            return ['erros' => [['descricao' => 'Evento não encontrado']]];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento não encontrado');
});

it('eventos returns failure when singular erro key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erro: array{descricao: string}} */
        public function execute(string $url): array
        {
            return ['erro' => ['descricao' => 'Evento não encontrado']];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento não encontrado');
});

it('eventos returns success with decompressed xml', function () {
    $originalXml = '<Evento/>';
    $gzipB64 = base64_encode((string) gzencode($originalXml));

    $fakeClient = new class($gzipB64) implements ExecutesNfseRequests
    {
        public function __construct(private readonly string $gzipB64) {}

        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{eventoXmlGZipB64: string, tipoAmbiente: int, versaoAplicativo: string, dataHoraProcessamento: string} */
        public function execute(string $url): array
        {
            return ['eventoXmlGZipB64' => $this->gzipB64, 'tipoAmbiente' => 2, 'versaoAplicativo' => '1.0.0', 'dataHoraProcessamento' => '2026-01-01T00:00:00'];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe('<Evento/>');
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-01-01T00:00:00');
});

it('buildUrl returns baseUrl when path is empty', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['query_nfse' => '']],
    ]));

    $innerClient = new class implements ExecutesNfseRequests
    {
        public string $lastUrl = '';

        public function executeAndDecompress(string $url): NfseResponse
        {
            $this->lastUrl = $url;

            return new NfseResponse(true);
        }

        /** @return array<string, mixed> */
        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $builder = new NfseConsulter($innerClient, 'https://sefin.base', '', $resolver, '9999998');
        $builder->nfse(makeChaveAcesso());

        expect($innerClient->lastUrl)->toBe('https://sefin.base');
    } finally {
        unlink($tmpJson);
    }
});

it('eventos uses default nSequencial = 1 in URL', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/101101/1');
});

it('passes custom tipoEvento enum and nSequencial to eventos URL', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, TipoEvento::CancelamentoPorDecisaoJudicial, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('coerces int tipoEvento to TipoEvento enum', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, 105102, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('throws ValueError for invalid int tipoEvento', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->eventos(makeChaveAcesso(), 999999))
        ->toThrow(ValueError::class);
});

it('danfse uses adnBaseUrl when populated', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', 'https://adn.base', $resolver, '9999999');
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://adn.base/danfse/'.$chave);
});

it('danfse falls back to seFinBaseUrl when adnBaseUrl is empty', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/danfse/'.$chave);
});

it('verificarDps returns true when status is 200', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $fakeClient->headStatus = 200;
    $builder = makeNfseConsulter($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS123');
});

it('verificarDps returns false when status is 404', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $fakeClient->headStatus = 404;
    $builder = makeNfseConsulter($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeFalse();
});

it('throws InvalidArgumentException for invalid chaveAcesso on nfse', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->nfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on danfse', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->danfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on eventos', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('buildUrl trims trailing slash from baseUrl', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base/', '', $resolver, '9999999');

    $builder->dps('DPS123');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS123');
});

it('buildUrl trims leading slash from path', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['query_nfse' => '/nfse/{chave}']],
    ]));

    $fakeClient = new FakeNfseClientForConsulta;

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999998');
        $chave = makeChaveAcesso();
        $builder->nfse($chave);

        expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave);
    } finally {
        unlink($tmpJson);
    }
});
