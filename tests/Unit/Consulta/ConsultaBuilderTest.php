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
    $resolver = new PrefeituraResolver(__DIR__ . '/../../../storage/prefeituras.json');
    return new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeGet with nfse url for nfse query', function () {
    $fakeClient = new FakeNfseClientForConsulta();
    $builder    = makeConsultaBuilder($fakeClient);

    $response = $builder->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toContain('nfse/CHAVE123');
});

it('calls executeGet with dps url', function () {
    $fakeClient = new FakeNfseClientForConsulta();
    $builder    = makeConsultaBuilder($fakeClient);

    $builder->dps('CHAVE456');

    expect($fakeClient->calls[0])->toContain('dps/CHAVE456');
});
