<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiStatementAnalysis
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey   = (string) config('services.gemini.api_key');
        $this->model    = (string) config('services.gemini.model', 'gemini-1.5-flash');
        $this->endpoint = rtrim((string) config('services.gemini.endpoint'), '/');
    }

    /**
     * Analyze a statement file (PDF/image) and return:
     * [
     * 'transactions' => [ ['date','description','merchant','amount','category','flags'=>[]], ... ],
     * 'summary'      => [... optional summary buckets ...]
     * ]
     */
    public function analyzeFile(string $filePath, string $mimeType): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['error' => 'File not readable'];
        }
        if (!$this->apiKey) {
            return ['error' => 'Gemini API key missing'];
        }

        // Inline upload (base64). Consider switching to Files API for very large inputs.
        $data   = base64_encode(file_get_contents($filePath));
        $prompt = $this->prompt();
        $url    = $this->endpoint . '/models/' . $this->model . ':generateContent';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType ?: 'application/pdf',
                            'data'      => $data,
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,
                'topP'            => 0.95,
                'topK'            => 40,
                'maxOutputTokens' => 8192,
            ],
        ];
        // For debugging: log the full payload (without base64 data)
        $payloadForLog = $payload;
        if (isset($payloadForLog['contents'][0]['parts'][1]['inline_data']['data'])) {
            $payloadForLog['contents'][0]['parts'][1]['inline_data']['data'] = '[base64 data omitted]';
        }

