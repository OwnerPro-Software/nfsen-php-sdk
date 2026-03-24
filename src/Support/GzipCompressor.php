<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

use OwnerPro\Nfsen\Exceptions\NfseException;
use Throwable;

class GzipCompressor
{
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
            $decompressed = gzdecode($decoded);
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
