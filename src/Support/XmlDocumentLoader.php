<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

use DOMDocument;

class XmlDocumentLoader
{
    public function __invoke(string $xml): DOMDocument|false
    {
        $prev = libxml_use_internal_errors(true);

        try {
            $doc = new DOMDocument;
            $result = $doc->loadXML($xml, LIBXML_NONET);
            libxml_clear_errors();

            return $result === false ? false : $doc;
        } finally {
            libxml_use_internal_errors($prev);
        }
    }
}
