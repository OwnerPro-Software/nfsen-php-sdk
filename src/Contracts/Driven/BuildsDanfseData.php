<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

interface BuildsDanfseData
{
    /**
     * @param  MarcaDagua|null  $marcaDagua  marca d'água da NT 008 (itens 2.5.1 e 2.5.2);
     *                                       `null` para a NFS-e vigente
     *
     * @throws XmlParseException
     */
    public function build(string $xmlNfse, ?MarcaDagua $marcaDagua = null): NfseData;
}
