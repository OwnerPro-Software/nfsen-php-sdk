<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Illuminate\Container\Container;
use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Signing\XmlSigner;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Xml\Builders\CancelamentoBuilder;
use Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;
use Throwable;

final class NfseClient implements NfseClientContract
{
    private ?CertificateManager $certManager = null;

    private ?string $prefeitura = null;

    private ?NfseHttpClient $httpClient = null;

    public function __construct(
        private readonly NfseAmbiente $ambiente,
        private readonly int $timeout,
        private readonly string $signingAlgorithm,
        private readonly bool $sslVerify,
        private readonly PrefeituraResolver $prefeituraResolver,
        private readonly DpsBuilder $dpsBuilder,
        private readonly CancelamentoBuilder $cancelamentoBuilder,
        private readonly SubstituicaoBuilder $substituicaoBuilder,
        private readonly GzipCompressor $gzipCompressor = new GzipCompressor,
    ) {}

    public static function for(string $pfxContent, string $senha, string $prefeitura): self
    {
        if (class_exists(Container::class)
            && Container::getInstance()->bound(self::class)
        ) {
            return app(self::class)->configure($pfxContent, $senha, $prefeitura);
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
    ): self {
        $jsonPath = $prefeiturasJsonPath ?? __DIR__.'/../storage/prefeituras.json';
        $schemasPath = $schemesPath ?? __DIR__.'/../storage/schemes';

        $xsdValidator = new XsdValidator($schemasPath);

        $instance = new self(
            ambiente: $ambiente,
            timeout: $timeout,
            signingAlgorithm: $signingAlgorithm,
            sslVerify: $sslVerify,
            prefeituraResolver: new PrefeituraResolver($jsonPath),
            dpsBuilder: new DpsBuilder($xsdValidator),
            cancelamentoBuilder: new CancelamentoBuilder($xsdValidator),
            substituicaoBuilder: new SubstituicaoBuilder($xsdValidator),
        );

        return $instance->configure($pfxContent, $senha, $prefeitura);
    }

    public function configure(string $pfxContent, string $senha, string $prefeitura): self
    {
        $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);
        $this->certManager = new CertificateManager($pfxContent, $senha);
        $this->prefeitura = $prefeitura;
        $this->httpClient = new NfseHttpClient($this->certManager->getCertificate(), $this->timeout, $this->sslVerify);

        return $this;
    }

    /**
     * @phpstan-assert !null $this->certManager
     * @phpstan-assert !null $this->prefeitura
     * @phpstan-assert !null $this->httpClient
     */
    private function ensureConfigured(): void
    {
        if (! $this->certManager instanceof CertificateManager || $this->prefeitura === null || ! $this->httpClient instanceof NfseHttpClient) {
            throw new NfseException(
                'NfseClient não configurado. Use NfseClient::for() ou configure certificado/prefeitura no config/nfse-nacional.php.'
            );
        }
    }

    private function dispatchEvent(object $event): void
    {
        if (function_exists('event')) {
            try {
                event($event);
            } catch (Throwable $e) {
                if (function_exists('report')) {
                    report($e);
                }
            }
        }
    }

