<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseTotaisTributos
{
    public function __construct(
        public string $federais,
        public string $estaduais,
        public string $municipais,
    ) {}

    /**
     * Linha fixa da nota 10 do item 2.4.5, impressa dentro de "Informações
     * Complementares".
     *
     * A NT a chama de fixa e a declara obrigatória: o texto e a pontuação vêm
     * transcritos da nota, e é por ser fixa que o corte das informações
     * complementares não a alcança.
     */
    public function linhaNt008(): string
    {
        return sprintf(
            'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: %s ; Estaduais: %s ; Municipais: %s',
            $this->federais,
            $this->estaduais,
            $this->municipais,
        );
    }
}
