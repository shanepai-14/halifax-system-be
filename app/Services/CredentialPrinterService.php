<?php

namespace App\Services;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Exception;
use Illuminate\Support\Facades\Log;

class CredentialPrinterService
{
    private $printerName;
    private $config;

    public function __construct()
    {
        $this->printerName = config('printing.default_printer', '\\\\Computer02\\EPSONLX310');
        $this->config = config('printing', []);
    }

    /**
     * Test printer with credential mapping
     */
    public function testPrinterWithCredentials(): array
    {
        try {
            // First, try to map the network drive with credentials
            $mapResult = $this->mapNetworkPrinter();
            
            if (!$mapResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to map network printer: ' . $mapResult['error'],
                    'suggestion' => 'Check Computer02 sharing settings and credentials'
                ];
            }

            // Now try to print
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);
            
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("NETWORK PRINTER SUCCESS!\n");
            $printer->selectPrintMode();
            
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Date: " . date('Y-m-d H:i:s') . "\n");
            $printer->text("From: HALIFAX-SERVER\n");
            $printer->text("Printer: " . $this->printerName . "\n");
            $printer->text("Method: Network with credentials\n");
            $printer->text(str_repeat("-", 40) . "\n");
            
            $printer->feed(3);
            $printer->close();
            
            return [
                'success' => true,
                'message' => 'Network printer test successful with credentials!',
                'printer' => $this->printerName,
                'mapping' => $mapResult
            ];

        } catch (Exception $e) {
            Log::error('Credential printer test failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Printer test failed: ' . $e->getMessage(),
                'printer' => $this->printerName,
                'suggestion' => 'Try running Laravel as Administrator or check firewall settings'
            ];
        }
    }

    /**
     * Map network printer with credentials
     */
    private function mapNetworkPrinter(): array
    {
        try {
            $printerPath = str_replace('\\\\', '\\', $this->printerName);
            $username = config('printing.printer_username');
            $password = config('printing.printer_password');
            
            // Method 1: Try without credentials first (guest access)
            $command = "net use \"{$printerPath}\" 2>&1";
            $output = shell_exec($command);
            
            if (strpos($output, 'completed successfully') !== false) {
                return [
                    'success' => true,
                    'method' => 'guest_access',
                    'output' => $output
                ];
            }
            
            // Method 2: Try with credentials if provided
            if ($username && $password) {
                $command = "net use \"{$printerPath}\" /user:\"{$username}\" \"{$password}\" 2>&1";
                $output = shell_exec($command);
                
                if (strpos($output, 'completed successfully') !== false) {
                    return [
                        'success' => true,
                        'method' => 'with_credentials',
                        'output' => $output
                    ];
                }
            }
            
            // Method 3: Try to connect to Computer02 first, then printer
            $computerPath = '\\\\Computer02';
            if ($username && $password) {
                $command = "net use \"{$computerPath}\" /user:\"{$username}\" \"{$password}\" 2>&1";
                $output = shell_exec($command);
                
                if (strpos($output, 'completed successfully') !== false) {
                    return [
                        'success' => true,
                        'method' => 'computer_then_printer',
                        'output' => $output
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => 'All network mapping attempts failed',
                'last_output' => $output ?? 'No output'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get printer connector with fallback methods
     */
    private function getPrinterConnector()
    {
        $errors = [];
        
        // Method 1: Try Windows connector with current name
        try {
            Log::info('Trying Windows connector: ' . $this->printerName);
            return new WindowsPrintConnector($this->printerName);
        } catch (Exception $e) {
            $errors['windows'] = $e->getMessage();
        }

        // Method 2: Try LPT ports (bypass network entirely)
        $lptPorts = ['LPT1', 'LPT2', 'LPT3'];
        foreach ($lptPorts as $port) {
            try {
                Log::info('Trying LPT port: ' . $port);
                return new FilePrintConnector($port);
            } catch (Exception $e) {
                $errors["lpt_{$port}"] = $e->getMessage();
            }
        }

        // Method 3: Try PRN (default printer port)
        try {
            Log::info('Trying PRN port');
            return new FilePrintConnector('PRN');
        } catch (Exception $e) {
            $errors['prn'] = $e->getMessage();
        }

        throw new Exception('All connection methods failed: ' . json_encode($errors));
    }

    /**
     * Test all available methods and return the best one
     */
    public function findWorkingMethod(): array
    {
        $methods = [];
        
        // Test 1: Network with credentials
        $methods['network_with_creds'] = $this->testNetworkWithCredentials();
        
        // Test 2: Local LPT ports
        $methods['lpt_ports'] = $this->testLptPorts();
        
        // Test 3: Direct copy commands
        $methods['copy_commands'] = $this->testCopyCommands();
        
        // Test 4: PowerShell methods
        $methods['powershell'] = $this->testPowerShellMethods();
        
        // Find the first working method
        $workingMethod = null;
        foreach ($methods as $methodName => $result) {
            if (is_array($result)) {
                foreach ($result as $subMethod => $subResult) {
                    if ($subResult['success'] ?? false) {
                        $workingMethod = [
                            'method' => $methodName,
                            'sub_method' => $subMethod,
                            'config' => $subResult
                        ];
                        break 2;
                    }
                }
            } elseif ($result['success'] ?? false) {
                $workingMethod = [
                    'method' => $methodName,
                    'config' => $result
                ];
                break;
            }
        }
        
        return [
            'all_tests' => $methods,
            'working_method' => $workingMethod,
            'recommendation' => $this->getRecommendation($workingMethod)
        ];
    }

    private function testNetworkWithCredentials(): array
    {
        return [
            'with_mapping' => $this->testPrinterWithCredentials(),
            'direct_smb' => $this->testSmbConnection()
        ];
    }

    private function testSmbConnection(): array
    {
        $smbPaths = [
            'smb://Computer02/EPSONLX310',
            'smb://Computer02/EPSON_LX-310',
            'smb://Computer02/EPSON LX-310'
        ];
        
        foreach ($smbPaths as $path) {
            try {
                $connector = new WindowsPrintConnector($path);
                $printer = new Printer($connector);
                $printer->close();
                
                return [
                    'success' => true,
                    'path' => $path,
                    'message' => 'SMB connection successful'
                ];
            } catch (Exception $e) {
                // Continue to next path
            }
        }
        
        return [
            'success' => false,
            'message' => 'All SMB paths failed'
        ];
    }

    private function testLptPorts(): array
    {
        $ports = ['LPT1', 'LPT2', 'LPT3', 'COM1', 'COM2', 'PRN'];
        $results = [];
        
        foreach ($ports as $port) {
            try {
                $connector = new FilePrintConnector($port);
                $printer = new Printer($connector);
                $printer->close();
                
                $results[$port] = [
                    'success' => true,
                    'message' => 'Port accessible'
                ];
            } catch (Exception $e) {
                $results[$port] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    private function testCopyCommands(): array
    {
        $paths = [
            'LPT1',
            'PRN',
            '\\\\Computer02\\EPSONLX310',
            '\\\\Computer02\\EPSON_LX-310'
        ];
        
        $results = [];
        
        foreach ($paths as $path) {
            try {
                $testContent = "COPY TEST - " . date('H:i:s') . "\n";
                $tempFile = tempnam(sys_get_temp_dir(), 'copy_test_');
                file_put_contents($tempFile, $testContent);
                
                $command = sprintf('copy /B "%s" "%s" 2>&1', $tempFile, $path);
                $output = shell_exec($command);
                
                unlink($tempFile);
                
                $results[$path] = [
                    'success' => strpos($output, '1 file(s) copied') !== false,
                    'output' => $output,
                    'command' => $command
                ];
                
            } catch (Exception $e) {
                $results[$path] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    private function testPowerShellMethods(): array
    {
        $printerNames = [
            '\\\\Computer02\\EPSONLX310',
            'EPSON LX-310',
            'EPSONLX310'
        ];
        
        $results = [];
        
        foreach ($printerNames as $name) {
            try {
                $testContent = "PS TEST - " . date('H:i:s') . "\n";
                $tempFile = tempnam(sys_get_temp_dir(), 'ps_test_');
                file_put_contents($tempFile, $testContent);
                
                $command = sprintf(
                    'powershell -Command "Get-Content \'%s\' | Out-Printer -Name \'%s\'" 2>&1',
                    $tempFile,
                    $name
                );
                
                $output = shell_exec($command);
                unlink($tempFile);
                
                $results[$name] = [
                    'success' => empty($output) || strpos(strtolower($output), 'error') === false,
                    'output' => $output,
                    'command' => $command
                ];
                
            } catch (Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    private function getRecommendation($workingMethod): string
    {
        if (!$workingMethod) {
            return "❌ No working method found. Check printer sharing, permissions, and network connectivity.";
        }
        
        $method = $workingMethod['method'];
        $config = $workingMethod['config'];
        
        switch ($method) {
            case 'network_with_creds':
                return "✅ Network printing works! Use: DEFAULT_PRINTER=\"{$this->printerName}\"";
                
            case 'lpt_ports':
                $port = $workingMethod['sub_method'];
                return "✅ Local port works! Use: DEFAULT_PRINTER=\"{$port}\"";
                
            case 'copy_commands':
                $path = $workingMethod['sub_method'];
                return "✅ Copy command works! Use: DEFAULT_PRINTER=\"{$path}\"";
                
            case 'powershell':
                $name = $workingMethod['sub_method'];
                return "✅ PowerShell works! Use: DEFAULT_PRINTER=\"{$name}\"";
                
            default:
                return "✅ Method {$method} works! Check configuration details.";
        }
    }
}