    public function emitir(DpsData $data): NfseResponse
    {
        $this->ensureConfigured();
        $prefeitura = $this->prefeitura;
        $certificate = $this->certManager->getCertificate();
        $httpClient = $this->httpClient;

        $operacao = 'emitir';
        $this->dispatchEvent(new NfseRequested($operacao, []));

        try {
            $xml = $this->dpsBuilder->buildAndValidate($data);
            $signer = new XmlSigner($certificate, $this->signingAlgorithm);
            $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$signer->sign($xml, 'infDPS', 'DPS');
            $compressed = ($this->gzipCompressor)($signed);
            if ($compressed === false) {
                throw new NfseException('Falha ao comprimir XML.');
            }

            $payload = ['dpsXmlGZipB64' => base64_encode($compressed)];

            $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);
            $opPath = $this->prefeituraResolver->resolveOperation($prefeitura, 'emitir_nfse');
            $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

            /** @var array{erros?: list<array{descricao?: string, codigo?: string}>, erro?: string, chNFSe?: string} $result */
            $result = $httpClient->post($url, $payload);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição sem descrição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));

                return new NfseResponse(false, null, null, $erro);
            }

            $chave = $result['chNFSe'] ?? null;

            if ($chave !== null) {
                $this->dispatchEvent(new NfseEmitted($chave));
            }

            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function cancelar(string $chave, CodigoJustificativaCancelamento $codigoMotivo, string $descricao, int $nPedRegEvento = 1): NfseResponse
    {
        $this->ensureConfigured();
        $prefeitura = $this->prefeitura;
        $certificate = $this->certManager->getCertificate();
        $httpClient = $this->httpClient;

        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        try {
            $cnpj = $certificate->getCnpj() ?: null;
            $cpf = $certificate->getCpf() ?: null;

            if ($cnpj === null && $cpf === null) {
                throw new NfseException('Certificado não contém CNPJ nem CPF. É necessário ao menos um para cancelar a NFS-e.');
            }

            $xml = $this->cancelamentoBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                dhEvento: date('c'),
                cnpjAutor: $cnpj,
                cpfAutor: $cpf,
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                descricao: $descricao,
                nPedRegEvento: $nPedRegEvento,
            );

            return $this->sendEvento($xml, $chave, $prefeitura, $certificate, $httpClient, $operacao, 'cancelar_nfse', new NfseCancelled($chave));
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1): NfseResponse
    {
        $this->ensureConfigured();
        $prefeitura = $this->prefeitura;
        $certificate = $this->certManager->getCertificate();
        $httpClient = $this->httpClient;

        $operacao = 'substituir';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        try {
            $cnpj = $certificate->getCnpj() ?: null;
            $cpf = $certificate->getCpf() ?: null;

            if ($cnpj === null && $cpf === null) {
                throw new NfseException('Certificado não contém CNPJ nem CPF. É necessário ao menos um para substituir a NFS-e.');
            }

            $xml = $this->substituicaoBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                dhEvento: date('c'),
                cnpjAutor: $cnpj,
                cpfAutor: $cpf,
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                chSubstituta: $chaveSubstituta,
                descricao: $descricao,
                nPedRegEvento: $nPedRegEvento,
            );

            return $this->sendEvento($xml, $chave, $prefeitura, $certificate, $httpClient, $operacao, 'substituir_nfse', new NfseSubstituted($chave, $chaveSubstituta));
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    private function sendEvento(string $xml, string $chave, string $prefeitura, Certificate $certificate, NfseHttpClient $httpClient, string $operacao, string $operationKey, NfseCancelled|NfseSubstituted $successEvent): NfseResponse
    {
        $signer = new XmlSigner($certificate, $this->signingAlgorithm);
        $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$signer->sign($xml, 'infPedReg', 'pedRegEvento');
        $compressed = ($this->gzipCompressor)($signed);
        if ($compressed === false) {
            throw new NfseException('Falha ao comprimir XML.');
        }

        $payload = ['pedidoRegistroEventoXmlGZipB64' => base64_encode($compressed)];

        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);
        $opPath = $this->prefeituraResolver->resolveOperation(
            $prefeitura, $operationKey, ['chave' => $chave]
        );
        $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

        /** @var array{erros?: list<array{descricao?: string, codigo?: string}>, erro?: string} $result */
        $result = $httpClient->post($url, $payload);

        if (! empty($result['erros']) || isset($result['erro'])) {
            $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição';
            $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
            $this->dispatchEvent(new NfseRejected($operacao, $codigo));

            return new NfseResponse(false, null, null, $erro);
        }

        $this->dispatchEvent($successEvent);

        return new NfseResponse(true, $chave, null, null);
    }

    public function consultar(): ConsultaBuilder
    {
        $this->ensureConfigured();
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);

        return new ConsultaBuilder(
            $this, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }

    public function executeGet(string $url): NfseResponse
    {
        $this->ensureConfigured();
        $httpClient = $this->httpClient;

        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, ['url' => $url]));

        try {
            /** @var array{erros?: list<array{descricao?: string, codigo?: string}>, erro?: string, nfseXmlGZipB64?: string, dpsXmlGZipB64?: string} $result */
            $result = $httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));

                return new NfseResponse(false, null, null, $erro);
            }

            $xml = null;
            $gzipB64 = $result['nfseXmlGZipB64'] ?? $result['dpsXmlGZipB64'] ?? null;
            if ($gzipB64) {
                $decoded = base64_decode($gzipB64, true);
                if ($decoded === false) {
                    throw new NfseException('Falha ao decodificar base64 do XML.');
                }

                try {
                    $decompressed = gzdecode($decoded);
                } catch (Throwable) {
                    $decompressed = false;
                }

                if ($decompressed === false) {
                    throw new NfseException('Falha ao descomprimir XML.');
                }

                $xml = $decompressed;
            }

            $this->dispatchEvent(new NfseQueried('consultar'));

            return new NfseResponse(true, null, $xml, null);
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    /**
     * @return array{
     *     erros?: list<array{descricao?: string, codigo?: string}>,
     *     erro?: string,
     *     danfseUrl?: string,
     *     eventos?: array<int, array<string, mixed>>,
     * }
     */
    public function executeGetRaw(string $url): array
    {
        $this->ensureConfigured();
        $httpClient = $this->httpClient;

        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, ['url' => $url]));

        try {
            /** @var array{erros?: list<array{descricao?: string, codigo?: string}>, erro?: string, danfseUrl?: string, eventos?: array<int, array<string, mixed>>} $result */
            $result = $httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
            } else {
                $this->dispatchEvent(new NfseQueried($operacao));
            }

            return $result;
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }
}
