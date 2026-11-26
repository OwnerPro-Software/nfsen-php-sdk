<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use InvalidArgumentException;
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

    public function documentos(int $nsu, ?string $cnpjConsulta = null, ?string $cpfConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, $cpfConsulta, lote: true);
    }

    public function documento(int $nsu, ?string $cnpjConsulta = null, ?string $cpfConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, $cpfConsulta, lote: false);
    }

    public function eventos(string $chave): DistribuicaoResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_events', ['ChaveAcesso' => $chave]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);

        return $this->executeRequest($url);
    }

    private function fetchDfe(int $nsu, ?string $cnpjConsulta, ?string $cpfConsulta, bool $lote): DistribuicaoResponse
    {
        // Manual das APIs do ADN v1.1 (03/11/2026), item 1.1.1.a: os dois juntos dão E2244.
        if ($cnpjConsulta !== null && $cpfConsulta !== null) {
            throw new InvalidArgumentException('Informe cnpjConsulta ou cpfConsulta, não os dois: o ADN rejeita a consulta com E2244.');
        }

        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_documents', ['NSU' => $nsu]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);

        $query = ['lote' => $lote ? 'true' : 'false'];

        if ($cpfConsulta !== null) {
            return $this->executeRequest($url.'?'.http_build_query(['cpfConsulta' => $cpfConsulta] + $query));
        }

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
