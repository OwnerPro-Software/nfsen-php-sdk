<?php

use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Danfse\ParticipanteBuilder;
use OwnerPro\Nfsen\Enums\Leiaute;
use OwnerPro\Nfsen\NfsenClient;

covers(DanfseDataBuilder::class, ParticipanteBuilder::class);

/** Aplica a variação e exige que o resultado continue válido no XSD da NT 009. */
function nt009NfseVariant(string $xml, string $pattern, string $replacement): string
{
    $variant = (string) preg_replace($pattern, $replacement, $xml, 1, $count);
    expect($count)->toBe(1, 'A variação não encontrou o trecho: '.$pattern);

    $fragment = (string) preg_replace('|^<\?xml.*?\?>\s*|', '', $variant);
    makeXsdValidator(Leiaute::Nt009)->validate($fragment, 'NFSe_v1.01.xsd');

    return $variant;
}

beforeEach(function () {
    $this->builder = new DanfseDataBuilder;
    $this->v101 = $this->builder->build((string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-ibscbs.xml'));
    $this->xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-ibscbs-nt009.xml');
    $this->nt009 = $this->builder->build($this->xml);
});

it('reads the repositioned fields exactly as it reads them in the production layout', function () {
    expect($this->nt009->finalidade)->toBe($this->v101->finalidade)->not->toBe('-');
    expect($this->nt009->tribIbsCbs->cstClassTrib)->toBe($this->v101->tribIbsCbs->cstClassTrib)->not->toBe('-');
    expect($this->nt009->destinatario)->toEqual($this->v101->destinatario)->not->toBeNull();
    expect($this->nt009->destinatarioEhTomador)->toBeFalse();
    expect($this->nt009->servico)->toEqual($this->v101->servico);
});

it('recognises the taker as recipient when infDPS/indDest is 0', function () {
    $xml = nt009NfseVariant($this->xml, '|<indDest>1</indDest>\s*<dest>.*?</dest>|s', '<indDest>0</indDest>');

    $data = $this->builder->build($xml);

    expect($data->destinatarioEhTomador)->toBeTrue();
    expect($data->destinatario)->toBeNull();
});

it('adds the declared and the calculated ISSQN adjustments, leaving the IBS/CBS one out', function () {
    expect($this->nt009->tribMun->totalDeducoesReducoes)->toBe('R$ 50,00');
});

it('prints only the calculated ISSQN adjustment when none was declared', function () {
    $xml = nt009NfseVariant($this->xml, '|<vAjusteBC>.*?</vAjusteBC>\s*|s', '');

    expect($this->builder->build($xml)->tribMun->totalDeducoesReducoes)->toBe('R$ 30,00');
});

it('prints only the declared ISSQN adjustment when none was calculated', function () {
    $xml = nt009NfseVariant($this->xml, '|<vCalcAjusteBCISSQN>30.00</vCalcAjusteBCISSQN>\s*|', '');

    expect($this->builder->build($xml)->tribMun->totalDeducoesReducoes)->toBe('R$ 20,00');
});

it('counts vCalcAjusteBCIBSCBS among the IBS/CBS base exclusions', function () {
    expect($this->nt009->tribIbsCbs->exclusoesReducoes)->toBe($this->v101->tribIbsCbs->exclusoesReducoes)->toBe('R$ 231,75');
});

it('counts vCalcAjusteBCLocImoveis among the IBS/CBS base exclusions', function () {
    $xml = nt009NfseVariant(
        $this->xml,
        '|<vCalcAjusteBCIBSCBS>50.00</vCalcAjusteBCIBSCBS>|',
        '<vCalcAjusteBCLocImoveis>50.00</vCalcAjusteBCLocImoveis>',
    );

    expect($this->builder->build($xml)->tribIbsCbs->exclusoesReducoes)->toBe('R$ 231,75');
});

it('prints a dash for a taker without a name', function () {
    $xml = nt009NfseVariant($this->xml, '|(<toma>.*?)<xNome>[^<]*</xNome>\s*|s', '$1');

    expect($this->builder->build($xml)->tomador?->nome)->toBe('-');
});

it('prints dashes when the NFS-e has no IBS/CBS values or totals', function () {
    $xml = nt009NfseVariant($this->xml, '|\n            <valores>.*?\n            </valores>|s', '');
    $xml = nt009NfseVariant($xml, '|<gIBS>.*?</gIBS>\s*|s', '');
    $xml = nt009NfseVariant($xml, '|<gCBS>.*?</gCBS>\s*|s', '');

    $data = $this->builder->build($xml);

    expect($data->tribIbsCbs->baseCalculo)->toBe('-');
    expect($data->tribIbsCbs->valorTotalIbs)->toBe('-');
    expect($data->tribIbsCbs->valorTotalCbs)->toBe('-');
});

it('renders the NT 009 NFS-e to a PDF', function () {
    $response = NfsenClient::forStandalone(makePfxContent(), 'secret', '9999999')->danfse()->toPdf($this->xml);

    expect($response->sucesso)->toBeTrue();
});

it('reads an NFS-e that declares neither cIndOp nor gIBSCBS', function () {
    $xml = nt009NfseVariant($this->xml, '|<cIndOp>110001</cIndOp>\s*|', '');
    $xml = nt009NfseVariant($xml, '|<gIBSCBS/>\s*|', '');

    $data = $this->builder->build($xml);

    expect($data->tribIbsCbs->cstClassTrib)->toBe($this->nt009->tribIbsCbs->cstClassTrib);
    expect($data->tribIbsCbs->indicadorOperacao)->not->toContain('110001');
});
