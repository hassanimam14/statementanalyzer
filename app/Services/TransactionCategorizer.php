<?php
namespace App\Services;

class TransactionCategorizer {
    protected array $fee = ['fee','charge','maintenance','overdraft','late fee'];
    protected array $subs = ['spotify','netflix','prime','adobe','icloud','dropbox','youtube'];

    public function categorize(array $t): string {
        $d=mb_strtolower($t['description']??'');
        if($this->any($d,$this->fee)) return 'fee';
        if($this->any($d,$this->subs)) return 'subscription';
        return ($t['amount']??0)<0?'expense':'income';
    }
    public function flags(array $t): array
{
    $flags = [];
    $d     = mb_strtolower($t['description'] ?? '');
    $amt   = (float)($t['amount'] ?? 0);  // guard against missing/NULL

    if (!preg_match('/[a-z]{3,}/', $d)) {
        $flags[] = 'ambiguous_description';
    }
    if (abs($amt) > 500) {
        $flags[] = 'large_amount';
    }
    if ($this->any($d, $this->fee) && $amt < 0) {
        $flags[] = 'potential_bank_fee';
    }
    return $flags;
}

    protected function any($hay, $arr){ foreach($arr as $k){ if(str_contains($hay,$k)) return true; } return false; }
}
