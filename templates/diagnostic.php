<?php
/** @var array $incomeData */
/** @var array $balanceData */
?>

<div class="error-box">
    <strong>No recognized financial fields could be matched.</strong><br>
    The raw text extracted from your PDFs is shown below so you can diagnose the issue.
    Common causes:
    <ul style="margin:0.5rem 0 0 1.2rem;">
        <li>The financial data uses non-standard labels or formatting that could not be recognized.</li>
        <li>The PDF has unusual encoding or very low scan quality that prevents accurate text extraction.</li>
        <li>The document language or layout differs from standard US financial statements.</li>
    </ul>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:2rem;">
    <!-- Income Statement raw text -->
    <div class="appraisal-box">
        <h2>Income Statement &mdash; Extracted Text</h2>
        <?php if (empty(trim($incomeData['raw_text']))): ?>
            <p style="color:var(--color-red);">
                <strong>No text was extracted.</strong> This PDF likely contains scanned images rather than
                selectable text. Consider using an OCR tool to convert it first.
            </p>
        <?php else: ?>
            <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:0.75rem;">
                Characters extracted: <?= number_format(strlen($incomeData['raw_text'])) ?>
                &nbsp;|&nbsp; Lines with amounts: <?= count($incomeData['line_items']) ?>
            </p>
            <?php if (!empty($incomeData['line_items'])): ?>
                <h3 style="font-size:0.9rem;margin:0.75rem 0 0.5rem;color:var(--color-primary);">Lines where amounts were detected:</h3>
                <div style="max-height:250px;overflow-y:auto;background:var(--color-bg);border-radius:8px;padding:0.75rem;font-size:0.8rem;font-family:monospace;">
                    <?php foreach ($incomeData['line_items'] as $item): ?>
                        <div style="margin-bottom:0.3rem;padding:0.2rem 0;border-bottom:1px solid var(--color-border);">
                            <span style="color:var(--color-text-muted);"><?= htmlspecialchars(mb_substr($item['label'], 0, 80)) ?></span>
                            <span style="color:var(--color-green);float:right;"><?= number_format($item['amount'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h3 style="font-size:0.9rem;margin:0.75rem 0 0.5rem;color:var(--color-primary);">Full raw text:</h3>
            <pre style="max-height:300px;overflow:auto;background:var(--color-bg);border-radius:8px;padding:0.75rem;font-size:0.75rem;white-space:pre-wrap;word-break:break-all;color:var(--color-text-muted);"><?= htmlspecialchars($incomeData['raw_text']) ?></pre>
        <?php endif; ?>
    </div>

    <!-- Balance Sheet raw text -->
    <div class="appraisal-box">
        <h2>Balance Sheet &mdash; Extracted Text</h2>
        <?php if (empty(trim($balanceData['raw_text']))): ?>
            <p style="color:var(--color-red);">
                <strong>No text was extracted.</strong> This PDF likely contains scanned images rather than
                selectable text. Consider using an OCR tool to convert it first.
            </p>
        <?php else: ?>
            <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:0.75rem;">
                Characters extracted: <?= number_format(strlen($balanceData['raw_text'])) ?>
                &nbsp;|&nbsp; Lines with amounts: <?= count($balanceData['line_items']) ?>
            </p>
            <?php if (!empty($balanceData['line_items'])): ?>
                <h3 style="font-size:0.9rem;margin:0.75rem 0 0.5rem;color:var(--color-primary);">Lines where amounts were detected:</h3>
                <div style="max-height:250px;overflow-y:auto;background:var(--color-bg);border-radius:8px;padding:0.75rem;font-size:0.8rem;font-family:monospace;">
                    <?php foreach ($balanceData['line_items'] as $item): ?>
                        <div style="margin-bottom:0.3rem;padding:0.2rem 0;border-bottom:1px solid var(--color-border);">
                            <span style="color:var(--color-text-muted);"><?= htmlspecialchars(mb_substr($item['label'], 0, 80)) ?></span>
                            <span style="color:var(--color-green);float:right;"><?= number_format($item['amount'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h3 style="font-size:0.9rem;margin:0.75rem 0 0.5rem;color:var(--color-primary);">Full raw text:</h3>
            <pre style="max-height:300px;overflow:auto;background:var(--color-bg);border-radius:8px;padding:0.75rem;font-size:0.75rem;white-space:pre-wrap;word-break:break-all;color:var(--color-text-muted);"><?= htmlspecialchars($balanceData['raw_text']) ?></pre>
        <?php endif; ?>
    </div>
</div>

<a href="index.php" class="back-link">&larr; Try Again with Different Documents</a>
