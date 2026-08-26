<?php


class Geminiinsights
{
    private string $apiKey;
    private string $model = 'gemini-flash-latest';
    private string $cacheDir;
    private int $cacheTtlSeconds = 3600; // 1 hour

    public function __construct(?string $apiKey = null, ?string $cacheDir = null)
    {
        $this->apiKey = $apiKey ?? (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        $this->cacheDir = $cacheDir ?? (__DIR__ . '/../cache/ai_insights');

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    public function generate(array $chartData, array $inspectorWorkload, int $year): string
    {
        if (empty($this->apiKey)) {
            return ''; // No key configured — caller should skip rendering the card
        }

        $cacheKey = md5('gemini_' . json_encode([$chartData, $inspectorWorkload, $year]));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        $cached = $this->readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        $facts = $this->buildFacts($chartData, $inspectorWorkload, $year);
        $narrative = $this->callGemini($facts);

        if ($narrative !== '') {
            $this->writeCache($cacheFile, $narrative);
        }

        return $narrative;
    }

    public function clearCache(): void
    {
        foreach (glob($this->cacheDir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    // ----------------------------------------------------------------
    // Internal — fact precomputation identical in approach to
    // OllamaInsights.php: PHP does all arithmetic, the model only phrases
    // already-correct facts into sentences. Keeps hallucinated numbers out
    // regardless of which model is behind the API.
    // ----------------------------------------------------------------

    private function buildFacts(array $chartData, array $inspectorWorkload, int $year): string
    {
        $facts = [];
        $facts[] = "Report year: {$year}";

        $status = $chartData['status'] ?? ['Approved' => 0, 'Rejected' => 0, 'Pending' => 0];
        $totalApps = array_sum($status);
        $facts[] = "FACT: Total applications in {$year} = {$totalApps}";
        $facts[] = "FACT: Approved = {$status['Approved']}";
        $facts[] = "FACT: Rejected = {$status['Rejected']}";
        $facts[] = "FACT: Pending/Other (includes submitted, under review, etc.) = {$status['Pending']}";

        $yoy = $chartData['yoy_comparison'] ?? ['current' => 0, 'previous' => 0];
        $prevYear = $year - 1;
        $delta = $yoy['current'] - $yoy['previous'];
        if ($yoy['previous'] > 0) {
            $pctChange = round(($delta / $yoy['previous']) * 100, 1);
            $direction = $delta > 0 ? 'an increase of' : ($delta < 0 ? 'a decrease of' : 'no change —');
            $facts[] = "FACT: Compared to {$prevYear} ({$yoy['previous']} applications), {$year} ({$yoy['current']} applications) shows {$direction} " . abs($pctChange) . "%.";
        } elseif ($yoy['current'] > 0) {
            $facts[] = "FACT: There is no {$prevYear} data to compare against (0 applications that year), so no year-over-year percentage can be calculated. Do not invent one.";
        } else {
            $facts[] = "FACT: No year-over-year comparison is available.";
        }

        $months = $chartData['months'] ?? [];
        $monthsWithData = array_filter($months, fn($c) => $c > 0);
        if (!empty($monthsWithData)) {
            arsort($monthsWithData);
            $topMonth = array_key_first($monthsWithData);
            $topCount = $monthsWithData[$topMonth];
            $activeMonths = count($monthsWithData);
            $facts[] = "FACT: The busiest month was {$topMonth} with {$topCount} application(s). Applications occurred in {$activeMonths} month(s) total during {$year}.";
        } else {
            $facts[] = "FACT: No monthly application data recorded for {$year}.";
        }

        $barangays = $chartData['barangays'] ?? [];
        if (!empty($barangays)) {
            $brgyLine = [];
            foreach ($barangays as $name => $count) {
                $brgyLine[] = "{$name}={$count}";
            }
            $facts[] = "FACT: Top barangays by TOTAL application count, all statuses mixed together (NOT approved-only): " . implode(', ', $brgyLine);
        }

        if (!empty($inspectorWorkload)) {
            $counts = array_column($inspectorWorkload, 'total_inspections');
            $avg = count($counts) ? round(array_sum($counts) / count($counts), 1) : 0;
            $iwLine = [];
            foreach ($inspectorWorkload as $row) {
                $name = $row['inspector_name'] ?? 'Unknown';
                $total = $row['total_inspections'] ?? 0;
                $iwLine[] = "{$name}={$total}";
            }
            $facts[] = "FACT: Inspector workload (total inspections, all-time): " . implode(', ', $iwLine);
            $facts[] = "FACT: Average inspections per inspector = {$avg}";
        }

        return implode("\n", $facts);
    }

    private function callGemini(string $factsBlock): string
    {
        $prompt = <<<PROMPT
Summarize this local government permit dashboard data for an admin.

Below is a list of FACT lines. Every number you need has already been
calculated for you.

STRICT RULES:
1. Do NOT perform any addition, subtraction, percentages, or other math.
   Every fact you need is already computed in the FACT lines — just restate
   them in plain language.
2. Do NOT combine two FACT lines into a new number (e.g. do not add two
   months together, do not merge barangay counts with status counts).
3. Do NOT mention a status (Approved/Rejected/Pending) for barangay data —
   barangay counts mix all statuses together, this is explicitly stated.
4. Only use numbers that appear in a FACT line below. If something is not
   in a FACT line, do not mention it.
5. If a FACT line says a comparison is unavailable, say it's unavailable —
   do not invent a percentage anyway.
6. MANDATORY: if the "Pending/Other" FACT is greater than 0, you MUST
   include a bullet point stating that number — these are applications
   still awaiting action, and the admin needs to see this every time. Do
   not omit it even if the total is small (e.g. 1).

Write exactly:
- One overall summary sentence, using only FACT numbers.
- 3 to 6 bullet points, each starting with "- ", each restating one FACT
  line in plain language. (Rule 6 above is mandatory when it applies —
  count it as one of your bullets, don't skip it to stay under a limit.)
- Keep it under 150 words. No headers, no markdown besides the bullets.

FACTS:
{$factsBlock}
PROMPT;

        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 600,
            ],
        ]);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            error_log("Geminiinsights: API call failed (HTTP {$httpCode}): {$curlError} | Response: " . substr((string)$response, 0, 500));
            return '';
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($text);
    }

    private function readCache(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!$decoded || !isset($decoded['generated_at'], $decoded['text'])) {
            return null;
        }
        if (time() - $decoded['generated_at'] > $this->cacheTtlSeconds) {
            return null;
        }
        return $decoded['text'];
    }

    private function writeCache(string $file, string $text): void
    {
        @file_put_contents($file, json_encode([
            'generated_at' => time(),
            'text' => $text,
        ]));
    }
}