<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Danfse\Data\NfseData;

interface RendersDanfseHtml
{
    public function render(NfseData $data): string;
}
