<?php

// config/printing.php - Updated configuration
return [
    // Your specific printer name from Windows
    'default_printer' => env('DEFAULT_PRINTER', 'EPSON LX-310 on Computer02'),
    
    // Use Windows Print Connector (recommended for your setup)
    'use_windows_connector' => env('USE_WINDOWS_CONNECTOR', true),
    
    // Alternative: Network printing (if printer has IP)
    'network_ip' => env('PRINTER_NETWORK_IP', null), // e.g., '192.168.0.100'
    'network_port' => env('PRINTER_NETWORK_PORT', 9100),
    
    // Alternative: LPT port
    'lpt_port' => env('PRINTER_LPT_PORT', 'LPT1'),
    
    // Print job settings
    'queue_enabled' => env('PRINT_QUEUE_ENABLED', true),
    'queue_name' => env('PRINT_QUEUE_NAME', 'printing'),
    'max_retries' => env('PRINT_MAX_RETRIES', 3),
    
    // Default formatting
    'default_width' => 48, // characters per line for ESC/POS
    'form_feed' => true,
];
