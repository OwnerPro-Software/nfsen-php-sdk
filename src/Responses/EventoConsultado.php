<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * Um evento devolvido por `consultar()->eventos()`.
 *
 * A rota GET de eventos entrega uma lista, e o governo pode passar a registrar tipos
 * que esta versão do SDK ainda não conhece. Por isso um item que não pôde ser
 * interpretado por completo **não** interrompe a resposta: os campos afetados vêm
 * `null` e {@see self::$parseError} descreve o que faltou.
 *
 * @api
 */
final readonly class EventoConsultado
{
    public function __construct(
        public ?string $chaveAcesso,
        public ?TipoEvento $tipoEvento,
        public ?int $numeroPedidoRegistroEvento,
        public ?string $dataHoraRecebimento,
        /** XML do evento, já descomprimido. */
        public ?string $xml,
        /** Por que o evento não pôde ser interpretado por completo; `null` quando íntegro. */
        public ?string $parseError = null,
    ) {}

    /**
     * O swagger não declara a lista `eventos`, então nada garante que cada item seja
     * um objeto. Um item escalar vira um evento vazio com o motivo dito, e não um
     * `TypeError` que levaria junto os itens íntegros da lista.
     */
    public static function fromArray(mixed $data): self
    {
        if (! is_array($data)) {
            return new self(
                chaveAcesso: null,
                tipoEvento: null,
                numeroPedidoRegistroEvento: null,
                dataHoraRecebimento: null,
                xml: null,
                parseError: sprintf('Item de eventos veio como %s, e não como objeto.', get_debug_type($data)),
            );
        }

        $problems = [];

        $codigoEvento = self::readInt($data['tipoEvento'] ?? null, 'tipoEvento', $problems);
        $tipoEvento = $codigoEvento !== null ? TipoEvento::tryFrom($codigoEvento) : null;

        if ($codigoEvento !== null && $tipoEvento === null) {
            $problems[] = sprintf('TipoEvento desconhecido: "%d".', $codigoEvento);
        }

        try {
            $xml = GzipCompressor::decompressB64(
                self::readString($data['arquivoXml'] ?? null, 'arquivoXml', $problems)
            );
        } catch (NfseException $nfseException) {
            $xml = null;
            $problems[] = $nfseException->getMessage();
        }

        return new self(
            chaveAcesso: self::readString($data['chaveAcesso'] ?? null, 'chaveAcesso', $problems),
            tipoEvento: $tipoEvento,
            numeroPedidoRegistroEvento: self::readInt($data['numeroPedidoRegistroEvento'] ?? null, 'numeroPedidoRegistroEvento', $problems),
            dataHoraRecebimento: self::readString($data['dataHoraRecebimento'] ?? null, 'dataHoraRecebimento', $problems),
            xml: $xml,
            parseError: $problems === [] ? null : implode(' ', $problems),
        );
    }

    /**
     * Um item fora do tipo declarado não pode derrubar a lista inteira: o
     * `array_map` que constrói os eventos levaria junto os itens íntegros, e a
     * promessa do docblock da classe é que só o campo afetado se perde.
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
     * Texto decimal também serve: o swagger declara `integer`, mas é justamente a
     * divergência entre o declarado e o devolvido que motivou esta classe.
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
