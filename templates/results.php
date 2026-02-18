<?php
/** @var array $results */
/** @var array $incomeData */
/** @var array $balanceData */

$score = $results['score'];
$rating = $results['rating'];
$scoreClass = match (true) {
    $score >= 85 => 'excellent',
    $score >= 70 => 'good',
    $score >= 55 => 'fair',
    $score >= 40 => 'below',
    $score >= 25 => 'poor',
    default       => 'critical',
};
?>

<!-- Score Hero -->
<div class="score-hero">
    <div class="score-circle <?= $scoreClass ?>">
        <?= $score ?>
    </div>
    <div class="score-label"><?= htmlspecialchars($rating) ?></div>
</div>

<!-- Executive Summary -->
<?php if (!empty($results['executive_summary']['highlights'])): ?>
<div class="appraisal-box" style="margin-bottom:1.5rem;">
    <h2>Executive Summary</h2>
    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
        <?php foreach ($results['executive_summary']['highlights'] as $hl):
            $dotColor = match ($hl['status']) {
                'positive' => 'var(--color-green)',
                'neutral'  => 'var(--color-primary)',
                'caution'  => 'var(--color-yellow)',
                'negative' => 'var(--color-red)',
                default    => 'var(--color-text-muted)',
            };
        ?>
        <div style="background:var(--color-surface-alt);border-radius:8px;padding:0.6rem 1rem;display:flex;align-items:center;gap:0.5rem;">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0;"></span>
            <span style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;"><?= htmlspecialchars($hl['label']) ?></span>
            <span style="font-weight:600;font-size:0.95rem;"><?= htmlspecialchars($hl['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
        $sev = $results['executive_summary']['severity_counts'];
        $flagTotal = $results['executive_summary']['flag_count'];
        $oppTotal = $results['executive_summary']['opportunity_count'];
    ?>
    <p style="font-size:0.9rem;color:var(--color-text-muted);">
        <strong><?= $oppTotal ?></strong> strength<?= $oppTotal !== 1 ? 's' : '' ?> identified
        &nbsp;&bull;&nbsp;
        <strong><?= $flagTotal ?></strong> concern<?= $flagTotal !== 1 ? 's' : '' ?> flagged
        <?php if ($sev['critical'] > 0): ?>
            <span style="color:var(--color-red);font-weight:600;">
                &nbsp;(<?= $sev['critical'] ?> critical)
            </span>
        <?php endif; ?>
        <?php if ($sev['high'] > 0): ?>
            <span style="color:var(--color-orange);font-weight:600;">
                &nbsp;(<?= $sev['high'] ?> high)
            </span>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- Appraisal -->
<div class="appraisal-box">
    <h2>Appraisal</h2>
    <p><?= htmlspecialchars($results['appraisal']) ?></p>
</div>

<!-- Key Metrics -->
<?php if (!empty($results['metrics'])): ?>
<h2 class="section-title">Key Financial Metrics</h2>
<div class="metrics-grid">
    <?php
    $metricLabels = [
        'gross_margin' => ['Gross Margin', '%'],
        'operating_margin' => ['Operating Margin', '%'],
        'net_profit_margin' => ['Net Profit Margin', '%'],
        'ebitda_margin' => ['EBITDA Margin', '%'],
        'return_on_assets' => ['Return on Assets', '%'],
        'return_on_equity' => ['Return on Equity', '%'],
        'current_ratio' => ['Current Ratio', 'x'],
        'quick_ratio' => ['Quick Ratio', 'x'],
        'cash_ratio' => ['Cash Ratio', 'x'],
        'debt_to_equity' => ['Debt-to-Equity', 'x'],
        'debt_to_assets' => ['Debt-to-Assets', ''],
        'interest_coverage' => ['Interest Coverage', 'x'],
        'asset_turnover' => ['Asset Turnover', 'x'],
        'equity_ratio' => ['Equity Ratio', ''],
        'working_capital' => ['Working Capital', '$'],
        'receivables_pct_revenue' => ['Receivables % Rev', '%'],
        'cogs_pct_revenue' => ['COGS % Revenue', '%'],
        'opex_ratio' => ['OpEx % Revenue', '%'],
        'cash_pct_assets' => ['Cash % Assets', '%'],
        'inventory_pct_current_assets' => ['Inventory % Current Assets', '%'],
    ];

    foreach ($results['metrics'] as $key => $value):
        $label = $metricLabels[$key][0] ?? ucwords(str_replace('_', ' ', $key));
        $suffix = $metricLabels[$key][1] ?? '';
        $formatted = ($suffix === '$')
            ? '$' . number_format($value)
            : number_format($value, 2) . $suffix;
    ?>
    <div class="metric-card">
        <div class="label"><?= htmlspecialchars($label) ?></div>
        <div class="value"><?= $formatted ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Red Flags -->
<?php if (!empty($results['red_flags'])): ?>
<h2 class="section-title">Red Flags (<?= count($results['red_flags']) ?>)</h2>
<ul class="flag-list">
    <?php foreach ($results['red_flags'] as $flag): ?>
    <li class="severity-<?= htmlspecialchars($flag['severity'] ?? 'medium') ?>">
        <div class="category"><?= htmlspecialchars($flag['category']) ?></div>
        <div class="title">
            <?= htmlspecialchars($flag['title']) ?>
            <span class="severity-badge <?= htmlspecialchars($flag['severity'] ?? 'medium') ?>">
                <?= htmlspecialchars($flag['severity'] ?? 'medium') ?>
            </span>
        </div>
        <div class="detail"><?= htmlspecialchars($flag['detail']) ?></div>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Opportunities -->
<?php if (!empty($results['opportunities'])): ?>
<h2 class="section-title">Opportunities &amp; Strengths (<?= count($results['opportunities']) ?>)</h2>
<ul class="opp-list">
    <?php foreach ($results['opportunities'] as $opp): ?>
    <li>
        <div class="category"><?= htmlspecialchars($opp['category']) ?></div>
        <div class="title"><?= htmlspecialchars($opp['title']) ?></div>
        <div class="detail"><?= htmlspecialchars($opp['detail']) ?></div>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Score Breakdown -->
<?php if (!empty($results['score_breakdown'])): ?>
<details style="margin-bottom:1.5rem;">
    <summary style="cursor:pointer; color:var(--color-primary); font-weight:600; margin-bottom:0.5rem; font-size:1.1rem;">
        Score Breakdown &mdash; How the Score Was Calculated
    </summary>
    <div class="appraisal-box" style="margin-top:0.75rem;">
        <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:0.75rem;">
            The score starts at <strong>50</strong> (neutral baseline) and is adjusted up or down based on each financial metric assessed.
            Positive metrics add points; concerns deduct points. The final score is clamped to 0&ndash;100.
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                    <th style="padding:0.4rem 0.5rem;">Adjustment</th>
                    <th style="padding:0.4rem 0.5rem;">Reason</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td style="padding:0.35rem 0.5rem;font-weight:600;color:var(--color-text-muted);">50.0</td>
                    <td style="padding:0.35rem 0.5rem;color:var(--color-text-muted);">Baseline (neutral starting score)</td>
                </tr>
                <?php
                $runningTotal = 50.0;
                foreach ($results['score_breakdown'] as $entry):
                    $pts = $entry['points'];
                    $runningTotal += $pts;
                    $sign = $pts >= 0 ? '+' : '';
                    $color = $pts > 0 ? 'var(--color-green)' : ($pts < 0 ? 'var(--color-red)' : 'var(--color-text-muted)');
                ?>
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td style="padding:0.35rem 0.5rem;font-weight:600;color:<?= $color ?>;white-space:nowrap;"><?= $sign . $pts ?></td>
                    <td style="padding:0.35rem 0.5rem;"><?= htmlspecialchars($entry['label']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="border-top:2px solid var(--color-primary);">
                    <td style="padding:0.5rem;font-weight:700;font-size:1rem;"><?= $results['score'] ?></td>
                    <td style="padding:0.5rem;font-weight:700;">Final Score (clamped 0&ndash;100)</td>
                </tr>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>

<!-- Recommendation -->
<div class="recommendation-box">
    <h2>Recommendation</h2>
    <p><?= nl2br(htmlspecialchars($results['recommendation'])) ?></p>
</div>

<!-- Parsed Data Summary -->
<details style="margin-bottom:2rem;">
    <summary style="cursor:pointer; color:var(--color-primary); font-weight:600; margin-bottom:0.5rem;">
        View Parsed Financial Data
    </summary>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
        <div class="appraisal-box">
            <h2>Income Statement (Parsed)</h2>
            <?php
            $incFields = [
                'revenue' => 'Revenue',
                'cost_of_goods_sold' => 'Cost of Goods Sold',
                'gross_profit' => 'Gross Profit',
                'operating_expenses' => 'Operating Expenses',
                'operating_income' => 'Operating Income',
                'interest_expense' => 'Interest Expense',
                'income_tax' => 'Income Tax',
                'net_income' => 'Net Income',
                'depreciation_amortization' => 'Depreciation & Amortization',
                'ebitda' => 'EBITDA',
            ];
            foreach ($incFields as $k => $label):
                $val = $incomeData[$k] ?? null;
            ?>
            <p><strong><?= $label ?>:</strong>
                <?php if ($val !== null): ?>
                    $<?= number_format($val, 0) ?>
                <?php else: ?>
                    <span style="color:var(--color-text-muted);">Not detected</span>
                <?php endif; ?>
            </p>
            <?php endforeach; ?>
        </div>
        <div class="appraisal-box">
            <h2>Balance Sheet (Parsed)</h2>
            <?php
            $balFields = [
                'cash' => 'Cash & Equivalents',
                'accounts_receivable' => 'Accounts Receivable',
                'inventory' => 'Inventory',
                'total_current_assets' => 'Total Current Assets',
                'total_assets' => 'Total Assets',
                'property_plant_equipment' => 'Property, Plant & Equipment',
                'accounts_payable' => 'Accounts Payable',
                'short_term_debt' => 'Short-Term Debt',
                'total_current_liabilities' => 'Total Current Liabilities',
                'long_term_debt' => 'Long-Term Debt',
                'total_liabilities' => 'Total Liabilities',
                'total_equity' => 'Total Equity',
                'retained_earnings' => 'Retained Earnings',
            ];
            foreach ($balFields as $k => $label):
                $val = $balanceData[$k] ?? null;
            ?>
            <p><strong><?= $label ?>:</strong>
                <?php if ($val !== null): ?>
                    $<?= number_format($val, 0) ?>
                <?php else: ?>
                    <span style="color:var(--color-text-muted);">Not detected</span>
                <?php endif; ?>
            </p>
            <?php endforeach; ?>
        </div>
    </div>
</details>

<a href="index.php" class="back-link">&larr; Analyze Another Set of Documents</a>
