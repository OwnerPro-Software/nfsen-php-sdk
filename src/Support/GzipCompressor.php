<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

use Pulsar\NfseNacional\Exceptions\NfseException;
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
        } catch (Throwable) {
            $decompressed = false;
        }

        if ($decompressed === false) {
            throw new NfseException('Falha ao descomprimir XML.');
        }

        return $decompressed;
    }
}
