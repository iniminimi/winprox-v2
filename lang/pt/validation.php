<?php

return [
    'required' => 'Este campo é obrigatório.',
    'required_if' => 'Este campo é obrigatório quando :other é :value.',
    'required_with' => 'Este campo é obrigatório quando :values está preenchido.',
    'required_without' => 'Este campo é obrigatório quando :values não está preenchido.',
    'email' => 'Introduza um endereço de e-mail válido.',
    'date' => 'Introduza uma data/hora válida.',
    'after' => ':attribute tem de ser posterior a :date.',
    'before' => ':attribute tem de ser anterior a :date.',
    'after_or_equal' => ':attribute tem de ser em :date ou depois.',
    'uploaded' => 'Não foi possível carregar o ficheiro. Verifique o formato e o tamanho.',
    'image' => 'Escolha uma imagem válida (PNG ou JPG).',
    'mimes' => 'Escolha um ficheiro do tipo: :values.',
    'max' => [
        'file' => 'O ficheiro é demasiado grande (máx. :max KB).',
        'string' => 'Este campo pode ter no máximo :max caracteres.',
    ],
    'min' => [
        'string' => 'Este campo tem de ter pelo menos :min caracteres.',
        'numeric' => 'Este campo tem de ser pelo menos :min.',
        'integer' => 'Este campo tem de ser pelo menos :min.',
    ],
];
