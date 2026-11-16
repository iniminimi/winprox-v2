<?php

return [
    'required' => 'Acest câmp este obligatoriu.',
    'required_if' => 'Acest câmp este obligatoriu când :other este :value.',
    'required_with' => 'Acest câmp este obligatoriu când :values este completat.',
    'required_without' => 'Acest câmp este obligatoriu când :values nu este completat.',
    'email' => 'Introduceți o adresă de e-mail validă.',
    'date' => 'Introduceți o dată/oră validă.',
    'after' => ':attribute trebuie să fie după :date.',
    'before' => ':attribute trebuie să fie înainte de :date.',
    'after_or_equal' => ':attribute trebuie să fie la :date sau după.',
    'uploaded' => 'Fișierul nu a putut fi încărcat. Verificați formatul și dimensiunea.',
    'image' => 'Alegeți o imagine validă (PNG sau JPG).',
    'mimes' => 'Alegeți un fișier de tipul: :values.',
    'max' => [
        'file' => 'Fișierul este prea mare (max. :max KB).',
        'string' => 'Acest câmp poate avea cel mult :max caractere.',
    ],
    'min' => [
        'string' => 'Acest câmp trebuie să aibă cel puțin :min caractere.',
        'numeric' => 'Acest câmp trebuie să fie cel puțin :min.',
        'integer' => 'Acest câmp trebuie să fie cel puțin :min.',
    ],
];
