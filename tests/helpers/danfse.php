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
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoIbsCbs;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Enums\MarcaDagua;
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
        endereco: 'Rua X, 100, Centro', municipio: 'São Paulo / SP',
        cep: '01.310-100', codigoIbge: '3550308', simplesNacional: 'Não Optante', regimeSN: '-',
    );
}

function sampleData(NfseAmbiente $ambiente = NfseAmbiente::PRODUCAO, ?DanfseParticipante $interm = null, string $codigoNbs = '-', ?MarcaDagua $marcaDagua = null, string $municipioEmitente = 'Niterói / RJ'): NfseData
{
    return new NfseData(
        chaveAcesso: '33033021211222333000181000000000001026010000010000',
        numeroNfse: '10', competencia: '15/01/2026', emissaoNfse: '15/01/2026 14:30:00',
        numeroDps: '5', serieDps: '20261', emissaoDps: '15/01/2026 14:00:00',
        ambiente: $ambiente,
        situacao: 'NFS-e Gerada',
        finalidade: 'NFS-e regular',
        emitidaPor: 'Prestador', ambienteGerador: 'Sistema Nacional da NFS-e',
        municipioEmitente: $municipioEmitente,
        emitente: sampleParticipante('EMITENTE LTDA'),
        tomador: sampleParticipante('TOMADOR S.A.'),
        intermediario: $interm,
        destinatario: null,
        destinatarioEhTomador: false,
        servico: new DanfseServico(
            codigoTribNacional: '01.07.00', descTribNacional: 'Desenvolvimento de software',
            codigoTribMunicipal: '007', descTribMunicipal: 'Desenvolvimento',
            localPrestacao: 'São Paulo', paisPrestacao: '-', descricao: 'Projeto Pulsar',
            codigoNbs: $codigoNbs,
        ),
        tribMun: new DanfseTributacaoMunicipal(
            tributacaoIssqn: 'Operação Tributável', municipioIncidencia: 'São Paulo / SP',
            regimeEspecial: 'Nenhum', tipoImunidade: '-', suspensaoExigibilidade: '-',
            numeroProcessoSuspensao: '-', beneficioMunicipal: '-', calculoBM: '-',
            totalDeducoesReducoes: '-', exibeRegimeEImunidade: false, exibeBeneficioEDeducoes: false, bcIssqn: 'R$ 1.350,00',
            aliquota: '2,00%', retencaoIssqn: 'Retido pelo Tomador', issqnApurado: 'R$ 27,00',
        ),
        tribFed: new DanfseTributacaoFederal(
            irrf: 'R$ 22,50', cp: 'R$ 15,00', csll: '-', pis: 'R$ 9,75', cofins: 'R$ 45,00',
            descricaoContribuicoesRetidas: '-',
        ),
        tribIbsCbs: new DanfseTributacaoIbsCbs('-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-'),
        totais: new DanfseTotais(
            valorServico: 'R$ 1.500,00', descontoCondicionado: '-', descontoIncondicionado: '-',
            totalRetencoes: 'R$ 79,50',
            valorLiquido: 'R$ 1.292,75', totalIbsCbs: '-', valorLiquidoComIbsCbs: '-',
        ),
        totaisTributos: new DanfseTotaisTributos(federais: '4,50%', estaduais: '0,10%', municipais: '2,00%'),
        informacoesComplementares: 'Referente ao contrato 2026-001',
        marcaDagua: $marcaDagua,
    );
}

function stubBuilder(NfseData|Throwable $result): BuildsDanfseData
{
    return new class($result) implements BuildsDanfseData
    {
        public ?MarcaDagua $marcaRecebida = null;

        public function __construct(private NfseData|Throwable $result) {}

        public function build(string $xmlNfse, ?MarcaDagua $marcaDagua = null): NfseData
        {
            $this->marcaRecebida = $marcaDagua;

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

/**
 * NFS-e no limite: todos os blocos preenchidos e os dois campos livres no tamanho
 * máximo que a NT admite — 1300 caracteres de descrição e 2000 de informações
 * complementares, ambos em caixa alta, que é mais larga e quebra linha antes.
 *
 * O pior caso vem da especificação, não de um número escolhido a dedo. E cabe com os
 * limites da própria NT: nada de corte extra do SDK para forçar a página única.
 *
 * Parte da fixture com IBSCBS, não da nfse-autorizada: os blocos de destinatário e de
 * IBS/CBS só existem com o grupo da reforma, e montá-los aqui à mão produzia um XML
 * que o schema rejeitaria — era o caso, com o IBSCBS de infNFSe depois do DPS e sem o
 * finNFSe obrigatório. `DanfseFixtureSchemaTest` valida a fixture; um XML inventado
 * neste helper escaparia dessa guarda.
 *
 * Serve a dois testes por motivos diferentes: ao `DanfseSinglePageTest`, porque é o caso
 * que primeiro estoura a página; ao `Nt008TemplateCoverageTest`, porque é o único que traz
 * os blocos condicionais — destinatário e IBS/CBS — ao papel.
 */
function nfsenXmlNoLimite(): string
{
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-ibscbs.xml');

    // Imunidade, suspensão e benefício municipal enchem as duas linhas que a nota 5 do
    // item 2.4.5 deixa suprimir. Vão antes de tpRetISSQN, que é a ordem de TCTribMunicipal.
    $xml = str_replace(
        '<tpRetISSQN>',
        '<tpImunidade>5</tpImunidade>'
        .'<exigSusp><tpSusp>2</tpSusp><nProcesso>001234567202608190002000000000</nProcesso></exigSusp>'
        .'<BM><nBM>99999999999999</nBM><vRedBCBM>90.00</vRedBCBM></BM>'
        .'<tpRetISSQN>',
        $xml,
    );

    // rtrim: TSDesc2000 recusa texto terminado em espaço.
    $xml = (string) preg_replace(
        '|<xDescServ>[^<]*</xDescServ>|',
        '<xDescServ>'.rtrim(str_repeat('DESCRICAO EXTENSA DO SERVICO PRESTADO NO LIMITE DA NORMA. ', 23)).'</xDescServ>',
        $xml,
    );

    return (string) preg_replace(
        '|<xInfComp>[^<]*</xInfComp>|',
        '<xInfComp>'.rtrim(str_repeat('INFORMACAO COMPLEMENTAR RELEVANTE PARA O TOMADOR. ', 40)).'</xInfComp>',
        $xml,
    );
}
