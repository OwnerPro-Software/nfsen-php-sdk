<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Enums\MarcaDagua;

covers(MarcaDagua::class);

it('imprime o texto exigido pelo item 2.5.1 para NFS-e cancelada', function (): void {
    expect(MarcaDagua::Cancelada->texto())->toBe('CANCELADA');
});

it('imprime o texto exigido pelo item 2.5.2 para NFS-e substituída, com acento', function (): void {
    expect(MarcaDagua::Substituida->texto())->toBe('SUBSTITUÍDA');
});
