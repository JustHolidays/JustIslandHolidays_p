<?php

namespace App\Models;

use App\Models\Concerns\HasJsonTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A kind of property (villa, apartment, ...). Super admins add, rename, reorder and switch
 * types on or off on the Property types page. The code is fixed once created, because
 * listings and search addresses (?type=villa) use it.
 */
class PropertyType extends Model
{
    use HasJsonTranslations;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    /** @var Collection<string, PropertyType>|null All types keyed by code, loaded once per request. */
    private static ?Collection $all = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$all = null);
        static::deleted(fn () => self::$all = null);
    }

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    /** @return Collection<string, PropertyType> */
    public static function cached(): Collection
    {
        return self::$all ??= self::orderBy('sort_order')->orderBy('id')->get()->keyBy('code');
    }

    /** Switched-on types, in order: offered for new listings and in search. @return Collection<string, PropertyType> */
    public static function active(): Collection
    {
        return self::cached()->where('is_active', true);
    }

    /** @return list<string> */
    public static function activeCodes(): array
    {
        return self::active()->keys()->all();
    }

    /**
     * Codes a listing may be saved with: every switched-on type, plus the listing's current
     * type even if it has since been switched off, so editing other details still works.
     *
     * @return list<string>
     */
    public static function allowedFor(?string $current): array
    {
        $codes = self::activeCodes();
        if ($current !== null && self::cached()->has($current) && ! in_array($current, $codes, true)) {
            $codes[] = $current;
        }

        return $codes;
    }

    /** The type's name in the current language, or a tidy version of the code if it is missing. */
    public static function label(?string $code, ?string $lang = null): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return self::cached()->get($code)?->translate('name', $lang) ?? ucfirst(str_replace('_', ' ', $code));
    }

    public static function forgetCache(): void
    {
        self::$all = null;
    }
}
