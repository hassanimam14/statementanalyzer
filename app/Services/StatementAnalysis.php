<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Carbon\Carbon;

class StatementAnalysis
{
    protected array $feeMap = [
        'Foreign Transaction Fee' => [
            'foreign transaction fee','intl fee','international fee','fx fee','int’l fee','foreign fee'
        ],
        'Currency Conversion Fee' => [
            'currency conversion','conversion fee','dcc fee','dynamic currency','fx conversion'
        ],
        'Interchange Fee' => [
            'interchange','scheme fee','assessment fee','network fee'
        ],
        'Late Payment Fee' => [
            'late payment fee','late fee','payment late','late charges'
        ],
        'Interest Charge' => [
            'interest charge','interest','finance charge','apr charge','revolving interest'
        ],
        'Overdraft Fee' => [
            'overdraft','nsf fee','insufficient funds'
        ],
        'Cash Advance Fee' => [
            'cash advance fee','atm cash fee','cash fee'
        ],
        'Annual Fee' => [
            'annual fee','membership fee'
        ],
        'Service/Other Fee' => [
            'service fee','processing fee','maintenance fee','bank service fee','fee'
        ],
    ];

    protected array $subscriptionVendors = [
        'spotify','netflix','youtube','icloud','apple icloud','prime','amazon prime','adobe',
        'microsoft 365','office 365','dropbox','hulu','disney+','crunchyroll','notion',
        'slack','zoom','canva'
    ];

public function summarize(Collection $txns): array
{
    $txns = $txns->values();

    $feeByCategory = $this->initializeFeeCategories();

    // Run fee detection first to get cache
    [$subscriptions, $hiddenFees, $flaggedRows, $isFeeCache, $labelsCache] =
        $this->processTransactions($txns, $feeByCategory);

    // ⬅️ compute spend AFTER fee detection, excluding fees
    $totalSpend = $this->calculateTotalSpend($txns);

    $subscriptions = $this->detectCadenceSubscriptions($txns, $subscriptions);
    $duplicates = $this->duplicates($txns);

    $feesOverTime = $this->calculateFeesOverTime($txns, $isFeeCache);
    $topFeeMerchants = $this->getTopFeeMerchants($txns, $isFeeCache);

    $totalFees = array_sum($feeByCategory);
    $tips = $this->generateTips($feeByCategory, $subscriptions, $totalFees);
    $cardSuggestions = $this->generateCardSuggestions($feeByCategory);

    return [
        'totalFees'       => (float) $totalFees,
        'totalSpend'      => (float) $totalSpend,
        'subscriptions'   => $subscriptions,
        'duplicates'      => $duplicates,
        'feeByCategory'   => $this->filterZeroes($feeByCategory),
        'topFeeMerchants' => $topFeeMerchants,
        'feesOverTime'    => $feesOverTime,
        'tips'            => array_values(array_unique($tips)),
        'cardSuggestions' => array_values(array_unique($cardSuggestions)),
        'hiddenFees'      => $hiddenFees,
        'flagged'         => $flaggedRows,
    ];
}


private function calculateTotalSpend(Collection $txns): float
{
    $spendCats = ['purchase','subscription','cash_advance'];

    return (float) $txns
        ->filter(function ($t) use ($spendCats) {
            $amt = (float) $t->amount;
            if ($amt >= 0) return false;              // only debits
            if ($this->isFeeLike($t)) return false;   // exclude fee-like
            $cat = strtolower((string)($t->category ?? ''));
            return ($cat === '' || in_array($cat, $spendCats, true));
        })
        ->sum(fn($t) => abs((float)$t->amount));
}


private function initializeFeeCategories(): array
{
    $feeByCategory = [];
    foreach (array_keys($this->feeMap) as $label) {
        $feeByCategory[$label] = 0.0;
    }
    return $feeByCategory;
}

private function processTransactions(Collection $txns, array &$feeByCategory): array
{
    $subscriptions = [];
    $hiddenFees = [];
    $flaggedRows = [];
    $isFeeCache = [];
    $labelsCache = [];

    foreach ($txns as $idx => $t) {
        $desc = mb_strtolower((string)($t->description ?? ''));
        $merch = mb_strtolower((string)($t->merchant ?? $t->description ?? ''));
        $amount = (float)$t->amount;

        $subscriptions = $this->detectSubscriptionVendors($desc, $merch, $subscriptions);
        
        $labels = $this->detectFeeLabels($desc);
        $labelsCache[$idx] = $labels;

        $isFee = ($t->category ?? null) === 'fee' || !empty($labels) || $this->flagsContainFee($t->flags ?? []);
        $isFeeCache[$idx] = $isFee;

        if ($isFee) {
            $feeByCategory = $this->processFee($labels, $amount, $feeByCategory);
            $hiddenFees[] = $this->createHiddenFeeRecord($t, $labels);
        }

        $flaggedRows = $this->flagTransaction($t, $desc, $amount, $isFee, $labels, $flaggedRows);
    }

    return [$subscriptions, $hiddenFees, $flaggedRows, $isFeeCache, $labelsCache];
}

private function detectSubscriptionVendors(string $desc, string $merch, array $subscriptions): array
{
    foreach ($this->subscriptionVendors as $v) {
        if (str_contains($desc, $v) || str_contains($merch, $v)) {
            $subscriptions[] = $v;
            break;
        }
    }
    return $subscriptions;
}

private function processFee(array $labels, float $amount, array $feeByCategory): array
{
    if (empty($labels)) {
        $labels = ['Service/Other Fee'];
    }
    
    foreach ($labels as $label) {
        if (!isset($feeByCategory[$label])) {
            $feeByCategory[$label] = 0.0;
        }
        $feeByCategory[$label] += abs($amount);
    }
    
    return $feeByCategory;
}

private function createHiddenFeeRecord(object $t, array $labels): array
{
    return [
        'date'        => optional($t->date)->toDateString() ?: (string)$t->date,
        'description' => (string)$t->description,
        'merchant'    => (string)($t->merchant ?? ''),
        'amount'      => (float)$t->amount,
        'labels'      => array_values($labels),
    ];
}

private function flagTransaction(object $t, string $desc, float $amount, bool $isFee, array $labels, array $flaggedRows): array
{
    $txnFlags = $this->normalizeFlags($t->flags ?? []);
    
    if (preg_match('/\b(payment|charge|fee)\b/i', (string)$t->description) && abs($amount) > 500) {
        $txnFlags[] = 'large_fee_amount';
    }
    
    if (!(bool)preg_match('/[a-z]{3,}/i', $desc)) {
        $txnFlags[] = 'ambiguous_description';
    }
    
    if ($isFee && abs($amount) >= 100 && in_array('Service/Other Fee', $labels, true)) {
        $txnFlags[] = 'unexpected_service_fee';
    }

    if (!empty($txnFlags)) {
        $flaggedRows[] = [
            'date'        => optional($t->date)->toDateString() ?: (string)$t->date,
            'description' => (string)$t->description,
            'amount'      => (float)$t->amount,
            'reason'      => implode(', ', array_unique($txnFlags)),
        ];
    }
    
    return $flaggedRows;
}

private function detectCadenceSubscriptions(Collection $txns, array $subscriptions): array
{
    $byMerchant = $txns->groupBy(fn($t) => mb_strtolower((string)($t->merchant ?? $t->description)));
    
    foreach ($byMerchant as $m => $g) {
        $amts = $g->map(fn($t) => round(abs((float)$t->amount), 2))->sort()->values();
        
        if ($amts->count() >= 3) {
            $median = $amts[(int)floor($amts->count() / 2)];
            $near = $g->filter(fn($t) => $median > 0 && abs(abs((float)$t->amount) - $median) <= 0.05 * $median);
            $dates = $near->map(fn($t) => Carbon::parse($t->date))->sort()->values();
            
            $hits = 0;
            for ($i = 1; $i < $dates->count(); $i++) {
                $diff = $dates[$i - 1]->diffInDays($dates[$i]);
                if ($diff >= 27 && $diff <= 34) {
                    $hits++;
                }
            }
            
            if ($hits >= 1) {
                $subscriptions[] = $m;
            }
        }
    }
    
    return array_values(array_unique($subscriptions));
}

private function calculateFeesOverTime(Collection $txns, array $isFeeCache): Collection
{
    if ($txns->keys()->isEmpty()) {
        return collect();
    }
    
    return $txns->filter(fn($t, $i) => $isFeeCache[$i] ?? false)
        ->groupBy(fn($t) => optional($t->date)->format('Y-m') ?: (string)$t->date)
        ->map(fn($g) => $g->sum(fn($t) => abs((float)$t->amount)))
        ->sortKeys();
}

private function getTopFeeMerchants(Collection $txns, array $isFeeCache): array
{
    $topFeeMerchants = $txns->filter(fn($t, $i) => $isFeeCache[$i] ?? false)
        ->groupBy(fn($t) => mb_strtolower((string)($t->merchant ?: $t->description)))
        ->map(function ($g) {
            $label = (string)($g->first()->merchant ?: $g->first()->description);
            return ['label' => $label, 'total' => $g->sum(fn($t) => abs((float)$t->amount))];
        })
        ->sortByDesc('total')
        ->take(5);

    return $topFeeMerchants->mapWithKeys(fn($v, $k) => [$v['label'] => round($v['total'], 2)])->toArray();
}

private function generateTips(array $feeByCategory, array $subscriptions, float $totalFees): array
{
    $tips = [];
    
    if ($totalFees > 0) {
        $tips[] = "You paid $" . number_format($totalFees, 2) . " in fees — ask for waivers or switch to lower-fee options.";
    }
    
    if (($feeByCategory['Foreign Transaction Fee'] ?? 0) > 0 || ($feeByCategory['Currency Conversion Fee'] ?? 0) > 0) {
        $tips[] = "Use a 0% FX card and always pay in local currency abroad.";
    }
    
    if (($feeByCategory['Late Payment Fee'] ?? 0) > 0) {
        $tips[] = "Turn on autopay/reminders to avoid late fees.";
    }
    
    if (($feeByCategory['Interest Charge'] ?? 0) > 0) {
        $tips[] = "Pay the statement balance in full to avoid interest.";
    }
    
    if (count($subscriptions) >= 5) {
        $tips[] = "Audit subscriptions; cancel anything unused.";
    }
    
    return $tips;
}

private function generateCardSuggestions(array $feeByCategory): array
{
    $cardSuggestions = [];
    
    if (($feeByCategory['Foreign Transaction Fee'] ?? 0) > 0) {
        $cardSuggestions[] = "Consider a card with 0% foreign transaction fees.";
    }
    
    if (($feeByCategory['Cash Advance Fee'] ?? 0) > 0) {
        $cardSuggestions[] = "Avoid cash advances — they trigger fees and immediate interest.";
    }
    
    if (($feeByCategory['Annual Fee'] ?? 0) > 95) {
        $cardSuggestions[] = "Annual fee looks high — keep only if benefits outweigh cost.";
    }
    
    return $cardSuggestions;
}

private function filterZeroes(array $feeByCategory): array
{
    return array_filter($feeByCategory, fn($value) => $value > 0);
}


