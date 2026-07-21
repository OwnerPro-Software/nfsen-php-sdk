<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Contracts\Driving\ExecutesNfseRequests;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

covers(NfseConsulter::class);

class FakeNfsenClientForConsulta implements ExecutesNfseRequests
{
    public array $calls = [];

    public int $headStatus = 200;

    public ?HttpResponse $rawResponse = null;

    public function executeAndDecompress(string $url): NfseResponse
    {
        $this->calls[] = $url;

        return new NfseResponse(true, 'chave123', '<xml/>');
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

    public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
    {
        $this->calls[] = $url;

        return $this->rawResponse ?? new HttpResponse(200, ['chaveAcesso' => null], '');
    }
}

function makeEventoResponse(int $statusCode = 200): HttpResponse
{
    return new HttpResponse($statusCode, ['eventoXmlGZipB64' => base64_encode((string) gzencode('<Evento/>'))], '');
}

function makeNfseConsulter(FakeNfsenClientForConsulta $fakeClient): NfseConsulter
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeAndDecompress with nfse url for nfse query', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $response = $builder->nfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave);
});

it('calls executeRaw with dps url', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    $builder->dps('DPS456');

    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS456');
});

it('dps returns failure when erros key present', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(400, ['erros' => [['descricao' => 'DPS rejeitada']]], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('DPS rejeitada');
});

it('dps returns success with idDps', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(200, ['chaveAcesso' => 'CHAVE_DPS', 'idDps' => 'DPS001', 'tipoAmbiente' => 2, 'versaoAplicativo' => '1.0.0', 'dataHoraProcessamento' => '2026-01-01T00:00:00'], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_DPS');
    expect($response->idDps)->toBe('DPS001');
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-01-01T00:00:00');
});

it('dps returns DPS_NOT_FOUND failure on 404 without error body', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(404, [], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->codigo)->toBe(NfseResponse::DPS_NOT_FOUND);
    expect($response->erros[0]->mensagem)->toBe('DPS não encontrada');
});

it('dps prepends DPS_NOT_FOUND and preserves SEFIN errors on 404 with error body', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(
        404,
        ['erros' => [['codigo' => 'E404', 'descricao' => 'DPS inexistente']], 'tipoAmbiente' => 2],
        '',
    );
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->dps('DPS123');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(2);
    expect($response->erros[0]->codigo)->toBe(NfseResponse::DPS_NOT_FOUND);
    expect($response->erros[1]->codigo)->toBe('E404');
    expect($response->erros[1]->descricao)->toBe('DPS inexistente');
    expect($response->tipoAmbiente)->toBe(2);
});

