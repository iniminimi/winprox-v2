<?php

return [
    'required' => 'Este campo es obligatorio.',
    'email' => 'Introduzca un correo electrónico válido.',
    'date' => 'Introduzca una fecha/hora válida.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'before' => ':attribute debe ser una fecha anterior a :date.',
    'after_or_equal' => ':attribute debe ser una fecha posterior o igual a :date.',
    'uploaded' => 'No se ha podido subir el archivo. Compruebe el formato y el tamaño.',
    'image' => 'Elija una imagen válida (PNG o JPG).',
    'mimes' => 'Elija un archivo de tipo: :values.',
    'max' => [
        'file' => 'El archivo es demasiado grande (máx. :max KB).',
        'string' => 'Este campo no puede tener más de :max caracteres.',
    ],
    'min' => [
        'string' => 'Este campo debe tener al menos :min caracteres.',
        'numeric' => 'Este campo debe ser al menos :min.',
        'integer' => 'Este campo debe ser al menos :min.',
    ],
];
