<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional;

use InvalidArgumentException;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\MensagemProcessamento;
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

/** @phpstan-import-type DpsDataArray from DpsData */
final readonly class NfseClient implements NfseClientContract
{
    public function __construct(
        private NfseAmbiente $ambiente,
        private string $signingAlgorithm,
        private PrefeituraResolver $prefeituraResolver,
        private DpsBuilder $dpsBuilder,
        private CancelamentoBuilder $cancelamentoBuilder,
        private SubstituicaoBuilder $substituicaoBuilder,
        private GzipCompressor $gzipCompressor,
        private CertificateManager $certManager,
        private string $prefeitura,
        private NfseHttpClient $httpClient,
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

        return new self(
            ambiente: $ambiente,
            signingAlgorithm: $signingAlgorithm,
            prefeituraResolver: $prefeituraResolver,
            dpsBuilder: new DpsBuilder($xsdValidator),
            cancelamentoBuilder: new CancelamentoBuilder($xsdValidator),
            substituicaoBuilder: new SubstituicaoBuilder($xsdValidator),
            gzipCompressor: new GzipCompressor,
            certManager: $certManager,
            prefeitura: $prefeitura,
            httpClient: $httpClient,
        );
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

    private function validateChaveAcesso(string $chave): void
    {
        if (! preg_match('/^\d{50}$/', $chave)) {
            throw new InvalidArgumentException(sprintf("chaveAcesso inválida: '%s'. Esperado: exatamente 50 dígitos numéricos.", $chave));
        }
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->doEmitir($data, 'emitir', 'emitir_nfse', 'dpsXmlGZipB64');
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->doEmitir($data, 'emitir_decisao_judicial', 'emitir_decisao_judicial', 'xmlGZipB64');
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    private function doEmitir(DpsData|array $data, string $operacao, string $operationKey, string $payloadKey): NfseResponse
    {
        if (is_array($data)) {
            $data = DpsData::fromArray($data);
        }

        $this->dispatchEvent(new NfseRequested($operacao, []));

        try {
            $xml = $this->dpsBuilder->buildAndValidate($data);
            $signer = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
            $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$signer->sign($xml, 'infDPS', 'DPS');
            $compressed = ($this->gzipCompressor)($signed);
            if ($compressed === false) {
                throw new NfseException('Falha ao comprimir XML.');
            }

            $payload = [$payloadKey => base64_encode($compressed)];

            $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath = $this->prefeituraResolver->resolveOperation($this->prefeitura, $operationKey);
            $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     chaveAcesso?: string,
             *     nfseXmlGZipB64?: string,
             *     idDps?: string,
             *     idDPS?: string,
             *     alertas?: list<array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string}>,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->post($url, $payload);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = MensagemProcessamento::fromApiResult($result);
                $codigo = $erros[0]->codigo ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));

                return new NfseResponse(
                    sucesso: false,
                    idDps: $result['idDPS'] ?? $result['idDps'] ?? null,
                    erros: $erros,
                    tipoAmbiente: $result['tipoAmbiente'] ?? null,
                    versaoAplicativo: $result['versaoAplicativo'] ?? null,
                    dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                );
            }

            $chave = $result['chaveAcesso'] ?? null;

            if ($chave === null) {
                $this->dispatchEvent(new NfseRejected($operacao, 'SEM_CHAVE'));

                return new NfseResponse(
                    sucesso: false,
                    erros: [new MensagemProcessamento(descricao: 'Resposta da API não contém chaveAcesso.')],
                );
            }

            $this->dispatchEvent(new NfseEmitted($chave));

            return new NfseResponse(
                sucesso: true,
                chave: $chave,
                xml: GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null),
                idDps: $result['idDps'] ?? null,
                alertas: MensagemProcessamento::fromArrayList($result['alertas'] ?? []),
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao, int $nPedRegEvento = 1): NfseResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaCancelamento::from($codigoMotivo);
        }

        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        try {
            $certificate = $this->certManager->getCertificate();
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

            return $this->sendEvento($xml, $chave, $operacao, 'cancelar_nfse', new NfseCancelled($chave));
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1): NfseResponse
    {
        $this->validateChaveAcesso($chave);
        $this->validateChaveAcesso($chaveSubstituta);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaSubstituicao::from($codigoMotivo);
        }

        $operacao = 'substituir';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        try {
            $certificate = $this->certManager->getCertificate();
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

            return $this->sendEvento($xml, $chave, $operacao, 'substituir_nfse', new NfseSubstituted($chave, $chaveSubstituta));
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    private function sendEvento(string $xml, string $chave, string $operacao, string $operationKey, NfseCancelled|NfseSubstituted $successEvent): NfseResponse
    {
        $signer = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
        $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$signer->sign($xml, 'infPedReg', 'pedRegEvento');
        $compressed = ($this->gzipCompressor)($signed);
        if ($compressed === false) {
            throw new NfseException('Falha ao comprimir XML.');
        }

        $payload = ['pedidoRegistroEventoXmlGZipB64' => base64_encode($compressed)];

        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $opPath = $this->prefeituraResolver->resolveOperation(
            $this->prefeitura, $operationKey, ['chave' => $chave]
        );
        $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

        /**
         * @var array{
         *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
         *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
         *     eventoXmlGZipB64?: string,
         *     tipoAmbiente?: int,
         *     versaoAplicativo?: string,
         *     dataHoraProcessamento?: string,
         * } $result
         */
        $result = $this->httpClient->post($url, $payload);

        if (! empty($result['erros']) || isset($result['erro'])) {
            $erros = MensagemProcessamento::fromApiResult($result);
            $codigo = $erros[0]->codigo ?? 'UNKNOWN';
            $this->dispatchEvent(new NfseRejected($operacao, $codigo));

            return new NfseResponse(
                sucesso: false,
                erros: $erros,
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        }

        $this->dispatchEvent($successEvent);

        return new NfseResponse(
            sucesso: true,
            chave: $chave,
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
            tipoAmbiente: $result['tipoAmbiente'] ?? null,
            versaoAplicativo: $result['versaoAplicativo'] ?? null,
            dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
        );
    }

    public function consultar(): ConsultaBuilder
    {
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);

        return new ConsultaBuilder(
            $this, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }

    public function executeGet(string $url): NfseResponse
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        try {
            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     nfseXmlGZipB64?: string,
             *     chaveAcesso?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = MensagemProcessamento::fromApiResult($result);
                $this->dispatchEvent(new NfseRejected($operacao, $erros[0]->codigo ?? 'UNKNOWN'));

                return new NfseResponse(
                    sucesso: false,
                    erros: $erros,
                    tipoAmbiente: $result['tipoAmbiente'] ?? null,
                    versaoAplicativo: $result['versaoAplicativo'] ?? null,
                    dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                );
            }

            $this->dispatchEvent(new NfseQueried('consultar'));

            return new NfseResponse(
                sucesso: true,
                chave: $result['chaveAcesso'] ?? null,
                xml: GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null),
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
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
     *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
     *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
     *     chaveAcesso?: string,
     *     idDps?: string,
     *     danfseUrl?: string,
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }
     */
    public function executeGetRaw(string $url): array
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        try {
            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     chaveAcesso?: string,
             *     idDps?: string,
             *     danfseUrl?: string,
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? $result['erro']['codigo'] ?? 'UNKNOWN'));
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

    public function executeHead(string $url): int
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        try {
            $status = $this->httpClient->head($url);

            $this->dispatchEvent(new NfseQueried($operacao));

            return $status;
        } catch (HttpException $httpException) {
            $this->dispatchEvent(new NfseFailed($operacao, $httpException->getMessage()));
            throw $httpException;
        } catch (Throwable $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }
}
