<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen;

use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Adapters\XmlSigner;
use OwnerPro\Nfsen\Contracts\Driving\CancelsNfse;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Contracts\Driving\QueriesDistribuicao;
use OwnerPro\Nfsen\Contracts\Driving\QueriesNfse;
use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Operations\NfseDistributor;
use OwnerPro\Nfsen\Operations\NfseEmitter;
use OwnerPro\Nfsen\Operations\NfseSubstitutor;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Support\GzipCompressor;
use OwnerPro\Nfsen\Support\XsdValidator;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;
use OwnerPro\Nfsen\Xml\DpsBuilder;
use SensitiveParameter;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class NfsenClient implements CancelsNfse, EmitsNfse, QueriesDistribuicao, QueriesNfse, SubstitutesNfse
{
    public function __construct(
        private EmitsNfse $emitter,
        private CancelsNfse $canceller,
        private SubstitutesNfse $substitutor,
        private ConsultsNfse $consulter,
        private DistributesNfse $distributor,
    ) {}

    public static function for(#[SensitiveParameter] string $pfxContent, #[SensitiveParameter] string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): self
    {
        if (function_exists('config') && config('nfsen') !== null) {
            /** @var array{ambiente: int|string, timeout: int, connect_timeout: int, signing_algorithm: string, ssl_verify: bool, validate_identity: bool} $config */
            $config = config('nfsen');

            return self::forStandalone(
                pfxContent: $pfxContent,
                senha: $senha,
                prefeitura: $prefeitura,
                ambiente: $ambiente ?? NfseAmbiente::fromConfig($config['ambiente']),
                timeout: $config['timeout'],
                signingAlgorithm: $config['signing_algorithm'],
                sslVerify: $config['ssl_verify'],
                connectTimeout: $config['connect_timeout'],
                validateIdentity: $config['validate_identity'],
            );
        }

        return self::forStandalone($pfxContent, $senha, $prefeitura, $ambiente ?? NfseAmbiente::HOMOLOGACAO);
    }

    public static function forStandalone(
        #[SensitiveParameter] string $pfxContent,
        #[SensitiveParameter] string $senha,
        string $prefeitura,
        NfseAmbiente $ambiente = NfseAmbiente::HOMOLOGACAO,
        int $timeout = 30,
        string $signingAlgorithm = 'sha1',
        bool $sslVerify = true,
        ?string $prefeiturasJsonPath = null,
        ?string $schemasPath = null,
        int $connectTimeout = 10,
        bool $validateIdentity = true,
    ): self {
        $jsonPath = $prefeiturasJsonPath ?? __DIR__.'/../storage/prefeituras.json';
        $schemasPath ??= __DIR__.'/../storage/schemes';

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
            validateIdentity: $validateIdentity,
        );

        $queryExecutor = new NfseResponsePipeline($httpClient);
        $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
        $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);
        $identity = $certManager->extract();
        $cnpjAutor = $identity['cnpj'] ?? ''; // @pest-mutate-ignore CoalesceRemoveLeft,EmptyStringToNotEmpty — cnpj may be null for CPF-only certs

        $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

        return new self(
            emitter: $emitter,
            canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
            substitutor: new NfseSubstitutor($emitter),
            consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
            distributor: new NfseDistributor($httpClient, $prefeituraResolver, $prefeitura, $adnUrl, $cnpjAutor),
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
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): NfseResponse
    {
        return $this->substitutor->substituir($chave, $dps, $codigoMotivo, $descricao);
    }

    public function consultar(): ConsultsNfse
    {
        return $this->consulter;
    }

    public function distribuicao(): DistributesNfse
    {
        return $this->distributor;
    }
}
