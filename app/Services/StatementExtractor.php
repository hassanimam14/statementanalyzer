<?php

namespace App\Services;

use Carbon\Carbon;
use League\Csv\Reader;
use Smalot\PdfParser\Parser as PdfParser;

class StatementExtractor
{
    // =========================
    // Tunables / guards
    // =========================
    protected int $maxProbeLines  = 6000;
    protected float $maxAbsAmount = 10_000_000;

    protected array $ignoreDescHints = [
        'opening balance','closing balance','balance brought forward','balance c/f','balance b/f',
        'total','subtotal','new balance','statement balance','minimum amount due','amount due',
        'payments & credits','payments and credits','late fee assessment','interest charged',
        'page','statement date','account number','statement number',
        'pay date','payment due','payment due on','due date',
        'customer number','card number','card limit',
        'credit card information','bill to','mailing address','total amount',
        'account summary','payment information','payment coupon','transactions summary',
        'important information','finance charge summary','rate information',
        'detach and mail','detatch and mail',
        'for customer service','for lost or stolen card',
        'payment due date','credit line','available credit','cash access line','available for cash',
    ];

    // =========================
    // Public API
    // =========================
    public function extract(string $fullPath, array $meta = []): array
    {
        $ext      = strtolower($meta['ext'] ?? pathinfo($fullPath, PATHINFO_EXTENSION));
        $fileName = $meta['filename'] ?? basename($fullPath);

        try {
            $rows = [];
            $rawLines = [];

            if (in_array($ext, ['csv','tsv','txt'], true)) {
                if ($ext === 'csv' || $ext === 'tsv') {
                    $rows = $this->readDelimitedFile($fullPath, $ext === 'tsv' ? "\t" : ',');
                    $rawLines = []; // not needed here
                } else {
                    $rawLines = $this->readTextFile($fullPath);
                    $slice    = $this->sliceTransactionsSection($rawLines);

                    $rows = $this->tryAllTabularParsers($slice);
                    if (empty($rows)) $rows = $this->parseRegexScan($slice);
                    if (empty($rows)) {
                        $rows = $this->tryAllTabularParsers($rawLines);
                        if (empty($rows)) $rows = $this->parseRegexScan($rawLines);
                    }
                }

            } elseif ($ext === 'pdf') {
                // 1) vector text
                $text = $this->pdfToText($fullPath);
                // 2) OCR fallback if suspiciously small
                if (mb_strlen(trim($text)) < 60) {
                    $ocrText = $this->ocrPdfPages($fullPath);
                    if (mb_strlen(trim($ocrText)) > mb_strlen($text)) $text = $ocrText;
                }

                $rawLines = $this->splitLines($text);
$statementCcy = $this->detectStatementCurrency($rawLines); // ADD


                // synthesize panel fees and append to rows later
                $panelFees = $this->scanFeePanels($rawLines);
                $close     = $this->inferStatementEndDate($rawLines);

                $slice = $this->sliceTransactionsSection($rawLines);

                $rows = $this->tryAllTabularParsers($slice);
                if (empty($rows)) $rows = $this->parseRegexScan($slice);
                if (empty($rows)) {
                    $rows = $this->tryAllTabularParsers($rawLines);
                    if (empty($rows)) $rows = $this->parseRegexScan($rawLines);
                }

                // append synthetic panel fee rows as [date, description, amount]
                if (!empty($panelFees)) {
                    foreach ($panelFees as $pf) {
                        $panelDate = $pf['date'] ?: ($close ? $close->toDateString() : '');
                        $rows[] = [$panelDate, $pf['label'], (string)$pf['amount']];
                    }
                }

            } elseif (in_array($ext, ['jpg','jpeg','png','bmp','tif','tiff','gif'], true)) {
                $text     = $this->imageToText($fullPath);
                $rawLines = $this->splitLines($text);
                $statementCcy = $this->detectStatementCurrency($rawLines); // ADD
                // synthesize panel fees and append to rows later
                $panelFees = $this->scanFeePanels($rawLines);
                $close     = $this->inferStatementEndDate($rawLines);

                $slice    = $this->sliceTransactionsSection($rawLines);

                $rows = $this->tryAllTabularParsers($slice);
                if (empty($rows)) $rows = $this->parseRegexScan($slice);
                if (empty($rows)) {
                    $rows = $this->tryAllTabularParsers($rawLines);
                    if (empty($rows)) $rows = $this->parseRegexScan($rawLines);
                }

                // append synthetic panel fee rows as [date, description, amount]
                if (!empty($panelFees)) {
                    foreach ($panelFees as $pf) {
                        $panelDate = $pf['date'] ?: ($close ? $close->toDateString() : '');
                        $rows[] = [$panelDate, $pf['label'], (string)$pf['amount']];
                    }
                }

            } else {
                // generic text
                $rawLines = $this->readTextFile($fullPath);
                $slice    = $this->sliceTransactionsSection($rawLines);

                $rows = $this->tryAllTabularParsers($slice);
                if (empty($rows)) $rows = $this->parseRegexScan($slice);
                if (empty($rows)) {
                    $rows = $this->tryAllTabularParsers($rawLines);
                    if (empty($rows)) $rows = $this->parseRegexScan($rawLines);
                }
            }

            // Normalize → Dedupe
$transactions = $this->normalizeTransactions($rows, $statementCcy ?? null);
            $transactions = $this->dedupe($transactions);
            $transactions = $this->dropEchoAmounts($transactions); // <-- add this line
             $transactions = $this->rescueLargeCanonicalFees($transactions);

            return [
                'file' => [
                    'name'               => $fileName,
                    'mime'               => $meta['mime'] ?? null,
                    'ext'                => $ext,
                    'total_rows_detected'=> is_array($rows) ? count($rows) : 0,
                    'total_transactions' => count($transactions),
                ],
                  'statement_currency' => $statementCcy ?? null,   // ADD
                'transactions' => $transactions,
            ];

        } catch (\Throwable $e) {
            return [
                'file' => ['name' => $fileName, 'ext' => $ext, 'error' => $e->getMessage()],
                'transactions' => [],
            ];
        }
    }

