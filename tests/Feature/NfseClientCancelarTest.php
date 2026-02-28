<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE50CARACTERES1234567890123456789012345678901');

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/CHAVE50CARACTERES1234567890123456789012345678901/eventos' &&
        $req->method() === 'POST' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/CHAVE50CARACTERES1234567890123456789012345678901/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar returns rejection NfseResponse on singular erro field', function () {
    Http::fake(['*' => Http::response(['erro' => 'Operação não permitida'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('Operação não permitida');
});

it('cancelar works with cert without ICP-Brasil OID', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});

it('cancelar throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    ))->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('cancelar succeeds and reports error when event listener throws', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $reported = [];
    $this->app->bind(\Illuminate\Contracts\Debug\ExceptionHandler::class, function () use (&$reported) {
        return new class($reported) extends \Illuminate\Foundation\Exceptions\Handler {
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

    \Illuminate\Support\Facades\Event::listen(
        \Pulsar\NfseNacional\Events\NfseRequested::class,
        function (): never {
            throw new \RuntimeException('Listener exploded');
        }
    );

    $client   = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(\RuntimeException::class);
    expect($reported[0]->getMessage())->toBe('Listener exploded');
});

it('cancelar uses Americana custom URL without operation path', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_AM'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar uses Santa Ana de Parnaiba custom URL with operation path', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_SP'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/CHAVE50CARACTERES1234567890123456789012345678901/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});
