<?php

use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

class FakeNfseClientForConsulta implements NfseClientContract
{
    public array $calls = [];

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
}

function makeConsultaBuilder(FakeNfseClientForConsulta $fakeClient): ConsultaBuilder
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeGet with nfse url for nfse query', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    $response = $builder->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/CHAVE123');
});

it('calls executeGetRaw with dps url', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    $builder->dps('CHAVE456');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/CHAVE456');
});

it('dps returns failure when erros key present', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('CHAVE123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('DPS não encontrada');
});

it('dps returns success with idDps', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_DPS');
    expect($response->idDps)->toBe('DPS001');
});

it('danfse returns failure when erros key present', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse('CHAVE123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('danfse returns success with danfseUrl', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/PDF');
});

it('eventos returns failure when erros key present', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos('CHAVE123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento não encontrado');
});

it('eventos returns failure when singular erro key present', function () {
    $fakeClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos('CHAVE123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento não encontrado');
});

it('eventos returns success with decompressed xml', function () {
    $originalXml = '<Evento/>';
    $gzipB64 = base64_encode((string) gzencode($originalXml));

    $fakeClient = new class($gzipB64) implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe('<Evento/>');
});

it('buildUrl returns baseUrl when path is empty', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['consultar_nfse' => '']],
    ]));

    $innerClient = new class implements NfseClientContract
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
    };

    $resolver = new PrefeituraResolver($tmpJson);
    $builder = new ConsultaBuilder($innerClient, 'https://sefin.base', '', $resolver, '9999998');
    $builder->nfse('CHAVE123');

    expect($innerClient->lastUrl)->toBe('https://sefin.base');

    unlink($tmpJson);
});

it('passes custom tipoEvento and nSequencial to eventos URL', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    $builder->eventos('CHAVE123', 105102, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/CHAVE123/eventos/105102/2');
});

it('danfse uses adnBaseUrl when populated', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', 'https://adn.base', $resolver, '9999999');

    $builder->danfse('CHAVE123');

    expect($fakeClient->calls[0])->toBe('https://adn.base/danfse/CHAVE123');
});

it('danfse falls back to seFinBaseUrl when adnBaseUrl is empty', function () {
    $fakeClient = new FakeNfseClientForConsulta;
    $builder = makeConsultaBuilder($fakeClient);

    $builder->danfse('CHAVE123');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/danfse/CHAVE123');
});
