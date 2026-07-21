<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TpImunidade: string
{
    use HasLabelOf;

    case NaoInformado = '0';
    case PatrimonioRendaServicos = '1';
    case TemploQualquerCulto = '2';
    case PartidosSindicaisEducacao = '3';
    case LivrosJornaisPeriodicos = '4';
    case FonogramasVideofonogramas = '5';

    /**
     * Rótulos transcritos do `<xs:documentation>` de `TSTipoImunidadeISSQN`.
     *
     * São longos de propósito — citam o dispositivo constitucional. O corte para o
     * DANFSe (37 caracteres, NT 008) é do builder, não daqui: o rótulo íntegro
     * também serve a quem consome o SDK fora da impressão.
     */
    public function label(): string
    {
        return match ($this) {
            self::NaoInformado => 'Imunidade (tipo não informado na nota de origem)',
            self::PatrimonioRendaServicos => 'Patrimônio, renda ou serviços, uns dos outros (CF88, Art 150, VI, a)',
            self::TemploQualquerCulto => 'Templos de qualquer culto (CF88, Art 150, VI, b)',
            self::PartidosSindicaisEducacao => 'Patrimônio, renda ou serviços dos partidos políticos, inclusive suas fundações, das entidades sindicais dos trabalhadores, das instituições de educação e de assistência social, sem fins lucrativos, atendidos os requisitos da lei (CF88, Art 150, VI, c)',
            self::LivrosJornaisPeriodicos => 'Livros, jornais, periódicos e o papel destinado a sua impressão (CF88, Art 150, VI, d)',
            self::FonogramasVideofonogramas => 'Fonogramas e videofonogramas musicais produzidos no Brasil contendo obras musicais ou literomusicais de autores brasileiros e/ou obras em geral interpretadas por artistas brasileiros bem como os suportes materiais ou arquivos digitais que os contenham, salvo na etapa de replicação industrial de mídias ópticas de leitura a laser. (CF88, Art 150, VI, e)',
        };
    }
}
