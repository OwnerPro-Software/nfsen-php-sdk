<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Dest;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DFeNacional;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocFiscalOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Documentos;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Fornec;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GDif;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GReeRepRes;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GTribRegular;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\IBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Imovel;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Valores;
use Pulsar\NfseNacional\Dps\DTO\Serv\EndExt;
use Pulsar\NfseNacional\Dps\DTO\Serv\EndObra;
use Pulsar\NfseNacional\Dps\DTO\Shared\End;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndFinal;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpEnteGov;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpOper;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;

final class IBSCBSBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, IBSCBS $ibscbs): DOMElement
    {
        $el = $doc->createElement('IBSCBS');

        $el->appendChild($this->text($doc, 'finNFSe', $ibscbs->finNFSe->value));

        if ($ibscbs->indFinal instanceof IndFinal) {
            $el->appendChild($this->text($doc, 'indFinal', $ibscbs->indFinal->value));
        }

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

        if ($ibscbs->dest instanceof Dest) {
            $el->appendChild($this->buildDest($doc, $ibscbs->dest));
        }

        if ($ibscbs->imovel instanceof Imovel) {
            $el->appendChild($this->buildImovel($doc, $ibscbs->imovel));
        }

        $el->appendChild($this->buildValores($doc, $ibscbs->valores));

        return $el;
    }

    private function buildDest(DOMDocument $doc, Dest $dest): DOMElement
    {
        $el = $doc->createElement('dest');

        if ($dest->CNPJ !== null) {
            $el->appendChild($this->text($doc, 'CNPJ', $dest->CNPJ));
        } elseif ($dest->CPF !== null) {
            $el->appendChild($this->text($doc, 'CPF', $dest->CPF));
        } elseif ($dest->NIF !== null) {
            $el->appendChild($this->text($doc, 'NIF', $dest->NIF));
        } elseif ($dest->cNaoNIF instanceof CNaoNIF) { // @pest-mutate-ignore InstanceOfToTrue unkillable — validation guarantees exactly one ID
            $el->appendChild($this->text($doc, 'cNaoNIF', $dest->cNaoNIF->value));
        }

        $el->appendChild($this->text($doc, 'xNome', $dest->xNome));

        if ($dest->end instanceof End) {
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

    private function buildImovel(DOMDocument $doc, Imovel $imovel): DOMElement
    {
        $el = $doc->createElement('imovel');

        if ($imovel->inscImobFisc !== null) {
            $el->appendChild($this->text($doc, 'inscImobFisc', $imovel->inscImobFisc));
        }

        if ($imovel->cCIB !== null) {
            $el->appendChild($this->text($doc, 'cCIB', $imovel->cCIB));
        } elseif ($imovel->end instanceof EndObra) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already EndObra when reached
            $endEl = $doc->createElement('end');
            if ($imovel->end->CEP !== null) {
                $endEl->appendChild($this->text($doc, 'CEP', $imovel->end->CEP));
            } elseif ($imovel->end->endExt instanceof EndExt) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already the expected type when reached
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

    private function buildValores(DOMDocument $doc, Valores $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        if ($valores->gReeRepRes instanceof GReeRepRes) {
            $gReeRepRes = $doc->createElement('gReeRepRes');
            foreach ($valores->gReeRepRes->documentos as $documento) {
                $gReeRepRes->appendChild($this->buildDocReeRepRes($doc, $documento));
            }

            $el->appendChild($gReeRepRes);
        }

        $el->appendChild($this->buildTrib($doc, $valores->trib->gIBSCBS));

        return $el;
    }

    private function buildDocReeRepRes(DOMDocument $doc, Documentos $documento): DOMElement
    {
        $el = $doc->createElement('documentos');

        if ($documento->dFeNacional instanceof DFeNacional) {
            $dfe = $doc->createElement('dFeNacional');
            $dfe->appendChild($this->text($doc, 'tipoChaveDFe', $documento->dFeNacional->tipoChaveDFe->value));
            if ($documento->dFeNacional->xTipoChaveDFe !== null) {
                $dfe->appendChild($this->text($doc, 'xTipoChaveDFe', $documento->dFeNacional->xTipoChaveDFe));
            }

            $dfe->appendChild($this->text($doc, 'chaveDFe', $documento->dFeNacional->chaveDFe));
            $el->appendChild($dfe);
        } elseif ($documento->docFiscalOutro instanceof DocFiscalOutro) {
            $docFisc = $doc->createElement('docFiscalOutro');
            $docFisc->appendChild($this->text($doc, 'cMunDocFiscal', $documento->docFiscalOutro->cMunDocFiscal));
            $docFisc->appendChild($this->text($doc, 'nDocFiscal', $documento->docFiscalOutro->nDocFiscal));
            $docFisc->appendChild($this->text($doc, 'xDocFiscal', $documento->docFiscalOutro->xDocFiscal));
            $el->appendChild($docFisc);
        } elseif ($documento->docOutro instanceof DocOutro) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already DocOutro when reached
            $docOutro = $doc->createElement('docOutro');
            $docOutro->appendChild($this->text($doc, 'nDoc', $documento->docOutro->nDoc));
            $docOutro->appendChild($this->text($doc, 'xDoc', $documento->docOutro->xDoc));
            $el->appendChild($docOutro);
        }

        if ($documento->fornec instanceof Fornec) {
            $fornec = $doc->createElement('fornec');
            if ($documento->fornec->CNPJ !== null) {
                $fornec->appendChild($this->text($doc, 'CNPJ', $documento->fornec->CNPJ));
            } elseif ($documento->fornec->CPF !== null) {
                $fornec->appendChild($this->text($doc, 'CPF', $documento->fornec->CPF));
            } elseif ($documento->fornec->NIF !== null) {
                $fornec->appendChild($this->text($doc, 'NIF', $documento->fornec->NIF));
            } elseif ($documento->fornec->cNaoNIF instanceof CNaoNIF) { // @pest-mutate-ignore InstanceOfToTrue unkillable — validation guarantees exactly one ID
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

    private function buildTrib(DOMDocument $doc, GIBSCBS $sitClas): DOMElement
    {
        $trib = $doc->createElement('trib');
        $gIBSCBS = $doc->createElement('gIBSCBS');

        $gIBSCBS->appendChild($this->text($doc, 'CST', $sitClas->CST));
        $gIBSCBS->appendChild($this->text($doc, 'cClassTrib', $sitClas->cClassTrib));

        if ($sitClas->cCredPres !== null) {
            $gIBSCBS->appendChild($this->text($doc, 'cCredPres', $sitClas->cCredPres));
        }

        if ($sitClas->gTribRegular instanceof GTribRegular) {
            $gTribRegular = $doc->createElement('gTribRegular');
            $gTribRegular->appendChild($this->text($doc, 'CSTReg', $sitClas->gTribRegular->CSTReg));
            $gTribRegular->appendChild($this->text($doc, 'cClassTribReg', $sitClas->gTribRegular->cClassTribReg));
            $gIBSCBS->appendChild($gTribRegular);
        }

        if ($sitClas->gDif instanceof GDif) {
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
