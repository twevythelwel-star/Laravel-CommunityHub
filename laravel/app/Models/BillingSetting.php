<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Single-row settings table replacing the BillingProvider monthlyFee React state. */
class BillingSetting extends Model
{
    use HasFactory;

    protected $table = 'billing_settings';

    protected $fillable = ['monthly_fee_minor', 'currency', 'due_day_of_month'];

    protected function casts(): array
    {
        return ['monthly_fee_minor' => 'integer', 'due_day_of_month' => 'integer'];
    }

    /** Monthly fee as a major-unit float, for display. */
    public function monthlyFee(): float
    {
        return (float) ($this->monthly_fee_minor / 100);
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    /** The currency the community bills in. */
    public static function currencyCode(): string
    {
        return static::current()->currency ?? 'JMD';
    }

    /** An amount in minor units as the app shows money elsewhere, e.g. "$75.00 JMD". */
    public static function format(int $amountMinor, string $currency): string
    {
        return '$'.number_format($amountMinor / 100, 2).' '.$currency;
    }
}