Log::debug('Gemini full outgoing payload', $payloadForLog);


        // ✅ LOG #1: what we’re sending (without leaking content)
        try {
            $size = @filesize($filePath);
        } catch (\Throwable $e) {
            $size = null;
        }
        Log::info('Sending file to Gemini for analysis', [
            'filePath' => $filePath,
            'mimeType' => $mimeType,
            'fileSize' => $size,
            'endpoint' => $this->endpoint,
            'model'    => $this->model,
        ]);

        Log::debug('Gemini full prompt', ['prompt' => $prompt]);

        try {
            $res = Http::timeout(120)
                ->withHeaders([
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => $this->apiKey, // header auth
                ])
                ->post($url, $payload);

            if (!$res->ok()) {
                Log::error('Gemini API HTTP error', [
                    'code'     => $res->status(),
                    'body'     => mb_substr($res->body(), 0, 1000),
                    'model'    => $this->model,
                    'endpoint' => $this->endpoint,
                ]);
                return ['error' => 'Gemini API error: HTTP '.$res->status()];
            }

            $json = $res->json();

            // Extract raw model text safely
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim((string) $text);

            if ($text === '') {
                Log::error('Gemini empty response', ['body' => $json]);
                return ['error' => 'Empty response from Gemini'];
            }

            // ✅ LOG #2: raw text from Gemini (trimmed)
            Log::info('Raw Gemini Response Text', [
                'raw_text' => mb_substr($text, 0, 2000),
            ]);

            // Strip common fences/prefixes
            $text = preg_replace('/^\s*```json\s*/i', '', $text);
            $text = preg_replace('/^\s*```/i', '', $text);
            $text = preg_replace('/```$/', '', $text);
            if (Str::startsWith($text, 'json')) {
                $text = ltrim(substr($text, 4));
            }

            $data = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::error('Gemini parse error', [
                    'msg' => json_last_error_msg(),
                    'raw' => mb_substr($text, 0, 2000),
                ]);
                return ['error' => 'Invalid JSON from Gemini'];
            }

            // Normalize a minimal contract
            $txns = [];
            foreach (($data['transactions'] ?? []) as $t) {
    $amount = (float) ($t['amount'] ?? 0);
    $desc   = trim((string) ($t['description'] ?? ''));
    $date   = (string) ($t['date'] ?? '');
    if ($desc === '' || $date === '') continue;

    // 👇 normalize: unsigned merchant-looking rows are debits
    if ($amount > 0) {
        $descLC = mb_strtolower($desc);
        $looksCredit = (bool) preg_match(
            '/\b(payment|credit|refund|reversal|return|cash\s*back|cashback|deposit|promo\s*credit|adjustment|waiver)\b/i',
            $descLC
        );
        if (!$looksCredit) {
            $amount = -abs($amount);
        }
    }

    $txns[] = [
        'date'        => $date,
        'description' => $desc,
        'merchant'    => (string) ($t['merchant'] ?? $desc),
        'amount'      => round($amount, 2),
        'type'        => $amount < 0 ? 'debit' : 'credit',
        'category'    => (string) ($t['category'] ?? 'uncategorized'),
        'flags'       => array_values(array_filter((array)($t['flags'] ?? []))),
    ];
}


            $summary = $data;
            unset($summary['transactions']);

            return [
                'transactions' => $txns,
                'summary'      => $summary,
            ];

        } catch (\Throwable $e) {
            Log::error('Gemini exception: '.$e->getMessage(), [
                'model'    => $this->model,
                'endpoint' => $this->endpoint,
            ]);
            return ['error' => 'Gemini exception: '.$e->getMessage()];
        }
    }

    protected function prompt(): string
    {
        return <<<PROMPT
SYSTEM ROLE
You are a world-class financial statement parser and auditor.  
You must robustly extract transactions from ANY type of bank/credit/debit statement, regardless of:  
- File type: PDF (digital or scanned), image/photo (JPG/PNG/TIFF/HEIC), CSV/TSV, TXT.  
- Layout: single/multi-column, multi-page, landscape/portrait, rotated, duplex scanned, skewed, stamped, watermarked, blurred.  
- Language/script: English, Urdu, Arabic, Chinese, Japanese, Korean, European formats (French, German, Spanish, etc.), RTL (Right-to-Left).  
- Currency: Any (USD, PKR, EUR, GBP, INR, JPY, SAR, AED, CAD, AUD, CNY, KRW, etc.).  
- Numeric format: "." or "," as decimal/thousands separators, spaces or apostrophes, parentheses for negatives, trailing/leading minus.  
- Statement type: credit card, debit card, current/savings account, prepaid card, corporate card, joint accounts.  

Your goal: **recover every transaction reliably, capture even the smallest charges, normalize globally, detect the correct account currency, and output only valid JSON. Never hallucinate.**

-----------------------------------------------------
OUTPUT CONTRACT (MANDATORY)
Return ONLY a valid, pretty-printed JSON object with EXACTLY these keys and types:

{
  "currency": "USD|PKR|EUR|GBP|INR|JPY|SAR|AED|CAD|AUD|CNY|KRW",
  "transactions": [
    { "date": "YYYY-MM-DD", "description": "string", "merchant": "string", "amount": -12.34, "category": "purchase|subscription|cash_advance|transfer|payment|refund|fee|interest", "flags": ["string"] }
  ],
  "totalFees": 0.00,
  "totalSpend": 0.00,
  "subscriptions": ["string"],
  "duplicates": [{"date":"YYYY-MM-DD","description":"string","amount":-0.00}],
  "feeByCategory": {"Foreign Transaction Fee": 0.00, "Late Payment Fee": 0.00, "Interest Charge": 0.00, "Service Fee": 0.00},
  "topFeeMerchants": {"bank fee": 0.00},
  "feesOverTime": {"YYYY-MM": 0.00},
  "tips": ["string"],
  "cardSuggestions": ["string"],
  "hiddenFees": [{"date":"YYYY-MM-DD","description":"string","merchant":"string","amount":-0.00,"labels":["string"]}],
  "flagged": [{"date":"YYYY-MM-DD","description":"string","amount":-0.00,"reason":"string"}]
}

If no transactions exist → return empty arrays and zeros for numeric fields.  
NEVER add extra keys. NEVER return prose.  

-----------------------------------------------------
GLOBAL DATA EXTRACTION POLICY

1) Transaction Line Discovery
   - Prefer table blocks (Date | Description | Amount | Balance).  
   - If no tables, infer transactions from repeating patterns (date + amount + text).  
   - Handle multi-column pages, headers/footers, balance carryovers, watermarks.  
   - Merge wrapped lines into the parent transaction if no new date/amount appears.  
   - Skip non-transactional rows (balance carried forward, opening balance, closing balance, credit limit, interest rate notices).

2) Dates
   - Accept any global date format: DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD, YYYY/MM/DD, DD.MM.YYYY, YYYY.MM.DD, DD-MMM-YYYY, 01 Jan 2025, 1-June-25, etc.  
   - Normalize all to ISO `YYYY-MM-DD`.  
   - Use statement context to disambiguate (e.g., if locale is EU → DD/MM/YYYY by default).  
   - Continuation lines inherit previous transaction’s date.  
   - Ignore statement period dates unless attached to a fee/charge.

3) Amount Parsing & Currency
   - Capture signs consistently: minus “-”, trailing minus, parentheses = negative.  
   - Credits/refunds/payments = inflow (positive). Purchases/fees/interest = outflow (negative).  
   - **Important:** Many credit card statements show charges as positive numbers (no minus). If a line is clearly a debit/charge, always treat it as negative outflow.  
   - Detect thousands/decimal separators: `1,234.56` vs `1.234,56`. Normalize to dot decimal.  
   - Detect currency codes/symbols anywhere: $, PKR, €, £, ¥, د.إ, etc.  
   - Always set the root `"currency"` key to the ISO 4217 code of the account/statement currency.  
   - OCR correction: fix `O/0`, `S/$`, `,/.`, `l/1`.  
   - Record posted/settled amount in account currency (not original FX currency unless statement explicitly posts both).  
   - Preserve micro-values (0.01). Always output with 2 decimals.

