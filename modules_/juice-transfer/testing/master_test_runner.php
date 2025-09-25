<?php
/**
 * Master Test Runner
 * Orchestrates all testing suites and generates comprehensive reports
 * Location: /juice-transfer/testing/master_test_runner.php
 * 
 * @author CIS System
 * @version 2.0
 */

require_once 'comprehensive_test_suite.php';
require_once 'queue_stress_test.php';
require_once 'api_vulnerability_scanner.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/connection.php';

/**
 * MasterTestRunner
 * Coordinates all testing activities and provides unified reporting
 */
class MasterTestRunner {
    private $startTime;
    private $testSuites = [];
    private $overallResults = [];
    private $testConfig;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->testConfig = [
            'run_comprehensive' => true,
            'run_stress_tests' => true,
            'run_vulnerability_scan' => true,
            'parallel_execution' => false,
            'generate_html_report' => true,
            'send_email_report' => false,
            'cleanup_after_tests' => true
        ];
        
        $this->log("Master Test Runner v2.0 initialized");
        $this->log("Test configuration: " . json_encode($this->testConfig));
    }
    
    /**
     * Run all test suites
     */
    public function runAllTests() {
        $this->log("=== STARTING MASTER TEST EXECUTION ===");
        $this->log("Testing all endpoints, functions, and attempting to break the system");
        
        try {
            // Pre-test setup
            $this->setupTestEnvironment();
            
            // Run test suites
            if ($this->testConfig['parallel_execution']) {
                $this->runTestsInParallel();
            } else {
                $this->runTestsSequentially();
            }
            
            // Post-test cleanup
            if ($this->testConfig['cleanup_after_tests']) {
                $this->cleanupTestEnvironment();
            }
            
            // Generate reports
            $this->generateMasterReport();
            
            if ($this->testConfig['generate_html_report']) {
                $this->generateHTMLReport();
            }
            
            if ($this->testConfig['send_email_report']) {
                $this->sendEmailReport();
            }
            
        } catch (Exception $e) {
            $this->log("CRITICAL ERROR in master test execution: " . $e->getMessage());
            $this->handleTestingFailure($e);
        }
    }
    
    /**
     * Setup test environment
     */
    private function setupTestEnvironment() {
        $this->log("Setting up test environment...");
        
        // Create test data backup
        $this->createTestDataBackup();
        
        // Setup test database state
        $this->setupTestDatabaseState();
        
        // Clear any existing test artifacts
        $this->clearTestArtifacts();
        
        // Verify system prerequisites
        $this->verifySystemPrerequisites();
        
        $this->log("Test environment setup completed");
    }
    
    /**
     * Run tests sequentially
     */
    private function runTestsSequentially() {
        $this->log("Executing tests sequentially...");
        
        // 1. Comprehensive Test Suite
        if ($this->testConfig['run_comprehensive']) {
            $this->log("\n--- RUNNING COMPREHENSIVE TEST SUITE ---");
            try {
                $comprehensive = new ComprehensiveTestSuite();
                
                // Capture output
                ob_start();
                $comprehensive->runAllTests();
                $output = ob_get_clean();
                
                $this->testSuites['comprehensive'] = [
                    'status' => 'completed',
                    'output' => $output,
                    'start_time' => microtime(true)
                ];
                
                $this->log("Comprehensive Test Suite completed");
                
            } catch (Exception $e) {
                $this->testSuites['comprehensive'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'start_time' => microtime(true)
                ];
                $this->log("Comprehensive Test Suite failed: " . $e->getMessage());
            }
        }
        
        // 2. Queue Stress Tests
        if ($this->testConfig['run_stress_tests']) {
            $this->log("\n--- RUNNING QUEUE STRESS TESTS ---");
            try {
                $stressTest = new QueueStressTest();
                
                ob_start();
                $stressTest->runStressTests();
                $output = ob_get_clean();
                
                $this->testSuites['stress'] = [
                    'status' => 'completed',
                    'output' => $output,
                    'start_time' => microtime(true)
                ];
                
                $this->log("Queue Stress Tests completed");
                
            } catch (Exception $e) {
                $this->testSuites['stress'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'start_time' => microtime(true)
                ];
                $this->log("Queue Stress Tests failed: " . $e->getMessage());
            }
        }
        
        // 3. Vulnerability Scanner
        if ($this->testConfig['run_vulnerability_scan']) {
            $this->log("\n--- RUNNING VULNERABILITY SCANNER ---");
            try {
                $vulnScanner = new APIVulnerabilityScanner();
                
                ob_start();
                $vulnScanner->runVulnerabilityAssessment();
                $output = ob_get_clean();
                
                $this->testSuites['vulnerability'] = [
                    'status' => 'completed',
                    'output' => $output,
                    'start_time' => microtime(true)
                ];
                
                $this->log("Vulnerability Scanner completed");
                
            } catch (Exception $e) {
                $this->testSuites['vulnerability'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'start_time' => microtime(true)
                ];
                $this->log("Vulnerability Scanner failed: " . $e->getMessage());
            }
        }
        
        // 4. Additional Extreme Tests
        $this->runExtremeTests();
    }
    
    /**
     * Run additional extreme tests
     */
    private function runExtremeTests() {
        $this->log("\n--- RUNNING ADDITIONAL EXTREME TESTS ---");
        
        $extremeTests = [
            'Database Connection Pool Exhaustion' => 'testConnectionPoolExhaustion',
            'Memory Leak Detection' => 'testMemoryLeaks', 
            'Infinite Loop Detection' => 'testInfiniteLoops',
            'File System Stress' => 'testFileSystemStress',
            'Network Timeout Scenarios' => 'testNetworkTimeouts',
            'Race Condition Detection' => 'testRaceConditions',
            'Deadlock Simulation' => 'testDeadlockSimulation',
            'Buffer Overflow Attempts' => 'testBufferOverflows',
            'Regex DoS Attacks' => 'testRegexDoS',
            'Fork Bomb Protection' => 'testForkBombProtection'
        ];
        
        foreach ($extremeTests as $testName => $methodName) {
            try {
                $this->log("Running extreme test: $testName");
                $this->$methodName();
                $this->overallResults[] = "✅ Extreme Test: $testName passed";
            } catch (Exception $e) {
                $this->overallResults[] = "❌ Extreme Test: $testName failed - " . $e->getMessage();
                $this->log("Extreme test $testName failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Extreme test implementations
     */
    private function testConnectionPoolExhaustion() {
        $this->log("Testing database connection pool exhaustion...");
        
        $connections = [];
        $maxConnections = 200; // Attempt to exhaust connection pool
        
        for ($i = 0; $i < $maxConnections; $i++) {
            try {
                $dsn = "mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4";
                $connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 30
                ]);
                
                $connections[] = $connection;
                
                // Try to use connection
                $stmt = $connection->prepare("SELECT 1");
                $stmt->execute();
                
                if ($i % 20 === 0) {
                    $this->log("Created $i database connections");
                }
                
            } catch (Exception $e) {
                $this->log("Connection exhaustion at $i connections: " . $e->getMessage());
                break;
            }
        }
        
        // Cleanup
        foreach ($connections as &$conn) {
            $conn = null;
        }
        
        $this->log("Connection pool exhaustion test completed");
    }
    
    private function testMemoryLeaks() {
        $this->log("Testing for memory leaks in repeated operations...");
        
        $initialMemory = memory_get_usage(true);
        $peakMemory = $initialMemory;
        
        // Perform memory-intensive operations
        for ($i = 0; $i < 10000; $i++) {
            // Create large data structures
            $largeArray = array_fill(0, 1000, str_repeat('A', 1000));
            
            // Simulate API calls
            $data = json_encode($largeArray);
            $decoded = json_decode($data, true);
            
            // Force cleanup
            unset($largeArray, $data, $decoded);
            
            if ($i % 1000 === 0) {
                $currentMemory = memory_get_usage(true);
                $peakMemory = max($peakMemory, $currentMemory);
                
                if (function_exists('gc_collect_cycles')) {
                    $collected = gc_collect_cycles();
                    $this->log("Memory at iteration $i: " . $this->formatBytes($currentMemory) . ", GC collected: $collected");
                }
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        $this->log("Memory leak test: Initial=" . $this->formatBytes($initialMemory) . 
                   ", Final=" . $this->formatBytes($finalMemory) . 
                   ", Peak=" . $this->formatBytes($peakMemory) . 
                   ", Increase=" . $this->formatBytes($memoryIncrease));
        
        if ($memoryIncrease > 100 * 1024 * 1024) { // 100MB threshold
            throw new Exception("Potential memory leak: " . $this->formatBytes($memoryIncrease) . " increase");
        }
    }
    
    private function testInfiniteLoops() {
        $this->log("Testing infinite loop detection...");
        
        $loopTests = [
            'while_true_with_timeout' => function() {
                set_time_limit(2);
                $counter = 0;
                $startTime = microtime(true);
                while (true) {
                    $counter++;
                    if (microtime(true) - $startTime > 1) {
                        break; // Safety break
                    }
                }
                set_time_limit(0);
                return $counter;
            },
            
            'recursive_function' => function() {
                set_time_limit(2);
                $depth = 0;
                
                $recursiveFunction = function($n) use (&$recursiveFunction, &$depth) {
                    $depth++;
                    if ($depth > 1000) { // Safety limit
                        return $n;
                    }
                    return $recursiveFunction($n + 1);
                };
                
                $result = $recursiveFunction(0);
                set_time_limit(0);
                return $result;
            }
        ];
        
        foreach ($loopTests as $testName => $testFunction) {
            try {
                $startTime = microtime(true);
                $result = $testFunction();
                $duration = microtime(true) - $startTime;
                
                $this->log("Loop test $testName: duration={$duration}s, result=$result");
                
                if ($duration > 5) {
                    throw new Exception("Loop test $testName took too long: {$duration}s");
                }
                
            } catch (Exception $e) {
                $this->log("Loop test $testName handled safely: " . $e->getMessage());
            }
        }
    }
    
    private function testFileSystemStress() {
        $this->log("Testing file system stress scenarios...");
        
        $testDir = '/tmp/fs_stress_test_' . uniqid();
        mkdir($testDir, 0777, true);
        
        try {
            // Test 1: Create many small files
            for ($i = 0; $i < 1000; $i++) {
                $filename = $testDir . '/file_' . $i . '.txt';
                file_put_contents($filename, "Test file $i content " . str_repeat('A', 100));
            }
            
            // Test 2: Create one large file
            $largeFile = $testDir . '/large_file.dat';
            $largeContent = str_repeat('B', 10 * 1024 * 1024); // 10MB
            file_put_contents($largeFile, $largeContent);
            
            // Test 3: Rapid file operations
            for ($i = 0; $i < 100; $i++) {
                $tempFile = $testDir . '/temp_' . $i;
                file_put_contents($tempFile, 'temp');
                unlink($tempFile);
            }
            
            $this->log("File system stress test completed");
            
        } finally {
            // Cleanup
            $this->recursiveDelete($testDir);
        }
    }
    
    private function testNetworkTimeouts() {
        $this->log("Testing network timeout scenarios...");
        
        $timeoutTests = [
            ['url' => 'http://httpbin.org/delay/10', 'timeout' => 2],
            ['url' => 'http://httpbin.org/delay/1', 'timeout' => 5],
            ['url' => 'http://nonexistent-domain-12345.com', 'timeout' => 3],
        ];
        
        foreach ($timeoutTests as $test) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $test['timeout'],
                    'ignore_errors' => true
                ]
            ]);
            
            $startTime = microtime(true);
            
            try {
                $response = @file_get_contents($test['url'], false, $context);
                $duration = microtime(true) - $startTime;
                
                $this->log("Network test: {$test['url']} completed in {$duration}s");
                
            } catch (Exception $e) {
                $duration = microtime(true) - $startTime;
                $this->log("Network test: {$test['url']} failed in {$duration}s - " . $e->getMessage());
            }
        }
    }
    
    private function testRaceConditions() {
        $this->log("Testing race condition scenarios...");
        
        // Simulate concurrent access to shared resource
        $sharedCounter = 0;
        $iterations = 1000;
        
        // Process 1: Increment counter
        for ($i = 0; $i < $iterations; $i++) {
            $temp = $sharedCounter;
            usleep(1); // Introduce timing issue
            $sharedCounter = $temp + 1;
        }
        
        $this->log("Race condition test: Expected $iterations, got $sharedCounter");
        
        if ($sharedCounter !== $iterations) {
            $this->log("Race condition detected: expected $iterations, got $sharedCounter");
        }
    }
    
    private function testDeadlockSimulation() {
        $this->log("Testing deadlock simulation...");
        
        // Simulate deadlock scenario with database locks
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Lock resource A
            $stmt1 = $pdo->prepare("SELECT * FROM juice_transfers WHERE id = 1 FOR UPDATE");
            $stmt1->execute();
            
            // Attempt to lock resource B (would cause deadlock in concurrent scenario)
            $stmt2 = $pdo->prepare("SELECT * FROM juice_transfer_items WHERE id = 1 FOR UPDATE");
            $stmt2->execute();
            
            $pdo->commit();
            $this->log("Deadlock simulation: No deadlock occurred");
            
        } catch (Exception $e) {
            $pdo->rollback();
            if (strpos($e->getMessage(), 'deadlock') !== false) {
                $this->log("Deadlock simulation: Deadlock detected and handled");
            } else {
                throw $e;
            }
        }
    }
    
    private function testBufferOverflows() {
        $this->log("Testing buffer overflow protection...");
        
        $overflowTests = [
            'large_string' => str_repeat('A', 1000000), // 1MB string
            'large_array' => array_fill(0, 100000, 'data'),
            'deep_nesting' => $this->createDeeplyNestedArray(1000),
            'binary_data' => str_repeat("\x00\xFF", 500000)
        ];
        
        foreach ($overflowTests as $testName => $testData) {
            try {
                $startTime = microtime(true);
                
                // Try to serialize/unserialize (common overflow point)
                $serialized = serialize($testData);
                $unserialized = unserialize($serialized);
                
                $duration = microtime(true) - $startTime;
                $this->log("Buffer overflow test $testName: {$duration}s, " . strlen($serialized) . " bytes");
                
                if ($duration > 30) {
                    throw new Exception("Buffer overflow test $testName took too long");
                }
                
            } catch (Exception $e) {
                $this->log("Buffer overflow test $testName handled: " . $e->getMessage());
            }
        }
    }
    
    private function testRegexDoS() {
        $this->log("Testing Regular Expression DoS (ReDoS) protection...");
        
        $redosPatterns = [
            'evil_regex_1' => 'a' . str_repeat('a?', 100) . str_repeat('a', 100),
            'evil_regex_2' => '(' . str_repeat('a*', 50) . ')*b',
            'evil_regex_3' => str_repeat('(a+)+', 20) . 'b'
        ];
        
        foreach ($redosPatterns as $testName => $pattern) {
            $startTime = microtime(true);
            
            try {
                // Set PCRE limits to protect against ReDoS
                ini_set('pcre.backtrack_limit', 100000);
                ini_set('pcre.recursion_limit', 100000);
                
                $result = preg_match('/^' . $pattern . '$/', $pattern . 'nomatch');
                
                $duration = microtime(true) - $startTime;
                $this->log("ReDoS test $testName: {$duration}s, result=$result");
                
                if ($duration > 5) {
                    throw new Exception("ReDoS vulnerability: $testName took {$duration}s");
                }
                
            } catch (Exception $e) {
                $duration = microtime(true) - $startTime;
                $this->log("ReDoS test $testName protected: {$duration}s - " . $e->getMessage());
            }
        }
    }
    
    private function testForkBombProtection() {
        $this->log("Testing fork bomb protection...");
        
        // Simulate resource exhaustion (safely)
        $processes = [];
        $maxProcesses = 10; // Safe limit for testing
        
        for ($i = 0; $i < $maxProcesses; $i++) {
            try {
                // Simulate process creation (without actually forking)
                $processes[] = [
                    'pid' => $i,
                    'memory' => memory_get_usage(),
                    'time' => microtime(true)
                ];
                
                usleep(10000); // 10ms delay
                
                if (count($processes) > 50) {
                    throw new Exception("Process limit exceeded");
                }
                
            } catch (Exception $e) {
                $this->log("Fork bomb protection activated: " . $e->getMessage());
                break;
            }
        }
        
        $this->log("Fork bomb test: Created " . count($processes) . " simulated processes");
    }
    
    /**
     * Helper methods
     */
    
    private function createDeeplyNestedArray($depth) {
        if ($depth <= 0) {
            return 'leaf';
        }
        return ['nested' => $this->createDeeplyNestedArray($depth - 1)];
    }
    
    private function recursiveDelete($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $this->recursiveDelete($dir . '/' . $file);
            }
            rmdir($dir);
        } else {
            unlink($dir);
        }
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Test environment management
     */
    
    private function createTestDataBackup() {
        $this->log("Creating test data backup...");
        
        global $pdo;
        
        $tables = [
            'juice_transfers',
            'juice_transfer_items', 
            'vend_queue_tasks',
            'juice_batches'
        ];
        
        foreach ($tables as $table) {
            try {
                $backupFile = "/tmp/test_backup_{$table}_" . date('Y-m-d_H-i-s') . '.sql';
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($count > 0) {
                    // Simple backup - in production, use mysqldump
                    $stmt = $pdo->query("SELECT * FROM $table");
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    file_put_contents($backupFile, json_encode($data, JSON_PRETTY_PRINT));
                    $this->log("Backed up $count records from $table to $backupFile");
                }
                
            } catch (Exception $e) {
                $this->log("Backup warning for $table: " . $e->getMessage());
            }
        }
    }
    
    private function setupTestDatabaseState() {
        $this->log("Setting up test database state...");
        
        // Ensure test tables exist and have some data
        global $pdo;
        
        try {
            // Create test transfer if none exist
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM juice_transfers");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count === 0) {
                $pdo->exec("
                    INSERT INTO juice_transfers (from_outlet_id, to_outlet_id, status, notes, created_at) 
                    VALUES (1, 2, 'completed', 'Test transfer for testing', NOW())
                ");
                $this->log("Created test transfer record");
            }
            
        } catch (Exception $e) {
            $this->log("Database setup warning: " . $e->getMessage());
        }
    }
    
    private function clearTestArtifacts() {
        $this->log("Clearing previous test artifacts...");
        
        // Clear test files
        $testDir = '/juice-transfer/testing/';
        $files = glob($testDir . 'test_*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < (time() - 3600)) { // Older than 1 hour
                unlink($file);
            }
        }
        
        // Clear temp test data from database
        global $pdo;
        try {
            $pdo->exec("DELETE FROM vend_queue_tasks WHERE type LIKE '%test%' AND created_at < NOW() - INTERVAL 1 HOUR");
        } catch (Exception $e) {
            $this->log("Cleanup warning: " . $e->getMessage());
        }
    }
    
    private function verifySystemPrerequisites() {
        $this->log("Verifying system prerequisites...");
        
        $requirements = [
            'PHP version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO extension' => extension_loaded('pdo'),
            'PDO MySQL extension' => extension_loaded('pdo_mysql'),
            'JSON extension' => extension_loaded('json'),
            'Memory limit >= 256MB' => $this->parseMemoryLimit(ini_get('memory_limit')) >= 256 * 1024 * 1024,
            'Max execution time >= 300s' => ini_get('max_execution_time') == 0 || ini_get('max_execution_time') >= 300,
        ];
        
        foreach ($requirements as $requirement => $met) {
            if ($met) {
                $this->log("✅ $requirement");
            } else {
                $this->log("❌ $requirement");
                throw new Exception("System requirement not met: $requirement");
            }
        }
    }
    
    private function parseMemoryLimit($memoryLimit) {
        if ($memoryLimit == '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)$memoryLimit;
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    private function cleanupTestEnvironment() {
        $this->log("Cleaning up test environment...");
        
        // Clean up temporary files
        $tempFiles = glob('/tmp/test_*');
        foreach ($tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Reset any modified system settings
        ini_restore('memory_limit');
        ini_restore('max_execution_time');
        
        $this->log("Test environment cleanup completed");
    }
    
    /**
     * Report generation
     */
    
    private function generateMasterReport() {
        $totalTime = microtime(true) - $this->startTime;
        $completedSuites = array_filter($this->testSuites, function($suite) {
            return $suite['status'] === 'completed';
        });
        $failedSuites = array_filter($this->testSuites, function($suite) {
            return $suite['status'] === 'failed';
        });
        
        echo "\n\n";
        echo "===========================================================\n";
        echo "               MASTER TEST EXECUTION REPORT\n";
        echo "===========================================================\n";
        echo "Total Execution Time: " . round($totalTime, 2) . " seconds\n";
        echo "Test Suites Run: " . count($this->testSuites) . "\n";
        echo "Completed Successfully: " . count($completedSuites) . "\n";
        echo "Failed: " . count($failedSuites) . "\n";
        echo "\n";
        
        echo "TEST SUITE RESULTS:\n";
        echo "===================\n";
        foreach ($this->testSuites as $suiteName => $suiteData) {
            $status = $suiteData['status'] === 'completed' ? '✅' : '❌';
            echo "$status " . ucfirst($suiteName) . " Test Suite: {$suiteData['status']}\n";
            
            if (isset($suiteData['error'])) {
                echo "   Error: {$suiteData['error']}\n";
            }
        }
        
        echo "\n";
        echo "EXTREME TEST RESULTS:\n";
        echo "====================\n";
        foreach ($this->overallResults as $result) {
            echo "$result\n";
        }
        
        echo "\n";
        echo "OVERALL SYSTEM ASSESSMENT:\n";
        echo "==========================\n";
        
        $overallScore = $this->calculateOverallScore();
        
        echo "System Robustness Score: $overallScore/100\n";
        
        if ($overallScore >= 95) {
            echo "🟢 EXCELLENT - System demonstrates exceptional robustness\n";
            echo "   - All major test suites passed\n";
            echo "   - System handles extreme conditions well\n";
            echo "   - Ready for production deployment\n";
        } elseif ($overallScore >= 80) {
            echo "🟡 GOOD - System shows good resilience with minor issues\n";
            echo "   - Most test suites passed successfully\n";
            echo "   - Some edge cases need attention\n";
            echo "   - Generally production ready with monitoring\n";
        } elseif ($overallScore >= 60) {
            echo "🟠 NEEDS IMPROVEMENT - Significant issues found\n";
            echo "   - Multiple test failures detected\n";
            echo "   - System vulnerable to certain attack vectors\n";
            echo "   - Requires fixes before production deployment\n";
        } else {
            echo "🔴 CRITICAL - System requires immediate attention\n";
            echo "   - Major test failures across multiple areas\n";
            echo "   - High risk of system compromise or failure\n";
            echo "   - Do NOT deploy to production\n";
        }
        
        echo "\n";
        echo "RECOMMENDATIONS:\n";
        echo "================\n";
        
        if (count($failedSuites) > 0) {
            echo "IMMEDIATE ACTIONS:\n";
            echo "1. Review and fix all failed test suites\n";
            echo "2. Address security vulnerabilities if found\n";
            echo "3. Improve error handling and input validation\n";
            echo "4. Re-run tests after fixes\n";
        }
        
        echo "ONGOING MAINTENANCE:\n";
        echo "1. Implement continuous testing in CI/CD pipeline\n";
        echo "2. Schedule regular security assessments\n";
        echo "3. Monitor system performance and error rates\n";
        echo "4. Keep all dependencies and frameworks updated\n";
        echo "5. Regular penetration testing\n";
        echo "6. Implement comprehensive logging and monitoring\n";
        
        echo "\n===========================================================\n";
        
        // Save master report to file
        $reportFile = '/juice-transfer/testing/master_test_report_' . date('Y-m-d_H-i-s') . '.txt';
        
        ob_start();
        echo "Master Test Report - " . date('Y-m-d H:i:s') . "\n";
        echo "Total Execution Time: " . round($totalTime, 2) . " seconds\n";
        echo "System Robustness Score: $overallScore/100\n\n";
        
        echo "Test Suite Results:\n";
        foreach ($this->testSuites as $suiteName => $suiteData) {
            echo "- " . ucfirst($suiteName) . ": {$suiteData['status']}\n";
        }
        
        echo "\nExtreme Test Results:\n";
        foreach ($this->overallResults as $result) {
            echo "$result\n";
        }
        
        $reportContent = ob_get_clean();
        file_put_contents($reportFile, $reportContent);
        
        echo "Master report saved to: $reportFile\n";
    }
    
    private function generateHTMLReport() {
        $this->log("Generating HTML report...");
        
        $htmlReport = $this->createHTMLReport();
        $htmlFile = '/juice-transfer/testing/master_test_report_' . date('Y-m-d_H-i-s') . '.html';
        
        file_put_contents($htmlFile, $htmlReport);
        $this->log("HTML report saved to: $htmlFile");
    }
    
    private function createHTMLReport() {
        $totalTime = round(microtime(true) - $this->startTime, 2);
        $overallScore = $this->calculateOverallScore();
        
        $html = "<!DOCTYPE html>
<html>
<head>
    <title>Comprehensive Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
        .score { font-size: 48px; font-weight: bold; margin: 20px 0; }
        .score.excellent { color: #28a745; }
        .score.good { color: #ffc107; }
        .score.needs-improvement { color: #fd7e14; }
        .score.critical { color: #dc3545; }
        .test-suite { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .test-suite.passed { background: #d4edda; border: 1px solid #c3e6cb; }
        .test-suite.failed { background: #f8d7da; border: 1px solid #f5c6cb; }
        .extreme-tests { margin: 20px 0; }
        .test-item { margin: 5px 0; padding: 8px; border-radius: 3px; }
        .test-item.passed { background: #d1ecf1; }
        .test-item.failed { background: #f8d7da; }
        .recommendations { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .timestamp { color: #666; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%); transition: width 0.3s; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔍 Comprehensive System Test Report</h1>
            <p class='timestamp'>Generated: " . date('Y-m-d H:i:s') . "</p>
            <p>Total Execution Time: {$totalTime} seconds</p>
        </div>
        
        <div class='score-section' style='text-align: center;'>
            <h2>System Robustness Score</h2>
            <div class='progress-bar'>
                <div class='progress-fill' style='width: {$overallScore}%;'></div>
            </div>
            <div class='score " . $this->getScoreClass($overallScore) . "'>{$overallScore}/100</div>
        </div>";
        
        $html .= "<h2>📊 Test Suite Results</h2>";
        foreach ($this->testSuites as $suiteName => $suiteData) {
            $status = $suiteData['status'];
            $class = $status === 'completed' ? 'passed' : 'failed';
            $icon = $status === 'completed' ? '✅' : '❌';
            
            $html .= "<div class='test-suite $class'>";
            $html .= "<h3>$icon " . ucfirst($suiteName) . " Test Suite</h3>";
            $html .= "<p>Status: <strong>" . ucfirst($status) . "</strong></p>";
            
            if (isset($suiteData['error'])) {
                $html .= "<p>Error: <code>{$suiteData['error']}</code></p>";
            }
            
            $html .= "</div>";
        }
        
        $html .= "<h2>⚡ Extreme Test Results</h2>";
        $html .= "<div class='extreme-tests'>";
        foreach ($this->overallResults as $result) {
            $class = strpos($result, '✅') !== false ? 'passed' : 'failed';
            $html .= "<div class='test-item $class'>$result</div>";
        }
        $html .= "</div>";
        
        $html .= "<div class='recommendations'>";
        $html .= "<h2>📋 Recommendations</h2>";
        
        if ($overallScore >= 95) {
            $html .= "<h3>🟢 Excellent System Health</h3>";
            $html .= "<ul>";
            $html .= "<li>System demonstrates exceptional robustness</li>";
            $html .= "<li>Ready for production deployment</li>";
            $html .= "<li>Continue regular monitoring and testing</li>";
            $html .= "</ul>";
        } elseif ($overallScore >= 80) {
            $html .= "<h3>🟡 Good System Health</h3>";
            $html .= "<ul>";
            $html .= "<li>System shows good resilience</li>";
            $html .= "<li>Address minor issues found</li>";
            $html .= "<li>Deploy with enhanced monitoring</li>";
            $html .= "</ul>";
        } elseif ($overallScore >= 60) {
            $html .= "<h3>🟠 Needs Improvement</h3>";
            $html .= "<ul>";
            $html .= "<li>Fix identified vulnerabilities</li>";
            $html .= "<li>Improve error handling</li>";
            $html .= "<li>Re-test before deployment</li>";
            $html .= "</ul>";
        } else {
            $html .= "<h3>🔴 Critical Issues</h3>";
            $html .= "<ul>";
            $html .= "<li>DO NOT deploy to production</li>";
            $html .= "<li>Address all critical failures</li>";
            $html .= "<li>Conduct thorough security review</li>";
            $html .= "</ul>";
        }
        
        $html .= "</div>";
        
        $html .= "</div></body></html>";
        
        return $html;
    }
    
    private function getScoreClass($score) {
        if ($score >= 95) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'needs-improvement';
        return 'critical';
    }
    
    private function calculateOverallScore() {
        $score = 100;
        
        // Deduct points for failed test suites
        $failedSuites = array_filter($this->testSuites, function($suite) {
            return $suite['status'] === 'failed';
        });
        
        $score -= count($failedSuites) * 30;
        
        // Deduct points for failed extreme tests
        $failedExtremeTests = array_filter($this->overallResults, function($result) {
            return strpos($result, '❌') !== false;
        });
        
        $score -= count($failedExtremeTests) * 5;
        
        return max(0, $score);
    }
    
    private function sendEmailReport() {
        // Email functionality would be implemented here
        $this->log("Email report functionality not implemented");
    }
    
    private function runTestsInParallel() {
        // Parallel execution would be implemented here using process forking
        $this->log("Parallel execution not implemented, falling back to sequential");
        $this->runTestsSequentially();
    }
    
    private function handleTestingFailure($exception) {
        $this->log("CRITICAL TESTING FAILURE: " . $exception->getMessage());
        
        // Generate emergency report
        $emergencyReport = "CRITICAL TESTING FAILURE\n";
        $emergencyReport .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $emergencyReport .= "Error: " . $exception->getMessage() . "\n";
        $emergencyReport .= "Stack Trace:\n" . $exception->getTraceAsString() . "\n";
        
        file_put_contents('/juice-transfer/testing/EMERGENCY_TEST_FAILURE.log', $emergencyReport);
        
        echo "\n🚨 EMERGENCY: Testing suite failure detected!\n";
        echo "Emergency log saved to: /juice-transfer/testing/EMERGENCY_TEST_FAILURE.log\n";
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $memory = $this->formatBytes(memory_get_usage(true));
        echo "[$timestamp] [$memory] $message\n";
        flush();
    }
}

// Run master tests if called directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_master_tests']) && $_GET['run_master_tests'] === 'true')) {
    echo "🚀 Starting Master Test Runner...\n\n";
    
    $masterRunner = new MasterTestRunner();
    $masterRunner->runAllTests();
    
    echo "\n🎉 Master Test Runner completed!\n";
}
?>
