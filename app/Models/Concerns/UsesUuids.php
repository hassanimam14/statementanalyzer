<?php
namespace App\Models\Concerns;
use Illuminate\Support\Str;

trait UsesUuids {
    protected static function bootUsesUuids() {
        static::creating(function ($m) { if (!$m->getKey()) $m->{$m->getKeyName()} = (string) Str::uuid(); });
    }
    public function getIncrementing(): bool { return false; }
    public function getKeyType(): string { return 'string'; }
}