4) Description vs Merchant
   - `description` = raw text exactly as shown (human readable).  
   - `merchant` = normalized brand/entity name. Remove trailing city/country unless relevant.  
   - Examples:  
     • "Netflix.com Los Gatos SG" → merchant: "Netflix.com".  
     • "DOCKERS MM ALAM LAHORE" → merchant: "DOCKERS MM ALAM LAHORE".  
   - If only location given, combine with adjacent brand if clear.

5) Categories (must match one of these only)
   - purchase → POS/online buys.  
   - subscription → recurring monthly/weekly services (Netflix, Spotify, iCloud, telecom plans).  
   - cash_advance → ATM withdrawal or cash at bank counter.  
   - transfer → Raast, IBFT, ACH, SEPA, FasterPayments, Zelle, Venmo, etc.  
   - payment → user bill payments to card or bank inflows.  
   - refund → merchant reversal/chargeback/credit.  
   - fee → service fees, excise/GST/VAT, withholding, annual, overlimit, SMS banking, giro reject, card replacement.  
   - interest → markup/finance/interest charges.

6) Flags (multi-label possible)
   - "foreign_tx_fee" → explicit foreign transaction fee line.  
   - "currency_conversion" → international merchant/FX indicator.  
   - "service_fee" → service/processing/ATM/SMS/annual/maintenance/IT/PRA/withholding/advance tax/excise duty.  
   - "late_payment" → late fee line.  
   - "interest_accrual" → finance charge/interest.  
   - "reversal" → refunds.  
   - "cash" → ATM/cash advances.  
   - "tax" → VAT/GST/excise/PRA/withholding/advance tax.  
   - "overlimit" → overlimit fee.  
   - Add others if explicit (e.g., "fx_pair", "posting_delay").

7) Hidden Fees
   - Always include lines customers usually miss:  
     • Foreign transaction fees.  
     • Excise/GST/VAT.  
     • PRA/withholding/advance tax.  
     • Service/processing/SMS banking fees.  
     • Annual fee, card maintenance fee.  
     • Overlimit fee.  
     • Rejected giro/penalty.  
     • Small markup/interest charges.  
   - Each entry: add `"labels": ["human friendly type"]`.

8) Fee Aggregation
   - feeByCategory:  
     • "Foreign Transaction Fee" = sum of FX fee lines.  
     • "Late Payment Fee" = sum of late payment lines.  
     • "Interest Charge" = sum of interest/finance/markup lines.  
     • "Service Fee" = sum of all operational charges, excise/VAT/GST, PRA/withholding/advance tax, SMS, annual, rejected giro, overlimit, etc.  
   - topFeeMerchants: group by normalized merchant. Bank/system charges use `"bank fee"`.

9) Totals
   - totalFees = sum of all `"fee"` + `"interest"`.  
   - totalSpend = sum of `"purchase"` + `"subscription"` + `"cash_advance"` (absolute).  
   - duplicates: if same date±1 day + same amount + similar description/merchant, keep one and push others into `"duplicates"`.

10) Fees Over Time
   - Group all `"fee"` + `"interest"` by `"YYYY-MM"`.

11) Subscriptions
   - Detect recurring cadence (same merchant, same/similar amount, monthly).  
   - Add merchant once in `"subscriptions"`.

12) Anomalies ("flagged")
   - Flag if:  
     • Fee ≥ 1000 (account currency).  
     • Late payment fee.  
     • Rejected giro fee.  
     • Overlimit fee.  
     • Interest spike (interest > 2× mean of prior interest).  
     • FX fee > 3.5% of related purchase.  
   - Format: {"date":"YYYY-MM-DD","description":"string","amount":-0.00,"reason":"string"}.

13) Quality Gates
   - No hallucinations. Never invent missing data.  
   - Dates must be ISO.  
   - Amounts must be numeric with 2 decimals.  
   - JSON must be syntactically valid, no trailing commas.  
   - Skip header/footer totals unless explicit transactions.  
   - If unsure of category, pick safest + add clarifying flag.

14) Coaching
   - tips: 3–5 plain actionable steps (avoid late fees, use autopay, waive service fees, pick no-FX card, track subscriptions).  
   - cardSuggestions: 2–4 concise suggestions (cashback, no-FX, fee-free, low markup).

RETURN ONLY THE JSON. NO EXTRA TEXT.

PROMPT;
}

}