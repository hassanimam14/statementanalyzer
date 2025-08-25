<?php

return [
    // absolute paths to your binaries (read from .env)
    'pdftotext_bin' => env('PDFTOTEXT_BIN', 'pdftotext'),
    'tesseract_bin' => env('TESSERACT_BIN', 'tesseract'),

    // OCR options
    'tesseract_lang' => env('TESSERACT_LANG', 'eng'),
    'tesseract_psm'  => env('TESSERACT_PSM', '6'),

    // safety limits
    'max_probe_lines' => env('EXTRACT_MAX_PROBE_LINES', 6000),
    'max_abs_amount'  => env('EXTRACT_MAX_ABS_AMOUNT', 10000000),
];