    // =========================
    // I/O helpers
    // =========================
    protected function readDelimitedFile(string $fullPath, string $delimiter = ','): array
    {
        $csv = Reader::createFromPath($fullPath, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(null);

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $rows[] = array_map(
                fn($v) => is_scalar($v) ? trim((string)$v) : '',
                $record
            );
        }
        return $rows;
    }

    protected function readTextFile(string $fullPath): array
    {
        $txt = @file_get_contents($fullPath) ?: '';
        return $this->splitLines($txt);
    }

    protected function splitLines(string $text): array
    {
        $text  = str_replace(["\r\n","\r"], "\n", $text);
        $lines = explode("\n", $text);
        $out   = [];
        foreach ($lines as $l) {
            $l = rtrim($l);
            if ($l !== '') $out[] = $l;
            if (count($out) >= $this->maxProbeLines) break;
        }
        return $out;
    }

    // =========================
    // PDF / OCR
    // =========================
    protected function pdfToText(string $fullPath): string
    {
        // prefer .env override, e.g.: "C:/poppler-25.07.0/Library/bin/pdftotext.exe"
        $bin = $this->findExecutable('PDFTOTEXT_BIN', 'pdftotext', 'pdftotext');
        if ($bin) {
            $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
            @shell_exec(sprintf('"%s" -layout -enc UTF-8 "%s" "%s"', $bin, $fullPath, $tmp));
            $content = @file_get_contents($tmp) ?: '';
            @unlink($tmp);
            if ($content !== '') return $content;
        }

        // fallback: pure PHP parser
        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($fullPath);
            return $pdf->getText();
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function ocrPdfPages(string $fullPath): string
    {
        $ppm  = $this->findExecutable('PDFTOPPM_BIN', 'pdftoppm', 'pdftoppm');
        $tess = $this->findExecutable('TESSERACT_BIN', 'tesseract', 'tesseract');
        if (!$ppm || !$tess) return '';

        $tmpBase  = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_'.uniqid();
        $maxPages = (int) env('OCR_MAX_PAGES', 6);
        $dpi      = (int) env('OCR_PDF_DPI', 200);
        $redir    = $this->isWindows() ? '2>NUL' : '2>/dev/null';

        @shell_exec(sprintf('"%s" -png -r %d "%s" "%s" %s', $ppm, $dpi, $fullPath, $tmpBase, $redir));

        $text = '';
        for ($i = 1; $i <= $maxPages; $i++) {
            $png = sprintf('%s-%d.png', $tmpBase, $i);
            if (!is_file($png)) break;
            $text .= "\n".$this->imageToText($png);
            @unlink($png);
        }
        return trim($text);
    }

    protected function imageToText(string $fullPath): string
    {
        $bin  = $this->findExecutable('TESSERACT_BIN', 'tesseract', 'tesseract');
        if (!$bin) return '';
        $lang = (string) env('TESSERACT_LANG', 'eng');
        $psm  = (string) env('TESSERACT_PSM', '6');

        $outBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $redir   = $this->isWindows() ? '2>NUL' : '2>/dev/null';
        @shell_exec(sprintf('"%s" "%s" "%s" -l %s --psm %s %s', $bin, $fullPath, $outBase, $lang, $psm, $redir));
        $txt = @file_get_contents($outBase.'.txt') ?: '';
        @unlink($outBase.'.txt');
        return $txt;
    }

    protected function findExecutable(string $envKey, string $winCmd, string $unixCmd): ?string
    {
        // 1) .env
        $cfg = env($envKey);
        if (is_string($cfg) && $cfg !== '' && file_exists($cfg)) return $cfg;

        // 2) PATH
        if ($this->isWindows()) {
            $found = trim((string)@shell_exec("where {$winCmd} 2>nul"));
            if ($found) {
                $cand = preg_split('/\R/', $found)[0] ?? '';
                if ($cand && file_exists($cand)) return $cand;
            }
        } else {
            $found = trim((string)@shell_exec("which {$unixCmd} 2>/dev/null"));
            if ($found && file_exists($found)) return $found;
        }

        // 3) well-known Windows installs
        $known = [
            // Poppler tools
            'C:\poppler\bin\pdftotext.exe',
            'C:\poppler\bin\pdftoppm.exe',
            'C:\poppler-25.07.0\Library\bin\pdftotext.exe',
            'C:\poppler-25.07.0\Library\bin\pdftoppm.exe',
            'C:\ProgramData\chocolatey\lib\poppler\tools\pdftotext.exe',
            'C:\ProgramData\chocolatey\lib\poppler\tools\pdftoppm.exe',
            // Tesseract
            'C:\Program Files\Tesseract-OCR\tesseract.exe',
            'C:\ProgramData\chocolatey\bin\tesseract.exe',
        ];
        foreach ($known as $p) if (is_file($p)) return $p;

        return null;
    }

    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    // =========================
    // Panels / Dates (scan helpers)
    // =========================
    protected function inferStatementEndDate(array $lines): ?Carbon
    {
        $text = implode(' ', $lines);

        $patterns = [
            '/(closing\s+date|statement\s+closing\s+date|cycle\s+ending|statement\s+end|billing\s+cycle\s+ending|period\s+ending|payment\s+due\s+on)[:\s]+([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}|\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2})/i',
            '/statement\s+period[:\s]+(?:from|)\s*[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}\s*(?:to|-)\s*([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2})/i',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $text, $m)) {
                try {
                    $dt = $this->parseDateFlexible($m[2] ?? $m[1] ?? '');
                    if ($dt) return $dt;
                } catch (\Throwable $e) {}
            }
        }
        return null;
    }
    protected function detectStatementCurrency(array $lines): ?string
{
    $txt = mb_strtolower(' '.implode(' ', $lines).' ');
    // strong hints
    $hits = [
        'pkr' => [' pkr',' rs.',' rs ',' pk rs','₨','rupees','pakistan rupees'],
        'usd' => [' usd',' us$','$',' dollars '],
        'eur' => [' eur','€',' euros '],
        'gbp' => [' gbp','£',' pounds '],
        'aed' => [' aed','د.إ',' dirham '],
        'sar' => [' sar','﷼',' riyal '],
        'inr' => [' inr','₹',' rupees '],
    ];
    foreach ($hits as $ccy => $needles) {
        foreach ($needles as $n) if (str_contains($txt, $n)) return strtoupper($ccy);
    }
    // fallback: bank locale (configure in .env), e.g. PK cards
    return strtoupper(env('DEFAULT_STATEMENT_CURRENCY','PKR'));
}


  // =========================
