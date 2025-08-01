<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class UsbPrinterPortFinder
{
    /**
     * Find the actual port used by USB printer
     */
    public function findUsbPrinterPort(): array
    {
        $results = [];
        
        // Method 1: Check actual printer ports from Windows
        $results['printer_ports'] = $this->getActualPrinterPorts();
        
        // Method 2: Check USB devices
        $results['usb_devices'] = $this->getUsbDevices();
        
        // Method 3: Check available ports
        $results['available_ports'] = $this->getAvailablePorts();
        
        // Method 4: Try to find EPSON-specific ports
        $results['epson_ports'] = $this->findEpsonPorts();
        
        // Method 5: Test all possible USB ports
        $results['usb_port_tests'] = $this->testUsbPorts();
        
        return $results;
    }

    /**
     * Get actual printer port information from Windows
     */
    private function getActualPrinterPorts(): array
    {
        try {
            // Get detailed printer information including ports
            $output = shell_exec('wmic printer get name,portname,drivername /format:csv 2>&1');
            $printers = [];
            
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (strpos($line, ',') !== false && strpos($line, 'EPSON') !== false) {
                        $parts = array_map('trim', explode(',', $line));
                        if (count($parts) >= 4) {
                            $printers[] = [
                                'name' => $parts[2] ?? '',
                                'port' => $parts[3] ?? '',
                                'driver' => $parts[1] ?? ''
                            ];
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'printers' => $printers,
                'raw_output' => $output
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get USB device information
     */
    private function getUsbDevices(): array
    {
        try {
            // Check USB devices for EPSON
            $usbOutput = shell_exec('wmic path Win32_USBControllerDevice get Dependent /format:csv 2>&1');
            $pnpOutput = shell_exec('wmic path Win32_PnPEntity where "Name like \'%EPSON%\'" get Name,DeviceID /format:csv 2>&1');
            
            return [
                'usb_devices' => $usbOutput,
                'pnp_devices' => $pnpOutput
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all available ports on the system
     */
    private function getAvailablePorts(): array
    {
        try {
            // Check all available ports
            $portsOutput = shell_exec('wmic path Win32_SerialPort get DeviceID,Name /format:csv 2>&1');
            $parallelOutput = shell_exec('wmic path Win32_ParallelPort get DeviceID,Name /format:csv 2>&1');
            
            return [
                'serial_ports' => $portsOutput,
                'parallel_ports' => $parallelOutput
            ];
            
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Find EPSON-specific ports and connections
     */
    private function findEpsonPorts(): array
    {
        try {
            // Look for EPSON in device manager style output
            $deviceOutput = shell_exec('wmic path Win32_PnPEntity where "Name like \'%EPSON%\'" get Name,DeviceID,Status /format:csv 2>&1');
            
            // Check printer connections specifically
            $printerConnections = shell_exec('wmic path Win32_TCPIPPrinterPort get Name,HostAddress,PortNumber /format:csv 2>&1');
            
            return [
                'epson_devices' => $deviceOutput,
                'printer_connections' => $printerConnections
            ];
            
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test various USB port formats
     */
    private function testUsbPorts(): array
    {
        $possiblePorts = [
            'USB001', 'USB002', 'USB003',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5',
            'DOT4_001', 'DOT4_002', 'DOT4_003',
            'WSD001', 'WSD002',
            'FILE:', 'PRN'
        ];
        
        $results = [];
        
        foreach ($possiblePorts as $port) {
            $results[$port] = $this->testPortAccess($port);
        }
        
        return $results;
    }

    /**
     * Test if a port is accessible
     */
    private function testPortAccess(string $port): array
    {
        try {
            // Try to open the port
            $handle = @fopen($port, 'wb');
            if ($handle) {
                fclose($handle);
                return [
                    'accessible' => true,
                    'method' => 'fopen'
                ];
            }
            
            // Try copy command test
            $tempFile = tempnam(sys_get_temp_dir(), 'port_test_');
            file_put_contents($tempFile, 'test');
            
            $command = sprintf('copy /B "%s" %s 2>&1', $tempFile, $port);
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            $copySuccess = strpos($output, '1 file(s) copied') !== false;
            
            return [
                'accessible' => $copySuccess,
                'method' => 'copy',
                'output' => $output
            ];
            
        } catch (Exception $e) {
            return [
                'accessible' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Try to print to each discovered port
     */
    public function testPrintToDiscoveredPorts(): array
    {
        $portInfo = $this->findUsbPrinterPort();
        $testResults = [];
        
        // Extract actual printer port from Windows data
        $actualPorts = [];
        if (isset($portInfo['printer_ports']['printers'])) {
            foreach ($portInfo['printer_ports']['printers'] as $printer) {
                if (!empty($printer['port'])) {
                    $actualPorts[] = $printer['port'];
                }
            }
        }
        
        // Test actual printer ports first
        foreach ($actualPorts as $port) {
            $testResults["actual_port_{$port}"] = $this->performTestPrint($port);
        }
        
        // Test accessible USB ports
        if (isset($portInfo['usb_port_tests'])) {
            foreach ($portInfo['usb_port_tests'] as $port => $info) {
                if ($info['accessible']) {
                    $testResults["usb_port_{$port}"] = $this->performTestPrint($port);
                }
            }
        }
        
        return [
            'port_discovery' => $portInfo,
            'print_tests' => $testResults,
            'recommendation' => $this->getPortRecommendation($testResults)
        ];
    }

    /**
     * Perform actual test print to a port
     */
    private function performTestPrint(string $port): array
    {
        try {
            $testContent = "USB PORT TEST\r\n";
            $testContent .= "Port: {$port}\r\n"; 
            $testContent .= "Time: " . date('H:i:s') . "\r\n";
            $testContent .= "If you see this, {$port} works!\r\n";
            $testContent .= str_repeat('-', 40) . "\r\n\r\n";
            
            // Method 1: Direct write
            $handle = @fopen($port, 'wb');
            if ($handle) {
                $bytesWritten = fwrite($handle, $testContent);
                fclose($handle);
                
                if ($bytesWritten > 0) {
                    return [
                        'success' => true,
                        'method' => 'direct_write',
                        'bytes_written' => $bytesWritten,
                        'message' => "Check printer - test sent to {$port}"
                    ];
                }
            }
            
            // Method 2: Copy command
            $tempFile = tempnam(sys_get_temp_dir(), 'test_print_');
            file_put_contents($tempFile, $testContent);
            
            $command = sprintf('copy /B "%s" %s 2>&1', $tempFile, $port);
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            if (strpos($output, '1 file(s) copied') !== false) {
                return [
                    'success' => true,
                    'method' => 'copy_command',
                    'output' => $output,
                    'message' => "Check printer - test sent to {$port}"
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Both methods failed',
                'copy_output' => $output
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get recommendation based on test results
     */
    private function getPortRecommendation(array $testResults): string
    {
        foreach ($testResults as $portTest => $result) {
            if ($result['success'] ?? false) {
                $port = str_replace(['actual_port_', 'usb_port_'], '', $portTest);
                return "✅ Found working port: {$port} - Update your .env with DEFAULT_PRINTER=\"{$port}\"";
            }
        }
        
        return "❌ No working ports found. The USB printer might not be properly configured or may need different drivers.";
    }
}

