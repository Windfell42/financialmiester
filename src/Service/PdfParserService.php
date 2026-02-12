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
        return $this->extractIncomeStatementData($lines);
    }

    /**
     * Parse a Balance Sheet PDF and return structured data.
     */
    public function parseBalanceSheet(string $filePath): array
    {
        $text = $this->extractText($filePath);
        $lines = $this->normalizeLines($text);
        return $this->extractBalanceSheetData($lines);
    }

    private function normalizeLines(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $lines = array_map('trim', $lines);
        return array_filter($lines, fn(string $line) => $line !== '');
    }

    /**
     * Attempt to extract a dollar amount from a line of text.
     * Handles formats like: $1,234,567  (1,234,567)  -1234567  1234567.89
     * Parentheses indicate negative values.
     */
    private function extractAmount(string $line): ?float
    {
        // Look for numbers that may have $, commas, decimals, parentheses, or minus signs
        if (preg_match('/\(?\$?\s*-?\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?\)?/', $line, $matches)) {
            $raw = $matches[0];
            $negative = (str_contains($raw, '(') && str_contains($raw, ')')) || str_contains($raw, '-');
            $cleaned = (float) preg_replace('/[^0-9.]/', '', $raw);
            return $negative ? -$cleaned : $cleaned;
        }
        return null;
    }

    /**
     * Find the last numeric value on a line (typically the most recent period).
     */
    private function extractLastAmount(string $line): ?float
    {
        // Match all dollar-like amounts on the line
        preg_match_all('/\(?\$?\s*-?\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?\)?/', $line, $matches);
        if (empty($matches[0])) {
            return null;
        }
        $raw = end($matches[0]);
        $negative = (str_contains($raw, '(') && str_contains($raw, ')')) || str_contains($raw, '-');
        $cleaned = (float) preg_replace('/[^0-9.]/', '', $raw);
        return $negative ? -$cleaned : $cleaned;
    }

    /**
     * Check if a line matches any of the given keywords (case-insensitive).
     */
    private function lineMatchesAny(string $line, array $keywords): bool
    {
        $lower = strtolower($line);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    private function extractIncomeStatementData(array $lines): array
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
            'raw_text' => implode("\n", $lines),
            'line_items' => [],
        ];

        $fieldMap = [
            'revenue' => ['total revenue', 'net revenue', 'net sales', 'total sales', 'revenue', 'sales'],
            'cost_of_goods_sold' => ['cost of goods sold', 'cost of revenue', 'cost of sales', 'cogs'],
            'gross_profit' => ['gross profit', 'gross margin', 'gross income'],
            'operating_expenses' => ['total operating expenses', 'operating expenses', 'total expenses'],
            'operating_income' => ['operating income', 'operating profit', 'income from operations', 'ebit'],
            'interest_expense' => ['interest expense', 'interest cost', 'finance cost', 'finance expense'],
            'income_tax' => ['income tax', 'tax expense', 'provision for income tax', 'income taxes'],
            'net_income' => ['net income', 'net profit', 'net earnings', 'net loss', 'profit after tax'],
            'depreciation_amortization' => ['depreciation', 'amortization', 'depreciation and amortization', 'd&a'],
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

    private function extractBalanceSheetData(array $lines): array
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
            'raw_text' => implode("\n", $lines),
            'line_items' => [],
        ];

        $fieldMap = [
            'cash' => ['cash and cash equivalents', 'cash & equivalents', 'cash and equivalents', 'cash'],
            'accounts_receivable' => ['accounts receivable', 'trade receivables', 'receivables'],
            'inventory' => ['inventory', 'inventories'],
            'total_current_assets' => ['total current assets', 'current assets total'],
            'total_assets' => ['total assets'],
            'property_plant_equipment' => ['property, plant and equipment', 'property plant and equipment', 'ppe', 'fixed assets', 'property and equipment'],
            'accounts_payable' => ['accounts payable', 'trade payables'],
            'short_term_debt' => ['short-term debt', 'short term debt', 'current portion of long-term debt', 'notes payable'],
            'total_current_liabilities' => ['total current liabilities', 'current liabilities total'],
            'long_term_debt' => ['long-term debt', 'long term debt', 'total long-term debt', 'long-term borrowings'],
            'total_liabilities' => ['total liabilities'],
            'total_equity' => ['total equity', 'total stockholders equity', "total shareholders' equity", 'total shareholders equity', 'stockholders equity'],
            'retained_earnings' => ['retained earnings', 'accumulated earnings'],
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
