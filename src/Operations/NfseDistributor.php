<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

final readonly class NfseDistributor implements DistributesNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private SendsRawHttpRequests $httpClient,
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
        $httpResponse = $this->httpClient->getResponse($url);

        return DistribuicaoResponse::fromHttpResponse($httpResponse);
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
