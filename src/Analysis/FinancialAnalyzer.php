<?php

namespace FinancialMiester\Analysis;

class FinancialAnalyzer
{
    private array $incomeData;
    private array $balanceData;
    private array $redFlags = [];
    private array $opportunities = [];
    private array $metrics = [];
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
        $this->assessLeverage();
        $this->assessEfficiency();
        $this->assessGrowthIndicators();
        $this->clampScore();

        return [
            'metrics' => $this->metrics,
            'red_flags' => $this->redFlags,
            'opportunities' => $this->opportunities,
            'score' => round($this->score, 1),
            'rating' => $this->scoreToRating($this->score),
            'appraisal' => $this->generateAppraisal(),
            'recommendation' => $this->generateRecommendation(),
        ];
    }

    // ─── Metric Computation ──────────────────────────────────────

    private function computeMetrics(): void
    {
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
                $this->score += 8;
            } elseif ($gm > 30) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Healthy Gross Margin',
                    'detail' => "Gross margin of {$gm}% is within a healthy range.",
                    'impact' => 'positive',
                ];
                $this->score += 4;
            } elseif ($gm < 15) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Gross Margin',
                    'detail' => "Gross margin of {$gm}% is very thin — vulnerable to cost increases or pricing pressure.",
                    'severity' => 'high',
                ];
                $this->score -= 10;
            } elseif ($gm < 25) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Below-Average Gross Margin',
                    'detail' => "Gross margin of {$gm}% is below average. Consider evaluating cost structure.",
                    'severity' => 'medium',
                ];
                $this->score -= 4;
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
                $this->score += 7;
            } elseif ($om < 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Operating Loss',
                    'detail' => "Operating margin of {$om}% indicates the company is losing money from core operations.",
                    'severity' => 'critical',
                ];
                $this->score -= 15;
            } elseif ($om < 5) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Thin Operating Margin',
                    'detail' => "Operating margin of {$om}% leaves little room for error.",
                    'severity' => 'medium',
                ];
                $this->score -= 5;
            }
        }

        // Net Profit Margin
        if (isset($this->metrics['net_profit_margin'])) {
            $npm = $this->metrics['net_profit_margin'];
            if ($npm < 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Net Loss',
                    'detail' => "Net profit margin of {$npm}% — the company is unprofitable.",
                    'severity' => 'critical',
                ];
                $this->score -= 12;
            } elseif ($npm > 15) {
                $this->opportunities[] = [
                    'category' => 'Profitability',
                    'title' => 'Excellent Net Margin',
                    'detail' => "Net profit margin of {$npm}% is excellent, showing strong bottom-line performance.",
                    'impact' => 'positive',
                ];
                $this->score += 8;
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
                $this->score += 6;
            } elseif ($roe < 0) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Negative Return on Equity',
                    'detail' => "ROE of {$roe}% means shareholders are losing value.",
                    'severity' => 'high',
                ];
                $this->score -= 8;
            } elseif ($roe < 5) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Return on Equity',
                    'detail' => "ROE of {$roe}% is below typical benchmarks. Capital may be better deployed elsewhere.",
                    'severity' => 'low',
                ];
                $this->score -= 3;
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
                $this->score += 5;
            } elseif ($roa < 1) {
                $this->redFlags[] = [
                    'category' => 'Profitability',
                    'title' => 'Low Return on Assets',
                    'detail' => "ROA of {$roa}% suggests assets are not generating adequate returns.",
                    'severity' => 'medium',
                ];
                $this->score -= 4;
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
                $this->score -= 12;
            } elseif ($cr < 1.5) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Tight Liquidity',
                    'detail' => "Current ratio of {$cr} is below the comfortable threshold of 1.5.",
                    'severity' => 'medium',
                ];
                $this->score -= 4;
            } elseif ($cr >= 1.5 && $cr <= 3.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Healthy Current Ratio',
                    'detail' => "Current ratio of {$cr} indicates adequate short-term liquidity.",
                    'impact' => 'positive',
                ];
                $this->score += 5;
            } elseif ($cr > 3.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Excess Liquidity',
                    'detail' => "Current ratio of {$cr} is very high — capital may not be deployed efficiently.",
                    'impact' => 'neutral',
                ];
                $this->score += 1;
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
                $this->score -= 7;
            } elseif ($qr < 1.0) {
                $this->redFlags[] = [
                    'category' => 'Liquidity',
                    'title' => 'Quick Ratio Below 1.0',
                    'detail' => "Quick ratio of {$qr} — may struggle to cover liabilities without selling inventory.",
                    'severity' => 'medium',
                ];
                $this->score -= 3;
            } elseif ($qr >= 1.0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Strong Quick Ratio',
                    'detail' => "Quick ratio of {$qr} indicates the company can cover current liabilities without relying on inventory.",
                    'impact' => 'positive',
                ];
                $this->score += 4;
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
                $this->score -= 10;
            } elseif ($wc > 0) {
                $this->opportunities[] = [
                    'category' => 'Liquidity',
                    'title' => 'Positive Working Capital',
                    'detail' => 'Working capital of $' . number_format($wc) . ' provides a cushion for operations.',
                    'impact' => 'positive',
                ];
                $this->score += 3;
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
                    'detail' => "D/E ratio of {$de} indicates dangerously high leverage.",
                    'severity' => 'critical',
                ];
                $this->score -= 12;
            } elseif ($de > 2.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'High Debt-to-Equity',
                    'detail' => "D/E ratio of {$de} indicates significant financial leverage.",
                    'severity' => 'high',
                ];
                $this->score -= 7;
            } elseif ($de > 1.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Moderate Debt Load',
                    'detail' => "D/E ratio of {$de} — liabilities exceed equity, but may be manageable.",
                    'severity' => 'low',
                ];
                $this->score -= 2;
            } elseif ($de <= 1.0) {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Conservative Debt Level',
                    'detail' => "D/E ratio of {$de} indicates a conservatively financed company.",
                    'impact' => 'positive',
                ];
                $this->score += 6;
            }
        }

        // Debt-to-Assets
        if (isset($this->metrics['debt_to_assets'])) {
            $da = $this->metrics['debt_to_assets'];
            if ($da > 0.7) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'High Debt-to-Assets',
                    'detail' => "Debt-to-assets of {$da} means over 70% of assets are funded by debt.",
                    'severity' => 'high',
                ];
                $this->score -= 6;
            } elseif ($da < 0.4) {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Low Debt Burden',
                    'detail' => "Debt-to-assets of {$da} indicates a strong equity-funded asset base.",
                    'impact' => 'positive',
                ];
                $this->score += 4;
            }
        }

        // Interest Coverage
        if (isset($this->metrics['interest_coverage'])) {
            $ic = $this->metrics['interest_coverage'];
            if ($ic < 1.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Cannot Cover Interest Payments',
                    'detail' => "Interest coverage of {$ic}x — operating income does not cover interest expense.",
                    'severity' => 'critical',
                ];
                $this->score -= 15;
            } elseif ($ic < 2.0) {
                $this->redFlags[] = [
                    'category' => 'Leverage',
                    'title' => 'Weak Interest Coverage',
                    'detail' => "Interest coverage of {$ic}x leaves minimal margin for debt servicing.",
                    'severity' => 'high',
                ];
                $this->score -= 8;
            } elseif ($ic > 5.0) {
                $this->opportunities[] = [
                    'category' => 'Leverage',
                    'title' => 'Strong Interest Coverage',
                    'detail' => "Interest coverage of {$ic}x — ample capacity to service debt.",
                    'impact' => 'positive',
                ];
                $this->score += 5;
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
                $this->score += 4;
            } elseif ($at < 0.3) {
                $this->redFlags[] = [
                    'category' => 'Efficiency',
                    'title' => 'Low Asset Turnover',
                    'detail' => "Asset turnover of {$at}x — assets are not generating proportional revenue.",
                    'severity' => 'medium',
                ];
                $this->score -= 4;
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
                    'detail' => "Receivables are {$receivablePct}% of revenue — possible collection issues or aggressive revenue recognition.",
                    'severity' => 'medium',
                ];
                $this->score -= 5;
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
                    'detail' => 'Accumulated deficit of $' . number_format(abs($bal['retained_earnings'])) . ' — historical losses exceed profits.',
                    'severity' => 'high',
                ];
                $this->score -= 8;
            } else {
                $this->opportunities[] = [
                    'category' => 'Financial Health',
                    'title' => 'Positive Retained Earnings',
                    'detail' => 'Retained earnings of $' . number_format($bal['retained_earnings']) . ' show accumulated profitability over time.',
                    'impact' => 'positive',
                ];
                $this->score += 4;
            }
        }

        // Equity ratio
        if (isset($this->metrics['equity_ratio'])) {
            $er = $this->metrics['equity_ratio'];
            if ($er > 0.5) {
                $this->opportunities[] = [
                    'category' => 'Financial Health',
                    'title' => 'Strong Equity Position',
                    'detail' => "Equity ratio of " . round($er * 100) . "% — majority of assets are equity-financed.",
                    'impact' => 'positive',
                ];
                $this->score += 4;
            } elseif ($er < 0.2) {
                $this->redFlags[] = [
                    'category' => 'Financial Health',
                    'title' => 'Thin Equity Cushion',
                    'detail' => "Equity ratio of " . round($er * 100) . "% — very thin equity buffer.",
                    'severity' => 'high',
                ];
                $this->score -= 6;
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
                $this->score += 5;
            } elseif ($em < 5 && $em >= 0) {
                $this->redFlags[] = [
                    'category' => 'Cash Generation',
                    'title' => 'Weak EBITDA Margin',
                    'detail' => "EBITDA margin of {$em}% — cash generation from operations is thin.",
                    'severity' => 'medium',
                ];
                $this->score -= 4;
            }
        }
    }

    // ─── Scoring and Output ──────────────────────────────────────

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
        $criticalCount = count($criticalFlags);

        if ($score >= 80) {
            $appraisal = "This entity demonstrates strong financial health across profitability, liquidity, and leverage metrics. ";
            if ($oppCount > 0) {
                $appraisal .= "There are {$oppCount} notable positive indicators. ";
            }
            if ($redFlagCount > 0) {
                $appraisal .= "While {$redFlagCount} minor concern(s) were noted, they do not materially diminish the overall financial position.";
            } else {
                $appraisal .= "No significant concerns were identified.";
            }
        } elseif ($score >= 60) {
            $appraisal = "The financial position is reasonably sound, but there are areas that warrant attention. ";
            $appraisal .= "{$oppCount} positive indicator(s) and {$redFlagCount} concern(s) were identified. ";
            $appraisal .= "The balance between strengths and weaknesses suggests a stable but improvable position.";
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
