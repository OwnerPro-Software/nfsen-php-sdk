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

        return new NfseResponse(true, 'chave123', '<xml/>', null);
    }

    public function executeGetRaw(string $url): array
    {
        $this->calls[] = $url;

        return ['sucesso' => true];
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
            return new NfseResponse(true, null, null, null);
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
    expect($response->erro)->toBe('DPS não encontrada');
});

it('danfse returns failure when erros key present', function () {
    $fakeClient = new class implements NfseClientContract
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true, null, null, null);
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
    expect($response->erro)->toBe('NFSe não encontrada');
});

it('danfse returns success with danfseUrl', function () {
    $fakeClient = new class implements NfseClientContract
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true, null, null, null);
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

it('eventos returns failure when erro key present', function () {
    $fakeClient = new class implements NfseClientContract
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true, null, null, null);
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
    expect($response->erro)->toBe('Evento não encontrado');
});

it('eventos returns success with events list', function () {
    $fakeClient = new class implements NfseClientContract
    {
        public function executeGet(string $url): NfseResponse
        {
            return new NfseResponse(true, null, null, null);
        }

        /** @return array{eventos: list<array{tipo: string}>} */
        public function executeGetRaw(string $url): array
        {
            return ['eventos' => [['tipo' => '101101']]];
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
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

            return new NfseResponse(true, null, null, null);
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
