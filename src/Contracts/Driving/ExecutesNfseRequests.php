<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * Porta interna de execução HTTP, construída apenas pelo wiring do
 * `NfsenClient`. Não faz parte da API pública: sua assinatura pode mudar em
 * releases minor.
 *
 * @internal
 */
interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    /**
     * Retorna apenas 200 ou 404. Qualquer outro status lança HttpException
     * (dentro do pipeline de eventos, disparando NfseFailed); falha de
     * transporte lança IndeterminateResultException.
     */
    public function executeHead(string $url): int;

    /**
     * Retorna a resposta HTTP crua (status + JSON + corpo).
     *
     * Lança HttpException quando o servidor responde status inesperado
     * (diferente de 200/201/404) sem corpo de erro estruturado (`erros`/`erro`).
     *
     * Com $requiredField, um 2xx cujo corpo não traga esse campo como string
     * não-vazia lança IndeterminateResultException: a operação exige o campo, e
     * ausência comprovada é sinalizada por 404, não por corpo vazio.
     */
    public function executeRaw(string $url, ?string $requiredField = null): HttpResponse;

    public function executeAndDownload(string $url): string;
}
