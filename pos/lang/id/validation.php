<?php

// Only the rules the app uses. Add a line when a form uses a new rule.
return [
    'alpha_dash' => ':Attribute hanya boleh berisi huruf, angka, - dan _, tanpa spasi.',
    'max' => [
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'min' => [
        'string' => ':Attribute minimal :min karakter.',
    ],
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',

    'attributes' => [
        'name' => 'nama',
        'password' => 'kata sandi',
        'username' => 'nama pengguna',
    ],
];
