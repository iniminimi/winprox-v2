<?php

return [
    'required' => 'Questo campo è obbligatorio.',
    'email' => 'Inserisca un indirizzo e-mail valido.',
    'date' => 'Inserisca una data/ora valida.',
    'after' => ':attribute deve essere una data successiva a :date.',
    'before' => ':attribute deve essere una data precedente a :date.',
    'after_or_equal' => ':attribute deve essere una data successiva o uguale a :date.',
    'uploaded' => 'Impossibile caricare il file. Verifichi il formato e le dimensioni.',
    'image' => 'Scelga un\'immagine valida (PNG o JPG).',
    'mimes' => 'Scelga un file di tipo: :values.',
    'max' => [
        'file' => 'Il file è troppo grande (max. :max KB).',
        'string' => 'Questo campo non può superare :max caratteri.',
    ],
    'min' => [
        'string' => 'Questo campo deve contenere almeno :min caratteri.',
        'numeric' => 'Questo campo deve essere almeno :min.',
        'integer' => 'Questo campo deve essere almeno :min.',
    ],
];
