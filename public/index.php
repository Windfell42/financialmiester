<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FinancialMiester\Service\PdfParserService;
use FinancialMiester\Analysis\FinancialAnalyzer;

$error = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = handleUpload($error);
} else {
    $content = renderUploadForm($error);
}

echo renderLayout($content);

// ─── Functions ────────────────────────────────────────────────

function handleUpload(string &$error): string
{
    $incomeFile  = $_FILES['income_file']  ?? null;
    $balanceFile = $_FILES['balance_file'] ?? null;

    // Validate uploads
    if (!$incomeFile || $incomeFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid Income Statement PDF.';
        return renderUploadForm($error);
    }

    if (!$balanceFile || $balanceFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid Balance Sheet / Asset List PDF.';
        return renderUploadForm($error);
    }

    // Validate file types
    foreach (['income_file' => $incomeFile, 'balance_file' => $balanceFile] as $label => $file) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            $error = ucfirst(str_replace('_', ' ', $label)) . ' must be a PDF file (received: ' . $mime . ').';
            return renderUploadForm($error);
        }
    }

    // Validate file sizes (max 20MB each)
    $maxSize = 20 * 1024 * 1024;
    if ($incomeFile['size'] > $maxSize || $balanceFile['size'] > $maxSize) {
        $error = 'Each file must be under 20MB.';
        return renderUploadForm($error);
    }

    // Move to temporary storage
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $incomePath  = $uploadDir . uniqid('income_', true) . '.pdf';
    $balancePath = $uploadDir . uniqid('balance_', true) . '.pdf';

    move_uploaded_file($incomeFile['tmp_name'], $incomePath);
    move_uploaded_file($balanceFile['tmp_name'], $balancePath);

    try {
        $parser = new PdfParserService();

        $incomeData  = $parser->parseIncomeStatement($incomePath);
        $balanceData = $parser->parseBalanceSheet($balancePath);

        // Check if we extracted any meaningful data
        $incomeFieldsFound = count(array_filter(
            array_diff_key($incomeData, array_flip(['raw_text', 'line_items'])),
            fn($v) => $v !== null
        ));
        $balanceFieldsFound = count(array_filter(
            array_diff_key($balanceData, array_flip(['raw_text', 'line_items'])),
            fn($v) => $v !== null
        ));

        if ($incomeFieldsFound === 0 && $balanceFieldsFound === 0) {
            // Show diagnostic view so user can see what was extracted
            ob_start();
            include __DIR__ . '/../templates/diagnostic.php';
            return ob_get_clean();
        }

        $analyzer = new FinancialAnalyzer($incomeData, $balanceData);
        $results  = $analyzer->analyze();

        ob_start();
        include __DIR__ . '/../templates/results.php';
        return ob_get_clean();

    } catch (\Throwable $e) {
        $error = 'Error processing PDFs: ' . $e->getMessage();
        return renderUploadForm($error);
    } finally {
        // Clean up uploaded files
        @unlink($incomePath);
        @unlink($balancePath);
    }
}

function renderUploadForm(string $error): string
{
    ob_start();
    include __DIR__ . '/../templates/upload.php';
    return ob_get_clean();
}

function renderLayout(string $content): string
{
    ob_start();
    include __DIR__ . '/../templates/layout.php';
    return ob_get_clean();
}
