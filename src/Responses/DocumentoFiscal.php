<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * Um documento de um lote de distribuição.
 *
 * Nenhum campo de `DistribuicaoNSU` é obrigatório no contrato do ADN, e o governo
 * pode passar a emitir tipos que esta versão do SDK ainda não conhece. Por isso um
 * item que não pôde ser interpretado por completo **não** interrompe o lote: os
 * campos afetados vêm `null` e {@see self::$parseError} descreve o que faltou.
 * O `nsu` é preservado em qualquer cenário, para que o chamador consiga refazer a
 * busca daquele documento em específico.
 *
 * @api
 */
final readonly class DocumentoFiscal
{
    public function __construct(
        public ?int $nsu,
        public ?string $chaveAcesso,
        public ?TipoDocumentoFiscal $tipoDocumento,
        public ?TipoEventoDistribuicao $tipoEvento,
        public ?string $arquivoXml,
        public ?string $dataHoraGeracao,
        /** Por que o documento não pôde ser interpretado por completo; `null` quando íntegro. */
        public ?string $parseError = null,
    ) {}

    /**
     * A conferência de tipo é tolerância deliberada, não contrato.
     *
     * `DistribuicaoNSU` no `ADN-Contribuinte-swagger.json` declara `NSU` como
     * `integer/int64`, `ChaveAcesso` e `DataHoraGeracao` como `string` e os dois
     * tipos como enums de string — um ADN em conformidade nunca manda outra coisa,
     * e `additionalProperties: false` fecha o objeto. Ler sem conferir, porém,
     * apostava o lote nessa conformidade: qualquer valor fora do tipo declarado
     * virava `TypeError`, que subia pelo `array_map` de
     * {@see DistribuicaoResponse::fromApiResult()} e derrubava a página inteira,
     * levando junto o `nsu` que esta classe promete preservar em qualquer cenário.
     *
     * A promessa do docblock acima é incondicional, e um lote perdido por inteiro
     * custa mais do que estas duas guardas. Não as remova por serem inalcançáveis
     * contra um servidor que siga o swagger — é justamente contra o que não segue
     * que elas existem.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $problems = [];

        $tipoDocumentoRaw = $data['TipoDocumento'] ?? null;

        if ($tipoDocumentoRaw === null) {
            $problems[] = 'Campo TipoDocumento ausente na resposta do ADN.';
            $tipoDocumento = null;
        } else {
            $codigoDocumento = self::readString($tipoDocumentoRaw, 'TipoDocumento', $problems);
            $tipoDocumento = $codigoDocumento !== null ? TipoDocumentoFiscal::tryFrom($codigoDocumento) : null;

            if ($codigoDocumento !== null && $tipoDocumento === null) {
                $problems[] = sprintf('TipoDocumento desconhecido: "%s".', $codigoDocumento);
            }
        }

        $tipoEventoRaw = $data['TipoEvento'] ?? null;
        $codigoEvento = $tipoEventoRaw !== null ? self::readString($tipoEventoRaw, 'TipoEvento', $problems) : null;
        $tipoEvento = $codigoEvento !== null ? TipoEventoDistribuicao::tryFrom($codigoEvento) : null;

        if ($codigoEvento !== null && $tipoEvento === null) {
            $problems[] = sprintf('TipoEvento desconhecido: "%s".', $codigoEvento);
        }

        try {
            $arquivoXml = GzipCompressor::decompressB64(
                self::readString($data['ArquivoXml'] ?? null, 'ArquivoXml', $problems)
            );
        } catch (NfseException $nfseException) {
            $arquivoXml = null;
            $problems[] = $nfseException->getMessage();
        }

        return new self(
            nsu: self::readInt($data['NSU'] ?? null, 'NSU', $problems),
            chaveAcesso: self::readString($data['ChaveAcesso'] ?? null, 'ChaveAcesso', $problems),
            tipoDocumento: $tipoDocumento,
            tipoEvento: $tipoEvento,
            arquivoXml: $arquivoXml,
            dataHoraGeracao: self::readString($data['DataHoraGeracao'] ?? null, 'DataHoraGeracao', $problems),
            parseError: $problems === [] ? null : implode(' ', $problems),
        );
    }

    /**
     * Sem conversão: nenhum destes campos tem forma numérica que se possa
     * aproveitar. Os códigos de tipo são nomes (`NFSE`, `CANCELAMENTO`), a chave de
     * acesso tem 50 dígitos que float algum guarda sem truncar, e as datas são ISO.
     * Um valor de outro tipo é dado que não dá para ler, e dizê-lo vale mais do que
     * converter e entregar algo que não veio.
     *
     * @param  list<string>  $problems
     */
    private static function readString(mixed $value, string $campo, array &$problems): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        $problems[] = sprintf('Campo %s veio como %s, e não como texto.', $campo, get_debug_type($value));

        return null;
    }

    /**
     * Texto decimal é a única conversão aceita, e só porque o `nsu` é o campo que a
     * classe promete preservar sempre: dele o chamador refaz a busca do documento.
     * Um NSU é sequencial e sem sinal, então dígitos — inclusive com zeros à
     * esquerda, que `ctype_digit` aceita e o cast descarta — não têm outra leitura
     * possível. O swagger declara `integer`; isto é o que fazer se vier diferente.
     *
     * @param  list<string>  $problems
     */
    private static function readInt(mixed $value, string $campo, array &$problems): ?int
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $problems[] = sprintf('Campo %s veio como %s, e não como número inteiro.', $campo, get_debug_type($value));

        return null;
    }
}
