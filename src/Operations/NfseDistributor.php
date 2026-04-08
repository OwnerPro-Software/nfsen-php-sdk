<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

final readonly class NfseDistributor implements DistributesNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private SendsHttpRequests $httpClient,
        private ResolvesOperations $resolver,
        private string $codigoIbge,
        private string $adnBaseUrl,
        private string $cnpjAutor,
    ) {}

    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, lote: true);
    }

    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, lote: false);
    }

    public function eventos(string $chave): DistribuicaoResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_events', ['ChaveAcesso' => $chave]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);

        return $this->executeRequest($url);
    }

    private function fetchDfe(int $nsu, ?string $cnpjConsulta, bool $lote): DistribuicaoResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_documents', ['NSU' => $nsu]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);
        $url .= '?'.http_build_query([
            'cnpjConsulta' => $cnpjConsulta ?? $this->cnpjAutor,
            'lote' => $lote ? 'true' : 'false',
        ]);

        return $this->executeRequest($url);
    }

    private function executeRequest(string $url): DistribuicaoResponse
    {
        try {
            /** @var array<string, mixed> $result */
            $result = $this->httpClient->get($url);

            return DistribuicaoResponse::fromApiResult($result);
        } catch (HttpException $httpException) {
            return $this->handleHttpError($httpException);
        }
    }

    private function handleHttpError(HttpException $e): DistribuicaoResponse
    {
        $body = $e->getResponseBody();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['StatusProcessamento'])) {
            return DistribuicaoResponse::fromApiResult($decoded);
        }

        return new DistribuicaoResponse(
            sucesso: false,
            statusProcessamento: StatusDistribuicao::Rejeicao,
            lote: [],
            alertas: [],
            erros: [new ProcessingMessage(
                mensagem: 'HTTP error: '.$e->getCode(),
                codigo: (string) $e->getCode(),
                descricao: $e->getResponseBody(),
            )],
            tipoAmbiente: null,
            versaoAplicativo: null,
            dataHoraProcessamento: null,
        );
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
