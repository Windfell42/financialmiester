<?php

namespace FinancialMiester\Analysis;

class FinancialAnalyzer
{
    private array $incomeData;
    private array $balanceData;
    private array $redFlags = [];
    private array $opportunities = [];
    private array $metrics = [];
    private array $scoreBreakdown = [];
    private float $score = 50.0; // Start at neutral

    public function __construct(array $incomeData, array $balanceData)
    {
        $this->incomeData = $incomeData;
        $this->balanceData = $balanceData;
    }

    public function analyze(): array
    {
        $this->computeMetrics();
        $this->assessProfitability();
        $this->assessLiquidity();
        $this->assessCashPosition();
        $this->assessLeverage();
        $this->assessEfficiency();
        $this->assessCostStructure();
        $this->assessInventoryRisk();
        $this->assessGrowthIndicators();
        $this->assessDataCompleteness();
        $this->clampScore();

        return [
            'metrics' => $this->metrics,
            'red_flags' => $this->redFlags,
            'opportunities' => $this->opportunities,
            'score' => round($this->score, 1),
            'score_breakdown' => $this->scoreBreakdown,
            'rating' => $this->scoreToRating($this->score),
            'executive_summary' => $this->generateExecutiveSummary(),
            'appraisal' => $this->generateAppraisal(),
            'recommendation' => $this->generateRecommendation(),
        ];
    }

    /**
     * Record a score adjustment with a human-readable label.
     */
    private function adjustScore(float $points, string $label): void
    {
        $this->score += $points;
        $this->scoreBreakdown[] = [
            'points' => $points,
            'label' => $label,
        ];
    }

    // ─── Metric Computation ──────────────────────────────────────

    /**
     * Derive missing income statement totals from their components
     * when the PDF parser couldn't find the aggregate line.
     */
    private function deriveIncomeFields(): void
    {
        $inc = &$this->incomeData;

        // Gross Profit = Revenue - COGS
        if ($inc['gross_profit'] === null && $inc['revenue'] !== null && $inc['cost_of_goods_sold'] !== null) {
            $inc['gross_profit'] = $inc['revenue'] - $inc['cost_of_goods_sold'];
        }

        // Operating Income = Gross Profit - Operating Expenses
        if ($inc['operating_income'] === null && $inc['gross_profit'] !== null && $inc['operating_expenses'] !== null) {
            $inc['operating_income'] = $inc['gross_profit'] - $inc['operating_expenses'];
        }

        // EBITDA = Operating Income + D&A (if not already set)
        if ($inc['ebitda'] === null && $inc['operating_income'] !== null && $inc['depreciation_amortization'] !== null) {
            $inc['ebitda'] = $inc['operating_income'] + abs($inc['depreciation_amortization']);
        }
    }

    private function computeMetrics(): void
    {
        $this->deriveIncomeFields();

        $inc = $this->incomeData;
        $bal = $this->balanceData;

        // Profitability ratios
        if ($inc['revenue'] && $inc['revenue'] != 0) {
            if ($inc['gross_profit'] !== null) {
                $this->metrics['gross_margin'] = round(($inc['gross_profit'] / $inc['revenue']) * 100, 2);
            }
            if ($inc['operating_income'] !== null) {
                $this->metrics['operating_margin'] = round(($inc['operating_income'] / $inc['revenue']) * 100, 2);
            }
            if ($inc['net_income'] !== null) {
                $this->metrics['net_profit_margin'] = round(($inc['net_income'] / $inc['revenue']) * 100, 2);
            }
        }

        // Return on Assets
        if ($inc['net_income'] !== null && $bal['total_assets'] && $bal['total_assets'] != 0) {
            $this->metrics['return_on_assets'] = round(($inc['net_income'] / $bal['total_assets']) * 100, 2);
        }

        // Return on Equity
        if ($inc['net_income'] !== null && $bal['total_equity'] && $bal['total_equity'] != 0) {
            $this->metrics['return_on_equity'] = round(($inc['net_income'] / $bal['total_equity']) * 100, 2);
        }

        // Liquidity ratios
        if ($bal['total_current_liabilities'] && $bal['total_current_liabilities'] != 0) {
            if ($bal['total_current_assets'] !== null) {
                $this->metrics['current_ratio'] = round($bal['total_current_assets'] / $bal['total_current_liabilities'], 2);
            }
            // Quick ratio (current assets minus inventory)
            if ($bal['total_current_assets'] !== null) {
                $inventory = $bal['inventory'] ?? 0;
                $this->metrics['quick_ratio'] = round(
                    ($bal['total_current_assets'] - $inventory) / $bal['total_current_liabilities'],
                    2
                );
            }
            // Cash ratio
            if ($bal['cash'] !== null) {
                $this->metrics['cash_ratio'] = round($bal['cash'] / $bal['total_current_liabilities'], 2);
            }
        }

        // Leverage ratios
        if ($bal['total_assets'] && $bal['total_assets'] != 0 && $bal['total_liabilities'] !== null) {
            $this->metrics['debt_to_assets'] = round($bal['total_liabilities'] / $bal['total_assets'], 2);
        }

        if ($bal['total_equity'] && $bal['total_equity'] != 0 && $bal['total_liabilities'] !== null) {
            $this->metrics['debt_to_equity'] = round($bal['total_liabilities'] / $bal['total_equity'], 2);
        }

        // Interest coverage
        if ($inc['interest_expense'] && $inc['interest_expense'] != 0 && $inc['operating_income'] !== null) {
            $this->metrics['interest_coverage'] = round(
                $inc['operating_income'] / abs($inc['interest_expense']),
                2
            );
        }

        // EBITDA margin
        if ($inc['ebitda'] !== null && $inc['revenue'] && $inc['revenue'] != 0) {
            $this->metrics['ebitda_margin'] = round(($inc['ebitda'] / $inc['revenue']) * 100, 2);
        }

        // Working capital
        if ($bal['total_current_assets'] !== null && $bal['total_current_liabilities'] !== null) {
            $this->metrics['working_capital'] = $bal['total_current_assets'] - $bal['total_current_liabilities'];
        }

        // Equity ratio
        if ($bal['total_equity'] !== null && $bal['total_assets'] && $bal['total_assets'] != 0) {
            $this->metrics['equity_ratio'] = round($bal['total_equity'] / $bal['total_assets'], 2);
        }

        // COGS as % of revenue (cost structure)
        if ($inc['cost_of_goods_sold'] !== null && $inc['revenue'] && $inc['revenue'] != 0) {
            $this->metrics['cogs_pct_revenue'] = round(($inc['cost_of_goods_sold'] / $inc['revenue']) * 100, 2);
        }

        // Operating expense ratio (opex / revenue)
        if ($inc['operating_expenses'] !== null && $inc['revenue'] && $inc['revenue'] != 0) {
            $this->metrics['opex_ratio'] = round(($inc['operating_expenses'] / $inc['revenue']) * 100, 2);
        }

        // Cash as % of total assets
        if ($bal['cash'] !== null && $bal['total_assets'] && $bal['total_assets'] != 0) {
            $this->metrics['cash_pct_assets'] = round(($bal['cash'] / $bal['total_assets']) * 100, 2);
        }

        // Inventory as % of current assets
        if ($bal['inventory'] !== null && $bal['total_current_assets'] && $bal['total_current_assets'] != 0) {
            $this->metrics['inventory_pct_current_assets'] = round(($bal['inventory'] / $bal['total_current_assets']) * 100, 2);
        }
    }

