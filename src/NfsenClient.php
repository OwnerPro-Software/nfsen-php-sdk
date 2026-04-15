<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen;

use InvalidArgumentException;
use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Adapters\XmlSigner;
use OwnerPro\Nfsen\Contracts\Driving\CancelsNfse;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Contracts\Driving\QueriesNfse;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Operations\NfseDanfseRenderer;
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
 *
 * @api
 */
final readonly class NfsenClient implements CancelsNfse, EmitsNfse, QueriesNfse, SubstitutesNfse
{
    public function __construct(
        private EmitsNfse $emitter,
        private CancelsNfse $canceller,
        private SubstitutesNfse $substitutor,
        private ConsultsNfse $consulter,
    ) {}

    /**
     * @param  array<string, mixed>|false|null  $danfse
     */
    public static function for(
        #[SensitiveParameter] string $pfxContent,
        #[SensitiveParameter] string $senha,
        string $prefeitura,
        ?NfseAmbiente $ambiente = null,
        array|false|null $danfse = null,
    ): self {
        // Sentinel: false força desligar, ignora config global.
        if ($danfse === false) {
            return self::buildFor($pfxContent, $senha, $prefeitura, $ambiente, null);
        }

        // null + config global ativo: usa config.
        // $fromConfig é mixed; isDanfseEnabled narra para array<string, mixed> via @phpstan-assert-if-true.
        if ($danfse === null && function_exists('config')) {
            $fromConfig = config('nfsen.danfse');
            if (self::isDanfseEnabled($fromConfig)) {
                $danfse = $fromConfig;
            }
        }

        return self::buildFor($pfxContent, $senha, $prefeitura, $ambiente, $danfse);
    }

    /**
     * Gate DRY usado por `for()` e por `NfsenServiceProvider`.
     *
     * Contrato: config/nfsen.php aplica `(bool)` cast em `enabled`, logo o valor chegando
     * aqui é bool. Strict `=== true` enforces o contrato — se alguém publicar um config
     * com `'enabled' => 1`, o auto-render silenciosamente não ativa. Intencional:
     * falha fechada é mais segura que ativar por coerção frouxa.
     *
     * Visibilidade public obrigatória: consumido por `NfsenServiceProvider`. Marcada como
     * `@api` preemptivamente: `NfsenServiceProvider` é um consumidor externo efetivo do
     * helper (mesma lib, mas acoplamento cross-class). `@api` garante que `tomasvotruba/unused-public`
     * não reclame em Task 15 Step 5 sem precisar de retry-com-edit.
     *
     * @phpstan-assert-if-true array<string, mixed> $block
     *
     * @api
     */
    public static function isDanfseEnabled(mixed $block): bool
    {
        if (! is_array($block)) {
            return false; // @pest-mutate-ignore RemoveEarlyReturn — fallthrough em não-array acessa offset e retorna false via `?? false`, comportamento equivalente.
        }

        return ($block['enabled'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>|null  $danfse
     */
    private static function buildFor(
        #[SensitiveParameter] string $pfxContent,
        #[SensitiveParameter] string $senha,
        string $prefeitura,
        ?NfseAmbiente $ambiente,
        ?array $danfse,
    ): self {
        // Chave `danfse?` opcional no shape: cobre instalações antigas cujo config/nfsen.php
        // publicado não tem o bloco novo (buildFor não acessa essa chave, mas o ServiceProvider sim).
        if (function_exists('config') && config('nfsen') !== null) {
            /** @var array{ambiente: int|string, timeout: int, connect_timeout: int, signing_algorithm: string, ssl_verify: bool, validate_identity: bool, danfse?: array<string, mixed>} $config */
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
                danfse: $danfse,
            );
        }

        return self::forStandalone(
            pfxContent: $pfxContent,
            senha: $senha,
            prefeitura: $prefeitura,
            ambiente: $ambiente ?? NfseAmbiente::HOMOLOGACAO,
            danfse: $danfse,
        );
    }

    /**
     * Semântica do parâmetro `$danfse`:
     * - `null` (default): sem auto-render.
     * - `[]` (array vazio): tratado como "sem config válida" — sem auto-render.
     * - array não-vazio: ativa auto-render. Chave `enabled` dentro do array é ignorada
     *   aqui — só tem efeito em `NfsenClient::for()` lendo config global.
     * - `false`: sentinel; em `forStandalone()` equivale a `null` (sem auto-render).
     *   Útil em `NfsenClient::for()` para sobrescrever `config.enabled=true`.
     *
     * @param  array<string, mixed>|false|null  $danfse
     */
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
        array|false|null $danfse = null,
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

        $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));
        $canceller = new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente);
        $substitutor = new NfseSubstitutor($emitter);
        $consulter = new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura);

        if (in_array($danfse, [null, false, []], true)) {
            return new self(
                emitter: $emitter,
                canceller: $canceller,
                substitutor: $substitutor,
                consulter: $consulter,
            );
        }

        $renderer = self::buildDanfseRenderer(DanfseConfig::fromArray($danfse));

        return new self(
            emitter: new EmitterWithDanfse($emitter, $renderer),
            canceller: $canceller,
            substitutor: new SubstitutorWithDanfse($substitutor, $renderer),
            consulter: new ConsulterWithDanfse($consulter, $renderer),
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

    /**
     * @param  DanfseConfig|array<string, mixed>|null  $config
     *
     * @throws InvalidArgumentException quando `$config` é array com shape inválido
     *                                  (chave desconhecida, tipo incorreto, `name` vazio).
     *                                  Validação é eager (na chamada `danfse()`), não no
     *                                  `toPdf()`/`toHtml()` subsequente.
     */
    public function danfse(DanfseConfig|array|null $config = null): RendersDanfse
    {
        $resolved = $config instanceof DanfseConfig
            ? $config
            : DanfseConfig::fromArray($config ?? []);

        return self::buildDanfseRenderer($resolved);
    }

    private static function buildDanfseRenderer(DanfseConfig $config): RendersDanfse
    {
        return new NfseDanfseRenderer(
            new DanfseDataBuilder,
            new DanfseHtmlRenderer(
                new BaconQrCodeGenerator,
                $config,
            ),
            new DompdfHtmlToPdfConverter,
        );
    }
}
