<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Builders\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\Ports\Driven\ResolvesPrefeituras;
use Pulsar\NfseNacional\Contracts\Ports\Driving\CancelsNfse;
use Pulsar\NfseNacional\Contracts\Ports\Driving\EmitsNfse;
use Pulsar\NfseNacional\Contracts\Ports\Driving\QueriesNfse;
use Pulsar\NfseNacional\Contracts\Ports\Driving\SubstitutesNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Builders\Consulta\NfseQueryExecutor;
use Pulsar\NfseNacional\Operations\NfseCanceller;
use Pulsar\NfseNacional\Operations\NfseEmitter;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Adapters\NfseHttpClient;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Adapters\XmlSigner;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Builders\Xml\Parts\CancelamentoBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\SubstituicaoBuilder;
use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class NfseClient implements CancelsNfse, EmitsNfse, QueriesNfse, SubstitutesNfse
{
    public function __construct(
        private NfseEmitter $emitter,
        private NfseCanceller $canceller,
        private NfseSubstitutor $substitutor,
        private NfseQueryExecutor $queryExecutor,
        private ResolvesPrefeituras $prefeituraResolver,
        private NfseAmbiente $ambiente,
        private string $prefeitura,
    ) {}

    public static function for(string $pfxContent, string $senha, string $prefeitura): self
    {
        if (function_exists('config') && config('nfse-nacional') !== null) {
            /** @var array{ambiente: int|string, timeout: int, connect_timeout: int, signing_algorithm: string, ssl_verify: bool} $config */
            $config = config('nfse-nacional');

            return self::forStandalone(
                pfxContent: $pfxContent,
                senha: $senha,
                prefeitura: $prefeitura,
                ambiente: NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                connectTimeout: $config['connect_timeout'],
            );
        }

        return self::forStandalone($pfxContent, $senha, $prefeitura);
    }

    public static function forStandalone(
        string $pfxContent,
        string $senha,
        string $prefeitura,
        NfseAmbiente $ambiente = NfseAmbiente::HOMOLOGACAO,
        int $timeout = 30,
        string $signingAlgorithm = 'sha1',
        bool $sslVerify = true,
        ?string $prefeiturasJsonPath = null,
        ?string $schemesPath = null,
        int $connectTimeout = 10,
    ): self {
        $jsonPath = $prefeiturasJsonPath ?? __DIR__.'/../storage/prefeituras.json';
        $schemasPath = $schemesPath ?? __DIR__.'/../storage/schemes';

        $prefeituraResolver = new PrefeituraResolver($jsonPath);
        $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);

        $xsdValidator = new XsdValidator($schemasPath);
        $certManager = new CertificateManager($pfxContent, $senha);
        $effectiveSslVerify = $ambiente === NfseAmbiente::PRODUCAO || $sslVerify;
        $httpClient = new NfseHttpClient($certManager->getCertificate(), $timeout, $connectTimeout, $effectiveSslVerify);

        $signer = new XmlSigner($certManager->getCertificate(), $signingAlgorithm);

        $pipeline = new NfseRequestPipeline(
            ambiente: $ambiente,
            prefeituraResolver: $prefeituraResolver,
            gzipCompressor: new GzipCompressor,
            signer: $signer,
            authorIdentity: $certManager,
            prefeitura: $prefeitura,
            httpClient: $httpClient,
        );

        return new self(
            emitter: new NfseEmitter($pipeline, new DpsBuilder($xsdValidator)),
            canceller: new NfseCanceller($pipeline, new CancelamentoBuilder($xsdValidator), $ambiente),
            substitutor: new NfseSubstitutor($pipeline, new SubstituicaoBuilder($xsdValidator), $ambiente),
            queryExecutor: new NfseQueryExecutor($httpClient),
            prefeituraResolver: $prefeituraResolver,
            ambiente: $ambiente,
            prefeitura: $prefeitura,
        );
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->emitter->emitir($data);
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->emitter->emitirDecisaoJudicial($data);
    }

    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao, int $nPedRegEvento = 1): NfseResponse
    {
        return $this->canceller->cancelar($chave, $codigoMotivo, $descricao, $nPedRegEvento);
    }

    public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1): NfseResponse
    {
        return $this->substitutor->substituir($chave, $chaveSubstituta, $codigoMotivo, $descricao, $nPedRegEvento);
    }

    public function consultar(): ConsultaBuilder
    {
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);

        return new ConsultaBuilder(
            $this->queryExecutor, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }
}