    protected function detectFeeLabels(string $desc): array
{
    $labels = [];
    $d = mb_strtolower($desc);

    // Existing exact matches
    foreach ($this->feeMap as $label => $needles) {
        foreach ($needles as $needle) {
            if ($needle !== 'fee' && str_contains($d, $needle)) { $labels[] = $label; break; }
        }
    }

    // Global tax/duty/levy patterns (Pakistan, EU, GCC, etc.)
    $taxHints = [
        'adv tax', 'withholding', 'wht', 'gst', 'vat', 'sales tax', 'service tax',
        'pra', 'srb', 'kpra', 'fbr', 'excise duty', 'duty', 'levy', '236y',
        'it services tax', 'it service tax'
    ];
    foreach ($taxHints as $n) {
        if (str_contains($d, $n)) {
            $labels[] = 'Service/Other Fee'; // or a dedicated 'Tax/Levy' bucket if you add it to $feeMap
            break;
        }
    }

    // Common generic fee words
    if (empty($labels) && preg_match('/\b(fee|charge|assessment|markup|finance\s*charge|sms\s*banking\s*fee)\b/i', $desc)) {
        $labels[] = 'Service/Other Fee';
    }

    // Finance/markup words sometimes used instead of "interest"
    if (preg_match('/\b(markup\s*rate|installment\s+plan|installment\s+markup|finance\s*charge|markup)\b/i', $desc)) {
    if (!preg_match('/\b0\s*%\b/', $desc)) {
        $labels[] = 'Interest Charge';
    }
    }
    return array_values(array_unique($labels));
}

private function isFeeLike(object $t): bool
{
    $desc = mb_strtolower((string)($t->description ?? ''));
    $flags = $this->normalizeFlags($t->flags ?? []);

    // principal/plan line is NOT a fee if explicitly 0%
    if (preg_match('/\bmarkup\s*rate\s*0\s*%\b/i', $desc)) return false;

    if (($t->category ?? null) === 'fee') return true;

    $feeFlags = ['service_fee','tax','foreign_tx_fee','interest_charge','late_payment','cash_advance'];
    foreach ($feeFlags as $f) if (in_array($f, $flags, true)) return true;

    return (bool) preg_match('/\b(fee|charge|interest|finance|excise|tax|withholding|levy|pra|kpra|srb|vat|gst|sms\s*banking|giro|overlimit)\b/i', $desc);
}

