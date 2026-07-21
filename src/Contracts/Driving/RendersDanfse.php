<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Enums\MarcaDagua;
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
     *
     * @param  MarcaDagua|null  $marcaDagua  marca d'água da NT 008 (itens 2.5.1 e 2.5.2);
     *                                       `null` para a NFS-e vigente
     */
    public function toPdf(string $xmlNfse, ?MarcaDagua $marcaDagua = null): DanfseResponse;

    /**
     * Renderiza o DANFSE como HTML a partir do XML da NFS-e autorizada.
     *
     * @param  MarcaDagua|null  $marcaDagua  marca d'água da NT 008 (itens 2.5.1 e 2.5.2);
     *                                       `null` para a NFS-e vigente
     *
     * @throws XmlParseException quando o XML é inválido ou não contém NFS-e
     */
    public function toHtml(string $xmlNfse, ?MarcaDagua $marcaDagua = null): string;
}
