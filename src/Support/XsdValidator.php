<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

use LibXMLError;
use Pulsar\NfseNacional\Exceptions\NfseException;

final readonly class XsdValidator
{
    public function __construct(
        private string $schemesPath,
        private XmlDocumentLoader $xmlDocumentLoader = new XmlDocumentLoader,
    ) {}

    public function validate(string $xmlFragment, string $xsdFilename): void
    {
        $xsdPath = $this->schemesPath.'/'.$xsdFilename;
        if (! file_exists($xsdPath)) {
            throw new NfseException(sprintf("Schema XSD não encontrado: '%s'.", $xsdFilename));
        }

        $xmlWithDecl = '<?xml version="1.0" encoding="UTF-8"?>'.$xmlFragment; // @pest-mutate-ignore ConcatRemoveLeft: declaration is defensive, loadXML handles fragments without it
        $doc = ($this->xmlDocumentLoader)($xmlWithDecl);

        if ($doc === false) {
            throw new NfseException('XML inválido: falha ao carregar documento.');
        }

        $prev = libxml_use_internal_errors(true);

        try {
            $valid = $doc->schemaValidate($xsdPath);
            $errors = libxml_get_errors();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($prev);
        }

        if (! $valid) {
            $messages = array_map(fn (LibXMLError $e): string => trim($e->message), $errors);
            throw new NfseException(
                'XML inválido: '.implode('; ', $messages)
            );
        }
    }
}