// 1) Replace your existing scanFeePanels() with this
// =========================
/**
 * Scan the whole text for fee/interest summary panels and return synthetic fee items.
 * Each item: ['label' => string, 'amount' => float(negative), 'date' => null|string]
 * Suppresses OCR “echo” fragments (e.g., 166.74 when 2,166.74 exists for same label).
 */
protected function scanFeePanels(array $lines): array
{
    if (empty($lines)) return [];

    $joined = ' ' . implode(' ', $lines) . ' ';
    $out = [];

    // Panel patterns: keep liberal; capture amount in group 1
    $candidates = [
        ['label' => 'Interest Charge',         're' => '/(?:finance|interest)\s+charge[s]?:?\s*\(?\s*[\p{Sc}]?\s*([\-0-9,.\s]+)\)?/iu'],
        ['label' => 'Late Payment Fee',        're' => '/late\s+payment\s+(?:fee|charge)s?:?\s*\(?\s*[\p{Sc}]?\s*([\-0-9,.\s]+)\)?/iu'],
        ['label' => 'Foreign Transaction Fee', 're' => '/foreign\s+transaction\s+fee:?\s*\(?\s*[\p{Sc}]?\s*([\-0-9,.\s]+)\)?/iu'],
        ['label' => 'Currency Conversion Fee', 're' => '/(?:currency\s+conversion|dcc|dynamic\s+currency)[^0-9\-]*([\-0-9,.\s]+)/iu'],
        ['label' => 'Annual Fee',              're' => '/annual\s+fee:?\s*\(?\s*[\p{Sc}]?\s*([\-0-9,.\s]+)\)?/iu'],
        ['label' => 'Cash Advance Fee',        're' => '/cash\s+advance\s+fee:?\s*\(?\s*[\p{Sc}]?\s*([\-0-9,.\s]+)\)?/iu'],

        // Global taxes/duties/levies (PK + common)
        ['label' => 'Service/Other Fee',       're' => '/(?:adv\s*tax|withholding|wht|pra|srb|kpra|fbr|gst|vat|sales\s*tax|excise\s*duty|levy|236y|it\s*services?\s*tax)[^0-9\-]*([\-0-9,.\s]+)/iu'],

        // Misc service fees summarized in panels
        ['label' => 'Service/Other Fee',       're' => '/(?:sms\s+banking\s+fee|service\s+charge|rejected\s+giro\s+service\s+fee)[^0-9\-]*([\-0-9,.\s]+)/iu'],

        // Installment/markup verbiage → treat as interest unless explicitly 0%
        ['label' => 'Interest Charge',         're' => '/(?:markup\s*rate|installment\s+markup|finance\s*charge)[^0-9\-]*([\-0-9,.\s]+)/iu'],
    ];

    foreach ($candidates as $c) {
        if (preg_match_all($c['re'], $joined, $mm)) {
            foreach ($mm[1] as $rawAmt) {
                $amt = $this->parseAmount($rawAmt);
                if (is_float($amt)) {
                    // Force panel numbers to outflow (negative)
                    $amt = $amt < 0 ? $amt : -abs($amt);

                    // Skip when 0% markup is globally indicated (best-effort)
                    if ($c['label'] === 'Interest Charge' && preg_match('/\b0\s*%\b/i', $joined)) {
                        continue;
                    }

                    $out[] = [
                        'label'  => $c['label'],
                        'amount' => $amt,
                        'date'   => null,
                    ];
                }
            }
        }
    }

    if (empty($out)) return $out;

    // ---- Echo-fragment suppression (keep 2,166.74; drop 166.74 for same label) ----
    $byLabel = [];
    foreach ($out as $i => $pf) {
        $lab = (string)$pf['label'];
        $amt = abs((float)$pf['amount']);
        $byLabel[$lab][] = ['i' => $i, 'amt' => $amt];
    }

    $dropIdx = [];
    foreach ($byLabel as $lab => $arr) {
        usort($arr, fn($a, $b) => $b['amt'] <=> $a['amt']);
        $max = $arr[0]['amt'] ?? 0.0;

        if ($max >= 500) {
            $maxS = number_format($max, 2, '.', '');
            foreach ($arr as $r) {
                $s = number_format($r['amt'], 2, '.', '');
                if ($s === $maxS) continue;
                if (str_ends_with($maxS, $s)) {
                    $dropIdx[$r['i']] = true;
                }
            }
        }
    }

    if (!empty($dropIdx)) {
        $out = array_values(array_filter($out, fn($v, $i) => !isset($dropIdx[$i]), ARRAY_FILTER_USE_BOTH));
    }

    return $out;
}



    // =========================
    // Page region trimming
    // =========================
    protected function sliceTransactionsSection(array $lines): array
    {
        if (empty($lines)) return $lines;

        $startIdx = null;
        $starts = [
            '/\b(card|account)\s+activity\b/i',
            '/\btransactions?\s+detail\b/i',
            '/\baccount\s+activity\s+detail\b/i',
            '/\btransactions?\b/i',
            '/\bactivity\s+since\b/i',
            '/\bactivity\s+for\b/i',
            '/\bcharges?\s+and\s+credits?\b/i',
        ];
        foreach ($lines as $i => $l) {
            foreach ($starts as $re) {
                if (preg_match($re, $l)) { $startIdx = $i; break 2; }
            }
            if (preg_match('/\b(trans|posted?)\b.\b(description)\b.\b(credit|credits)\b.*\b(charge|charges|debit)\b/i', $l)) {
                $startIdx = max(0, $i - 1);
                break;
            }
        }
        if ($startIdx === null) return $lines;

        $endIdx = count($lines) - 1;
        $stops = [
            '/\bsummary\s+of\s+fees\b/i',
            '/\bclosing\s+summary\b/i',
            '/\bsummary\s+of\s+account\b/i',
            '/\b(payment\s+coupon|detach\s+and\s+mail|send\s+payment\s+to)\b/i',
            '/\b(rate|finance\s+charge)\s+information\b/i',
            '/\baccount\s+summary\b/i',
            '/\bimportant\b.*\binformation\b/i',
            '/\bfor\s+customer\s+service\b/i',
            '/\bpage\s+\d+\s+of\s+\d+\b/i',
        ];
        for ($j = $startIdx + 1; $j < count($lines); $j++) {
            foreach ($stops as $re) {
                if (preg_match($re, $lines[$j])) { $endIdx = $j - 1; break 2; }
            }
        }

        $slice = array_slice($lines, $startIdx, max(0, $endIdx - $startIdx + 1));
        return array_values(array_filter($slice, fn($l) => trim($l) !== ''));
    }

    // =========================
    // Table extraction strategies
    // =========================
    protected function tryAllTabularParsers(array $rawLines): array
    {
        $rows = $this->parseDelimitedGuess($rawLines);
        if (!empty($rows)) return $rows;

        $rows = $this->parseFixedWidthText($rawLines);
        if (!empty($rows)) return $rows;

        $rows = $this->parseSpaceClustered($rawLines);
        if (!empty($rows)) return $rows;

        return [];
    }

    protected function parseDelimitedGuess(array $lines): array
    {
        $delims = [",", "\t", ";", "|"];
        $best = []; $bestScore = 0;

        foreach ($delims as $d) {
            $parsed=[]; $widths=[];
            foreach ($lines as $line) {
                if (!is_string($line)) continue;
                $cells = array_map('trim', explode($d, $line));
                if (count($cells) < 2) continue;
                $parsed[] = $cells;
                $widths[] = count($cells);
            }
            if (count($parsed) >= 5) {
                $mode = $this->mode($widths);
                $consistency = $mode > 0
                    ? (count(array_filter($widths, fn($w)=>$w===$mode)) / max(1, count($widths)))
                    : 0;
                $score = $consistency * count($parsed);
                if ($score > $bestScore) { $best=$parsed; $bestScore=$score; }
            }
        }
        return $best;
    }

    protected function parseFixedWidthText(array $lines): array
    {
        $probe = array_values(array_filter($lines, fn($l)=>is_string($l)));
        $probe = array_slice($probe, 0, min(200, count($probe)));
        $maxLen = $probe ? max(array_map('strlen', $probe)) : 0;
        if ($maxLen === 0) return [];

        $spaceCounts = array_fill(0, $maxLen, 0);
        foreach ($probe as $line) {
            $line = str_pad($line, $maxLen, ' ');
            for ($i=0; $i<$maxLen; $i++) if ($line[$i]===' ') $spaceCounts[$i]++;
        }

        $threshold = max(3, (int) floor(0.8*count($probe)));
        $splits=[]; $inGap=false;
        for ($i=0; $i<$maxLen; $i++) {
            if ($spaceCounts[$i] >= $threshold) {
                if (!$inGap) { $splits[]=$i; $inGap=true; }
            } else $inGap=false;
        }
        if (empty($splits)) return [];

        $cutPoints=[0];
        foreach ($splits as $pos) if ($pos - end($cutPoints) >= 3) $cutPoints[] = $pos;
        if ($maxLen - end($cutPoints) >= 3) $cutPoints[] = $maxLen;
        if (count($cutPoints) < 3) return [];

        $rows=[];
        foreach ($lines as $line) {
            if (!is_string($line)) continue;
            $line = str_pad($line, $maxLen, ' ');
            $cols=[];
            for ($i=0; $i<count($cutPoints)-1; $i++) {
                $start=$cutPoints[$i]; $end=$cutPoints[$i+1];
                $cols[] = trim(substr($line, $start, $end-$start));
            }
            if (count(array_filter($cols, fn($c)=>$c!==''))
                > 1) $rows[]=$cols;
        }
        return $rows;
    }

    protected function parseSpaceClustered(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (!is_string($line)) continue;

            // collapse 2+ spaces as split markers, keep single spaces
            $tmp   = preg_replace('/ {2,}/', '␟', $line);
            $cells = array_filter(array_map('trim', explode('␟', $tmp)), fn($c)=>$c!=='');

            if (count($cells) >= 2) {
                // join OCR/table-split amount fragments
                $cells = $this->mergeBrokenMoneyTokens(array_values($cells));
                $rows[] = array_values($cells);
            }
        }
        return $rows;
    }

    /**
     * Join money fragments split by OCR or table spacing, e.g.:
     * ["2,", "166.74"] -> "2,166.74"
     * ["1", "200.00"]  -> "1 200.00"
     * ["12,", "345", ".67"] -> "12,345.67"
     */
    protected function mergeBrokenMoneyTokens(array $cells): array
    {
        $out = [];
        $i = 0;

        $cells = array_values(array_map(fn($c) => trim((string)$c), $cells));

        while ($i < count($cells)) {
            $cur = $cells[$i];

            // A: "2," + "166.74"
            if ($i + 1 < count($cells)
                && preg_match('/^\-?\d{1,3},$/', $cur)
                && preg_match('/^\d{3}(?:[.,]\d{2})?$/', $cells[$i+1])) {
                $out[] = $cur . $cells[$i+1];
                $i += 2;
                continue;
            }

            // B: "2," + "166" + ".74"
            if ($i + 2 < count($cells)
                && preg_match('/^\-?\d{1,3},$/', $cur)
                && preg_match('/^\d{3}$/', $cells[$i+1])
                && preg_match('/^[.,]\d{2}$/', $cells[$i+2])) {
                $out[] = $cur . $cells[$i+1] . $cells[$i+2];
                $i += 3;
                continue;
            }

            // C: "2" + ",166.74"
            if ($i + 1 < count($cells)
                && preg_match('/^\-?\d{1,3}$/', $cur)
                && preg_match('/^,[0-9]{3}(?:[.,]\d{2})?$/', $cells[$i+1])) {
                $out[] = $cur . $cells[$i+1];
                $i += 2;
                continue;
            }

            // D: "1" + "200.00" (space thousands)
            if ($i + 1 < count($cells)
                && preg_match('/^\-?\d{1,3}$/', $cur)
                && preg_match('/^\d{3}(?:[.,]\d{2})?$/', $cells[$i+1])) {
                $out[] = $cur . ' ' . $cells[$i+1];
                $i += 2;
                continue;
            }

            // E: dangling decimal ".75"
            if (!empty($out) && preg_match('/^[.,]\d{2}$/', $cur) && preg_match('/\d$/', end($out))) {
                $out[count($out)-1] .= $cur;
                $i++;
                continue;
            }

            $out[] = $cur;
            $i++;
        }
        return $out;
    }

    // =========================
    // Regex scan fallback
    // =========================
    protected function parseRegexScan(array $lines): array
    {
        $rows = [];
        $dateRe  = '(?:\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}|\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{2,4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{2,4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4})';
        $moneyRe = '\(?\s*(?:\p{Sc}|USD|EUR|GBP|INR|AUD|CAD|CHF|JPY|PKR|SAR|AED)?\s*-?\d{1,3}(?:[ ,\.]\d{3})*(?:[.,]\d{2})\s*\)?\s*(?:CR|DR)?';

        foreach ($lines as $l) {
            if (!is_string($l)) continue;

            // date + desc + LAST money token
            if (preg_match('/(?P<date>'.$dateRe.')(?:\s+|-|:|,)\s*(?P<desc>.+?)\s+(?P<amt>'.$moneyRe.')(?:\s+\S.*)?$/u', $l, $m)) {
                $rows[] = [ trim($m['date']), trim($m['desc']), trim($m['amt']) ];
                continue;
            }

            // sometimes amount precedes description
            if (preg_match('/(?P<date>'.$dateRe.')\s+(?P<amt>'.$moneyRe.')\s+(?P<desc>.+)$/u', $l, $m)) {
                $rows[] = [ trim($m['date']), trim($m['desc']), trim($m['amt']) ];
                continue;
            }
        }
        return $rows;
    }

    // =========================
    // Normalization
    // =========================
    /**
     * Normalize loosely parsed rows into canonical transactions.
     */
