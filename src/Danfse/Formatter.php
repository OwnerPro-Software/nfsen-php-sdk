<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use DateTimeImmutable;
use Exception;

/**
 * Formatadores para padrões brasileiros (CNPJ, CPF, telefone, CEP, moeda, datas).
 *
 * Portado de andrevabo/danfse-nacional (https://github.com/andrevabo/danfse-nacional) — MIT.
 *
 * @api
 */
final class Formatter
{
    public function cnpjCpf(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value); // @pest-mutate-ignore RemoveStringCast — preg_replace retorna ?string; cast defensivo.

        if (strlen($digits) === 14) {
            return (string) preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        if (strlen($digits) === 11) {
            return (string) preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        return $digits;
    }

    public function phone(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value); // @pest-mutate-ignore RemoveStringCast — defensivo.

        if (strlen($digits) === 11) {
            return (string) preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        if (strlen($digits) === 10) {
            return (string) preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        return $digits;
    }

    public function cep(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value); // @pest-mutate-ignore RemoveStringCast — defensivo.

        if (strlen($digits) === 8) {
            return (string) preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        return $digits;
    }

    public function date(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y');
        } catch (Exception) {
            return $value;
        }
    }

    public function dateTime(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y H:i:s');
        } catch (Exception) {
            return $value;
        }
    }

    public function currency(string|float $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public function codTribNacional(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value); // @pest-mutate-ignore RemoveStringCast — defensivo.

        if (strlen($digits) === 6) {
            return (string) preg_replace('/(\d{2})(\d{2})(\d{2})/', '$1.$2.$3', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        return $digits;
    }

    public function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit).$end;
    }
}
