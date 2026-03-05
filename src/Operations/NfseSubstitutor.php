<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Operations;

use Pulsar\NfseNacional\Contracts\Driving\SubstitutesNfse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Pipeline\Concerns\ParsesEventResponse;
use Pulsar\NfseNacional\Pipeline\Concerns\ValidatesChaveAcesso;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

final readonly class NfseSubstitutor implements SubstitutesNfse
{
    use DispatchesEvents;
    use ParsesEventResponse;
    use ValidatesChaveAcesso;

    public function __construct(
        private NfseRequestPipeline $pipeline,
        private SubstitutionBuilder $substitutionBuilder,
        private NfseAmbiente $ambiente,
    ) {}

    public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse
    {
        $this->validateChaveAcesso($chave);
        $this->validateChaveAcesso($chaveSubstituta);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaSubstituicao::from($codigoMotivo);
        }

        $operacao = 'substituir';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        return $this->withFailureEvent($operacao, function () use ($chave, $chaveSubstituta, $codigoMotivo, $descricao, $operacao): NfseResponse {
            $identity = $this->pipeline->extractAuthorIdentity('substituir');

            $xml = $this->substitutionBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                dhEvento: date('c'),
                cnpjAutor: $identity['cnpj'],
                cpfAutor: $identity['cpf'],
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                chSubstituta: $chaveSubstituta,
                descricao: $descricao,
            );

            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->pipeline->signCompressSend(
                $xml, 'infPedReg', 'pedRegEvento', 'pedidoRegistroEventoXmlGZipB64', 'substitute_nfse', ['chave' => $chave]
            );

            return $this->parseEventResponse($result, $chave, $operacao, new NfseSubstituted($chave, $chaveSubstituta));
        });
    }
}
