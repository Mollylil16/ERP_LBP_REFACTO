<?php

namespace App\Core;

class Validator
{
    /**
     * Valide un tableau de données selon un ensemble de règles.
     * 
     * @param array $data Les données à valider (souvent $_POST ou JSON décodé)
     * @param array $rules Les règles ex: ['email' => 'required|email', 'age' => 'numeric']
     * @throws \InvalidArgumentException Si la validation échoue
     * @return array Les données validées (propres)
     */
    public static function validate(array $data, array $rules): array
    {
        $validatedData = [];
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($rulesArray as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[] = "Le champ $field est requis.";
                }

                if ($value !== null && $value !== '') {
                    if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Le champ $field doit être une adresse email valide.";
                    }
                    if ($rule === 'numeric' && !is_numeric($value)) {
                        $errors[] = "Le champ $field doit être un nombre.";
                    }
                    if ($rule === 'array' && !is_array($value)) {
                        $errors[] = "Le champ $field doit être un tableau.";
                    }
                }
            }

            // On garde la valeur même si elle est null (sauf si required a levé une erreur)
            $validatedData[$field] = $value;
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors), 400);
        }

        return $validatedData;
    }
}
