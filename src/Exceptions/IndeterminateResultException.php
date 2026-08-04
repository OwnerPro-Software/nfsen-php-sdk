<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

use Throwable;

/**
 * Resultado indeterminado: o SDK não conseguiu obter uma resposta completa e
 * legível do servidor — a requisição pode ou não ter sido recebida e
 * processada pela SEFIN.
 *
 * Cobre três situações:
 * - Falha antes de qualquer resposta (timeout, DNS, conexão recusada, TLS):
 *   a requisição pode nem ter chegado ao servidor.
 * - Falha no meio da transferência (conexão resetada, corpo truncado): o
 *   servidor recebeu e processou, mas o resultado não pôde ser lido.
 * - Resposta 2xx com corpo ilegível (JSON inválido ou vazio): o servidor
 *   confirmou o processamento, mas o resultado não pôde ser interpretado.
 * - Resposta 5xx a uma operação que altera estado, sem rejeição estruturada da
 *   SEFIN no corpo: o erro pode ter vindo de um proxy antes da SEFIN, ou da
 *   própria SEFIN depois de gravar a nota — nada no corpo distingue os dois.
 *
 * Contrato: NUNCA faça retry cego de emissão após capturar esta exceção — a
 * NFS-e pode já ter sido emitida e um retry causaria dupla emissão. Reconcilie
 * primeiro: calcule o ID com `DpsId::generate()` e consulte
 * `consultar()->dps($id)`:
 * - encontrou → a nota FOI emitida; siga o fluxo normal com a chave retornada;
 * - `NfseResponse::DPS_NOT_FOUND` → a emissão NÃO aconteceu; é seguro
 *   re-emitir com o mesmo nDPS.
 *
 * Qualquer outra exceção ou resposta do SDK é uma resposta definitiva do
 * servidor (rejeição, erro de negócio, erro de certificado).
 *
 * Falhas comprovadamente anteriores ao envio (DNS, TCP, TLS) são lançadas como
 * {@see RequestNotDeliveredException} — nelas o retry direto é seguro, sem
 * reconciliação.
 *
 * @api
 */
final class IndeterminateResultException extends CommunicationException
{
    /** Teto do corpo preservado em {@see self::$body}; `raw` guarda a estrutura inteira. */
    private const int BODY_MAX_BYTES = 8192; // @pest-mutate-ignore IncrementInteger,DecrementInteger — ±1 byte no teto não é observável por teste