it('danfse returns success with pdf bytes', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function executeAndDownload(string $url): string
        {
            return 'PDF-BINARY-CONTENT';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
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

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
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

        public function executeAndDownload(string $url): string
        {
            $e = HttpException::fromResponse(
                400,
                json_encode(['erros' => [['descricao' => 'DANFSe não encontrada', 'codigo' => '404']]]),
            );
            throw $e;
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('DANFSe não encontrada');
    expect($response->erros[0]->codigo)->toBe('404');
});

it('danfse parses a SEFIN error envelope larger than 500 bytes', function () {
    // HttpException truncava o corpo em 500 bytes; parseHttpError() faz json_decode()
    // dele, então um envelope maior que o corte virava JSON inválido e as mensagens
    // estruturadas da SEFIN eram trocadas por um genérico "HTTP error: N".
    $descricaoLonga = str_repeat('detalhe do erro ', 60); // ~960 bytes

    $fakeClient = new class($descricaoLonga) implements ExecutesNfseRequests
    {
        public function __construct(private string $descricao) {}

        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function executeAndDownload(string $url): string
        {
            throw HttpException::fromResponse(
                400,
                (string) json_encode(['erros' => [['descricao' => $this->descricao, 'codigo' => 'E999']]]),
            );
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse()
        ->and($response->erros[0]->codigo)->toBe('E999')
        ->and($response->erros[0]->descricao)->toBe($descricaoLonga);
});

it('danfse returns failure with raw error on non-JSON HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function executeAndDownload(string $url): string
        {
            throw HttpException::fromResponse(500, 'Server Error');
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
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

        public function executeAndDownload(string $url): string
        {
            $e = HttpException::fromResponse(
                400,
                json_encode(['erro' => ['descricao' => 'Chave inválida', 'codigo' => 'E400']]),
            );
            throw $e;
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
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

        public function executeAndDownload(string $url): string
        {
            throw HttpException::fromResponse(
                503,
                json_encode(['message' => 'Service Unavailable']),
            );
        }

        public function executeHead(string $url): int
        {
            return 200;
        }

        public function executeRaw(string $url, ?string $requiredField = null): HttpResponse
        {
            return new HttpResponse(200, [], '');
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 503');
    expect($response->erros[0]->codigo)->toBe('503');
});

it('eventos returns failure on non-404 with erros key', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(400, ['erros' => [['descricao' => 'Evento rejeitado']]], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento rejeitado');
});

it('eventos returns failure on non-404 with singular erro key', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(400, ['erro' => ['descricao' => 'Evento rejeitado']], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('Evento rejeitado');
});

it('eventos returns EVENT_NOT_FOUND failure on 404 without error body', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(404, [], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->codigo)->toBe(EventsResponse::EVENT_NOT_FOUND);
    expect($response->erros[0]->mensagem)->toBe('Evento não encontrado');
});

it('eventos prepends EVENT_NOT_FOUND and preserves SEFIN errors on 404 with error body', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(
        404,
        ['erros' => [['codigo' => 'E404', 'descricao' => 'Evento inexistente']], 'tipoAmbiente' => 2],
        '',
    );
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(2);
    expect($response->erros[0]->codigo)->toBe(EventsResponse::EVENT_NOT_FOUND);
    expect($response->erros[1]->codigo)->toBe('E404');
    expect($response->erros[1]->descricao)->toBe('Evento inexistente');
    expect($response->tipoAmbiente)->toBe(2);
});

it('eventos prepends EVENT_NOT_FOUND and preserves singular SEFIN erro on 404', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(404, ['erro' => ['codigo' => 'E404', 'descricao' => 'Evento inexistente']], '');
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->erros)->toHaveCount(2);
    expect($response->erros[0]->codigo)->toBe(EventsResponse::EVENT_NOT_FOUND);
    expect($response->erros[1]->codigo)->toBe('E404');
});

it('eventos returns success with decompressed xml', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = new HttpResponse(
        200,
        [
            'eventoXmlGZipB64' => base64_encode((string) gzencode('<Evento/>')),
            'tipoAmbiente' => 2,
            'versaoAplicativo' => '1.0.0',
            'dataHoraProcessamento' => '2026-01-01T00:00:00',
        ],
        '',
    );
    $builder = makeNfseConsulter($fakeClient);

    $response = $builder->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe('<Evento/>');
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-01-01T00:00:00');
});

it('eventos uses default nSequencial = 1 in URL', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = makeEventoResponse();
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/101101/1');
});

it('passes custom tipoEvento enum and nSequencial to eventos URL', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = makeEventoResponse();
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, TipoEvento::CancelamentoPorSubstituicao, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('coerces int tipoEvento to TipoEvento enum', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->rawResponse = makeEventoResponse();
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->eventos($chave, 105102, 2);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/nfse/'.$chave.'/eventos/105102/2');
});

it('throws ValueError for invalid int tipoEvento', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->eventos(makeChaveAcesso(), 999999))
        ->toThrow(ValueError::class);
});

it('danfse uses adnBaseUrl when populated', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', 'https://adn.base', $resolver, '9999999');
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://adn.base/danfse/'.$chave);
});

it('danfse falls back to seFinBaseUrl when adnBaseUrl is empty', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);
    $chave = makeChaveAcesso();

    $builder->danfse($chave);

    expect($fakeClient->calls[0])->toBe('https://sefin.base/danfse/'.$chave);
});

it('verificarDps returns true when status is 200', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->headStatus = 200;
    $builder = makeNfseConsulter($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeTrue();
    expect($fakeClient->calls[0])->toBe('https://sefin.base/dps/DPS123');
});

it('verificarDps returns false when status is 404', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $fakeClient->headStatus = 404;
    $builder = makeNfseConsulter($fakeClient);

    expect($builder->verificarDps('DPS123'))->toBeFalse();
});

it('throws InvalidArgumentException for invalid chaveAcesso on nfse', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->nfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on danfse', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->danfse('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('throws InvalidArgumentException for invalid chaveAcesso on eventos', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
    $builder = makeNfseConsulter($fakeClient);

    expect(fn () => $builder->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('buildUrl trims trailing slash from baseUrl', function () {
    $fakeClient = new FakeNfsenClientForConsulta;
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

    $fakeClient = new FakeNfsenClientForConsulta;

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
