<?php

return [
    'required' => 'This field is required.',
    'email' => 'Enter a valid email address.',
    'date' => 'Enter a valid date/time.',
    'after' => 'The :attribute must be a date after :date.',
    'before' => 'The :attribute must be a date before :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'uploaded' => 'The file could not be uploaded. Check the format and file size.',
    'image' => 'Choose a valid image (PNG or JPG).',
    'mimes' => 'Choose a file of type: :values.',
    'max' => [
        'file' => 'The file is too large (max. :max KB).',
        'string' => 'This field may not be greater than :max characters.',
    ],
    'min' => [
        'string' => 'This field must be at least :min characters.',
        'numeric' => 'This field must be at least :min.',
        'integer' => 'This field must be at least :min.',
    ],
];
