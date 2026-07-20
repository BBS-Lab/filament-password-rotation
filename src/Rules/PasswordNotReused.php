<?php

declare(strict_types=1);

namespace BBSLab\FilamentPasswordRotation\Rules;

use BBSLab\FilamentPasswordRotation\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordNotReused implements ValidationRule
{
    public function __construct(
        private Model $user,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PasswordHistory::isReused($this->user, $value)) {
            $fail('filament-password-rotation::validation.reused')->translate();
        }
    }
}
