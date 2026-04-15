<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;

final readonly class BaconQrCodeGenerator implements GeneratesQrCode
{
    public function __construct(private int $size = 200) {} // @pest-mutate-ignore DecrementInteger,IncrementInteger — tamanho default é cosmético (px); QR válido em qualquer tamanho razoável.

    public function dataUri(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($this->size),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
