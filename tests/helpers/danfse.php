<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Danfse\Data\DanfseParticipante;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

function fakeQrGen(): GeneratesQrCode
{
    return new class implements GeneratesQrCode
    {
        public function dataUri(string $payload): string
        {
            return 'data:image/svg+xml;base64,FAKEQR_'.base64_encode($payload);
        }
    };
}

function sampleParticipante(string $nome = 'ACME LTDA'): DanfseParticipante
{
    return new DanfseParticipante(
        nome: $nome, cnpjCpf: '11.222.333/0001-81', im: '-',
        telefone: '(11) 3333-4444', email: 'acme@example.com',
        endereco: 'Rua X, 100, Centro', municipio: 'São Paulo - SP',
        cep: '01310-100', simplesNacional: 'Não Optante', regimeSN: '-',
    );
}

function sampleData(NfseAmbiente $ambiente = NfseAmbiente::PRODUCAO, ?DanfseParticipante $interm = null): NfseData
{
    return new NfseData(
        chaveAcesso: '3303302112233450000195000000000000100000000001',
        numeroNfse: '10', competencia: '15/01/2026', emissaoNfse: '15/01/2026 14:30:00',
        numeroDps: '5', serieDps: '20261', emissaoDps: '15/01/2026 14:00:00',
        ambiente: $ambiente,
        emitente: sampleParticipante('EMITENTE LTDA'),
        tomador: sampleParticipante('TOMADOR S.A.'),
        intermediario: $interm,
        servico: new DanfseServico(
            codigoTribNacional: '01.07.00', descTribNacional: 'Desenvolvimento de software',
            codigoTribMunicipal: '007', descTribMunicipal: 'Desenvolvimento',
            localPrestacao: 'São Paulo', paisPrestacao: '-', descricao: 'Projeto Pulsar',
        ),
        tribMun: new DanfseTributacaoMunicipal(
            tributacaoIssqn: 'Operação Tributável', municipioIncidencia: 'São Paulo - SP',
            regimeEspecial: 'Nenhum', valorServico: 'R$ 1.500,00', bcIssqn: 'R$ 1.350,00',
            aliquota: '2.00%', retencaoIssqn: 'Retido pelo Tomador', issqnApurado: 'R$ 27,00',
        ),
        tribFed: new DanfseTributacaoFederal(
            irrf: 'R$ 22,50', cp: 'R$ 15,00', csll: '-', pis: 'R$ 9,75', cofins: 'R$ 45,00',
        ),
        totais: new DanfseTotais(
            valorServico: 'R$ 1.500,00', descontoCondicionado: '-', descontoIncondicionado: '-',
            issqnRetido: 'R$ 27,00', retencoesFederais: 'R$ 52,50', pisCofins: 'R$ 54,75',
            valorLiquido: 'R$ 1.292,75',
        ),
        totaisTributos: new DanfseTotaisTributos(federais: '4.50%', estaduais: '0.10%', municipais: '2.00%'),
        informacoesComplementares: 'Referente ao contrato 2026-001',
    );
}

function stubBuilder(NfseData|Throwable $result): BuildsDanfseData
{
    return new class($result) implements BuildsDanfseData
    {
        public function __construct(private NfseData|Throwable $result) {}

        public function build(string $xmlNfse): NfseData
        {
            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

function stubHtmlRenderer(string|Throwable $html = '<html>OK</html>'): RendersDanfseHtml
{
    return new class($html) implements RendersDanfseHtml
    {
        public function __construct(private string|Throwable $html) {}

        public function render(NfseData $data): string
        {
            if ($this->html instanceof Throwable) {
                throw $this->html;
            }

            return $this->html;
        }
    };
}

function stubPdfConverter(string|Throwable $pdf = '%PDF-1.4'): ConvertsHtmlToPdf
{
    return new class($pdf) implements ConvertsHtmlToPdf
    {
        public function __construct(private string|Throwable $pdf) {}

        public function convert(string $html): string
        {
            if ($this->pdf instanceof Throwable) {
                throw $this->pdf;
            }

            return $this->pdf;
        }
    };
}
