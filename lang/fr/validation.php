<?php

return [
    'required' => 'Ce champ est obligatoire.',
    'email' => 'Saisissez une adresse e-mail valide.',
    'date' => 'Saisissez une date/heure valide.',
    'after' => 'Le champ :attribute doit être une date postérieure à :date.',
    'before' => 'Le champ :attribute doit être une date antérieure à :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale à :date.',
    'uploaded' => 'Le fichier n’a pas pu être envoyé. Vérifiez le format et la taille.',
    'image' => 'Choisissez une image valide (PNG ou JPG).',
    'mimes' => 'Choisissez un fichier de type : :values.',
    'max' => [
        'file' => 'Le fichier est trop volumineux (max. :max Ko).',
        'string' => 'Ce champ ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'string' => 'Ce champ doit contenir au moins :min caractères.',
        'numeric' => 'Ce champ doit être au moins :min.',
        'integer' => 'Ce champ doit être au moins :min.',
    ],
];
