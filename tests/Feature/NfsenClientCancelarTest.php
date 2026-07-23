<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Events\NfseCancelled;
use OwnerPro\Nfsen\Events\NfseFailed;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Support\GzipCompressor;

covers(NfsenClient::class, NfseCanceller::class);

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfsenClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('12345678901234567890123456789012345678901234567890');
    expect($response->xml)->not->toBeNull();
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-03-02T12:00:00-03:00');

    Http::assertSent(function (Request $req) {
        $xml = gzdecode(base64_decode($req['pedidoRegistroEventoXmlGZipB64']));

        return $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
            $req->method() === 'POST' &&
            ! str_contains($xml, '<nPedRegEvento>');
    });
});

it('cancelar accepts string codigoMotivo and coerces to enum', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfsenClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        '1',
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('12345678901234567890123456789012345678901234567890');
    expect($response->xml)->not->toBeNull();
});

it('cancelar throws ValueError for invalid string codigoMotivo', function () {
    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        'INVALID',
        'Erro na emissao da nota fiscal'
    ))->toThrow(ValueError::class);
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfsenClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toContain('não encontrada');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar returns rejection NfseResponse on singular erro field', function () {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Operação não permitida', 'codigo' => 'E999']], 200)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('Operação não permitida');
});

it('cancelar throws NfseException when cert has no CNPJ nor CPF', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(NfseException::class, 'Certificado não contém CNPJ nem CPF');
});

it('cancelar throws IndeterminateResultException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(IndeterminateResultException::class);
});

it('cancelar keeps the result indeterminate when nothing proves the event was registered', function (array $body, int $status) {
    // EventosPostResponseSucesso declara eventoXmlGZipB64 required: sem
    // rejeição estruturada e sem o recibo, sucesso: true seria silencioso.
    Http::fake(['*' => Http::response($body, $status)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(IndeterminateResultException::class, 'eventoXmlGZipB64');
})->with([
    '2xx sem eventoXmlGZipB64 (fora do contrato do swagger)' => [['tipoAmbiente' => 2], 201],
    '2xx com eventoXmlGZipB64 vazio' => [['eventoXmlGZipB64' => ''], 201],
    '4xx de proxy/WAF com JSON sem envelope da SEFIN' => [['message' => 'not found'], 404],
]);

it('cancelar dispatches NfseFailed, never NfseCancelled, when the event receipt is missing', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['tipoAmbiente' => 2], 201)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    try {
        $client->cancelar(
            '12345678901234567890123456789012345678901234567890',
            CodigoJustificativaCancelamento::ErroEmissao,
            'Erro na emissao da nota fiscal'
        );
    } catch (IndeterminateResultException) {
        // esperado
    }

    Event::assertDispatched(NfseFailed::class);
    Event::assertNotDispatched(NfseCancelled::class);
});

it('cancelar preserva o cancelamento com xml null e alerta quando o recibo vem corrompido', function (string $reciboCorrompido, string $motivo) {
    // eventoXmlGZipB64 presente prova que o evento foi registrado: um recibo
    // ilegível não pode reverter isso em falha. O motivo vai no complemento.
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => $reciboCorrompido, 'tipoAmbiente' => 2], 201)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue()
        ->and($response->chave)->toBe('12345678901234567890123456789012345678901234567890')
        ->and($response->xml)->toBeNull()
        ->and($response->alertas)->toHaveCount(1)
        ->and($response->alertas[0]->codigo)->toBe('XML_ILEGIVEL')
        ->and($response->alertas[0]->descricao)->toContain('consultar()->eventos')
        ->and($response->alertas[0]->complemento)->toBe($motivo);
})->with([
    'base64 inválido' => ['!!!invalid!!!', 'Falha ao decodificar base64 do XML.'],
    'gzip inválido' => [base64_encode('not-gzip-data'), 'Falha ao descomprimir XML.'],
]);

it('cancelar dispara apenas NfseCancelled, nunca NfseFailed, quando o recibo vem corrompido', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => '!!!invalid!!!'], 201)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    Event::assertDispatched(NfseCancelled::class);
    Event::assertNotDispatched(NfseFailed::class);
});

it('cancelar succeeds and reports error when event listener throws', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $reported = [];
    $this->app->bind(ExceptionHandler::class, function () use (&$reported) {
        return new class($reported) extends Handler
        {
            /** @param list<Throwable> $reported */
            public function __construct(private array &$reported)
            {
                parent::__construct(app());
            }

            public function report(Throwable $e): void
            {
                $this->reported[] = $e;
            }
        };
    });

    Event::listen(
        NfseRequested::class,
        function (): never {
            throw new RuntimeException('Listener exploded');
        }
    );

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(RuntimeException::class);
    expect($reported[0]->getMessage())->toBe('Listener exploded');
});

it('cancelar builds a path under the Americana base URL', function () {
    // Americana declarava a URL completa de recepção de DPS no lugar da base, e
    // zerava todo template para o path não ser concatenado. Cancelamento ia parar
    // no endpoint de recepção, sem a chave. Com a base separada do path de emissão,
    // as demais operações herdam os caminhos nacionais sob /api/adn.
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode((string) gzencode('<Evento/>'))], 201)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '3501608');
    $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal',
    );

    Http::assertSent(fn (Request $req): bool => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/nfse/12345678901234567890123456789012345678901234567890/eventos');
});

it('cancelar uses Santa Ana de Parnaiba custom URL with operation path', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '3547304');
    $client->cancelar('12345678901234567890123456789012345678901234567890', CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar throws InvalidArgumentException for invalid chaveAcesso', function () {
    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        'INVALID_CHAVE',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('cancelar builds a valid dhEvento on a host whose offset has non-zero minutes', function (string $timezone) {
    // TSDateTimeUTC só aceita offset com minuto zero e na faixa -11..+12, então
    // date('c') gerava valor reprovado pelo XSD em host com timezone quebrado —
    // todo cancelamento falhava por lá. gmdate('c') é sempre válido.
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode((string) gzencode('<Evento/>'))], 201)]);

    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set($timezone);

    try {
        $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
        $response = $client->cancelar(
            '12345678901234567890123456789012345678901234567890',
            CodigoJustificativaCancelamento::ErroEmissao,
            'Erro na emissao da nota fiscal',
        );

        expect($response->sucesso)->toBeTrue();
    } finally {
        date_default_timezone_set($originalTimezone);
    }
})->with([
    'India +05:30' => ['Asia/Kolkata'],
    'Nepal +05:45' => ['Asia/Kathmandu'],
    'Chatham +12:45' => ['Pacific/Chatham'],
    'Brasil -03:00' => ['America/Sao_Paulo'],
]);

it('cancelar rejects a chaveAcesso with a trailing newline', function () {
    // `/^\d{50}$/` sem o modificador /D casa também antes de um \n final: a chave
    // passava na validação e ia interpolada na URL, produzindo uma URL malformada
    // em vez do InvalidArgumentException que a mensagem promete.
    Http::fake();

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        str_repeat('1', 50)."\n",
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');

    Http::assertNothingSent();
});

it('cancelar throws NfseException when gzip compression fails', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 200)]);

    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfsenClient(gzipCompressor: $compressor, pfxContent: makeIcpBrPfxContent());

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(NfseException::class, 'comprimir XML');
});
