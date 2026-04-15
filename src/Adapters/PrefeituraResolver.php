<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use InvalidArgumentException;
use OwnerPro\Nfsen\Contracts\Driven\ResolvesPrefeituras;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Support\FileReader;

/**
 * @internal
 */
final class PrefeituraResolver implements ResolvesPrefeituras
{
    /**
     * Cache estático por path — evita re-leitura em lote.
     *
     * @var array<string, array<string, array{
     *     urls?: array<string, string>,
     *     operations?: array<string, string>,
     * }>>
     */
    private static array $cache = [];

    private const array DEFAULT_URLS = [
        'sefin_staging' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'sefin_production' => 'https://sefin.nfse.gov.br/SefinNacional',
        'adn_staging' => 'https://adn.producaorestrita.nfse.gov.br',
        'adn_production' => 'https://adn.nfse.gov.br',
    ];

    private const array DEFAULT_OPERATIONS = [
        'query_nfse' => 'nfse/{chave}',
        'query_dps' => 'dps/{id}',
        'verify_dps' => 'dps/{id}',
        'query_events' => 'nfse/{chave}/eventos/{tipoEvento}/{nSequencial}',
        'query_danfse' => 'danfse/{chave}',
        'emit_nfse' => 'nfse',
        'emit_court_order' => 'decisao-judicial/nfse',
        'cancel_nfse' => 'nfse/{chave}/eventos',
    ];

    /** @var array<string, array{urls?: array<string, string>, operations?: array<string, string>}> */
    private array $data;

    public function __construct(
        string $jsonPath,
        private readonly FileReader $fileReader = new FileReader,
    ) {
        if (! isset(self::$cache[$jsonPath])) {
            if (! file_exists($jsonPath)) {
                throw new InvalidArgumentException('Arquivo de prefeituras não encontrado.');
            }

            $contents = ($this->fileReader)($jsonPath);
            if ($contents === false) {
                throw new InvalidArgumentException('Falha ao ler arquivo de prefeituras.');
            }

            $decoded = json_decode($contents, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException(sprintf('JSON inválido no arquivo de prefeituras. Erro: %s.', json_last_error_msg()));
            }

            /** @var array<string, array{urls?: array<string, string>, operations?: array<string, string>}> $decoded */
            self::$cache[$jsonPath] = $decoded;
        }

        $this->data = self::$cache[$jsonPath];
    }

    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'sefin_production' : 'sefin_staging';
        $url = $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];

        $this->validateHttps($url);

        return $url;
    }

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'adn_production' : 'adn_staging';
        $url = $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];

        $this->validateHttps($url);

        return $url;
    }

    /** @param array<string, int|string> $params */
    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string
    {
        $this->validateIbge($codigoIbge);
        $template = $this->data[$codigoIbge]['operations'][$operacao]
            ?? self::DEFAULT_OPERATIONS[$operacao]
            ?? throw new InvalidArgumentException(sprintf("Operação desconhecida: '%s'.", $operacao));

        foreach ($params as $key => $value) {
            $template = str_replace('{'.$key.'}', rawurlencode((string) $value), $template);
        }

        if (preg_match('/\{(\w+)\}/', $template, $matches)) {
            throw new InvalidArgumentException(sprintf("Parâmetro não fornecido: '{%s}' na operação '%s'.", $matches[1], $operacao));
        }

        return $template;
    }

    private function validateIbge(string $code): void
    {
        if (! preg_match('/^\d{7}$/', $code)) {
            throw new InvalidArgumentException(sprintf("Código IBGE inválido: '%s'. Esperado: 7 dígitos numéricos.", $code));
        }
    }

    private function validateHttps(string $url): void
    {
        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException(sprintf("URL deve usar HTTPS: '%s'.", $url));
        }
    }
}
