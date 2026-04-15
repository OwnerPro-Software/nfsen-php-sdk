<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;

covers(TribISSQN::class, HasLabelOf::class);

it('labelOf returns expected labels', function () {
    expect(TribISSQN::labelOf('1'))->toBe('Operação Tributável');
    expect(TribISSQN::labelOf('2'))->toBe('Imunidade');
    expect(TribISSQN::labelOf('3'))->toBe('Exportação de Serviço');
    expect(TribISSQN::labelOf('4'))->toBe('Não Incidência');
});

it('labelOf returns dash for null/unknown', function () {
    expect(TribISSQN::labelOf(null))->toBe('-');
    expect(TribISSQN::labelOf('99'))->toBe('-');
});
