<?php

/**
 * Canonical Philippine Advent College (PAC) departments and the programs
 * offered under each, as confirmed by the project owner.
 *
 * Used by:
 *  - App\Http\Requests\RegisterRequest (validates department is a known key,
 *    program is a known value, and that the pair actually matches)
 *  - resources/views/auth/register.blade.php (renders the cascading
 *    department -> program <select> dropdowns)
 *
 * If PAC's actual program list changes, update this file only — both the
 * validation and the registration form read from it.
 */

return [
    'Business Administration' => [
        'BS Business Administration (BSBA)',
        'Bachelor of Public Administration (BPA)',
    ],

    'Education' => [
        'Bachelor of Elementary Education (BEED)',
        'Bachelor of Early Childhood Education (BECED)',
        'Bachelor of Technical-Vocational Teacher Education (BTVTED)',
    ],

    'Nursing' => [
        'BS Nursing (BSN)',
        'Midwifery',
    ],

    'Computer Studies' => [
        'BS Information Technology (BSIT)',
        'BS Computer Science (BSCS)',
        'Associate in Computer Technology (ACT)',
    ],

    'Tourism' => [
        'BS Tourism Management (BSTM)',
    ],
];
