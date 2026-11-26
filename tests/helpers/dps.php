<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Dest;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\DFeNacional;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Documentos;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GIBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GReeRepRes;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GTribRegular;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\IBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Imovel;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Trib as IbsCbsTrib;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Valores as IbsCbsValores;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Dps\DTO\Serv\ComExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\InfoCompl;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Shared\End;
use OwnerPro\Nfsen\Dps\DTO\Shared\EndNac;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\DTO\Valores\DocDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\Piscofins;
use OwnerPro\Nfsen\Dps\DTO\Valores\Trib;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribFed;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDescCondIncond;
use OwnerPro\Nfsen\Dps\DTO\Valores\VServPrest;
use OwnerPro\Nfsen\Dps\DTO\Valores\VTotTrib;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndDest;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndFinal;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TipoChaveDFe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TpReeRepRes;
use OwnerPro\Nfsen\Dps\Enums\Serv\Mdic;
use OwnerPro\Nfsen\Dps\Enums\Serv\MdPrestacao;
use OwnerPro\Nfsen\Dps\Enums\Serv\MecAFComexP;
use OwnerPro\Nfsen\Dps\Enums\Serv\MecAFComexT;
use OwnerPro\Nfsen\Dps\Enums\Serv\MovTempBens;
use OwnerPro\Nfsen\Dps\Enums\Serv\VincPrest;
use OwnerPro\Nfsen\Dps\Enums\Valores\CST;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpDedRed;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Enums\Leiaute;
use OwnerPro\Nfsen\Xml\DpsBuilder;

/**
 * DPS com todos os grupos que os dois leiautes aceitam. Os quatro parâmetros
 * acrescentam, um a um, os grupos que só o leiaute de produção aceita.
 */
function makeDpsNucleo(
    ?VDedRed $vDedRed = null,
    ?ComExt $comExt = null,
    ?Imovel $imovel = null,
    ?GReeRepRes $gReeRepRes = null,
): DpsData {
    return new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Serv(
            cServ: new CServ(
                cTribNac: '010101',
                xDescServ: 'Desenvolvimento de sistema',
                cNBS: '123456789',
                cTribMun: '001',
                cIntContrib: 'INT01',
            ),
            cLocPrestacao: '3501608',
            comExt: $comExt,
            infoCompl: new InfoCompl(xInfComp: 'Informação complementar do serviço'),
        ),
        valores: new Valores(
            vServPrest: new VServPrest(vServ: '1000.00'),
            trib: new Trib(
                tribMun: new TribMun(
                    tribISSQN: TribISSQN::Tributavel,
                    tpRetISSQN: TpRetISSQN::NaoRetido,
                ),
                vTotTrib: new VTotTrib(vTotTribFed: '10.00', vTotTribEst: '5.00', vTotTribMun: '3.00'),
                tribFed: new TribFed(
                    piscofins: new Piscofins(
                        CST: CST::AliqBasica,
                        vBCPisCofins: '1000.00',
                        pAliqPis: '0.65',
                        pAliqCofins: '3.00',
                        vPis: '6.50',
                        vCofins: '30.00',
                    ),
                ),
            ),
            vDescCondIncond: new VDescCondIncond(vDescIncond: '10.00'),
            vDedRed: $vDedRed ?? new VDedRed(pDR: '10.00'),
        ),
        subst: new Subst(
            chSubstda: makeChaveAcesso(),
            cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        ),
        toma: new Toma(xNome: 'Tomador Ltda', CNPJ: '11222333000181'),
        interm: new Toma(xNome: 'Intermediária Ltda', CNPJ: '99888777000166', path: 'infDPS/interm'),
        IBSCBS: new IBSCBS(
            finNFSe: FinNFSe::Regular,
            cIndOp: '010101',
            indDest: IndDest::OutraPessoa,
            valores: new IbsCbsValores(
                trib: new IbsCbsTrib(
                    gIBSCBS: new GIBSCBS(
                        CST: '000',
                        cClassTrib: '000001',
                        gTribRegular: new GTribRegular(CSTReg: '000', cClassTribReg: '000001'),
                    ),
                ),
                gReeRepRes: $gReeRepRes,
            ),
            indFinal: IndFinal::Nao,
            dest: new Dest(
                xNome: 'Destinatária S.A.',
                CNPJ: '55666777000188',
                end: new End(
                    xLgr: 'Rua das Flores',
                    nro: '100',
                    xBairro: 'Centro',
                    endNac: new EndNac(cMun: '3501608', CEP: '13465000'),
                ),
                email: 'destinataria@example.com',
            ),
            imovel: $imovel,
        ),
    );
}

