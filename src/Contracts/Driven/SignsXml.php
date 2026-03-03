<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driven;

interface SignsXml
{
    public function sign(string $xml, string $tagname, string $rootname): string;
}