    protected function flagsContainFee($flags): bool
{
    $flags = $this->normalizeFlags($flags);
    $needles = [
        'foreign_tx_fee','currency_conversion','interchange_pass','late_payment',
        'interest_charge','overdraft','cash_advance','annual_fee','service_fee',
        // global tax-ish hints that may be emitted by upstream or future models
        'tax','duty','levy','withholding','vat','gst'
    ];
    foreach ($flags as $f) if (in_array($f, $needles, true)) return true;
    return false;
}


    protected function normalizeFlags($flags): array
    {
        if (is_string($flags)) {
            $decoded = json_decode($flags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
            return [$flags];
        }
        if ($flags instanceof Collection) return $flags->map(fn($x)=>(string)$x)->all();
        if (is_array($flags)) return array_values(array_filter(array_map('strval', $flags)));
        return [];
    }

    protected function duplicates(Collection $txns): array
    {
        $map = [];
        foreach ($txns as $t) {
            $key = (optional($t->date)->toDateString() ?: (string)$t->date)
                 .'|'.preg_replace('/\s+/', ' ', mb_strtolower((string)$t->description))
                 .'|'.(string)$t->amount;
            $map[$key] = ($map[$key] ?? 0) + 1;
        }

        $dupes = [];
        foreach ($txns as $t) {
            $key = (optional($t->date)->toDateString() ?: (string)$t->date)
                 .'|'.preg_replace('/\s+/', ' ', mb_strtolower((string)$t->description))
                 .'|'.(string)$t->amount;
            if (($map[$key] ?? 0) > 1) {
                $dupes[] = [
                    'date'        => optional($t->date)->toDateString() ?: (string)$t->date,
                    'description' => (string)$t->description,
                    'amount'      => (float)$t->amount,
                ];
            }
        }
        return $dupes;
    }

    // protected function filterZeroes(array $buckets): array
    // {
    //     return array_filter($buckets, fn($v) => ((float)$v) > 0);
    // }
}