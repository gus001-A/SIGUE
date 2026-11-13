<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El registro existe y es visible para el usuario en la institución y plantel
 * activos (aplica los scopes globales del modelo). Impide afectar registros de
 * otro plantel enviando su ID.
 */
class Visible implements ValidationRule
{
    public function __construct(private string $model, private ?\Closure $where = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $q = $this->model::query()->whereKey($value);
        if ($this->where) {
            ($this->where)($q);
        }
        if (! $q->exists()) {
            $fail('El registro seleccionado no pertenece al plantel activo.');
        }
    }
}
