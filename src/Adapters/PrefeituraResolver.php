<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Adapters;

use InvalidArgumentException;
use Pulsar\NfseNacional\Contracts\Driven\ResolvesPrefeituras;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Support\FileReader;

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

    private const DEFAULT_URLS = [
        'sefin_homologacao' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'sefin_producao' => 'https://sefin.nfse.gov.br/SefinNacional',
        'adn_homologacao' => 'https://adn.producaorestrita.nfse.gov.br',
        'adn_producao' => 'https://adn.nfse.gov.br',
    ];

    private const DEFAULT_OPERATIONS = [
        'consultar_nfse' => 'nfse/{chave}',
        'consultar_dps' => 'dps/{id}',
        'verificar_dps' => 'dps/{id}',
        'consultar_eventos' => 'nfse/{chave}/eventos/{tipoEvento}/{nSequencial}',
        'consultar_danfse' => 'danfse/{chave}',
        'emitir_nfse' => 'nfse',
        'emitir_decisao_judicial' => 'decisao-judicial/nfse',
        'cancelar_nfse' => 'nfse/{chave}/eventos',
        'substituir_nfse' => 'nfse/{chave}/eventos',
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

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'sefin_producao' : 'sefin_homologacao';

        return $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];
    }

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'adn_producao' : 'adn_homologacao';

        return $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];
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
}
