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

        $query = ['lote' => $lote ? 'true' : 'false'];

        // `cnpjConsulta` é opcional no swagger do ADN (`required` ausente em
        // GET /DFe/{NSU}), e o autor pode não ter CNPJ: com certificado e-CPF,
        // `CertificateManager::extract()` devolve `cnpj => null` e o cliente guarda
        // string vazia. Mandar `?cnpjConsulta=` é oferecer um valor malformado onde
        // bastava calar — omitir deixa o ADN aplicar o próprio default.
        $cnpj = $cnpjConsulta ?? $this->cnpjAutor;

        if ($cnpj !== '') {
            $query = ['cnpjConsulta' => $cnpj] + $query;
        }

        return $this->executeRequest($url.'?'.http_build_query($query));
    }

    private function executeRequest(string $url): DistribuicaoResponse
    {
        $httpResponse = $this->httpClient->getResponse($url);

        return DistribuicaoResponse::fromHttpResponse($httpResponse);
    }

    /**
     * O path nunca é vazio aqui: toda operação destas classes passa parâmetro, e
     * `resolveOperation()` rejeita template sem placeholder quando há parâmetros.
     * O caso de path vazio (emissão) é tratado em NfseRequestPipeline.
     */
    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
