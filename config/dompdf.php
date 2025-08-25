<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Default values for Dompdf. You can override anything from dompdf_config.inc.php
    | here. These are safe, production-friendly defaults for Laravel.
    |
    */
    'show_warnings' => false,   // Throw an Exception on Dompdf warnings
    'public_path'   => null,    // Override the public path if needed

    /*
     * Dejavu Sans font is missing glyphs for some converted entities, turn it off
     * if you need to show certain symbols like € and £ with other fonts.
     */
    'convert_entities' => true,

    'options' => [
        /**
         * Font directories (must exist and be writable)
         */
        'font_dir'   => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),

        /**
         * Temp directory (must be writable)
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * Dompdf "chroot": all local files must live under this path
         * Never set this to '/' in production.
         */
        'chroot' => realpath(base_path()),

        /**
         * Protocol whitelist for remote resources
         */
        'allowed_protocols' => [
            'data://'  => ['rules' => []],
            'file://'  => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        /**
         * Internal artifacts/logging
         */
        'artifactPathValidation' => null,
        'log_output_file'        => null,

        /**
         * Font handling
         */
        'enable_font_subsetting' => false,

        /**
         * PDF rendering backend
         */
        'pdf_backend' => 'CPDF',

        /**
         * Media & page setup
         */
        'default_media_type'       => 'screen',
        'default_paper_size'       => 'a4',
        'default_paper_orientation' => 'portrait',
        'default_font'             => 'serif',

        /**
         * Rendering DPI
         */
        'dpi' => 96,

        

        /**
         * Embedded PHP in templates (keep OFF for security)
         */
        'enable_php' => false,

        /**
         * Enable inline JavaScript (PDF viewer JS, not browser JS)
         */
        'enable_javascript' => true,

        /**
         * Enable remote file access (needed for QuickChart images, CDN assets, etc.)
         */
        'enable_remote' => true,

        /**
         * Restrict remote hosts (null = allow any when enable_remote is true)
         * Set to an array like ['quickchart.io', 'cdn.example.com'] to lock down.
         */
        'allowed_remote_hosts' => null,

        /**
         * Line-height ratio for fonts
         */
        'font_height_ratio' => 1.1,

        /**
         * HTML5 parser (always on in Dompdf 2.x; keep true for forward compat)
         */
        'enable_html5_parser' => true,
    ],
];