protected function normalizeTransactions(array $rows, ?string $defaultCurrency = null): array
    {
        $headerIdx = $this->guessHeaderIndex($rows);
        $headers   = $headerIdx !== null ? array_map('strtolower', $rows[$headerIdx]) : [];

        $txs = [];

        foreach ($rows as $i => $cols) {
            if ($headerIdx !== null && $i === $headerIdx) continue;
            if (!is_array($cols) || count($cols) < 2) continue;

            // Map columns to (date/description/amount|debit|credit)
            $mapped = $this->mapColumns($headers, $cols);

            // DATE
            $date = $this->parseDateFlexible($mapped['date'] ?? null) ?? $this->parseDateFromAny($cols);
            if (!$date || !$this->isPlausibleDate($date)) continue;

            // DESCRIPTION
            $desc = $mapped['description'] ?? $this->firstTextish($cols);
            if (!$desc || $this->looksLikeDateOnly($desc) || $this->looksLikeSummaryRow($desc)) continue;

            // AMOUNT
            // --- AMOUNT (currency-aware) ---
$money = null;

// prefer mapped columns first
if (isset($mapped['debit']) || isset($mapped['credit'])) {
    $d = $this->parseMoneyToken((string)($mapped['debit']  ?? ''), $defaultCurrency);
    $c = $this->parseMoneyToken((string)($mapped['credit'] ?? ''), $defaultCurrency);

    if ($d && $c) {
        $money = [
            'amount'        => round(($c['amount'] ?? 0) - abs($d['amount'] ?? 0), 2),
            'currency_code' => $c['currency_code'] ?: ($d['currency_code'] ?? $defaultCurrency ?? 'PKR'),
        ];
        $money['amount_minor'] = (int) round($money['amount'] * 100);
    } elseif ($d) {
        $money = $d;
        $money['amount'] = -abs($money['amount']);
        $money['amount_minor'] = -abs($money['amount_minor']);
    } elseif ($c) {
        $money = $c;
        $money['amount'] = abs($money['amount']);
        $money['amount_minor'] = abs($money['amount_minor']);
    }
} else {
    // single amount column or infer from row
    $m = null;
    if (!empty($mapped['amount'])) {
        $m = $this->parseMoneyToken((string)$mapped['amount'], $defaultCurrency);
    }
    if (!$m) {
        // fall back to scanning the row from right to left as you already do
        $amt = $this->parseAmountFromAny($cols);
        if ($amt !== null) {
            $m = [
                'amount'        => $amt,
                'amount_minor'  => (int) round($amt * 100),
                'currency_code' => $defaultCurrency ?: 'PKR',
            ];
        }
    }
    $money = $m;
}

if (!$money || !$this->isPlausibleAmount((float)$money['amount'])) continue;

// Unsigned purchase heuristic (apply to float, but keep currency/minor)
if ($money['amount'] > 0) {
    $descLC = mb_strtolower($desc);
    $looksCredit = (bool) preg_match(
        '/\b(payment|credit|refund|reversal|return|charge\s*back|cash\s*back|cashback|deposit|promo\s*credit|adjustment|waiver|waived|fee\s*reversal|rebate|issuer\s*reversal|bill\s*payment\s*received|bank\s*transfer\s*in|ach\s*credit|neft\s*in|rtgs\s*in|sepa\s*credit|salary|payroll)\b/i',
        $descLC
    );
    if (!$looksCredit) {
        $money['amount'] = -abs($money['amount']);
        $money['amount_minor'] = -abs($money['amount_minor']);
    }
}
            // Canonical row
            $txs[] = [
                        'date'           => $date->toDateString(),
                        'description'    => $desc,
                        'merchant'       => $this->merchantFromDesc($desc),
                        'amount'         => round((float)$money['amount'], 2),
                        'type'           => ((float)$money['amount'] < 0 ? 'debit' : 'credit'),
                        'currency_code'  => (string)($money['currency_code'] ?? ($defaultCurrency ?? 'PKR')),
                        'amount_minor'   => (int)($money['amount_minor'] ?? (int) round(((float)$money['amount']) * 100)),
                        'raw'            => $cols,
                    ];

        }

        return $txs;
    }

    // =========================
    // Column mapping
    // =========================
    protected function guessHeaderIndex(array $rows): ?int
    {
        $candidates = ['date','description','desc','details','merchant','amount','debit','credit','balance','narration','particular'];
        $best=null; $bestScore=0;
        foreach ($rows as $idx => $r) {
            $rowText = strtolower(implode(' ', $r));
            $hits = 0; foreach ($candidates as $w) if (str_contains($rowText, $w)) $hits++;
            if ($hits >= 2 && count($r) >= 3) { // must look like headers
                $best = $idx; $bestScore = $hits;
            }
        }
        return $best;
    }

    protected function mapColumns(array $headers, array $cols): array
    {
        $map = ['date'=>null,'description'=>null,'amount'=>null,'debit'=>null,'credit'=>null];

        if (!empty($headers) && count($headers) === count($cols)) {
            foreach ($headers as $i => $hRaw) {
                $h = strtolower(trim($hRaw));

                if (preg_match('/^(trans|transaction|post(ed)?|posted\s*date|value\s*date|date)$/i', $h)) {
                    if ($map['date'] === null) $map['date'] = $cols[$i];
                }
                if (preg_match('/(desc|narration|details|description|merchant|memo|particular|activity)/i', $h)) {
                    $map['description'] = $cols[$i];
                }
                if (preg_match('/\b(amount|amt|value)\b/i', $h)) {
                    $map['amount'] = $cols[$i];
                }
                if (preg_match('/\b(debit|withdrawal|charge|charges?)\b|\bdr\b/i', $h)) {
                    $map['debit'] = $cols[$i];
                }
                if (preg_match('/\b(credit|credits?|payment|deposit|refund|reversal)\b|\bcr\b/i', $h)) {
                    $map['credit'] = $cols[$i];
                }
            }
        }

        if ($map['description'] === null) {
            $mid = (int) floor((count($cols)-1)/2);
            $map['description'] = $cols[$mid] ?? $cols[0] ?? null;
        }
        if ($map['date'] === null) {
            $map['date'] = $cols[0] ?? null;
        }
        if ($map['amount'] === null && $map['debit'] === null && $map['credit'] === null) {
            $map['amount'] = end($cols) ?: null;
        }
        return $map;
    }

    // =========================
    // Parsing helpers (amounts, dates, money tokens)
    // =========================
    protected function parseAmount(?string $raw): ?float
    {
        if ($raw === null) return null;
        $rawStr = trim((string) $raw);
        if ($rawStr === '') return null;

        // Normalize unicode minus/dashes
        $rawStr = str_replace(["\xE2\x88\x92", '–', '—'], '-', $rawStr);

        // Detect CR/DR and parentheses
        $isCR     = (bool) preg_match('/\bCR\b/i', $rawStr);
        $isDR     = (bool) preg_match('/\bDR\b/i', $rawStr);
        $negParen = (bool) preg_match('/\(\s*[\p{Sc}\d.,\s-]+\)/u', $rawStr);
        // Keep digits, dot, comma, minus
        $num = preg_replace('/[^\d,.\-]/u', '', $rawStr);
        if ($num === '' || $num === '-' || $num === '--') return null;

        $lastDot = strrpos($num, '.');
        $lastCom = strrpos($num, ',');

        if ($lastDot !== false && $lastCom !== false) {
            if ($lastCom > $lastDot) {
                // 1.234,56
                $num = str_replace('.', '', $num);
                $num = str_replace(',', '.', $num);
            } else {
                // 1,234.56
                $num = str_replace(',', '', $num);
            }
        } elseif ($lastCom !== false) {
            // Only comma
            if (preg_match('/,\d{2}\s*$/', $num)) {
                $num = str_replace('.', '', $num);
                $num = str_replace(',', '.', $num);
            } else {
                $num = str_replace(',', '', $num);
            }
        } elseif ($lastDot !== false) {
            // Only dot
            if (!preg_match('/\.\d{2}\s*$/', $num)) {
                // likely grouping like 1.234.567
                $num = str_replace('.', '', $num);
            } else {
                // if multiple dots, keep only the last as decimal
                if (substr_count($num, '.') > 1) {
                    $parts = explode('.', $num);
                    $dec   = array_pop($parts);
                    $int   = implode('', $parts);
                    $num   = $int.'.'.$dec;
                }
            }
        }

        if (!preg_match('/^-?\d+(\.\d+)?$/', $num)) return null;

        $val = (float) $num;

        // Determine sign (DR/parentheses/minus make negative; CR overrides to positive)
        $negative = false;
        if ($negParen || $isDR) $negative = true;
        if (strpos($rawStr, '-') !== false) $negative = true;
        if ($isCR) $negative = false;

        return $negative ? -abs($val) : abs($val);
    }

    // NEW: currency-aware money token parser
