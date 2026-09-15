<?php

use Maatwebsite\Excel\Excel;

return [
    'exports' => [
        'chunk_size' => 1000,
        'pre_calculate_formulas' => false,
        'strict_null_comparison' => false,
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => false,
            'include_separator_line' => false,
            'excel_compatibility' => false,
            'output_encoding' => '',
            'test_auto_detect' => true,
        ],
    ],

    'imports' => [
        'read_only' => true,
        'ignore_empty' => true,
        'heading_row' => [
            'formatter' => 'slug',
        ],
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',
        ],
    ],

    'extension_detector' => [
        'xlsx' => Excel::XLSX,
        'xls' => Excel::XLS,
        'csv' => Excel::CSV,
        'tsv' => Excel::CSV,
        'ods' => Excel::ODS,
        'xlsm' => Excel::XLSX,
        'xlsb' => Excel::XLSX,
    ],
];