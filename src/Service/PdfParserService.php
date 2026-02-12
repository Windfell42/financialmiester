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
     * Extract text content from a PDF file (all pages).
     */
    public function extractText(string $filePath): string
    {
        $pdf = $this->parser->parseFile($filePath);
        return $pdf->getText();
    }

    /**
     * Parse an Income Statement PDF and return structured data.
     */
    public function parseIncomeStatement(string $filePath): array
    {
        $text = $this->extractText($filePath);
        $lines = $this->normalizeLines($text);
        $merged = $this->mergeAdjacentLines($lines);
        return $this->extractIncomeStatementData($merged, $text);
    }

    /**
     * Parse a Balance Sheet PDF and return structured data.
     */
    public function parseBalanceSheet(string $filePath): array
    {
        $text = $this->extractText($filePath);
        $lines = $this->normalizeLines($text);
        $merged = $this->mergeAdjacentLines($lines);
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
     * This merges a text-only line with the next number-containing line,
     * and also collapses lines with excessive whitespace into a single line
     * with spaces (handling columnar layouts).
     */
    private function mergeAdjacentLines(array $lines): array
    {
        $merged = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Collapse multiple spaces/tabs into a single space
            $line = preg_replace('/\s{2,}/', ' ', $line);

            // If this line has no number and the next line starts with or is a number,
            // merge them so the label and value end up on one line.
            if (!$this->hasNumber($line) && $i + 1 < $count && $this->hasNumber($lines[$i + 1])) {
                $nextLine = preg_replace('/\s{2,}/', ' ', $lines[$i + 1]);
                $merged[] = $line . ' ' . $nextLine;
                $i++; // skip the next line since we consumed it
            } else {
                $merged[] = $line;
            }
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

        // Take the last match (typically most-recent period in multi-column statements)
        $raw = trim(end($matches[1]));

        // Skip very short matches that are likely dates or page numbers (e.g. "2024", "12")
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($digits) < 2) {
            // Try the second-to-last match if available
            if (count($matches[1]) >= 2) {
                $raw = trim($matches[1][count($matches[1]) - 2]);
                $digits = preg_replace('/[^0-9]/', '', $raw);
                if (strlen($digits) < 2) {
                    return null;
                }
            } else {
                return null;
            }
        }

        $negative = (str_contains($raw, '(') && str_contains($raw, ')'))
                 || str_contains($raw, '-');
        $cleaned = (float) preg_replace('/[^0-9.]/', '', $raw);

        return $negative ? -$cleaned : $cleaned;
    }

    /**
     * Check if a line matches any of the given keywords (case-insensitive).
     * Also handles keywords split by extra whitespace in PDF extraction.
     */
    private function lineMatchesAny(string $line, array $keywords): bool
    {
        // Normalize: lowercase + collapse whitespace
        $lower = strtolower(preg_replace('/\s+/', ' ', $line));
        foreach ($keywords as $keyword) {
            if (str_contains($lower, strtolower($keyword))) {
                return true;
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
                'total cost of revenue',
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
            ],
        ];

        foreach ($lines as $line) {
            $amount = $this->extractLastAmount($line);
            if ($amount === null) {
                continue;
            }

            $data['line_items'][] = ['label' => $line, 'amount' => $amount];

            foreach ($fieldMap as $field => $keywords) {
                if ($data[$field] === null && $this->lineMatchesAny($line, $keywords)) {
                    $data[$field] = $amount;
                    break;
                }
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

            foreach ($fieldMap as $field => $keywords) {
                if ($data[$field] === null && $this->lineMatchesAny($line, $keywords)) {
                    $data[$field] = $amount;
                    break;
                }
            }
        }

        return $data;
    }
}