protected function parseMoneyToken(string $raw, ?string $defaultCcy = null): ?array
{
    $s = trim($raw);
    if ($s === '') return null;

    // parentheses/minus/DR/CR → sign
    $neg = (bool) preg_match('/\(\s*.*\s*\)$/u', $s);
    $s   = str_replace(["\xE2\x88\x92",'–','—'], '-', $s);
    $isDR = (bool) preg_match('/\bDR\b/i', $raw);
    $isCR = (bool) preg_match('/\bCR\b/i', $raw);

    // detect explicit currency hints
    $sLC = mb_strtolower($s);
    $iso = null;
    $map = [
        'USD' => [' usd',' us$','$',' dollars '],
        'EUR' => [' eur','€',' euros '],
        'GBP' => [' gbp','£',' pounds '],
        'PKR' => [' pkr',' rs.',' rs ','₨',' pak rs',' pakistan rupees'],
        'INR' => [' inr','₹',' rs '],
        'AED' => [' aed','د.إ',' dirham '],
        'SAR' => [' sar','﷼',' riyal '],
    ];
    foreach ($map as $code => $needles) {
        foreach ($needles as $n) {
            if (str_contains($sLC, $n)) { $iso = $code; break 2; }
        }
    }

    // normalize numeric part
    $num = preg_replace('/[^\d,.\-]/u', '', $s);
    if ($num === '' || $num === '-' || $num === '--') return null;

    $lastDot = strrpos($num, '.');
    $lastCom = strrpos($num, ',');

    if ($lastDot !== false && $lastCom !== false) {
        if ($lastCom > $lastDot) { // 1.234,56
            $num = str_replace('.', '', $num);
            $num = str_replace(',', '.', $num);
        } else {                   // 1,234.56
            $num = str_replace(',', '', $num);
        }
    } elseif ($lastCom !== false) {
        if (preg_match('/,\d{2}$/', $num)) {
            $num = str_replace('.', '', $num);
            $num = str_replace(',', '.', $num);
        } else {
            $num = str_replace(',', '', $num);
        }
    } elseif ($lastDot !== false) {
        if (!preg_match('/\.\d{2}$/', $num) && substr_count($num, '.') > 1) {
            $parts = explode('.', $num);
            $dec   = array_pop($parts);
            $num   = implode('', $parts).'.'.$dec;
        }
    }

    if (!preg_match('/^-?\d+(\.\d+)?$/', $num)) return null;

    $val = (float) $num;
    if ($isDR) $neg = true;
    if (str_contains($raw, '-')) $neg = true;
    if ($isCR) $neg = false;
    if ($neg) $val = -abs($val);

    return [
        'amount'        => $val,
        'amount_minor'  => (int) round($val * 100),
        'currency_code' => $iso ?: ($defaultCcy ?: 'PKR'),
    ];
}


    protected function parseAmountFromAny(array $cols): ?float
    {
        // scan from right to left, trying pairs/triples too
        for ($i = count($cols)-1; $i >= 0; $i--) {
            $c = trim((string)$cols[$i]);

            // try this cell alone
            if ($this->looksLikeMoneyToken($c)) {
                $a = $this->parseAmount($c);
                if ($a !== null) return $a;
            }

            // try merge with left neighbor
            if ($i - 1 >= 0) {
                $merge = trim((string)$cols[$i-1]) . trim((string)$cols[$i]);
                if ($this->looksLikeMoneyToken($merge)) {
                    $a = $this->parseAmount($merge);
                    if ($a !== null) return $a;
                }
            }

            // try triple merge
            if ($i - 2 >= 0) {
                $merge3 = trim((string)$cols[$i-2]) . trim((string)$cols[$i-1]) . trim((string)$cols[$i]);
                if ($this->looksLikeMoneyToken($merge3)) {
                    $a = $this->parseAmount($merge3);
                    if ($a !== null) return $a;
                }
            }
        }
        return null;
    }

    protected function parseDateFlexible(?string $raw): ?Carbon
    {
        if (!$raw) return null;
        $raw = trim($raw);

        $formats = [
            'Y-m-d','d-m-Y','m-d-Y','d/m/Y','m/d/Y','d.m.Y','m.d.Y',
            'd M Y','j M Y','M d Y','M j Y','d-M-Y','M-d-Y','d.M.Y',
            'Y/m/d','Y.m.d','Y M d','d M y','j M y','d/m/y','m/d/y',
            'd M Y H:i','j M Y H:i','M d Y H:i','M j Y H:i','Y-m-d H:i','d/m/Y H:i','m/d/Y H:i',
        ];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt && $this->isPlausibleDate($dt)) return $dt;
            } catch (\Throwable $e) {}
        }

        $patterns = [
            '/\b\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4}(?:\s+\d{1,2}:\d{2})?/',
            '/\b[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{2,4}(?:\s+\d{1,2}:\d{2})?/',
            '/\b\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}(?:\s+\d{1,2}:\d{2})?/',
            '/\b\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{2,4}(?:\s+\d{1,2}:\d{2})?/',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $raw, $m)) {
                try {
                    $dt = new Carbon($m[0]);
                    if ($this->isPlausibleDate($dt)) return $dt;
                } catch (\Throwable $e) {}
            }
        }

        try {
            $dt = new Carbon($raw);
            if ($this->isPlausibleDate($dt)) return $dt;
        } catch (\Throwable $e) {}

        return null;
    }

    protected function parseDateFromAny(array $cols): ?Carbon
    {
        foreach ($cols as $c) {
            $d = $this->parseDateFlexible($c);
            if ($d) return $d;
        }
        return null;
    }

    protected function firstTextish(array $cols): ?string
    {
        foreach ($cols as $c) {
            if (preg_match('/[A-Za-z]/', $c)) return $c;
        }
        return $cols[0] ?? null;
    }

    protected function looksLikeMoneyToken(string $token): bool
    {
        $t = trim($token);
        // allow currency words/symbols around
        if (preg_match('/\p{Sc}|USD|EUR|GBP|INR|AUD|CAD|CHF|JPY|PKR|SAR|AED/i', $t)) return true;
        if (preg_match('/\b(CR|DR)\b/i', $t)) return true;

        // strip surrounding parentheses for negative
        $core = trim($t, " \t\n\r\0\x0B()");

        // 1) standard 1,234.56 / 1.234,56
        if (preg_match('/^-?\d{1,3}(?:[ ,\.]\d{3})+(?:[.,]\d{2})?$/', $core)) return true;

        // 2) plain decimals 123.45 / 123,45
        if (preg_match('/^-?\d+[.,]\d{2}$/', $core)) return true;

        // 3) space-thousands "1 234.56" or "1 234,56"
        if (preg_match('/^-?\d{1,3}(?:\s\d{3})+(?:[.,]\d{2})?$/', $core)) return true;

        return false;
    }

    // =========================
    // Heuristics / filters
    // =========================
    protected function looksLikeDateOnly(string $s): bool
    {
        $s2 = strtolower(trim($s));
        if (preg_match('/^(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)\s+\d{1,2}$/i', $s2)) return true;
        if (preg_match('/^\d{4}\s+\d{1,2}$/', $s2)) return true;
        if ($this->parseDateFlexible($s)) {
            return !preg_match('/[A-Za-z]{4,}/', $s2);
        }
        return false;
    }

    protected function looksLikeSummaryRow(string $s): bool
    {
        $x = strtolower($s);
        foreach ($this->ignoreDescHints as $hint) {
            if (str_contains($x, $hint)) return true;
        }
        return false;
    }

    protected function isPlausibleAmount(float $a): bool
    {
        return is_finite($a) && abs($a) > 0.005 && abs($a) <= $this->maxAbsAmount;
    }

    protected function isPlausibleDate(Carbon $d): bool
    {
        $y = (int) $d->year;
        return $y >= 1990 && $y <= 2100;
    }

    protected function merchantFromDesc(string $desc): string
    {
        $m = strtolower($desc);
        $m = preg_replace('/[0-9]+/', '', $m);
        $m = preg_replace('/\s{2,}/', ' ', $m);
        $m = trim($m);
        return $m ?: $desc;
    }

    protected function mode(array $arr): int
    {
        $freq = [];
        foreach ($arr as $v) $freq[$v] = ($freq[$v] ?? 0) + 1;
        arsort($freq);
        return (int) array_key_first($freq);
    }

