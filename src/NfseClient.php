<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Illuminate\Container\Container;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Throwable;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Signing\XmlSigner;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseClient implements NfseClientContract
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
    ) {}

    public static function for(string $pfxContent, string $senha, string $prefeitura): static
    {
        if (class_exists(Container::class)
            && Container::getInstance()->bound(static::class)
        ) {
            return app(static::class)->configure($pfxContent, $senha, $prefeitura);
        }

        return static::forStandalone($pfxContent, $senha, $prefeitura);
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
    ): static {
        $jsonPath    = $prefeiturasJsonPath ?? __DIR__ . '/../storage/prefeituras.json';
        $schemasPath = $schemesPath ?? __DIR__ . '/../storage/schemes';

        $instance = new static(
            ambiente:           $ambiente,
            timeout:            $timeout,
            signingAlgorithm:   $signingAlgorithm,
            sslVerify:          $sslVerify,
            prefeituraResolver: new PrefeituraResolver($jsonPath),
            dpsBuilder:         new DpsBuilder($schemasPath),
        );

        return $instance->configure($pfxContent, $senha, $prefeitura);
    }

    public function configure(string $pfxContent, string $senha, string $prefeitura): static
    {
        $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);
        $this->certManager = new CertificateManager($pfxContent, $senha);
        $this->prefeitura  = $prefeitura;
        $this->httpClient  = new NfseHttpClient($this->certManager->getCertificate(), $this->timeout, $this->sslVerify);
        return $this;
    }

    private function ensureConfigured(): void
    {
        if (!$this->certManager instanceof CertificateManager || $this->prefeitura === null || !$this->httpClient instanceof NfseHttpClient) {
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
            } catch (Throwable) {}
        }
    }

    public function emitir(DpsData $data): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'emitir';
        $this->dispatchEvent(new NfseRequested($operacao, []));

        try {
            $xml     = $this->dpsBuilder->build($data);
            $signer  = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infDPS', 'DPS');
            $payload = ['dpsXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl   = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath     = $this->prefeituraResolver->resolveOperation($this->prefeitura, 'emitir_nfse');
            $url        = rtrim($seFinUrl, '/') . ($opPath !== '' && $opPath !== '0' ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição sem descrição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $chave = $result['chNFSe'] ?? null;
            $this->dispatchEvent(new NfseEmitted($chave ?? ''));
            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        }
    }

    public function cancelar(string $chave, MotivoCancelamento $motivo, string $descricao): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        try {
            $cert = $this->certManager->getCertificate();
            $cnpj = $cert->getCnpj() ?: null;
            $cpf  = $cert->getCpf() ?: null;

            $xml = (new EventoBuilder())->build(
                tpAmb:     $this->ambiente->value,
                verAplic:  '1.0',
                dhEvento:  date('c'),
                cnpjAutor: $cnpj,
                cpfAutor:  $cpf,
                chNFSe:    $chave,
                motivo:    $motivo,
                descricao: $descricao,
            );

            $signer  = new XmlSigner($cert, $this->signingAlgorithm);
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infPedReg', 'pedRegEvento');
            $payload = ['pedidoRegistroEventoXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl  = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath    = $this->prefeituraResolver->resolveOperation(
                $this->prefeitura, 'cancelar_nfse', ['chave' => $chave]
            );
            $url = rtrim($seFinUrl, '/') . ($opPath !== '' && $opPath !== '0' ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro   = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $this->dispatchEvent(new NfseCancelled($chave));
            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        }
    }

    public function consultar(): ConsultaBuilder
    {
        $this->ensureConfigured();
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl   = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);
        return new ConsultaBuilder(
            $this, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }

    public function executeGet(string $url): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, ['url' => $url]));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                return new NfseResponse(false, null, null, $erro);
            }

            $xml = null;
            $gzipB64 = $result['nfseXmlGZipB64'] ?? $result['dpsXmlGZipB64'] ?? null;
            if ($gzipB64) {
                $xml = gzdecode(base64_decode((string) $gzipB64)) ?: null;
            }

            $this->dispatchEvent(new NfseQueried('nfse'));
            return new NfseResponse(true, null, $xml, null);
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        }
    }

    public function executeGetRaw(string $url): array
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, ['url' => $url]));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                throw new NfseException($erro);
            }

            $this->dispatchEvent(new NfseQueried($operacao));
            return $result;
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        }
    }
}