    // ─── Profitability Assessment ────────────────────────────────

    private function assessProfitability(): void
    {
        // Gross Margin
        if (isset($this->metrics['gross_margin'])) {
            $gm = $this->metrics['gross_margin'];
            if ($gm > 50) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Strong Gross Margin',
                    'detail' => "Gross margin of {$gm}% indicates strong pricing power or efficient production.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(8, "Gross margin {$gm}% > 50% (strong)");
            } elseif ($gm > 30) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Healthy Gross Margin',
                    'detail' => "Gross margin of {$gm}% is within a healthy range.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Gross margin {$gm}% > 30% (healthy)");
            } elseif ($gm >= 25) {
                // Moderate — not flagged before, now surfaced as a concern
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Moderate Gross Margin',
                    'detail' => "Gross margin of {$gm}% is adequate but leaves limited buffer against cost increases. Industry leaders typically exceed 35%.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Gross margin {$gm}% is moderate (25-30%)");
            } elseif ($gm >= 15) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Below-Average Gross Margin',
                    'detail' => "Gross margin of {$gm}% is below average. Cost structure or pricing strategy should be evaluated.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-5, "Gross margin {$gm}% below average (15-25%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Gross Margin',
                    'detail' => "Gross margin of {$gm}% is very thin — vulnerable to cost increases or pricing pressure.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-10, "Gross margin {$gm}% critically low (<15%)");
            }
        }

        // Operating Margin
        if (isset($this->metrics['operating_margin'])) {
            $om = $this->metrics['operating_margin'];
            if ($om > 20) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Strong Operating Margin',
                    'detail' => "Operating margin of {$om}% shows strong operational efficiency.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(7, "Operating margin {$om}% > 20% (strong)");
            } elseif ($om > 10) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Adequate Operating Margin',
                    'detail' => "Operating margin of {$om}% is reasonable but below the 15-20% range seen in operationally efficient companies.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(3, "Operating margin {$om}% adequate (10-20%)");
            } elseif ($om >= 5) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Thin Operating Margin',
                    'detail' => "Operating margin of {$om}% leaves little room for error. A small revenue decline or cost increase could eliminate profitability.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-5, "Operating margin {$om}% thin (5-10%)");
            } elseif ($om >= 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Near-Zero Operating Margin',
                    'detail' => "Operating margin of {$om}% means the company barely breaks even on operations. Any downturn would produce losses.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-8, "Operating margin {$om}% near zero (0-5%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Operating Loss',
                    'detail' => "Operating margin of {$om}% indicates the company is losing money from core operations.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-15, "Operating margin {$om}% negative (operating loss)");
            }
        }

        // Net Profit Margin
        if (isset($this->metrics['net_profit_margin'])) {
            $npm = $this->metrics['net_profit_margin'];
            if ($npm > 15) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Excellent Net Margin',
                    'detail' => "Net profit margin of {$npm}% is excellent, showing strong bottom-line performance.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(8, "Net profit margin {$npm}% > 15% (excellent)");
            } elseif ($npm > 5) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Positive Net Margin',
                    'detail' => "Net profit margin of {$npm}% is positive but moderate. Consider whether overhead, interest, or taxes can be optimized.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(3, "Net profit margin {$npm}% positive (5-15%)");
            } elseif ($npm >= 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Thin Net Margin',
                    'detail' => "Net profit margin of {$npm}% is barely positive. The company retains very little of each dollar earned.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "Net profit margin {$npm}% barely positive (0-5%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Net Loss',
                    'detail' => "Net profit margin of {$npm}% — the company is unprofitable after all expenses.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-12, "Net profit margin {$npm}% negative (net loss)");
            }
        }

        // Return on Equity
        if (isset($this->metrics['return_on_equity'])) {
            $roe = $this->metrics['return_on_equity'];
            if ($roe > 15) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Strong Return on Equity',
                    'detail' => "ROE of {$roe}% indicates efficient use of shareholder capital.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(6, "ROE {$roe}% > 15% (strong)");
            } elseif ($roe > 5) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Moderate Return on Equity',
                    'detail' => "ROE of {$roe}% is below the 15% benchmark that typically signals efficient capital use. Shareholders may find better returns elsewhere.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "ROE {$roe}% moderate (5-15%)");
            } elseif ($roe >= 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Return on Equity',
                    'detail' => "ROE of {$roe}% is below typical benchmarks. Capital may be better deployed elsewhere.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "ROE {$roe}% low (0-5%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Negative Return on Equity',
                    'detail' => "ROE of {$roe}% means shareholders are losing value.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-8, "ROE {$roe}% negative");
            }
        }

        // Return on Assets
        if (isset($this->metrics['return_on_assets'])) {
            $roa = $this->metrics['return_on_assets'];
            if ($roa > 10) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'High Return on Assets',
                    'detail' => "ROA of {$roa}% shows the company generates strong returns from its asset base.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(5, "ROA {$roa}% > 10% (strong)");
            } elseif ($roa > 3) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Moderate Return on Assets',
                    'detail' => "ROA of {$roa}% is adequate but not exceptional. Consider whether the asset base could generate higher returns.",
                    'severity' => 'low',
                ];
                $this->adjustScore(0, "ROA {$roa}% adequate (3-10%)");
            } elseif ($roa >= 1) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Return on Assets',
                    'detail' => "ROA of {$roa}% suggests the asset base is underperforming. Asset-heavy balance sheets require stronger returns.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "ROA {$roa}% low (1-3%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Very Low Return on Assets',
                    'detail' => "ROA of {$roa}% suggests assets are not generating adequate returns.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-6, "ROA {$roa}% very low (<1%)");
            }
        }
    }

    // ─── Liquidity Assessment ────────────────────────────────────

    private function assessLiquidity(): void
    {
        // Current Ratio
        if (isset($this->metrics['current_ratio'])) {
            $cr = $this->metrics['current_ratio'];
            if ($cr < 1.0) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Current Ratio Below 1.0',
                    'detail' => "Current ratio of {$cr} means current liabilities exceed current assets — potential solvency risk.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-12, "Current ratio {$cr} < 1.0 (solvency risk)");
            } elseif ($cr < 1.5) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Tight Liquidity',
                    'detail' => "Current ratio of {$cr} is below the comfortable threshold of 1.5. An unexpected obligation could create cash flow strain.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "Current ratio {$cr} tight (1.0-1.5)");
            } elseif ($cr <= 3.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Healthy Current Ratio',
                    'detail' => "Current ratio of {$cr} indicates adequate short-term liquidity.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(5, "Current ratio {$cr} healthy (1.5-3.0)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Excess Liquidity',
                    'detail' => "Current ratio of {$cr} is very high — substantial capital is sitting idle rather than being reinvested. This may indicate overly conservative management or lack of growth opportunities.",
                    'severity' => 'low',
                ];
                $this->adjustScore(1, "Current ratio {$cr} excess liquidity (>3.0)");
            }
        }

        // Quick Ratio
        if (isset($this->metrics['quick_ratio'])) {
            $qr = $this->metrics['quick_ratio'];
            if ($qr < 0.5) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Very Low Quick Ratio',
                    'detail' => "Quick ratio of {$qr} indicates heavy reliance on inventory to meet obligations.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-7, "Quick ratio {$qr} very low (<0.5)");
            } elseif ($qr < 1.0) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Quick Ratio Below 1.0',
                    'detail' => "Quick ratio of {$qr} — may struggle to cover liabilities without selling inventory. In a downturn, inventory can be hard to liquidate quickly.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Quick ratio {$qr} below 1.0");
            } elseif ($qr >= 1.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Strong Quick Ratio',
                    'detail' => "Quick ratio of {$qr} indicates the company can cover current liabilities without relying on inventory.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Quick ratio {$qr} strong (>=1.0)");
            }
        }

        // Working Capital
        if (isset($this->metrics['working_capital'])) {
            $wc = $this->metrics['working_capital'];
            if ($wc < 0) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Negative Working Capital',
                    'detail' => 'Working capital is negative ($' . number_format(abs($wc)) . ') — immediate cash flow concerns.',
                    'severity' => 'critical',
                ];
                $this->adjustScore(-10, "Negative working capital");
            } elseif ($wc > 0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Positive Working Capital',
                    'detail' => 'Working capital of $' . number_format($wc) . ' provides a cushion for operations.',
                    'impact' => 'positive',
                ];
                $this->adjustScore(3, "Positive working capital");
            }
        }
    }

    // ─── Cash Position Assessment ──────────────────────────────

    private function assessCashPosition(): void
    {
        // Cash Ratio (cash / current liabilities)
        if (isset($this->metrics['cash_ratio'])) {
            $cr = $this->metrics['cash_ratio'];
            if ($cr >= 1.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Strong Cash Ratio',
                    'detail' => "Cash ratio of {$cr} — the company can cover all current liabilities with cash alone, without relying on receivables or inventory.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(3, "Cash ratio {$cr} strong (>=1.0)");
            } elseif ($cr >= 0.5) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Adequate Cash Ratio',
                    'detail' => "Cash ratio of {$cr} — cash covers a reasonable portion of current liabilities.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(1, "Cash ratio {$cr} adequate (0.5-1.0)");
            } elseif ($cr >= 0.2) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Low Cash Ratio',
                    'detail' => "Cash ratio of {$cr} — limited cash buffer relative to current obligations. The company depends heavily on receivables collection or inventory liquidation to meet near-term debts.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Cash ratio {$cr} low (0.2-0.5)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Very Low Cash Ratio',
                    'detail' => "Cash ratio of {$cr} — very thin cash reserves relative to current liabilities. A disruption to receivables or sales could cause immediate liquidity problems.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Cash ratio {$cr} very low (<0.2)");
            }
        }

        // Cash as percentage of total assets
        if (isset($this->metrics['cash_pct_assets'])) {
            $cp = $this->metrics['cash_pct_assets'];
            if ($cp > 40) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Excessive Cash Holdings',
                    'detail' => "Cash represents {$cp}% of total assets. While safe, this may indicate management is not effectively deploying capital for growth or returns.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Cash {$cp}% of assets (excess idle capital)");
            } elseif ($cp < 2) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Minimal Cash Reserves',
                    'detail' => "Cash is only {$cp}% of total assets — an unexpectedly low level that leaves almost no margin for operational disruptions.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-2, "Cash only {$cp}% of assets (minimal reserves)");
            }
        }
    }

    // ─── Leverage Assessment ─────────────────────────────────────

    private function assessLeverage(): void
    {
        // Debt-to-Equity
        if (isset($this->metrics['debt_to_equity'])) {
            $de = $this->metrics['debt_to_equity'];
            if ($de > 3.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Extremely High Debt-to-Equity',
                    'detail' => "D/E ratio of {$de} indicates dangerously high leverage. The business is heavily reliant on creditors.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-12, "D/E ratio {$de} extremely high (>3.0)");
            } elseif ($de > 2.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'High Debt-to-Equity',
                    'detail' => "D/E ratio of {$de} indicates significant financial leverage. A credit tightening or revenue decline could create distress.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-7, "D/E ratio {$de} high (2.0-3.0)");
            } elseif ($de > 1.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Moderate Debt Load',
                    'detail' => "D/E ratio of {$de} — liabilities exceed equity. While potentially manageable, this limits financial flexibility and increases sensitivity to interest rate changes.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "D/E ratio {$de} moderate (1.0-2.0)");
            } elseif ($de > 0.5) {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Conservative Debt Level',
                    'detail' => "D/E ratio of {$de} indicates a conservatively financed company with room to take on additional leverage if needed.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "D/E ratio {$de} conservative (0.5-1.0)");
            } else {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Very Low Debt',
                    'detail' => "D/E ratio of {$de} indicates very low leverage. While safe, the company may be underutilizing debt as a tool for growth.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(6, "D/E ratio {$de} very low (<0.5)");
            }
        }

        // Debt-to-Assets
        if (isset($this->metrics['debt_to_assets'])) {
            $da = $this->metrics['debt_to_assets'];
            if ($da > 0.7) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'High Debt-to-Assets',
                    'detail' => "Debt-to-assets of {$da} means over " . round($da * 100) . "% of assets are funded by debt. Creditors bear most of the risk.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-6, "Debt-to-assets {$da} high (>0.7)");
            } elseif ($da > 0.4) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Moderate Debt-to-Assets',
                    'detail' => "Debt-to-assets of {$da} — " . round($da * 100) . "% of assets are debt-financed. This is within normal bounds but worth monitoring.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Debt-to-assets {$da} moderate (0.4-0.7)");
            } else {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Low Debt Burden',
                    'detail' => "Debt-to-assets of {$da} indicates a strong equity-funded asset base.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Debt-to-assets {$da} low (<0.4)");
            }
        }

        // Interest Coverage
        if (isset($this->metrics['interest_coverage'])) {
            $ic = $this->metrics['interest_coverage'];
            if ($ic < 1.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Cannot Cover Interest Payments',
                    'detail' => "Interest coverage of {$ic}x — operating income does not cover interest expense. Risk of debt default.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-15, "Interest coverage {$ic}x < 1.0 (cannot service debt)");
            } elseif ($ic < 2.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Weak Interest Coverage',
                    'detail' => "Interest coverage of {$ic}x leaves minimal margin for debt servicing. Any earnings decline threatens debt obligations.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-8, "Interest coverage {$ic}x weak (<2.0)");
            } elseif ($ic < 5.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Adequate Interest Coverage',
                    'detail' => "Interest coverage of {$ic}x is sufficient but not robust. Lenders typically prefer coverage above 5x.",
                    'severity' => 'low',
                ];
                $this->adjustScore(1, "Interest coverage {$ic}x adequate (2-5)");
            } else {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Strong Interest Coverage',
                    'detail' => "Interest coverage of {$ic}x — ample capacity to service debt.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(5, "Interest coverage {$ic}x strong (>5.0)");
            }
        }
    }

    // ─── Efficiency Assessment ───────────────────────────────────

    private function assessEfficiency(): void
    {
        $inc = $this->incomeData;
        $bal = $this->balanceData;

        // Asset turnover
        if ($inc['revenue'] && $bal['total_assets'] && $bal['total_assets'] != 0) {
            $at = round($inc['revenue'] / $bal['total_assets'], 2);
            $this->metrics['asset_turnover'] = $at;

            if ($at > 1.5) {
                $this->opportunities[] = [
                    'category' => 'Efficiency',
                    'title' => 'High Asset Turnover',
                    'detail' => "Asset turnover of {$at}x indicates efficient use of assets to generate revenue.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Asset turnover {$at}x high (>1.5)");
            } elseif ($at >= 0.5) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Moderate Asset Turnover',
                    'detail' => "Asset turnover of {$at}x is typical for asset-heavy industries but may indicate underutilized assets in other sectors.",
                    'severity' => 'low',
                ];
                $this->adjustScore(0, "Asset turnover {$at}x moderate (0.5-1.5)");
            } elseif ($at >= 0.3) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Low Asset Turnover',
                    'detail' => "Asset turnover of {$at}x — assets are generating relatively little revenue. May signal over-investment or declining sales.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Asset turnover {$at}x low (0.3-0.5)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Very Low Asset Turnover',
                    'detail' => "Asset turnover of {$at}x — assets are not generating proportional revenue. Consider whether significant assets are idle or unproductive.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-5, "Asset turnover {$at}x very low (<0.3)");
            }
        }

        // Receivables relative check
        if ($bal['accounts_receivable'] !== null && $inc['revenue'] && $inc['revenue'] != 0) {
            $receivablePct = round(($bal['accounts_receivable'] / $inc['revenue']) * 100, 1);
            $this->metrics['receivables_pct_revenue'] = $receivablePct;

            if ($receivablePct > 25) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'High Receivables Relative to Revenue',
                    'detail' => "Receivables are {$receivablePct}% of revenue — possible collection issues, customer concentration risk, or aggressive revenue recognition.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-5, "Receivables {$receivablePct}% of revenue (>25%)");
            } elseif ($receivablePct > 15) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Elevated Receivables',
                    'detail' => "Receivables are {$receivablePct}% of revenue — slightly elevated. Monitor collection timelines and aging.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Receivables {$receivablePct}% of revenue (15-25%)");
            }
        }

        // Gross-to-operating margin spread (cost control check)
        if (isset($this->metrics['gross_margin']) && isset($this->metrics['operating_margin'])) {
            $spread = $this->metrics['gross_margin'] - $this->metrics['operating_margin'];
            if ($spread > 30) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Large Overhead Spread',
                    'detail' => "The {$spread} percentage-point gap between gross margin and operating margin suggests high overhead costs (SG&A, R&D, etc.) are consuming a large share of gross profit.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Gross-to-operating spread {$spread}pp (>30pp overhead)");
            }
        }
    }

    // ─── Cost Structure Analysis ─────────────────────────────────

    private function assessCostStructure(): void
    {
        // COGS as % of revenue
        if (isset($this->metrics['cogs_pct_revenue'])) {
            $cogs = $this->metrics['cogs_pct_revenue'];
            if ($cogs > 85) {
                $this->redFlags[] = [
                    'category' => 'Cost Structure',
                    'title' => 'Very High Cost of Goods Sold',
                    'detail' => "COGS consumes {$cogs}% of revenue, leaving very little gross profit to cover operating expenses, interest, and taxes. Pricing power is extremely limited.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-5, "COGS {$cogs}% of revenue (very high)");
            } elseif ($cogs > 70) {
                $this->redFlags[] = [
                    'category' => 'Cost Structure',
                    'title' => 'Elevated Cost of Goods Sold',
                    'detail' => "COGS at {$cogs}% of revenue indicates a cost-heavy business model. Efficiency improvements in production or procurement could meaningfully improve margins.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-2, "COGS {$cogs}% of revenue (elevated)");
            } elseif ($cogs <= 50) {
                $this->opportunities[] = [
                    'category' => 'Cost Structure',
                    'title' => 'Low Direct Costs',
                    'detail' => "COGS is only {$cogs}% of revenue, indicating a high-value or asset-light business model with strong unit economics.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(2, "COGS {$cogs}% of revenue (low — strong unit economics)");
            }
        }

        // Operating expense ratio
        if (isset($this->metrics['opex_ratio'])) {
            $opex = $this->metrics['opex_ratio'];
            if ($opex > 50) {
                $this->redFlags[] = [
                    'category' => 'Cost Structure',
                    'title' => 'High Operating Expenses',
                    'detail' => "Operating expenses represent {$opex}% of revenue. SG&A, R&D, or other overhead costs are consuming a disproportionate share of revenue.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Operating expenses {$opex}% of revenue (high)");
            } elseif ($opex > 35) {
                $this->redFlags[] = [
                    'category' => 'Cost Structure',
                    'title' => 'Moderate Operating Expenses',
                    'detail' => "Operating expenses at {$opex}% of revenue are moderate. Review whether overhead costs scale efficiently with revenue growth.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Operating expenses {$opex}% of revenue (moderate)");
            } elseif ($opex <= 20) {
                $this->opportunities[] = [
                    'category' => 'Cost Structure',
                    'title' => 'Lean Operating Expenses',
                    'detail' => "Operating expenses are only {$opex}% of revenue — the company runs a lean operation with efficient overhead management.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(2, "Operating expenses {$opex}% of revenue (lean)");
            }
        }
    }

    // ─── Inventory Risk Analysis ──────────────────────────────────

    private function assessInventoryRisk(): void
    {
        // Inventory as % of current assets
        if (isset($this->metrics['inventory_pct_current_assets'])) {
            $invPct = $this->metrics['inventory_pct_current_assets'];
            if ($invPct > 60) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Inventory-Heavy Current Assets',
                    'detail' => "Inventory accounts for {$invPct}% of current assets. Since inventory is the least liquid current asset, this reduces the company's ability to quickly meet obligations. Risk of obsolescence or write-downs is elevated.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-3, "Inventory {$invPct}% of current assets (heavy)");
            } elseif ($invPct > 40) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Significant Inventory in Current Assets',
                    'detail' => "Inventory is {$invPct}% of current assets. While not unusual for manufacturing or retail, monitor for signs of slow-moving or obsolete stock.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Inventory {$invPct}% of current assets (significant)");
            }
        }
    }

    // ─── Growth / Structure Indicators ───────────────────────────

    private function assessGrowthIndicators(): void
    {
        $bal = $this->balanceData;

        // Retained earnings as a sign of accumulated profitability
        if ($bal['retained_earnings'] !== null) {
            if ($bal['retained_earnings'] < 0) {
                $this->redFlags[] = [
                    'category' => 'Financial Health',
                    'title' => 'Negative Retained Earnings',
                    'detail' => 'Accumulated deficit of $' . number_format(abs($bal['retained_earnings'])) . ' — historical losses exceed profits. This may limit dividend capacity and signal chronic underperformance.',
                    'severity' => 'high',
                ];
                $this->adjustScore(-8, "Negative retained earnings");
            } else {
                $this->opportunities[] = [
                    'category' => 'Financial Health',
                    'title' => 'Positive Retained Earnings',
                    'detail' => 'Retained earnings of $' . number_format($bal['retained_earnings']) . ' show accumulated profitability over time.',
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Positive retained earnings");
            }
        }

        // Equity ratio
        if (isset($this->metrics['equity_ratio'])) {
            $er = $this->metrics['equity_ratio'];
            $erPct = round($er * 100);
            if ($er > 0.5) {
                $this->opportunities[] = [
                    'category' => 'Financial Health',
                    'title' => 'Strong Equity Position',
                    'detail' => "Equity ratio of {$erPct}% — majority of assets are equity-financed.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(4, "Equity ratio {$erPct}% strong (>50%)");
            } elseif ($er >= 0.3) {
                $this->redFlags[] = [
                    'category' => 'Financial Health',
                    'title' => 'Moderate Equity Position',
                    'detail' => "Equity ratio of {$erPct}% — less than half of assets are equity-financed. The company has moderate reliance on debt funding.",
                    'severity' => 'low',
                ];
                $this->adjustScore(-1, "Equity ratio {$erPct}% moderate (30-50%)");
            } elseif ($er >= 0.2) {
                $this->redFlags[] = [
                    'category' => 'Financial Health',
                    'title' => 'Low Equity Position',
                    'detail' => "Equity ratio of {$erPct}% — the majority of assets are debt-funded, reducing the margin of safety for creditors and investors.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "Equity ratio {$erPct}% low (20-30%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Financial Health',
                    'title' => 'Thin Equity Cushion',
                    'detail' => "Equity ratio of {$erPct}% — very thin equity buffer. The company is overwhelmingly debt-financed.",
                    'severity' => 'high',
                ];
                $this->adjustScore(-6, "Equity ratio {$erPct}% very thin (<20%)");
            }
        }

        // EBITDA Margin
        if (isset($this->metrics['ebitda_margin'])) {
            $em = $this->metrics['ebitda_margin'];
            if ($em > 25) {
                $this->opportunities[] = [
                    'category' => 'Cash Generation',
                    'title' => 'Strong EBITDA Margin',
                    'detail' => "EBITDA margin of {$em}% signals robust cash generation capability.",
                    'impact' => 'positive',
                ];
                $this->adjustScore(5, "EBITDA margin {$em}% strong (>25%)");
            } elseif ($em > 10) {
                $this->redFlags[] = [
                    'category' => 'Cash Generation',
                    'title' => 'Moderate EBITDA Margin',
                    'detail' => "EBITDA margin of {$em}% is acceptable but may not provide sufficient cushion for debt service, capital expenditure, and growth investment simultaneously.",
                    'severity' => 'low',
                ];
                $this->adjustScore(1, "EBITDA margin {$em}% moderate (10-25%)");
            } elseif ($em >= 0) {
                $this->redFlags[] = [
                    'category' => 'Cash Generation',
                    'title' => 'Weak EBITDA Margin',
                    'detail' => "EBITDA margin of {$em}% — cash generation from operations is thin. Limited capacity for debt payments, reinvestment, or distributions.",
                    'severity' => 'medium',
                ];
                $this->adjustScore(-4, "EBITDA margin {$em}% weak (0-10%)");
            } else {
                $this->redFlags[] = [
                    'category' => 'Cash Generation',
                    'title' => 'Negative EBITDA',
                    'detail' => "EBITDA margin of {$em}% — the business is not generating cash from operations even before interest, taxes, and capital costs.",
                    'severity' => 'critical',
                ];
                $this->adjustScore(-10, "EBITDA margin {$em}% negative");
            }
        }
    }

    // ─── Data Completeness Assessment ────────────────────────────

    private function assessDataCompleteness(): void
    {
        $inc = $this->incomeData;
        $bal = $this->balanceData;

        $missingIncome = [];
        $missingBalance = [];

        // Key income statement fields
        if ($inc['revenue'] === null)           $missingIncome[] = 'Revenue';
        if ($inc['cost_of_goods_sold'] === null) $missingIncome[] = 'Cost of Goods Sold';
        if ($inc['operating_income'] === null)  $missingIncome[] = 'Operating Income';
        if ($inc['net_income'] === null)         $missingIncome[] = 'Net Income';

        // Key balance sheet fields
        if ($bal['total_assets'] === null)            $missingBalance[] = 'Total Assets';
        if ($bal['total_liabilities'] === null)       $missingBalance[] = 'Total Liabilities';
        if ($bal['total_equity'] === null)            $missingBalance[] = 'Total Equity';
        if ($bal['total_current_assets'] === null)    $missingBalance[] = 'Total Current Assets';
        if ($bal['total_current_liabilities'] === null) $missingBalance[] = 'Total Current Liabilities';

        $totalMissing = count($missingIncome) + count($missingBalance);

        if ($totalMissing > 0) {
            $allMissing = array_merge($missingIncome, $missingBalance);
            $severity = $totalMissing >= 4 ? 'high' : ($totalMissing >= 2 ? 'medium' : 'low');

            $this->redFlags[] = [
                'category' => 'Data Quality',
                'title' => "Incomplete Data ({$totalMissing} Key Fields Missing)",
                'detail' => "The following key fields could not be parsed: " . implode(', ', $allMissing) . ". This limits the analysis — some ratios could not be computed, which may make the score appear better than warranted.",
                'severity' => $severity,
            ];
            $penalty = min(10, $totalMissing * 2);
            $this->adjustScore(-$penalty, "{$totalMissing} key financial fields missing");
        }

        // Negative equity detection
        if ($bal['total_equity'] !== null && $bal['total_equity'] < 0) {
            $this->redFlags[] = [
                'category' => 'Financial Health',
                'title' => 'Negative Shareholder Equity',
                'detail' => "Total equity is -$" . number_format(abs($bal['total_equity'])) . " — liabilities exceed assets. The company is technically insolvent on a book-value basis. This is a severe warning sign that may indicate accumulated losses, excessive borrowing, or significant asset write-downs.",
                'severity' => 'critical',
            ];
            $this->adjustScore(-12, "Negative shareholder equity (technical insolvency)");
        }

        // Balance sheet equation check: Assets ≈ Liabilities + Equity
        if ($bal['total_assets'] !== null && $bal['total_liabilities'] !== null && $bal['total_equity'] !== null) {
            $expected = $bal['total_liabilities'] + $bal['total_equity'];
            $actual = $bal['total_assets'];
            if ($actual != 0) {
                $discrepancy = abs($actual - $expected) / abs($actual);
                if ($discrepancy > 0.10) {
                    $this->redFlags[] = [
                        'category' => 'Data Quality',
                        'title' => 'Balance Sheet Does Not Balance',
                        'detail' => "Total Assets ($" . number_format($actual) . ") differs from Liabilities + Equity ($" . number_format($expected) . ") by " . round($discrepancy * 100, 1) . "%. This likely indicates a parsing error — some figures may be incorrect, which reduces confidence in the overall analysis.",
                        'severity' => 'high',
                    ];
                    $this->adjustScore(-5, "Balance sheet equation discrepancy >" . round($discrepancy * 100) . "%");
                }
            }
        }

        // No interest expense detected — note the limitation
        if ($inc['interest_expense'] === null && $bal['long_term_debt'] !== null && $bal['long_term_debt'] > 0) {
            $this->redFlags[] = [
                'category' => 'Data Quality',
                'title' => 'Interest Expense Not Detected',
                'detail' => "Long-term debt is present on the balance sheet but no interest expense was found on the income statement. This may indicate a parsing gap — interest coverage could not be assessed.",
                'severity' => 'low',
            ];
            $this->adjustScore(-1, "Interest expense missing despite debt on balance sheet");
        }
    }

    // ─── Scoring and Output ──────────────────────────────────────

    private function generateExecutiveSummary(): array
    {
        $highlights = [];

        // Profitability snapshot
        if (isset($this->metrics['net_profit_margin'])) {
            $npm = $this->metrics['net_profit_margin'];
            $status = $npm > 15 ? 'positive' : ($npm > 5 ? 'neutral' : ($npm >= 0 ? 'caution' : 'negative'));
            $highlights[] = [
                'label' => 'Profitability',
                'value' => "Net margin {$npm}%",
                'status' => $status,
            ];
        } elseif (isset($this->metrics['gross_margin'])) {
            $gm = $this->metrics['gross_margin'];
            $status = $gm > 50 ? 'positive' : ($gm > 30 ? 'neutral' : 'caution');
            $highlights[] = [
                'label' => 'Profitability',
                'value' => "Gross margin {$gm}%",
                'status' => $status,
            ];
        }

        // Liquidity snapshot
        if (isset($this->metrics['current_ratio'])) {
            $cr = $this->metrics['current_ratio'];
            $status = $cr >= 1.5 ? 'positive' : ($cr >= 1.0 ? 'caution' : 'negative');
            $highlights[] = [
                'label' => 'Liquidity',
                'value' => "Current ratio {$cr}x",
                'status' => $status,
            ];
        }

        // Leverage snapshot
        if (isset($this->metrics['debt_to_equity'])) {
            $de = $this->metrics['debt_to_equity'];
            $status = $de <= 0.5 ? 'positive' : ($de <= 1.0 ? 'neutral' : ($de <= 2.0 ? 'caution' : 'negative'));
            $highlights[] = [
                'label' => 'Leverage',
                'value' => "D/E ratio {$de}x",
                'status' => $status,
            ];
        }

        // Efficiency snapshot
        if (isset($this->metrics['return_on_equity'])) {
            $roe = $this->metrics['return_on_equity'];
            $status = $roe > 15 ? 'positive' : ($roe > 5 ? 'neutral' : ($roe >= 0 ? 'caution' : 'negative'));
            $highlights[] = [
                'label' => 'Returns',
                'value' => "ROE {$roe}%",
                'status' => $status,
            ];
        }

        // Severity breakdown
        $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($this->redFlags as $flag) {
            $sev = $flag['severity'] ?? 'medium';
            if (isset($severityCounts[$sev])) {
                $severityCounts[$sev]++;
            }
        }

        return [
            'highlights' => $highlights,
            'flag_count' => count($this->redFlags),
            'opportunity_count' => count($this->opportunities),
            'severity_counts' => $severityCounts,
        ];
    }

    private function clampScore(): void
    {
        $this->score = max(0, min(100, $this->score));
    }

    private function scoreToRating(float $score): string
    {
        if ($score >= 85) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 55) return 'Fair';
        if ($score >= 40) return 'Below Average';
        if ($score >= 25) return 'Poor';
        return 'Critical';
    }

    private function generateAppraisal(): string
    {
        $score = $this->score;
        $redFlagCount = count($this->redFlags);
        $oppCount = count($this->opportunities);

        $criticalFlags = array_filter($this->redFlags, fn($f) => ($f['severity'] ?? '') === 'critical');
        $highFlags = array_filter($this->redFlags, fn($f) => ($f['severity'] ?? '') === 'high');
        $criticalCount = count($criticalFlags);
        $highCount = count($highFlags);

        if ($score >= 85) {
            $appraisal = "This entity demonstrates strong financial health across profitability, liquidity, and leverage metrics. ";
            $appraisal .= "{$oppCount} positive indicator(s) were identified";
            if ($redFlagCount > 0) {
                $appraisal .= " alongside {$redFlagCount} area(s) for monitoring. These are minor observations that do not materially diminish the overall financial position.";
            } else {
                $appraisal .= ". No material concerns were identified.";
            }
        } elseif ($score >= 70) {
            $appraisal = "The financial position is generally solid with {$oppCount} positive indicator(s), though the analysis identified {$redFlagCount} area(s) that warrant attention. ";
            if ($highCount > 0) {
                $appraisal .= "Of these, {$highCount} require closer monitoring. ";
            }
            $appraisal .= "Overall, the entity is well-positioned but has room for improvement in specific areas.";
        } elseif ($score >= 55) {
            $appraisal = "The financial position is adequate but with notable weaknesses. ";
            $appraisal .= "{$redFlagCount} concern(s) were identified against {$oppCount} positive indicator(s). ";
            $appraisal .= "The balance between strengths and weaknesses suggests a stable but improvable position that requires active management.";
        } elseif ($score >= 40) {
            $appraisal = "The financial data reveals a mixed picture with significant concerns. ";
            $appraisal .= "{$redFlagCount} red flag(s) were identified against {$oppCount} positive indicator(s). ";
            if ($criticalCount > 0) {
                $appraisal .= "{$criticalCount} critical issue(s) require immediate management attention.";
            } else {
                $appraisal .= "Several areas require focused improvement to strengthen the financial position.";
            }
        } else {
            $appraisal = "The financial analysis reveals serious concerns about the entity's financial health. ";
            $appraisal .= "{$redFlagCount} red flag(s) were identified, including {$criticalCount} critical issue(s). ";
            $appraisal .= "The combination of negative indicators paints a picture of significant financial distress that requires urgent intervention.";
        }

        return $appraisal;
    }

    private function generateRecommendation(): string
    {
        $score = $this->score;
        $criticalFlags = array_filter($this->redFlags, fn($f) => ($f['severity'] ?? '') === 'critical');
        $highFlags = array_filter($this->redFlags, fn($f) => ($f['severity'] ?? '') === 'high');

        $recommendations = [];

        if ($score >= 80) {
            $recommendations[] = "FAVORABLE — The financial data supports a positive outlook.";
            $recommendations[] = "Continue monitoring key performance indicators to maintain this strong position.";
        } elseif ($score >= 60) {
            $recommendations[] = "CAUTIOUSLY FAVORABLE — Fundamentals are intact, but improvements are needed.";
        } elseif ($score >= 40) {
            $recommendations[] = "CAUTION — Material risks are present and should be addressed before committing resources.";
        } else {
            $recommendations[] = "UNFAVORABLE — Significant financial distress indicators are present.";
            $recommendations[] = "A thorough restructuring assessment or professional audit is strongly recommended.";
        }

        // Add specific recommendations based on flags
        foreach ($criticalFlags as $flag) {
            $recommendations[] = "[CRITICAL] Address: " . $flag['title'] . " — " . $flag['detail'];
        }

        foreach ($highFlags as $flag) {
            $recommendations[] = "[HIGH PRIORITY] Review: " . $flag['title'] . " — " . $flag['detail'];
        }

        return implode("\n", $recommendations);
    }
}
