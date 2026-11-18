<?php

namespace App\Search;

use App\Models\Area;
use App\Models\Island;
use Illuminate\Http\Request;

/** Validated search filters, read from the query string so a search can be bookmarked. */
final class SearchFilters
{
    public const TYPES = ['villa', 'apartment', 'townhouse', 'finca', 'bungalow', 'studio'];

    public const SORTS = ['recommended', 'price_asc', 'price_desc', 'newest', 'sleeps'];

    /**
     * @param  list<string>  $types
     * @param  list<string>  $amenities  amenity codes; all must match
     */
    public function __construct(
        public readonly ?Island $island = null,
        public readonly ?Area $area = null,
        public readonly array $types = [],
        public readonly ?int $sleeps = null,
        public readonly ?int $bedrooms = null,
        public readonly ?int $priceMin = null,   // whole currency units per week
        public readonly ?int $priceMax = null,
        public readonly array $amenities = [],
        public readonly bool $pets = false,
        public readonly bool $pool = false,
        public readonly ?string $text = null,
        public readonly string $sort = 'recommended',
        public readonly int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request, ?Island $island = null, ?Area $area = null): self
    {
        $int = function (string $key, int $min, int $max) use ($request): ?int {
            $value = $request->query($key);
            if (! is_scalar($value) || ! ctype_digit((string) $value)) {
                return null;
            }

            return max($min, min($max, (int) $value));
        };

        $list = function (string $key) use ($request): array {
            $value = $request->query($key, []);
            $value = is_array($value) ? $value : explode(',', (string) $value);

            return array_values(array_unique(array_filter(array_map('strval', $value), fn ($v) => $v !== '')));
        };

        if ($island === null && is_string($request->query('island'))) {
            $island = Island::active()->where('slug', $request->query('island'))->first();
        }
        if ($area === null && $island !== null && is_string($request->query('area'))) {
            $area = $island->areas()->where('slug', $request->query('area'))->first();
        }

        $text = $request->query('q');
        $text = is_string($text) ? trim(mb_substr($text, 0, 100)) : null;

        $sort = $request->query('sort');

        return new self(
            island: $island,
            area: $area,
            types: array_values(array_intersect($list('type'), self::TYPES)),
            sleeps: $int('sleeps', 1, 40),
            bedrooms: $int('bedrooms', 1, 20),
            priceMin: $int('price_min', 0, 100000),
            priceMax: $int('price_max', 0, 100000),
            amenities: array_slice(array_values(array_filter($list('amenity'), fn ($c) => preg_match('/^[a-z_]{2,50}$/', $c))), 0, 10),
            pets: $request->boolean('pets'),
            pool: $request->boolean('pool'),
            text: $text === '' ? null : $text,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'recommended',
            page: max(1, (int) $request->query('page', 1)),
        );
    }

    /**
     * Only island, resort, property type and sleeps make an indexable page.
     * Any other filter gets noindex, follow.
     */
    public function isIndexable(): bool
    {
        return $this->bedrooms === null && $this->priceMin === null && $this->priceMax === null
            && $this->amenities === [] && ! $this->pets && ! $this->pool && $this->text === null
            && $this->sort === 'recommended' && $this->page === 1 && count($this->types) <= 1;
    }

    public function hasFilters(): bool
    {
        return $this->types !== [] || $this->sleeps !== null || $this->bedrooms !== null
            || $this->priceMin !== null || $this->priceMax !== null || $this->amenities !== []
            || $this->pets || $this->pool || $this->text !== null;
    }
}
