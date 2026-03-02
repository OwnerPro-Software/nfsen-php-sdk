<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoDest;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoImovel;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosDif;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosSitClas;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosTribRegular;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoValoresIBSCBS;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocDFe;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocFiscalOutro;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocFornec;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocOutro;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocReeRepRes;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TpEnteGov;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TpOper;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

final class IBSCBSBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, InfoIBSCBS $ibscbs): DOMElement
    {
        $el = $doc->createElement('IBSCBS');

        $el->appendChild($this->text($doc, 'finNFSe', $ibscbs->finNFSe->value));
        $el->appendChild($this->text($doc, 'indFinal', $ibscbs->indFinal->value));
        $el->appendChild($this->text($doc, 'cIndOp', $ibscbs->cIndOp));

        if ($ibscbs->tpOper instanceof TpOper) {
            $el->appendChild($this->text($doc, 'tpOper', $ibscbs->tpOper->value));
        }

        if ($ibscbs->refNFSe !== null) {
            $gRefNFSe = $doc->createElement('gRefNFSe');
            foreach ($ibscbs->refNFSe as $ref) {
                $gRefNFSe->appendChild($this->text($doc, 'refNFSe', $ref));
            }

            $el->appendChild($gRefNFSe);
        }

        if ($ibscbs->tpEnteGov instanceof TpEnteGov) {
            $el->appendChild($this->text($doc, 'tpEnteGov', $ibscbs->tpEnteGov->value));
        }

        $el->appendChild($this->text($doc, 'indDest', $ibscbs->indDest->value));

        if ($ibscbs->dest instanceof InfoDest) {
            $el->appendChild($this->buildDest($doc, $ibscbs->dest));
        }

        if ($ibscbs->imovel instanceof InfoImovel) {
            $el->appendChild($this->buildImovel($doc, $ibscbs->imovel));
        }

        $el->appendChild($this->buildValores($doc, $ibscbs->valores));

        return $el;
    }

    private function buildDest(DOMDocument $doc, InfoDest $dest): DOMElement
    {
        $el = $doc->createElement('dest');

        if ($dest->CNPJ !== null) {
            $el->appendChild($this->text($doc, 'CNPJ', $dest->CNPJ));
        } elseif ($dest->CPF !== null) {
            $el->appendChild($this->text($doc, 'CPF', $dest->CPF));
        } elseif ($dest->NIF !== null) {
            $el->appendChild($this->text($doc, 'NIF', $dest->NIF));
        } elseif ($dest->cNaoNIF instanceof CodNaoNIF) {
            $el->appendChild($this->text($doc, 'cNaoNIF', $dest->cNaoNIF->value));
        }

        $el->appendChild($this->text($doc, 'xNome', $dest->xNome));

        if ($dest->end instanceof Endereco) {
            $el->appendChild($this->buildEnd($doc, $dest->end));
        }

        if ($dest->fone !== null) {
            $el->appendChild($this->text($doc, 'fone', $dest->fone));
        }

        if ($dest->email !== null) {
            $el->appendChild($this->text($doc, 'email', $dest->email));
        }

        return $el;
    }

    private function buildImovel(DOMDocument $doc, InfoImovel $imovel): DOMElement
    {
        $el = $doc->createElement('imovel');

        if ($imovel->inscImobFisc !== null) {
            $el->appendChild($this->text($doc, 'inscImobFisc', $imovel->inscImobFisc));
        }

        if ($imovel->cCIB !== null) {
            $el->appendChild($this->text($doc, 'cCIB', $imovel->cCIB));
        } elseif ($imovel->end instanceof EnderecoObra) {
            $endEl = $doc->createElement('end');
            if ($imovel->end->CEP !== null) {
                $endEl->appendChild($this->text($doc, 'CEP', $imovel->end->CEP));
            } elseif ($imovel->end->endExt instanceof EnderecoExteriorObra) {
                $endExt = $doc->createElement('endExt');
                $endExt->appendChild($this->text($doc, 'cEndPost', $imovel->end->endExt->cEndPost));
                $endExt->appendChild($this->text($doc, 'xCidade', $imovel->end->endExt->xCidade));
                $endExt->appendChild($this->text($doc, 'xEstProvReg', $imovel->end->endExt->xEstProvReg));
                $endEl->appendChild($endExt);
            }

            $endEl->appendChild($this->text($doc, 'xLgr', $imovel->end->xLgr));
            $endEl->appendChild($this->text($doc, 'nro', $imovel->end->nro));
            if ($imovel->end->xCpl !== null) {
                $endEl->appendChild($this->text($doc, 'xCpl', $imovel->end->xCpl));
            }

            $endEl->appendChild($this->text($doc, 'xBairro', $imovel->end->xBairro));
            $el->appendChild($endEl);
        }

        return $el;
    }

    private function buildValores(DOMDocument $doc, InfoValoresIBSCBS $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        if ($valores->gReeRepRes instanceof InfoReeRepRes) {
            $gReeRepRes = $doc->createElement('gReeRepRes');
            foreach ($valores->gReeRepRes->documentos as $documento) {
                $gReeRepRes->appendChild($this->buildDocReeRepRes($doc, $documento));
            }

            $el->appendChild($gReeRepRes);
        }

        $el->appendChild($this->buildTrib($doc, $valores->trib->gIBSCBS));

        return $el;
    }

    private function buildDocReeRepRes(DOMDocument $doc, ListaDocReeRepRes $documento): DOMElement
    {
        $el = $doc->createElement('documentos');

        if ($documento->dFeNacional instanceof ListaDocDFe) {
            $dfe = $doc->createElement('dFeNacional');
            $dfe->appendChild($this->text($doc, 'tipoChaveDFe', $documento->dFeNacional->tipoChaveDFe->value));
            if ($documento->dFeNacional->xTipoChaveDFe !== null) {
                $dfe->appendChild($this->text($doc, 'xTipoChaveDFe', $documento->dFeNacional->xTipoChaveDFe));
            }

            $dfe->appendChild($this->text($doc, 'chaveDFe', $documento->dFeNacional->chaveDFe));
            $el->appendChild($dfe);
        } elseif ($documento->docFiscalOutro instanceof ListaDocFiscalOutro) {
            $docFisc = $doc->createElement('docFiscalOutro');
            $docFisc->appendChild($this->text($doc, 'cMunDocFiscal', $documento->docFiscalOutro->cMunDocFiscal));
            $docFisc->appendChild($this->text($doc, 'nDocFiscal', $documento->docFiscalOutro->nDocFiscal));
            $docFisc->appendChild($this->text($doc, 'xDocFiscal', $documento->docFiscalOutro->xDocFiscal));
            $el->appendChild($docFisc);
        } elseif ($documento->docOutro instanceof ListaDocOutro) {
            $docOutro = $doc->createElement('docOutro');
            $docOutro->appendChild($this->text($doc, 'nDoc', $documento->docOutro->nDoc));
            $docOutro->appendChild($this->text($doc, 'xDoc', $documento->docOutro->xDoc));
            $el->appendChild($docOutro);
        }

        if ($documento->fornec instanceof ListaDocFornec) {
            $fornec = $doc->createElement('fornec');
            if ($documento->fornec->CNPJ !== null) {
                $fornec->appendChild($this->text($doc, 'CNPJ', $documento->fornec->CNPJ));
            } elseif ($documento->fornec->CPF !== null) {
                $fornec->appendChild($this->text($doc, 'CPF', $documento->fornec->CPF));
            } elseif ($documento->fornec->NIF !== null) {
                $fornec->appendChild($this->text($doc, 'NIF', $documento->fornec->NIF));
            } elseif ($documento->fornec->cNaoNIF instanceof CodNaoNIF) {
                $fornec->appendChild($this->text($doc, 'cNaoNIF', $documento->fornec->cNaoNIF->value));
            }

            $fornec->appendChild($this->text($doc, 'xNome', $documento->fornec->xNome));
            $el->appendChild($fornec);
        }

        $el->appendChild($this->text($doc, 'dtEmiDoc', $documento->dtEmiDoc));
        $el->appendChild($this->text($doc, 'dtCompDoc', $documento->dtCompDoc));
        $el->appendChild($this->text($doc, 'tpReeRepRes', $documento->tpReeRepRes->value));

        if ($documento->xTpReeRepRes !== null) {
            $el->appendChild($this->text($doc, 'xTpReeRepRes', $documento->xTpReeRepRes));
        }

        $el->appendChild($this->text($doc, 'vlrReeRepRes', $documento->vlrReeRepRes));

        return $el;
    }

    private function buildTrib(DOMDocument $doc, InfoTributosSitClas $sitClas): DOMElement
    {
        $trib = $doc->createElement('trib');
        $gIBSCBS = $doc->createElement('gIBSCBS');

        $gIBSCBS->appendChild($this->text($doc, 'CST', $sitClas->CST));
        $gIBSCBS->appendChild($this->text($doc, 'cClassTrib', $sitClas->cClassTrib));

        if ($sitClas->cCredPres !== null) {
            $gIBSCBS->appendChild($this->text($doc, 'cCredPres', $sitClas->cCredPres));
        }

        if ($sitClas->gTribRegular instanceof InfoTributosTribRegular) {
            $gTribRegular = $doc->createElement('gTribRegular');
            $gTribRegular->appendChild($this->text($doc, 'CSTReg', $sitClas->gTribRegular->CSTReg));
            $gTribRegular->appendChild($this->text($doc, 'cClassTribReg', $sitClas->gTribRegular->cClassTribReg));
            $gIBSCBS->appendChild($gTribRegular);
        }

        if ($sitClas->gDif instanceof InfoTributosDif) {
            $gDif = $doc->createElement('gDif');
            $gDif->appendChild($this->text($doc, 'pDifUF', $sitClas->gDif->pDifUF));
            $gDif->appendChild($this->text($doc, 'pDifMun', $sitClas->gDif->pDifMun));
            $gDif->appendChild($this->text($doc, 'pDifCBS', $sitClas->gDif->pDifCBS));
            $gIBSCBS->appendChild($gDif);
        }

        $trib->appendChild($gIBSCBS);

        return $trib;
    }
}