/**
 * Drops smaller OCR echo amounts when a larger amount with *equivalent* normalized label
 * exists on the same date. Normalization strips dates, digits/counters (" 2"), and noise.
 * Always KEEP large canonical fees (late fee, giro fee, service charge, markup/interest).
 */
protected function dropEchoAmounts(array $txs): array
{
    if (empty($txs)) return $txs;

    // Canonical fee keywords we must NEVER drop when >= 500
    $alwaysKeepBig = '/(late\s*payment|rejected\s+giro|service\s*charge|markup|interest)/i';

    $canon = function (string $s): string {
        $s = mb_strtolower($s);
        // remove full dates and month words
        $s = preg_replace('/\b\d{1,2}\s+[a-z]{3,9}\s+\d{4}\b/u', '', $s);
        $s = preg_replace('/\b\d{1,2}\s+[a-z]{3,9}\b/u', '', $s);
        // strip obvious counters/ids/digits & punctuation
        $s = preg_replace('/[0-9]+/u', ' ', $s);
        $s = preg_replace('/[^a-z\s]/u', ' ', $s);
        // collapse common fee phrases to canonical tokens
        $map = [
            'foreign transaction fee'   => 'fxfee',
            'excise duty on charges'    => 'exciseduty',
            'adv tax  y  filer'         => 'advtax',     // 236y gets digit-stripped
            'service charge'            => 'servicecharge',
            'late payment charge'       => 'latefee',
            'sms banking fee'           => 'smsfee',
            'rejected giro service fee' => 'girofee',
            'markup rate'               => 'markup',
            'finance charge'            => 'interest',
            'interest charge'           => 'interest',
        ];
        foreach ($map as $k=>$v) {
            $s = str_replace($k, $v, $s);
        }
        // collapse whitespace & duplicate tokens
        $s = preg_replace('/\s+/', ' ', trim($s));
        $s = preg_replace('/\b(\w+)\s+\1\b/u', '$1', $s);
        return $s;
    };

    // Group by (date + canonical description)
    $groups = [];
    foreach ($txs as $i => $t) {
        $date = (string)($t['date'] ?? '');
        $lab  = $canon((string)($t['description'] ?? ''));
        $amtA = abs((float)$t['amount']);
        $groups[$date.'|'.$lab][] = ['i'=>$i, 'amt'=>$amtA];
    }

    $drop = [];
    foreach ($groups as $key => $g) {
        if (count($g) < 2) continue;

        // Find the max amount in this canon group
        usort($g, fn($a,$b)=>$b['amt']<=>$a['amt']);
        $max = $g[0]['amt'];
        $maxS = number_format($max, 2, '.', '');
        $date = explode('|', $key)[0] ?? '';
        $labelOnly = explode('|', $key)[1] ?? '';

        // Never drop the big canonical fee if it matches our must-keep list
        if ($max >= 500 && preg_match($alwaysKeepBig, $labelOnly)) {
            // ok, we can drop suffix echoes safely
        }

        // Drop suffix echoes (e.g., keep 2,166.74; drop 166.74) within this canon group
        foreach ($g as $r) {
            $s = number_format($r['amt'], 2, '.', '');
            if ($s === $maxS) continue;
            if ($max >= 500 && str_ends_with($maxS, $s)) {
                $drop[$r['i']] = true;
            }
        }
    }

    if (empty($drop)) return $txs;
    return array_values(array_filter($txs, fn($t,$i)=>!isset($drop[$i]), ARRAY_FILTER_USE_BOTH));
}
protected function rescueLargeCanonicalFees(array $txs): array
{
    if (empty($txs)) return $txs;

    $byDate = [];
    foreach ($txs as $t) $byDate[$t['date']][] = $t;

    $keepBig = function (string $d): bool {
        return (bool) preg_match('/(late\s*payment|rejected\s+giro|service\s*charge|markup|interest)/i', $d);
    };

    foreach ($byDate as $date => $arr) {
        // find potential pairs (small echoes present without the big)
        $labels = [];
        foreach ($arr as $t) {
            $labels[] = mb_strtolower((string)$t['description']);
        }
        // If you later add panel-scan to stash originals, you could restore here.
        // For now, this is a placeholder; main fix is the canonical grouping above.
    }
    return $txs;
}

    protected function dedupe(array $txs): array
{
    $seen = [];
    $out  = [];

    foreach ($txs as $t) {
        $date = $t['date'] ?? 'n/a';
        $desc = (string)($t['description'] ?? '');
        $amt  = number_format((float)($t['amount'] ?? 0), 2, '.', '');

        // Normalize description for OCR quirks
        $d = mb_strtolower($desc);
        $d = preg_replace('/\s+/', ' ', $d);
        // strip embedded dates like "16 JUN 2025", "11 Jul 2025"
        $d = preg_replace('/\b\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}\b/u', '', $d);
        $d = preg_replace('/\b\d{1,2}\s+[A-Za-z]{3,9}\b/u', '', $d);
        // collapse duplicate tokens: "jun jun", "service charge charge"
        $d = preg_replace('/\b(\w+)\s+\1\b/u', '$1', $d);
        $d = trim(preg_replace('/\s{2,}/', ' ', $d));

        $key = $date . '|' . md5($d) . '|' . $amt;

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $t;
        } else {
            // prefer the line with a richer/longer original description
            $idx = array_key_last($out);
            if ($idx !== null && isset($out[$idx]) && mb_strlen($desc) > mb_strlen($out[$idx]['description'] ?? '')) {
                $out[$idx] = $t;
            }
        }
    }

    return $out;
}
}
