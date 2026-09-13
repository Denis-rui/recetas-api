<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class IdentificadorEntero implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::esValido($value)) {
            $fail('El campo :attribute debe ser un identificador entero positivo válido.');
        }
    }

    public static function esValido(mixed $valor): bool
    {
        if (is_int($valor)) {
            return $valor > 0;
        }

        if (! is_string($valor) || preg_match('/\A[1-9][0-9]*\z/', $valor) !== 1) {
            return false;
        }

        $maximo = (string) PHP_INT_MAX;

        return strlen($valor) < strlen($maximo)
            || (strlen($valor) === strlen($maximo) && strcmp($valor, $maximo) <= 0);
    }
}
