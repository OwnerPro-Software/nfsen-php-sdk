<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO;

use OwnerPro\Nfsen\Dps\DTO\IBSCBS\IBSCBS;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\InfDPS;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Dps\DTO\Prest\Prest;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-import-type InfDPSArray from InfDPS
 * @phpstan-import-type PrestArray from Prest
 * @phpstan-import-type ServArray from Serv
 * @phpstan-import-type ValoresArray from Valores
 * @phpstan-import-type SubstArray from Subst
 * @phpstan-import-type TomaArray from Toma
 * @phpstan-import-type IBSCBSArray from IBSCBS
 *
 * @phpstan-type DpsDataArray array{infDPS: InfDPSArray, prest: PrestArray, serv: ServArray, valores: ValoresArray, subst?: SubstArray, toma?: TomaArray, interm?: TomaArray, IBSCBS?: IBSCBSArray}
 */
final readonly class DpsData
{
    public function __construct(
        public InfDPS $infDPS,
        public Prest $prest,
        public Serv $serv,
        public Valores $valores,
        public ?Subst $subst = null,
        public ?Toma $toma = null,
        public ?Toma $interm = null,
        public ?IBSCBS $IBSCBS = null,
    ) {}

    /** @phpstan-param DpsDataArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            infDPS: InfDPS::fromArray($data['infDPS']),
            prest: Prest::fromArray($data['prest']),
            serv: Serv::fromArray($data['serv']),
            valores: Valores::fromArray($data['valores']),
            subst: isset($data['subst']) ? Subst::fromArray($data['subst']) : null,
            toma: isset($data['toma']) ? Toma::fromArray($data['toma'], path: 'infDPS/toma') : null,
            interm: isset($data['interm']) ? Toma::fromArray($data['interm'], path: 'infDPS/interm') : null,
            IBSCBS: isset($data['IBSCBS']) ? IBSCBS::fromArray($data['IBSCBS']) : null,
        );
    }

    /**
     * Inscrição federal de quem emite esta DPS, que `tpEmit` designa.
     *
     * O emitente nem sempre é o prestador: `TSEmitenteDPS` admite que o tomador (2)
     * ou o intermediário (3) emitam a DPS. E é do emitente que saem a série e o
     * número — cada um controla a própria sequência. Sem a inscrição dele no
     * identificador, dois emitentes distintos que usem a mesma série e o mesmo
     * número chegam ao mesmo `Id`, e a chave deixa de ser única.
     *
     * O `xs:choice` de `TCInfoPessoa`/`TCInfoPrestador` garante que uma das quatro
     * identificações veio; quando é NIF ou cNaoNIF, ambas saem null e o chamador
     * decide o que fazer com a ausência.
     *
     * `toma` e `interm` são o mesmo `TCInfoPessoa` no XSD, e por isso compartilham
     * o DTO {@see Toma} — não há um tipo próprio de intermediário.
     *
     * @return array{cnpj: ?string, cpf: ?string}
     */
    public function emitterIdentity(): array
    {
        $emitter = match ($this->infDPS->tpEmit) {
            TpEmit::Prestador => $this->prest,
            TpEmit::Tomador => $this->toma,
            TpEmit::Intermediario => $this->interm,
        };

        // Só o prestador é obrigatório: `toma` e `interm` são minOccurs=0, então o
        // XSD não consegue exigir o grupo que tpEmit designou. Sem ele não há
        // inscrição alguma a informar, e o identificador sairia zerado — igual ao
        // de todos os outros na mesma situação.
        //
        // Daí a mensagem só precisar escolher entre 'toma' e 'interm': com tpEmit=1
        // o emitente é `$this->prest`, que o construtor exige e nunca é null.
        if ($emitter === null) {
            throw new InvalidDpsArgument(sprintf(
                'infDPS/tpEmit indica que o %s emite a DPS, mas o grupo infDPS/%s não foi informado. '
                .'O identificador da DPS (TSIdDPS) é formado com a inscrição federal do emitente.',
                $this->infDPS->tpEmit->label(),
                $this->infDPS->tpEmit === TpEmit::Tomador ? 'toma' : 'interm',
            ));
        }

        return ['cnpj' => $emitter->CNPJ, 'cpf' => $emitter->CPF];
    }
}
