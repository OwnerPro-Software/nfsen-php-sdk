<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driving\CancelsNfse;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Events\NfseCancelled;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Pipeline\Concerns\DispatchesEvents;
use OwnerPro\Nfsen\Pipeline\Concerns\ParsesEventResponse;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;

/** @phpstan-import-type MessageData from ProcessingMessage */
final readonly class NfseCanceller implements CancelsNfse
{
    use DispatchesEvents;
    use ParsesEventResponse;
    use ValidatesChaveAcesso;

    public function __construct(
        private NfseRequestPipeline $pipeline,
        private CancellationBuilder $cancellationBuilder,
        private NfseAmbiente $ambiente,
    ) {}

    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao): NfseResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaCancelamento::from($codigoMotivo);
        }

        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        return $this->withFailureEvent($operacao, function () use ($chave, $codigoMotivo, $descricao, $operacao): NfseResponse {
            $identity = $this->pipeline->extractAuthorIdentity('cancelar');

            $xml = $this->cancellationBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                // gmdate, não date: TSDateTimeUTC só aceita offset de minuto zero e na
                // faixa -11..+12, então date('c') gera valor inválido em host com
                // timezone de offset quebrado (+05:30 na Índia, +05:45 no Nepal) ou
                // em +13:00. UTC é sempre válido e representa o mesmo instante.
                dhEvento: gmdate('c'),
                cnpjAutor: $identity['cnpj'],
                cpfAutor: $identity['cpf'],
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                descricao: $descricao,
            );

            /**
             * @var array{
             *     erros?: list<MessageData>,
             *     erro?: MessageData,
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->pipeline->signCompressSend(
                $xml, 'infPedReg', 'pedRegEvento', 'pedidoRegistroEventoXmlGZipB64', 'cancel_nfse', ['chave' => $chave]
            );

            return $this->parseEventResponse($result, $chave, $operacao, new NfseCancelled($chave));
        });
    }
}