    /**
     * @param  'body'|'connect'|'dns'|'read'|'tls'|'transfer'|null  $phase  fase da falha, quando detectável
     * @param  int|null  $statusCode  status HTTP da resposta, quando ela chegou
     * @param  string|null  $body  corpo cru, truncado em 8 KiB; `null` quando a falha
     *                             foi anterior a qualquer resposta ou quando o call
     *                             site só recebeu o JSON já decodificado
     * @param  array<string, mixed>|null  $raw  corpo JSON decodificado, quando houve um
     */
    public function __construct(
        string $message,
        public readonly ?string $phase = null,
        ?Throwable $previous = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $body = null,
        public readonly ?array $raw = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromTransportFailure(Throwable $previous): self
    {
        return self::fromTransportFailureWithPhase($previous, self::detectPhase($previous->getMessage()));
    }

    /**
     * Variante com fase explícita, usada pelo TransportFailureClassifier quando
     * a evidência vem do errno do cURL em vez do texto da mensagem.
     *
     * @param  'body'|'connect'|'dns'|'read'|'tls'|'transfer'|null  $phase
     */
    public static function fromTransportFailureWithPhase(Throwable $previous, ?string $phase): self
    {
        return new self(
            'Resultado indeterminado: a comunicação falhou antes de uma resposta completa ser recebida. '.$previous->getMessage(),
            $phase,
            $previous,
        );
    }

    public static function fromUnreadableResponse(int $statusCode, string $body): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: o servidor respondeu HTTP %d, mas o corpo da resposta não pôde ser interpretado. Corpo: "%s"',
                $statusCode,
                substr($body, 0, 200),
            ),
            'body',
            statusCode: $statusCode,
            body: self::truncate($body),
        );
    }

    /**
     * Resposta 2xx com JSON válido, porém sem nenhum dos campos que a operação
     * exige: o corpo existe mas não é interpretável como resultado — mesma régua
     * do 2xx ilegível, nunca um sucesso silencioso.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromMissingResponseField(int $statusCode, string $body, array $raw, string ...$fields): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: o servidor respondeu HTTP %d, mas a resposta não traz nenhum dos campos exigidos pela operação (%s); não pôde ser interpretada.',
                $statusCode,
                self::listFields($fields),
            ),
            'body',
            statusCode: $statusCode,
            body: self::truncate($body),
            raw: $raw,
        );
    }

    /**
     * 2xx de consulta sem o campo obrigatório da operação, na rota em que o
     * status não acompanha o corpo (`SendsHttpRequests::get()` devolve só o
     * array — e ela só entrega corpo de 2xx ou de rejeição estruturada, já
     * tratada antes): corpo legível, resultado ininterpretável.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromMissingQueryField(string $field, array $raw): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: o servidor respondeu 2xx sem o campo obrigatório "%s"; a resposta não pôde ser interpretada.',
                $field,
            ),
            'body',
            raw: $raw,
        );
    }

    /**
     * Resposta a um POST de evento sem rejeição estruturada e sem o recibo
     * obrigatório: `EventosPostResponseSucesso` (SefinNacional-swagger.json)
     * declara `eventoXmlGZipB64` required, então a ausência do campo é quebra
     * de contrato — nada prova que o evento foi registrado. O status HTTP não
     * chega a este ponto do pipeline; por isso a mensagem não o carrega.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromMissingEventReceipt(string $field, array $raw): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: a resposta ao evento não trouxe rejeição estruturada nem o campo obrigatório "%s"; '.
                'não há evidência de que o evento tenha sido registrado. Reconcilie com consultar()->eventos().',
                $field,
            ),
            'body',
            raw: $raw,
        );
    }

    /**
     * 5xx sem rejeição estruturada da SEFIN numa operação que altera estado.
     *
     * Sem `phase`: nenhuma fase de transporte falhou — a resposta chegou inteira.
     * O que falta é evidência sobre o processamento, não sobre a comunicação.
     */
    public static function fromServerError(int $statusCode, string $body): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: o servidor respondeu HTTP %d sem rejeição estruturada da SEFIN; '.
                'não há evidência de que a operação tenha ou não sido processada. Corpo: "%s"',
                $statusCode,
                substr($body, 0, 200),
            ),
            statusCode: $statusCode,
            body: self::truncate($body),
        );
    }

    private static function truncate(string $body): string
    {
        return substr($body, 0, self::BODY_MAX_BYTES);
    }

    /** @param array<string> $fields */
    private static function listFields(array $fields): string
    {
        return '"'.implode('", "', $fields).'"';
    }

    /**
     * @return 'connect'|'dns'|'read'|'tls'|'transfer'|null
     */
    private static function detectPhase(string $message): ?string
    {
        return match (true) {
            str_contains($message, 'cURL error 6:') => 'dns',
            str_contains($message, 'cURL error 7:') => 'connect',
            str_contains($message, 'cURL error 35:'),
            str_contains($message, 'cURL error 58:'),
            str_contains($message, 'cURL error 60:') => 'tls',
            str_contains($message, 'cURL error 18:'),
            str_contains($message, 'cURL error 56:'),
            str_contains($message, 'cURL error 92:') => 'transfer',
            str_contains($message, 'cURL error 28:') => str_contains($message, 'Connection timed out') ? 'connect' : 'read',
            default => null,
        };
    }
}
