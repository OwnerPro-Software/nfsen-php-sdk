<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

use OwnerPro\Nfsen\Exceptions\NfseException;
use Throwable;

class GzipCompressor
{
    /**
     * Teto de descompressão. O conteúdo gzip vem do servidor e, sem limite, uma bomba
     * de descompressão (razões da ordem de 1000:1, CWE-409) expandiria GB em memória a
     * partir de poucos MB de resposta. 50 MB comporta com folga qualquer XML de NFS-e
     * ou documento de distribuição.
     *
     * É teto de memória, não corte exato: o `max_length` do gzdecode interrompe o
     * inflate e devolve false quando a saída passaria do teto — na prática alguns MB
     * acima do nominal, pelo arredondamento do buffer interno do zlib; o que cabe volta
     * inteiro, nunca truncado. Uma bomba fica assim limitada a dezenas de MB e cai no
     * contrato de erro (false → NfseException), que é o objetivo.
     */
    private const int MAX_DECOMPRESSED_BYTES = 52_428_800; // @pest-mutate-ignore IncrementInteger,DecrementInteger — ±1 byte no teto não é observável por teste

    public function __invoke(string $data): string|false
    {
        return gzencode($data);
    }

    public static function decompressB64(?string $gzipB64): ?string
    {
        if ($gzipB64 === null || $gzipB64 === '') {
            return null;
        }

        $decoded = base64_decode($gzipB64, true);
        if ($decoded === false) {
            throw new NfseException('Falha ao decodificar base64 do XML.');
        }

        try {
            $decompressed = gzdecode($decoded, self::MAX_DECOMPRESSED_BYTES);
            // @codeCoverageIgnoreStart
        } catch (Throwable) { // @pest-mutate-ignore
            $decompressed = false;
        }

        // @codeCoverageIgnoreEnd

        if ($decompressed === false) {
            throw new NfseException('Falha ao descomprimir XML.');
        }

        return $decompressed;
    }
}
