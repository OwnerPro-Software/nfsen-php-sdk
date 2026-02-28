<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

use DOMDocument;

class XmlDocumentLoader
{
    public function __invoke(string $xml): DOMDocument|false
    {
        $doc = new DOMDocument();

        return $doc->loadXML($xml) === false ? false : $doc;
    }
}
