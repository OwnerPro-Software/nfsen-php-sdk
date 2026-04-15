<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Responses\DanfseResponse;

/**
 * @api
 */
interface RendersDanfse
{
    /**
     * Renderiza o DANFSE como PDF a partir do XML da NFS-e autorizada.
     * Erros são encapsulados no `DanfseResponse` retornado.
     */
    public function toPdf(string $xmlNfse): DanfseResponse;

    /**
     * Renderiza o DANFSE como HTML a partir do XML da NFS-e autorizada.
     *
     * @throws XmlParseException quando o XML é inválido ou não contém NFS-e
     */
    public function toHtml(string $xmlNfse): string;
}
