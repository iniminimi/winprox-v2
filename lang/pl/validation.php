<?php

return [
    'required' => 'To pole jest wymagane.',
    'required_if' => 'To pole jest wymagane, gdy :other ma wartość :value.',
    'required_with' => 'To pole jest wymagane, gdy :values jest wypełnione.',
    'required_without' => 'To pole jest wymagane, gdy :values nie jest wypełnione.',
    'email' => 'Proszę podać prawidłowy adres e-mail.',
    'date' => 'Proszę podać prawidłową datę i godzinę.',
    'after' => ':attribute musi być późniejsze niż :date.',
    'before' => ':attribute musi być wcześniejsze niż :date.',
    'after_or_equal' => ':attribute musi przypadać :date lub później.',
    'uploaded' => 'Nie udało się przesłać pliku. Proszę sprawdzić format i rozmiar.',
    'image' => 'Proszę wybrać prawidłowy obraz (PNG lub JPG).',
    'mimes' => 'Proszę wybrać plik typu: :values.',
    'max' => [
        'file' => 'Plik jest za duży (maks. :max KB).',
        'string' => 'To pole może mieć najwyżej :max znaków.',
    ],
    'min' => [
        'string' => 'To pole musi mieć co najmniej :min znaków.',
        'numeric' => 'To pole musi wynosić co najmniej :min.',
        'integer' => 'To pole musi wynosić co najmniej :min.',
    ],
];
