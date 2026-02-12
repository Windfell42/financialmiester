<?php

namespace FinancialMiester\Service;

use Smalot\PdfParser\Parser;

class PdfParserService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Write a line to the OCR debug log (var/log/ocr_debug.log).
     */
    private function debugLog(string $msg): void
    {
        static $logFile = null;
        if ($logFile === null) {
            $logDir = dirname(__DIR__, 2) . '/var/log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/ocr_debug.log';
        }
        @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
    }

    /**
     * Extract text content from a PDF file (all pages).
     * Tries multiple methods in order:
     *   1. Smalot\PdfParser (pure PHP — works everywhere)
     *   2. pdftotext CLI (lighter than OCR, sometimes available on shared hosts)
     *   3. Tesseract OCR (requires pdftoppm + tesseract binaries)
     */
    public function extractText(string $filePath): string
    {
        $this->debugLog("=== extractText START for: {$filePath} ===");

        // ── 1. Smalot\PdfParser (pure PHP) ──────────────────────────
        $text = $this->extractTextViaSmalot($filePath);
        $digitCount = preg_match_all('/\d/', $text);
        $this->debugLog("Smalot: " . strlen($text) . " chars, {$digitCount} digits");

        if ($digitCount >= 3) {
            $this->debugLog("Using Smalot output (has digits)");
            return $text;
        }

        // ── 2. pdftotext from poppler-utils (lighter than full OCR) ─
        $text = $this->extractTextViaPdftotext($filePath);
        $digitCount = preg_match_all('/\d/', $text);
        $this->debugLog("pdftotext: " . strlen($text) . " chars, {$digitCount} digits");

        if ($digitCount >= 3) {
            $this->debugLog("Using pdftotext output");
            return $text;
        }

        // ── 3. Full OCR (Tesseract + pdftoppm) ──────────────────────
        $text = $this->extractTextViaOcr($filePath);
        $digitCount = preg_match_all('/\d/', $text);
        $this->debugLog("OCR: " . strlen($text) . " chars, {$digitCount} digits");

        if ($digitCount >= 3) {
            $this->debugLog("Using OCR output");
            return $this->cleanOcrText($text);
        }

        // ── Nothing worked — return whatever Smalot got (even if empty) ──
        // Re-extract with Smalot so the diagnostic page at least shows raw output
        $this->debugLog("WARNING: No extraction method produced useful text");
        return $this->extractTextViaSmalot($filePath);
    }

    /**
     * Extract text using the pure-PHP Smalot\PdfParser library.
     * Tries whole-document extraction first, then page-by-page as a fallback.
     */
    private function extractTextViaSmalot(string $filePath): string
    {
        try {
            $pdf = $this->parser->parseFile($filePath);

            // Try whole-document extraction first
            $text = $pdf->getText();
            $digitCount = preg_match_all('/\d/', $text);

            if ($digitCount >= 3) {
                return $text;
            }

            // Fallback: extract page by page (sometimes works when getText() doesn't)
            $pages = $pdf->getPages();
            $this->debugLog("Smalot page-by-page: " . count($pages) . " pages found");
            $pageTexts = [];

            foreach ($pages as $i => $page) {
                try {
                    $pageText = $page->getText();
                    $this->debugLog("  Page {$i}: " . strlen($pageText) . " chars");
                    $pageTexts[] = $pageText;
                } catch (\Throwable $e) {
                    $this->debugLog("  Page {$i} FAILED: " . $e->getMessage());
                }
            }

            $combined = implode("\n", $pageTexts);
            return strlen($combined) > strlen($text) ? $combined : $text;
        } catch (\Throwable $e) {
            $this->debugLog("Smalot EXCEPTION: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Try extracting text via the pdftotext CLI tool (from poppler-utils).
     * This is lighter than full OCR and is sometimes available on shared hosts.
     */
    private function extractTextViaPdftotext(string $filePath): string
    {
        // Try common binary locations
        $candidates = ['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'];
        $binary = null;

        foreach ($candidates as $path) {
            if (@is_executable($path)) {
                $binary = $path;
                break;
            }
        }

        // Also try bare command (might be on PATH)
        if ($binary === null) {
            $output = [];
            $code = 0;
            @exec('which pdftotext 2>/dev/null', $output, $code);
            if ($code === 0 && !empty($output[0])) {
                $binary = trim($output[0]);
            }
        }

        if ($binary === null) {
            $this->debugLog("pdftotext: NOT FOUND");
            return '';
        }

        $this->debugLog("pdftotext binary: {$binary}");
        $escaped = escapeshellarg($filePath);
        $output = [];
        $code = 0;
        exec("{$binary} -layout {$escaped} - 2>/dev/null", $output, $code);

        if ($code !== 0) {
            $this->debugLog("pdftotext exit code: {$code}");
            return '';
        }

        return implode("\n", $output);
    }

    /**
     * Convert PDF pages to images with pdftoppm, then OCR each with Tesseract.
     * Tries multiple page-segmentation modes to get the best result.
     */
    private function extractTextViaOcr(string $filePath): string
    {
        // Use absolute paths — web server PATH may not include /usr/bin
        $pdftoppm = '/usr/bin/pdftoppm';
        $tesseract = '/usr/bin/tesseract';

        if (!@is_executable($pdftoppm) || !@is_executable($tesseract)) {
            $this->debugLog("OCR: binaries not available (pdftoppm="
                . (@is_executable($pdftoppm) ? 'yes' : 'NO')
                . ", tesseract=" . (@is_executable($tesseract) ? 'yes' : 'NO') . ")");
            return '';
        }

        $tmpDir = sys_get_temp_dir() . '/fm_ocr_' . uniqid('', true);
        mkdir($tmpDir, 0755, true);

        try {
            $pdfEscaped = escapeshellarg($filePath);
            $prefixEscaped = escapeshellarg($tmpDir . '/page');
            $output = [];
            $code = 0;
            exec("{$pdftoppm} -r 300 -png {$pdfEscaped} {$prefixEscaped} 2>&1", $output, $code);

            if ($code !== 0) {
                $this->debugLog("pdftoppm failed (exit {$code}): " . implode("\n", $output));
                return '';
            }

            $images = glob($tmpDir . '/page-*.png');
            if (empty($images)) {
                return '';
            }
            sort($images);

            $psmModes = [4, 3, 6];
            $bestText = '';
            $bestScore = 0;

            foreach ($psmModes as $psm) {
                $attempt = '';
                foreach ($images as $image) {
                    $imgEscaped = escapeshellarg($image);
                    $ocrLines = [];
                    $ocrCode = 0;
                    exec("{$tesseract} {$imgEscaped} stdout --psm {$psm} 2>/dev/null", $ocrLines, $ocrCode);
                    if ($ocrCode === 0) {
                        $attempt .= implode("\n", $ocrLines) . "\n";
                    }
                }
                $score = preg_match_all('/^.*\d+.*$/m', $attempt);
                $this->debugLog("PSM {$psm}: {$score} lines with digits, " . strlen($attempt) . " chars");
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestText = $attempt;
                }
            }

            return $bestText;
        } finally {
            $files = glob($tmpDir . '/*');
            foreach ($files as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    /**
     * Clean up common OCR artifacts in extracted text.
     */
    private function cleanOcrText(string $text): string
    {
        // Fix spaced-out letters: "R e v e n u e" → "Revenue"
        // Detect lines where most "words" are single characters
        $lines = explode("\n", $text);
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $cleaned[] = '';
                continue;
            }

            // Check if the line looks like spaced-out text (mostly single chars separated by spaces)
            // e.g. "T o t a l   R e v e n u e   1 , 2 3 4"
            $words = preg_split('/\s+/', $trimmed);
            $singleCharCount = count(array_filter($words, fn($w) => mb_strlen($w) === 1));
            if (count($words) > 4 && $singleCharCount / count($words) > 0.6) {
                // Collapse by removing spaces between single chars
                $trimmed = preg_replace('/(?<=\b\w)\s+(?=\w\b)/', '', $trimmed);
                // Re-insert spaces at transitions: lowercase→uppercase, letter→digit, digit→letter
                $trimmed = preg_replace('/([a-z])([A-Z])/', '$1 $2', $trimmed);
                $trimmed = preg_replace('/([a-zA-Z])(\d)/', '$1 $2', $trimmed);
                $trimmed = preg_replace('/(\d)([a-zA-Z])/', '$1 $2', $trimmed);
            }

            // Fix common OCR character substitutions in number regions
            // Replace O/o with 0, l/I with 1 when they appear inside number-like sequences
            $trimmed = preg_replace_callback(
                '/(\$?\s*[\d\(][\dOolIBSs,\.\s\(\)\-]+)/',
                function ($m) {
                    $s = $m[0];
                    $s = str_replace(['O', 'o'], '0', $s);
                    $s = str_replace(['l', 'I'], '1', $s);
                    $s = str_replace('B', '8', $s);
                    $s = str_replace(['S', 's'], ['$', '5'], $s);
                    return $s;
                },
                $trimmed
            );

            // Fix common OCR character substitutions in the label portion
            if (preg_match('/^(.*?)(\s*[\d\$\(].*)$/', $trimmed, $parts)) {
                $label = $parts[1];
                $numbers = $parts[2];

                // Common OCR misreads in financial labels
                $label = preg_replace('/\bIlncome\b/i', 'Income', $label);
                $label = preg_replace('/\bRovenue\b/i', 'Revenue', $label);
                $label = preg_replace('/\bTotai\b/i', 'Total', $label);
                $label = preg_replace('/\bExpenses?\b/i', 'Expenses', $label);
                $label = str_replace(['|', '!'], 'l', $label);

                $trimmed = $label . $numbers;
            }

            $cleaned[] = $trimmed;
        }

        return implode("\n", $cleaned);
    }

    /**
     * Parse an Income Statement PDF and return structured data.
     */
    public function parseIncomeStatement(string $filePath): array
    {
        $this->debugLog("=== parseIncomeStatement ===");
        $text = $this->extractText($filePath);
        $lines = $this->normalizeLines($text);
        $merged = $this->mergeAdjacentLines($lines);
        $this->debugLog("Normalized lines: " . count($lines) . ", merged lines: " . count($merged));
        return $this->extractIncomeStatementData($merged, $text);
    }

    /**
     * Parse a Balance Sheet PDF and return structured data.
     */
    public function parseBalanceSheet(string $filePath): array
    {
        $this->debugLog("=== parseBalanceSheet ===");
        $text = $this->extractText($filePath);
        $lines = $this->normalizeLines($text);
        $merged = $this->mergeAdjacentLines($lines);
        $this->debugLog("Normalized lines: " . count($lines) . ", merged lines: " . count($merged));
        return $this->extractBalanceSheetData($merged, $text);
    }

    private function normalizeLines(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines, fn(string $line) => $line !== ''));
    }

    /**
     * PDF parsers often split a label and its value across multiple lines.
     * This merges text-only lines with the next number-containing line,
     * and also collapses lines with excessive whitespace into a single line
     * with spaces (handling columnar layouts).
     * OCR text can fragment a single row across 3+ lines, so we accumulate
     * consecutive text-only lines and merge them all with the first number line.
     */
    private function mergeAdjacentLines(array $lines): array
    {
        $merged = [];
        $count = count($lines);
        $pendingLabels = [];

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Collapse multiple spaces/tabs into a single space
            $line = preg_replace('/\s{2,}/', ' ', $line);

            if (!$this->hasNumber($line)) {
                // Accumulate text-only lines as pending labels
                $pendingLabels[] = $line;

                // Don't accumulate more than 4 lines (avoid runaway merges)
                if (count($pendingLabels) > 4) {
                    $merged[] = array_shift($pendingLabels);
                }
            } else {
                // This line has a number — merge any pending labels onto it
                if (!empty($pendingLabels)) {
                    $line = implode(' ', $pendingLabels) . ' ' . $line;
                    $pendingLabels = [];
                }
                $merged[] = $line;
            }
        }

        // Flush any remaining label-only lines
        foreach ($pendingLabels as $label) {
            $merged[] = $label;
        }

        return $merged;
    }

    private function hasNumber(string $line): bool
    {
        return (bool) preg_match('/\d/', $line);
    }

    /**
     * Extract a dollar amount from text.
     * Handles many formats:
     *   $1,234,567   1,234,567   1234567   1234567.89
     *   ($1,234)     (1,234)     -1,234    -$1,234
     *   $ 1,234      1 234 567 (space-grouped digits)
     */
    private function extractLastAmount(string $line): ?float
    {
        // Pattern covers: optional parens/minus, optional $, digits with commas/spaces/dots
        $pattern = '/(?<!\w)(\(?\s*-?\s*\$?\s*\d[\d,\s]*(?:\.\d{1,2})?\s*\)?)(?!\w)/';

        preg_match_all($pattern, $line, $matches);
        if (empty($matches[1])) {
            return null;
        }

        // Filter out matches that look like years (4-digit numbers between 1900-2099
        // with no $ sign, comma, decimal, or parentheses)
        $candidates = [];
        foreach ($matches[1] as $m) {
            $trimmed = trim($m);
            $digitsOnly = preg_replace('/[^0-9]/', '', $trimmed);

            // Skip standalone years (e.g., "2024", "2025")
            $isYear = (strlen($digitsOnly) === 4
                && !str_contains($trimmed, '$')
                && !str_contains($trimmed, ',')
                && !str_contains($trimmed, '.')
                && !str_contains($trimmed, '(')
                && (int) $digitsOnly >= 1900
                && (int) $digitsOnly <= 2099);

            if ($isYear) {
                continue;
            }

            $candidates[] = $trimmed;
        }

        if (empty($candidates)) {
            return null;
        }

        // Take the last non-year match
        $raw = end($candidates);
        $digits = preg_replace('/[^0-9]/', '', $raw);

        // Allow even single-digit amounts if preceded by $ or in parens
        // Only skip if it's a bare single digit with no financial markers
        if (strlen($digits) === 0) {
            return null;
        }
        if (strlen($digits) === 1
            && !str_contains($raw, '$')
            && !str_contains($raw, '(')
            && !str_contains($raw, '-')) {
            return null;
        }

        $negative = (str_contains($raw, '(') && str_contains($raw, ')'))
                 || str_contains($raw, '-');
        $cleaned = (float) preg_replace('/[^0-9.]/', '', $raw);

        return $negative ? -$cleaned : $cleaned;
    }

    /**
     * Check if a line matches any of the given keywords (case-insensitive).
     * Uses both exact substring matching and fuzzy matching to handle OCR errors.
     */
    private function lineMatchesAny(string $line, array $keywords): bool
    {
        // Normalize: lowercase + collapse whitespace + strip non-alphanumeric except spaces
        $lower = strtolower(preg_replace('/\s+/', ' ', $line));
        // Also create a stripped version for fuzzy matching (letters and spaces only)
        $stripped = preg_replace('/[^a-z\s]/', '', $lower);
        $stripped = preg_replace('/\s+/', ' ', trim($stripped));

        foreach ($keywords as $keyword) {
            $kw = strtolower($keyword);

            // Exact substring match
            if (str_contains($lower, $kw)) {
                return true;
            }

            // Fuzzy match: compare stripped versions using Levenshtein distance
            // Only match against the label portion (strip trailing numbers)
            $kwStripped = preg_replace('/[^a-z\s]/', '', $kw);
            $kwStripped = preg_replace('/\s+/', ' ', trim($kwStripped));

            if ($kwStripped === '') {
                continue;
            }

            // Check if the stripped line contains a substring close to the keyword
            // Use a sliding window approach for longer lines
            $kwLen = strlen($kwStripped);
            $strippedLen = strlen($stripped);

            // For short keywords (< 8 chars), require exact substring match to avoid false positives
            if ($kwLen < 8) {
                if (str_contains($stripped, $kwStripped)) {
                    return true;
                }
                continue;
            }

            // For longer keywords, allow up to ~20% character errors via Levenshtein
            $maxDist = max(1, (int) ceil($kwLen * 0.2));

            // If the whole stripped line is close in length to the keyword, compare directly
            if (abs($strippedLen - $kwLen) <= $maxDist) {
                if (levenshtein($stripped, $kwStripped) <= $maxDist) {
                    return true;
                }
            }

            // Slide a window of kwLen (+/- 2 chars) across the stripped line
            if ($strippedLen >= $kwLen - 2) {
                $loopEnd = max(0, $strippedLen - $kwLen + 3);
                for ($start = 0; $start <= $loopEnd; $start++) {
                    foreach ([$kwLen - 2, $kwLen - 1, $kwLen, $kwLen + 1, $kwLen + 2] as $windowSize) {
                        if ($windowSize < 1 || $start + $windowSize > $strippedLen) {
                            continue;
                        }
                        $substr = substr($stripped, $start, $windowSize);
                        $dist = levenshtein($substr, $kwStripped);
                        if ($dist <= $maxDist) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    private function extractIncomeStatementData(array $lines, string $rawText): array
    {
        $data = [
            'revenue' => null,
            'cost_of_goods_sold' => null,
            'gross_profit' => null,
            'operating_expenses' => null,
            'operating_income' => null,
            'interest_expense' => null,
            'income_tax' => null,
            'net_income' => null,
            'depreciation_amortization' => null,
            'ebitda' => null,
            'raw_text' => $rawText,
            'line_items' => [],
        ];

        // Keywords ordered from most-specific to least-specific within each field
        $fieldMap = [
            'revenue' => [
                'total revenue', 'total net revenue', 'net revenue', 'net sales',
                'total sales', 'gross revenue', 'revenue', 'sales', 'income earned',
                'total income', 'fee income', 'service revenue',
            ],
            'cost_of_goods_sold' => [
                'cost of goods sold', 'cost of revenue', 'cost of sales',
                'cost of products sold', 'cost of services', 'cogs',
                'total cost of revenue', 'cost of goods', 'direct costs',
                'direct cost of revenue', 'direct cost of sales',
                'cost of services sold', 'cost of sales and services',
                'cost of products', 'direct expenses',
            ],
            'gross_profit' => [
                'gross profit', 'gross margin', 'gross income',
            ],
            'operating_expenses' => [
                'total operating expenses', 'operating expenses',
                'total expenses', 'general and administrative',
                'selling, general and administrative', 'sg&a', 'sga',
                'selling general and admin',
            ],
            'operating_income' => [
                'operating income', 'operating profit', 'operating loss',
                'income from operations', 'loss from operations', 'ebit',
                'earnings before interest',
            ],
            'interest_expense' => [
                'interest expense', 'interest cost', 'finance cost',
                'finance expense', 'interest and debt expense',
                'interest paid', 'net interest expense',
                'finance charges', 'financing costs', 'financing expense',
                'interest on loans', 'interest on debt', 'loan interest',
                'total interest expense', 'borrowing costs',
                'interest & financing', 'interest income (expense)',
                'interest charges',
            ],
            'income_tax' => [
                'income tax expense', 'provision for income tax',
                'income taxes', 'income tax', 'tax expense', 'tax provision',
            ],
            'net_income' => [
                'net income', 'net profit', 'net earnings', 'net loss',
                'profit after tax', 'income after tax', 'net income (loss)',
                'net income attributable', 'total net income',
            ],
            'depreciation_amortization' => [
                'depreciation and amortization', 'depreciation & amortization',
                'depreciation', 'amortization', 'd&a',
                'depreciation expense', 'amortization expense',
                'depreciation/amortization', 'dep. & amort.',
                'dep & amort', 'depr. and amort', 'depr and amort',
                'total depreciation', 'depreciation cost',
                'depreciation & amort', 'depr.', 'dep.',
            ],
        ];

        foreach ($lines as $line) {
            $amount = $this->extractLastAmount($line);
            if ($amount === null) {
                continue;
            }

            $data['line_items'][] = ['label' => $line, 'amount' => $amount];

            $matched = false;
            foreach ($fieldMap as $field => $keywords) {
                if ($data[$field] === null && $this->lineMatchesAny($line, $keywords)) {
                    $data[$field] = $amount;
                    $this->debugLog("  MATCHED '{$field}' = {$amount} from: " . mb_substr($line, 0, 100));
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $this->debugLog("  UNMATCHED line (amount={$amount}): " . mb_substr($line, 0, 100));
            }
        }

        // Compute derived metrics if not found directly
        if ($data['gross_profit'] === null && $data['revenue'] !== null && $data['cost_of_goods_sold'] !== null) {
            $data['gross_profit'] = $data['revenue'] - abs($data['cost_of_goods_sold']);
        }

        if ($data['ebitda'] === null && $data['operating_income'] !== null) {
            $depAmort = abs($data['depreciation_amortization'] ?? 0);
            $data['ebitda'] = $data['operating_income'] + $depAmort;
        }

        return $data;
    }

    private function extractBalanceSheetData(array $lines, string $rawText): array
    {
        $data = [
            'cash' => null,
            'accounts_receivable' => null,
            'inventory' => null,
            'total_current_assets' => null,
            'total_assets' => null,
            'property_plant_equipment' => null,
            'accounts_payable' => null,
            'short_term_debt' => null,
            'total_current_liabilities' => null,
            'long_term_debt' => null,
            'total_liabilities' => null,
            'total_equity' => null,
            'retained_earnings' => null,
            'raw_text' => $rawText,
            'line_items' => [],
        ];

        $fieldMap = [
            'cash' => [
                'cash and cash equivalents', 'cash & cash equivalents',
                'cash and equivalents', 'cash & equivalents',
                'cash, cash equivalents', 'total cash', 'cash',
            ],
            'accounts_receivable' => [
                'accounts receivable, net', 'accounts receivable net',
                'accounts receivable', 'trade receivables',
                'net receivables', 'receivables', 'trade accounts receivable',
            ],
            'inventory' => [
                'inventories, net', 'inventories net',
                'inventory', 'inventories', 'merchandise inventory',
            ],
            'total_current_assets' => [
                'total current assets', 'current assets total',
                'current assets',
            ],
            'total_assets' => [
                'total assets',
            ],
            'property_plant_equipment' => [
                'property, plant and equipment', 'property plant and equipment',
                'property, plant & equipment', 'property and equipment',
                'net property, plant', 'net property plant',
                'fixed assets', 'ppe',
            ],
            'accounts_payable' => [
                'accounts payable', 'trade payables', 'trade accounts payable',
            ],
            'short_term_debt' => [
                'short-term debt', 'short term debt',
                'current portion of long-term debt', 'current portion of debt',
                'notes payable', 'short-term borrowings',
            ],
            'total_current_liabilities' => [
                'total current liabilities', 'current liabilities total',
                'current liabilities',
            ],
            'long_term_debt' => [
                'long-term debt', 'long term debt', 'total long-term debt',
                'long-term borrowings', 'long term borrowings',
                'non-current debt', 'long-term liabilities',
            ],
            'total_liabilities' => [
                'total liabilities',
            ],
            'total_equity' => [
                'total equity', "total stockholders' equity",
                'total stockholders equity', "total shareholders' equity",
                'total shareholders equity', 'stockholders equity',
                'shareholders equity', "shareholders' equity",
                "stockholders' equity", 'total owner equity',
                'net worth', 'total net worth',
            ],
            'retained_earnings' => [
                'retained earnings', 'accumulated earnings',
                'retained surplus', 'accumulated deficit',
                'accumulated profit',
            ],
        ];

        foreach ($lines as $line) {
            $amount = $this->extractLastAmount($line);
            if ($amount === null) {
                continue;
            }

            $data['line_items'][] = ['label' => $line, 'amount' => $amount];

            $matched = false;
            foreach ($fieldMap as $field => $keywords) {
                if ($data[$field] === null && $this->lineMatchesAny($line, $keywords)) {
                    $data[$field] = $amount;
                    $this->debugLog("  MATCHED '{$field}' = {$amount} from: " . mb_substr($line, 0, 100));
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $this->debugLog("  UNMATCHED line (amount={$amount}): " . mb_substr($line, 0, 100));
            }
        }

        return $data;
    }
}
