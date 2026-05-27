<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

final class PrivatePasswordPolicy
{
    /**
     * @return array<int, string>
     */
    public function validate(string $password, ?string $confirmation = null): array
    {
        $errors = [];
        $password = trim($password);
        $confirmation = $confirmation !== null ? trim($confirmation) : null;
        $minimumLength = max(12, (int) app_config('private.password_min_length', 14));

        if ($password === '' || strlen($password) < $minimumLength) {
            $errors[] = 'password_length';
        }

        if ($confirmation !== null && !hash_equals($password, $confirmation)) {
            $errors[] = 'password_confirmation';
        }

        if ((bool) app_config('private.password_complexity_enabled', true)) {
            if (preg_match('/[a-z]/', $password) !== 1) {
                $errors[] = 'password_lowercase';
            }

            if (preg_match('/[A-Z]/', $password) !== 1) {
                $errors[] = 'password_uppercase';
            }

            if (preg_match('/[0-9]/', $password) !== 1) {
                $errors[] = 'password_digit';
            }

            if (preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
                $errors[] = 'password_symbol';
            }
        }

        return array_values(array_unique($errors));
    }

    public function errorMessage(array $errors): string
    {
        if (in_array('password_confirmation', $errors, true)) {
            return 'La confirmation du mot de passe ne correspond pas.';
        }

        return sprintf(
            'Le mot de passe doit contenir au moins %d caractères, avec majuscule, minuscule, chiffre et symbole.',
            max(12, (int) app_config('private.password_min_length', 14))
        );
    }
}