function makeComExt(): ComExt
{
    return new ComExt(
        mdPrestacao: MdPrestacao::Transfronteirico,
        vincPrest: VincPrest::SemVinculo,
        tpMoeda: '790',
        vServMoeda: '500.00',
        mecAFComexP: MecAFComexP::PROEXFinanciamento,
        mecAFComexT: MecAFComexT::PromocaoBrasilExterior,
        movTempBens: MovTempBens::Desconhecido,
        mdic: Mdic::NaoEnviar,
    );
}

function makeImovel(): Imovel
{
    return new Imovel(cCIB: '12345678');
}

function makeGReeRepRes(): GReeRepRes
{
    return new GReeRepRes(documentos: [
        new Documentos(
            dtEmiDoc: '2026-01-15',
            dtCompDoc: '2026-01-15',
            tpReeRepRes: TpReeRepRes::RepasseImoveis,
            vlrReeRepRes: '50.00',
            dFeNacional: new DFeNacional(tipoChaveDFe: TipoChaveDFe::NFSe, chaveDFe: makeChaveAcesso()),
        ),
    ]);
}

function makeVDedRedDocumentos(): VDedRed
{
    return new VDedRed(documentos: [
        new DocDedRed(
            tpDedRed: TpDedRed::Materiais,
            dtEmiDoc: '2026-01-15',
            vDedutivelRedutivel: '100.00',
            vDeducaoReducao: '50.00',
            chNFSe: makeChaveAcesso(),
        ),
    ]);
}

/** O núcleo mais os quatro grupos que o leiaute da NT 009 reestruturou. */
function makeDpsCompleta(): DpsData
{
    return makeDpsNucleo(
        vDedRed: makeVDedRedDocumentos(),
        comExt: makeComExt(),
        imovel: makeImovel(),
        gReeRepRes: makeGReeRepRes(),
    );
}

function dpsXPath(string $xml): DOMXPath
{
    $document = new DOMDocument;
    $document->loadXML($xml);

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

/** @return list<string> */
function childNames(string $xml, string $xpath): array
{
    $names = [];
    foreach (dpsXPath($xml)->query($xpath.'/*') ?: [] as $child) {
        $names[] = (string) $child->localName;
    }

    return $names;
}

function nt009Xml(DpsData $data): string
{
    return (new DpsBuilder(makeXsdValidator(Leiaute::Nt009), Leiaute::Nt009))->buildAndValidate($data);
}

function xpathValue(string $xml, string $xpath): string
{
    return (string) dpsXPath($xml)->evaluate('string('.$xpath.')');
}

function nameAfter(string $xml, string $parentXpath, string $child): ?string
{
    $names = childNames($xml, $parentXpath);
    $index = array_search($child, $names, true);

    return $index === false ? null : $names[$index + 1] ?? null;
}

function sentDpsXml(): string
{
    $request = Http::recorded()->first()[0];

    return (string) gzdecode((string) base64_decode((string) $request['dpsXmlGZipB64'], true));
}

/** DPS cujo cClassTrib não indica tributação de IBS/CBS a declarar (ind_gIBSCBS = 0). */
function makeDpsSemTributacaoIbsCbs(?GTribRegular $gTribRegular = null): DpsData
{
    return new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        IBSCBS: new IBSCBS(
            finNFSe: FinNFSe::Regular,
            cIndOp: null,
            indDest: IndDest::Tomador,
            valores: new IbsCbsValores(trib: new IbsCbsTrib(
                gIBSCBS: new GIBSCBS(CST: '410', cClassTrib: '410001', gTribRegular: $gTribRegular),
            )),
        ),
    );
}
