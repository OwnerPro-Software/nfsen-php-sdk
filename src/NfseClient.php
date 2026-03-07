<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Adapters\NfseHttpClient;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Adapters\XmlSigner;
use Pulsar\NfseNacional\Contracts\Driving\CancelsNfse;
use Pulsar\NfseNacional\Contracts\Driving\ConsultsNfse;
use Pulsar\NfseNacional\Contracts\Driving\EmitsNfse;
use Pulsar\NfseNacional\Contracts\Driving\QueriesNfse;
use Pulsar\NfseNacional\Contracts\Driving\SubstitutesNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Operations\NfseCanceller;
use Pulsar\NfseNacional\Operations\NfseConsulter;
use Pulsar\NfseNacional\Operations\NfseEmitter;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Pipeline\NfseResponsePipeline;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Xml\Builders\CancellationBuilder;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class NfseClient implements CancelsNfse, EmitsNfse, QueriesNfse, SubstitutesNfse
{
    public function __construct(
        private EmitsNfse $emitter,
        private CancelsNfse $canceller,
        private SubstitutesNfse $substitutor,
        private ConsultsNfse $consulter,
    ) {}

    public static function for(string $pfxContent, string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): self
    {
        if (function_exists('config') && config('nfse-nacional') !== null) {
            /** @var array{ambiente: int|string, timeout: int, connect_timeout: int, signing_algorithm: string, ssl_verify: bool} $config */
            $config = config('nfse-nacional');

            return self::forStandalone(
                pfxContent: $pfxContent,
                senha: $senha,
                prefeitura: $prefeitura,
                ambiente: $ambiente ?? NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                connectTimeout: $config['connect_timeout'],
            );
        }

        return self::forStandalone($pfxContent, $senha, $prefeitura, $ambiente ?? NfseAmbiente::HOMOLOGACAO);
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

        $queryExecutor = new NfseResponsePipeline($httpClient);
        $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
        $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

        $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

        return new self(
            emitter: $emitter,
            canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
            substitutor: new NfseSubstitutor($emitter, $pipeline, new SubstitutionBuilder($xsdValidator), $ambiente),
            consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
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

    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao): NfseResponse
    {
        return $this->canceller->cancelar($chave, $codigoMotivo, $descricao);
    }

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): SubstituicaoResponse
    {
        return $this->substitutor->substituir($chave, $dps, $codigoMotivo, $descricao);
    }

    public function confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): NfseResponse
    {
        return $this->substitutor->confirmarSubstituicao($chaveSubstituida, $chaveSubstituta, $codigoMotivo, $descricao);
    }

    public function consultar(): ConsultsNfse
    {
        return $this->consulter;
    }
}
