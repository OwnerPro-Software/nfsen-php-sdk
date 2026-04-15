<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

interface BuildsDanfseData
{
    /** @throws XmlParseException */
    public function build(string $xmlNfse): NfseData;
}
