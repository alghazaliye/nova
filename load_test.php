<?php
/**
 * NOVA Messenger - Load Testing Script
 * Tests API performance under load
 */

declare(strict_types=1);

class LoadTester
{
    private string $baseUrl;
    private int $concurrency;
    private int $totalRequests;
    private array $results = [];
    private array $timings = [];

    public function __construct(string $baseUrl, int $concurrency = 10, int $totalRequests = 1000)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->concurrency = $concurrency;
        $this->totalRequests = $totalRequests;
    }

    /**
     * Run load test on messages endpoint
     */
    public function testMessagesEndpoint(): void
    {
        echo "=== اختبار ضغط API الرسائل ===\n";
        echo "عدد الطلبات: {$this->totalRequests}\n";
        echo "التزامن: {$this->concurrency}\n\n";

        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;

        for ($i = 0; $i < $this->totalRequests; $i++) {
            $requestStart = microtime(true);
            
            $ch = curl_init("{$this->baseUrl}/api/v1/conversations/1/messages");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer test-token',
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $requestTime = (microtime(true) - $requestStart) * 1000;
            $this->timings[] = $requestTime;

            if ($httpCode >= 200 && $httpCode < 300) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Progress indicator
            if (($i + 1) % 100 === 0) {
                echo "معالجة: " . ($i + 1) . "/{$this->totalRequests}\n";
            }
        }

        $totalTime = (microtime(true) - $startTime);

        $this->printResults('رسائل', $successCount, $errorCount, $totalTime);
    }

    /**
     * Run load test on stories endpoint
     */
    public function testStoriesEndpoint(): void
    {
        echo "\n=== اختبار ضغط API الحالات ===\n";
        echo "عدد الطلبات: {$this->totalRequests}\n";
        echo "التزامن: {$this->concurrency}\n\n";

        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;
        $this->timings = [];

        for ($i = 0; $i < $this->totalRequests; $i++) {
            $requestStart = microtime(true);
            
            $ch = curl_init("{$this->baseUrl}/api/v1/stories");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer test-token',
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $requestTime = (microtime(true) - $requestStart) * 1000;
            $this->timings[] = $requestTime;

            if ($httpCode >= 200 && $httpCode < 300) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Progress indicator
            if (($i + 1) % 100 === 0) {
                echo "معالجة: " . ($i + 1) . "/{$this->totalRequests}\n";
            }
        }

        $totalTime = (microtime(true) - $startTime);

        $this->printResults('حالات', $successCount, $errorCount, $totalTime);
    }

    /**
     * Run load test on conversations endpoint
     */
    public function testConversationsEndpoint(): void
    {
        echo "\n=== اختبار ضغط API المحادثات ===\n";
        echo "عدد الطلبات: {$this->totalRequests}\n";
        echo "التزامن: {$this->concurrency}\n\n";

        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;
        $this->timings = [];

        for ($i = 0; $i < $this->totalRequests; $i++) {
            $requestStart = microtime(true);
            
            $ch = curl_init("{$this->baseUrl}/api/v1/conversations");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer test-token',
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $requestTime = (microtime(true) - $requestStart) * 1000;
            $this->timings[] = $requestTime;

            if ($httpCode >= 200 && $httpCode < 300) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Progress indicator
            if (($i + 1) % 100 === 0) {
                echo "معالجة: " . ($i + 1) . "/{$this->totalRequests}\n";
            }
        }

        $totalTime = (microtime(true) - $startTime);

        $this->printResults('محادثات', $successCount, $errorCount, $totalTime);
    }

    /**
     * Print test results
     */
    private function printResults(string $endpoint, int $success, int $errors, float $totalTime): void
    {
        $avgTime = array_sum($this->timings) / count($this->timings);
        $minTime = min($this->timings);
        $maxTime = max($this->timings);

        // Calculate percentiles
        sort($this->timings);
        $p50 = $this->timings[count($this->timings) * 0.50];
        $p95 = $this->timings[count($this->timings) * 0.95];
        $p99 = $this->timings[count($this->timings) * 0.99];

        $rps = $this->totalRequests / $totalTime;
        $successRate = ($success / $this->totalRequests) * 100;

        echo "\n--- النتائج ---\n";
        echo "نقطة النهاية: $endpoint\n";
        echo "إجمالي الطلبات: {$this->totalRequests}\n";
        echo "الطلبات الناجحة: $success\n";
        echo "الطلبات الفاشلة: $errors\n";
        echo "معدل النجاح: " . number_format($successRate, 2) . "%\n";
        echo "الوقت الإجمالي: " . number_format($totalTime, 2) . " ثانية\n";
        echo "الطلبات/الثانية (RPS): " . number_format($rps, 2) . "\n\n";

        echo "--- إحصائيات التوقيت (بالميلي ثانية) ---\n";
        echo "الحد الأدنى: " . number_format($minTime, 2) . " ms\n";
        echo "الحد الأقصى: " . number_format($maxTime, 2) . " ms\n";
        echo "المتوسط: " . number_format($avgTime, 2) . " ms\n";
        echo "الوسيط (P50): " . number_format($p50, 2) . " ms\n";
        echo "P95: " . number_format($p95, 2) . " ms\n";
        echo "P99: " . number_format($p99, 2) . " ms\n";
    }
}

// Run tests
if (php_sapi_name() !== 'cli') {
    die("هذا السكريبت يجب أن يعمل من سطر الأوامر فقط\n");
}

$baseUrl = $argv[1] ?? 'http://localhost:8000';
$concurrency = (int)($argv[2] ?? 10);
$requests = (int)($argv[3] ?? 1000);

echo "=== اختبار ضغط NOVA Messenger API ===\n";
echo "عنوان الخادم: $baseUrl\n";
echo "التزامن: $concurrency\n";
echo "عدد الطلبات لكل اختبار: $requests\n\n";

$tester = new LoadTester($baseUrl, $concurrency, $requests);

// Run all tests
$tester->testConversationsEndpoint();
$tester->testMessagesEndpoint();
$tester->testStoriesEndpoint();

echo "\n=== انتهى الاختبار ===\n";
