<?php

use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\Ports\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\TipoEvento;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;

class FakeNfseClientForConsulta implements ExecutesNfseRequests
{
    public array $calls = [];

    public int $headStatus = 200;

    public function executeGet(string $url): NfseResponse
    {
        $this->calls[] = $url;

        return new NfseResponse(true, 'chave123', '<xml/>');
    }

    public function executeGetRaw(string $url): array
    {
        $this->calls[] = $url;

        return ['chaveAcesso' => null];
    }

    public function executeHead(string $url): int
    {
        $this->calls[] = $url;

        return $this->headStatus;
    }
}

function makeConsultaBuilder(FakeNfseClientForConsulta $fakeClient): ConsultaBuilder
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeGet with nfse url for nfse query', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);
    $chave = makeChaveAcesso();

    $response = $builder->nfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave);
});

it('calls executeGetRaw with dps url', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    $builder->dps('DPS456');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS456');
});

it('dps returns failure when erros key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erros: list<array{descricao: string}>} */
        public function executeGetRaw(string $url): array
        {
            return ['erros' => [['descricao' => 'DPS não encontrada']]];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('DPS não encontrada');
});

it('dps returns success with idDps', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{chaveAcesso: string, idDps: string} */
        public function executeGetRaw(string $url): array
        {
            return ['chaveAcesso' => 'CHAVE_DPS', 'idDps' => 'DPS001'];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_DPS');
    expect($response->idDps)->toBe('DPS001');
});

it('danfse returns failure when erros key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erros: list<array{descricao: string}>} */
        public function executeGetRaw(string $url): array
        {
            return ['erros' => [['descricao' => 'NFSe não encontrada']]];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('danfse returns success with danfseUrl', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{danfseUrl: string} */
        public function executeGetRaw(string $url): array
        {
            return ['danfseUrl' => 'https://danfse.url/PDF'];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/PDF');
});

it('eventos returns failure when erros key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erros: list<array{descricao: string}>} */
        public function executeGetRaw(string $url): array
        {
            return ['erros' => [['descricao' => 'Evento não encontrado']]];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento não encontrado');
});

it('eventos returns failure when singular erro key present', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{erro: array{descricao: string}} */
        public function executeGetRaw(string $url): array
        {
            return ['erro' => ['descricao' => 'Evento não encontrado']];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

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

        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        /** @return array{eventoXmlGZipB64: string} */
        public function executeGetRaw(string $url): array
        {
            return ['eventoXmlGZipB64' => $this->gzipB64];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe('<Evento/>');
});

it('buildUrl returns baseUrl when path is empty', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['consultar_nfse' => '']],
    ]));

    $innerClient = new class implements ExecutesNfseRequests
    {
        public string $lastUrl = '';

        public function executeGet(string $url): NfseResponse
        {
            $this->lastUrl = $url;

            return new NfseResponse(true);
        }

        /** @return array<string, mixed> */
        public function executeGetRaw(string $url): array
        {
            return [];
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver($tmpJson);
    $builder = new ConsultaBuilder($innerClient, 'https://sefin.base', '', $resolver, '9999998');
    $builder->nfse(makeChaveAcesso());

    expect($innerClient->lastUrl)->toBe('https://sefin.base');

    unlink($tmpJson);
});

it('passes custom tipoEvento enum and nSequencial to eventos URL', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, TipoEvento::CancelamentoPorDecisaoJudicial, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('coerces int tipoEvento to TipoEvento enum', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, 105102, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('throws ValueError for invalid int tipoEvento', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    expect(fn () => $builder->eventos(makeChaveAcesso(), 999999))
        ->toThrow(ValueError::class);
});

it('danfse uses adnBaseUrl when populated', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', 'https://adn.base', $resolver, '9999999');
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://adn.base/danfse/'.$chave);
});

it('danfse falls back to seFinBaseUrl when adnBaseUrl is empty', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/danfse/'.$chave);
});

it('verificarDps returns true when status is 200', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $fakeClient->headStatus = 200;
    $builder = makeConsultaBuilder($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS123');
});

it('verificarDps returns false when status is 404', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $fakeClient->headStatus = 404;
    $builder = makeConsultaBuilder($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeFalse();
});

it('throws InvalidArgumentException for invalid chaveAcesso on nfse', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    expect(fn () => $builder->nfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on danfse', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    expect(fn () => $builder->danfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on eventos', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    expect(fn () => $builder->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});
