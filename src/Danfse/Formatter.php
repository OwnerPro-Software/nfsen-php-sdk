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

        // Máscara `nn.nnn-nnn` do exemplo da tabela do item 2.4.5, não a `nnnnn-nnn` de uso
        // corrente: a linha "CÓDIGO IBGE / CEP" traz `nnnnnnn / nn.nnn-nnn`.
        if (strlen($digits) === 8) {
            return (string) preg_replace('/(\d{2})(\d{3})(\d{3})/', '$1.$2-$3', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
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

    /**
     * Percentual com o separador decimal brasileiro, no qual o resto do documento já
     * escreve os valores monetários.
     *
     * Preserva as casas que o XML trouxe em vez de fixar duas: `pAliq` vem com duas,
     * mas as alíquotas de IBS/CBS admitem mais, e reformatar inventaria ou perderia
     * precisão de um campo fiscal.
     */
    public function percent(string $value): string
    {
        return str_replace('.', ',', $value).'%';
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

    /** Máscara `n.nnnn.nn.nn` que a tabela do item 2.4.5 dá ao código da NBS. */
    public function codNbs(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value); // @pest-mutate-ignore RemoveStringCast — defensivo.

        if (strlen($digits) === 9) {
            return (string) preg_replace('/(\d)(\d{4})(\d{2})(\d{2})/', '$1.$2.$3.$4', $digits); // @pest-mutate-ignore RemoveStringCast — defensivo.
        }

        return $digits;
    }

    /** Junta com " / " os pedaços presentes, ou '-' quando nenhum veio. */
    public function joinPresent(string ...$partes): string
    {
        $preenchidos = array_filter($partes, $this->preenchido(...));

        return $preenchidos !== [] ? implode(' / ', $preenchidos) : '-';
    }

    /**
     * Junta com " / " campos que a tabela do item 2.4.5 descreve por máscara posicional
     * — `nnn / nnnnnn`, `% / %`, `% / % / %` —, preenchendo com traço a posição vazia,
     * como manda a nota 12.
     *
     * Descartar a posição vazia deslocaria as demais: numa redução de alíquota só da
     * CBS, um `1,00%` solitário seria lido como redução do IBS estadual, que é a
     * primeira posição da máscara. O campo inteiro continua virando '-' quando nenhuma
     * posição veio, para a NFS-e anterior à reforma não sair com "- / - / -".
     */
    public function joinSlots(string ...$partes): string
    {
        if (array_filter($partes, $this->preenchido(...)) === []) {
            return '-';
        }

        return implode(' / ', array_map($this->ouTraco(...), $partes));
    }

    private function preenchido(string $parte): bool
    {
        return $parte !== '' && $parte !== '-';
    }

    private function ouTraco(string $parte): string
    {
        return $parte !== '' ? $parte : '-';
    }

    public function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $cut = mb_substr($value, 0, $limit);
        $lastSpace = mb_strrpos($cut, ' ');

        // Recuar até o espaço evita partir palavra ao meio, mas só vale quando o recuo
        // é curto. Num texto quase sem espaços — uma chave de acesso, um código longo,
        // um rótulo seguido de dado contínuo — o último espaço pode estar no começo, e
        // recuar até ele devolveria o rótulo sozinho no lugar do campo inteiro.
        if ($lastSpace !== false && $lastSpace >= (int) ($limit * 0.9)) { // @pest-mutate-ignore GreaterThanOrEqualToGreaterThan,DecrementInteger,IncrementInteger — piso de recuo aceitável; um caractere a mais ou a menos não é regressão.
            return mb_substr($cut, 0, $lastSpace).$end;
        }

        return $cut.$end;
    }
}
