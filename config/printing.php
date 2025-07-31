<?php

// config/printing.php
return [
    'default_printer' => env('DEFAULT_PRINTER', 'EPSON LX-310'),
    
    // Network printer path (if shared)
    'network_path' => env('PRINTER_NETWORK_PATH', null), // e.g., '\\\\COMPUTER-NAME\\EPSON_LX310'
    
    // Local printer share name
    'share_name' => env('PRINTER_SHARE_NAME', null), // e.g., 'EPSON_LX310'
    
    // LPT port (for direct connection)
    'lpt_port' => env('PRINTER_LPT_PORT', 'LPT1'),
    
    // Print job settings
    'queue_enabled' => env('PRINT_QUEUE_ENABLED', true),
    'queue_name' => env('PRINT_QUEUE_NAME', 'printing'),
    'max_retries' => env('PRINT_MAX_RETRIES', 3),
    
    // Printer settings
    'default_width' => 80,
    'default_quality' => 'nlq', // draft, nlq
    'default_line_spacing' => 24,
];