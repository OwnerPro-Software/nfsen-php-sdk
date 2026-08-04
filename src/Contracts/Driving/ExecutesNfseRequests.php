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
     * Um 2xx que não traga nenhum dos campos exigidos **na forma exigida** lança
     * IndeterminateResultException: a operação exige o campo, e ausência comprovada
     * é sinalizada por 404, não por corpo vazio. Os campos valem como alternativas
     * entre si — é o caso da consulta de eventos, que aceita tanto a lista `eventos`
     * quanto o texto `eventoXmlGZipB64`.
     *
     * A forma faz parte da exigência: um campo de texto que chegue como lista (ou
     * vice-versa) é resposta fora do contrato, não campo presente — sem isso o valor
     * seguiria para um construtor tipado e estouraria `TypeError`, fora do contrato
     * de exceções do SDK.
     *
     * @param  list<string>  $requiredStrings  campos que valem como string não-vazia
     * @param  list<string>  $requiredLists  campos que valem como lista não-vazia
     */
    public function executeRaw(string $url, array $requiredStrings = [], array $requiredLists = []): HttpResponse;

    public function executeAndDownload(string $url): string;
}
