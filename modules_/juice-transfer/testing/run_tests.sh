#!/bin/bash
# Test Launcher Script
# Quick access to run all comprehensive tests
# Location: /juice-transfer/testing/run_tests.sh

echo "🔍 CIS Comprehensive Testing Suite Launcher"
echo "==========================================="

# Check if running as web request or CLI
if [ -n "$QUERY_STRING" ]; then
    echo "Content-Type: text/html"
    echo ""
    echo "<pre>"
fi

echo "Available Test Suites:"
echo ""
echo "1. Comprehensive Test Suite (All endpoints, functions, security)"
echo "2. Queue Stress Tests (RockSolidVendQueue stress testing)"
echo "3. API Vulnerability Scanner (Deep security assessment)"
echo "4. Master Test Runner (All tests + extreme scenarios)"
echo ""

# Get user choice
if [ "$1" = "" ]; then
    echo "Usage: $0 [1|2|3|4|all]"
    echo "Or run with parameters:"
    echo "  1 - Comprehensive tests only"
    echo "  2 - Queue stress tests only" 
    echo "  3 - Vulnerability scan only"
    echo "  4 - Master test runner (recommended)"
    echo "  all - All test suites individually"
    echo ""
    exit 1
fi

TEST_DIR="/home/master/applications/jcepnzzkmj/public_html/juice-transfer/testing"

case $1 in
    "1")
        echo "🚀 Running Comprehensive Test Suite..."
        php "$TEST_DIR/comprehensive_test_suite.php"
        ;;
    "2") 
        echo "🚀 Running Queue Stress Tests..."
        php "$TEST_DIR/queue_stress_test.php"
        ;;
    "3")
        echo "🚀 Running API Vulnerability Scanner..."
        php "$TEST_DIR/api_vulnerability_scanner.php"
        ;;
    "4")
        echo "🚀 Running Master Test Runner (All Tests + Extreme Scenarios)..."
        php "$TEST_DIR/master_test_runner.php"
        ;;
    "all")
        echo "🚀 Running All Test Suites Individually..."
        echo ""
        echo "=== COMPREHENSIVE TEST SUITE ==="
        php "$TEST_DIR/comprehensive_test_suite.php"
        echo ""
        echo "=== QUEUE STRESS TESTS ==="
        php "$TEST_DIR/queue_stress_test.php"
        echo ""
        echo "=== API VULNERABILITY SCANNER ==="
        php "$TEST_DIR/api_vulnerability_scanner.php"
        echo ""
        echo "🎉 All individual test suites completed!"
        ;;
    *)
        echo "❌ Invalid option. Use 1, 2, 3, 4, or 'all'"
        exit 1
        ;;
esac

if [ -n "$QUERY_STRING" ]; then
    echo "</pre>"
fi

echo ""
echo "🎯 Test execution completed!"
echo "Check the output above for results and recommendations."
