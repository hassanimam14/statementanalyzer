<?php
namespace App\Services;
class FeeClassifier {
    protected array $map=[
        'foreign_tx_fee'=>['foreign transaction fee','intl fee','international fee','fx fee','intl svc fee'],
        'currency_conversion'=>['currency conversion','dynamic currency','dcc fee','conversion fee'],
        'interchange_pass'=>['interchange','scheme fee','assessment fee','network fee'],
        'late_payment'=>['late fee','late payment','payment late'],
        'interest_charge'=>['interest','finance charge','apr','revolving interest'],
        'overdraft'=>['overdraft','nsf fee','insufficient funds'],
        'cash_advance'=>['cash advance','atm withdrawal fee','cash fee'],
        'annual_fee'=>['annual fee','membership fee'],
        'service_fee'=>['service fee','processing fee','maintenance fee'],
    ];
    public function classify(string $description,float $amount): array {
        $d=mb_strtolower($description); $hits=[];
        foreach($this->map as $label=>$keys){ foreach($keys as $k){ if(str_contains($d,$k)){ $hits[]=$label; break; } } }
        if(empty($hits) && $amount<0 && preg_match('/\b(fee|charge|assessment)\b/i',$description)) $hits[]='service_fee';
        return array_values(array_unique($hits));
    }
}
