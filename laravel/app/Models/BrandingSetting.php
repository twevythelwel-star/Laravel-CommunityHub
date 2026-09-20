<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** White-label branding, shared across all users instead of per-browser localStorage. */
class BrandingSetting extends Model
{
    use HasFactory;

    protected $table = 'branding_settings';

    protected $fillable = ['community_id', 'app_name', 'logo_url', 'primary_color', 'accent_color', 'background_color', 'default_theme', 'theme_tokens'];

    protected function casts(): array
    {
        return ['theme_tokens' => 'array'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['app_name' => 'Community Hub']);
    }
}
