<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Listers can never mass-assign these. Status changes go through ListingWorkflow;
     * plan, ranking and deeplink checks are set by the platform.
     */
    protected $guarded = [
        'id', 'owner_id', 'agency_id', 'public_ref',
        'status', 'plan_code', 'featured_until', 'rank_score', 'rejection_reason', 'published_at',
        'deeplink_status', 'deeplink_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'has_private_pool' => 'boolean',
            'min_price_week_cents' => 'integer',
            'sleeps' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'distance_to_beach_m' => 'integer',
            'pets_allowed' => 'boolean',
            'display_lat' => 'decimal:6',
            'display_lng' => 'decimal:6',
            'deeplink_checked_at' => 'datetime',
            'featured_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            $listing->public_ref ??= static::newPublicRef();
        });
    }

    /** Short random code for URLs; avoids look-alike characters. */
    public static function newPublicRef(): string
    {
        do {
            $ref = '';
            $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
            for ($i = 0; $i < 8; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::withTrashed()->where('public_ref', $ref)->exists());

        return $ref;
    }

    /** The public sees only live, non-deleted listings. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Live);   // SoftDeletes already hides deleted rows
    }

    /** SQL for when the listing was last sent for review (from the audit log). */
    public static function submittedAtSql(): string
    {
        return "(SELECT MAX(a.created_at) FROM audit_log a WHERE a.entity = 'listing'"
            ." AND a.action IN ('listing.submit', 'listing.back_to_review')"
            .' AND a.entity_id = CAST(listings.id AS CHAR))';
    }

    /** Adds submitted_at, used for the 48-hour review target. */
    public function scopeWithSubmittedAt(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('listings.*');
        }

        return $query->selectRaw(self::submittedAtSql().' AS submitted_at');
    }

    /** Waiting for review for longer than the 48-hour target. */
    public function scopeOverdue(Builder $query, int $hours = 48): Builder
    {
        return $query->where('status', ListingStatus::PendingReview)
            ->whereRaw(self::submittedAtSql().' < ?', [now()->subHours($hours)->format('Y-m-d H:i:s')]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** Owner-private data. Never load this on public routes. */
    public function private(): HasOne
    {
        return $this->hasOne(ListingPrivate::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ListingTranslation::class);
    }

    public function licence(): HasOne
    {
        return $this->hasOne(ListingLicence::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'listing_amenities');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(DeeplinkClick::class);
    }

    public function isAffiliateListing(): bool
    {
        return $this->owner?->isAffiliate() ?? false;
    }

    /**
     * Which map marker this listing gets: affiliate, paid (any plan but Free, including
     * a complimentary one) or free. plan_code is kept in step by AccountPlan::sync.
     */
    public function markerKind(): string
    {
        if ($this->isAffiliateListing()) {
            return 'affiliate';
        }

        return $this->plan_code !== null && $this->plan_code !== 'free' ? 'paid' : 'free';
    }

    /**
     * Text in the visitor's language, falling back to English.
     * Returns [translation or null, true when it is the English fallback].
     *
     * @return array{0: ?ListingTranslation, 1: bool}
     */
    public function translationFor(?string $lang = null): array
    {
        $lang ??= app()->getLocale();
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        $exact = $translations->firstWhere('lang', $lang);
        if ($exact) {
            return [$exact, false];
        }

        return [$translations->firstWhere('lang', 'en'), $lang !== 'en'];
    }

    public function title(?string $lang = null): string
    {
        return $this->translationFor($lang)[0]?->title ?? 'Holiday home';
    }

    public function summary(?string $lang = null): ?string
    {
        return $this->translationFor($lang)[0]?->summary;
    }

    /** /{lang}/{island}/{area}/{slug}-{ref} */
    public function publicUrl(?string $lang = null): string
    {
        return route('listings.show', [
            'lang' => $lang ?? app()->getLocale(),
            'island' => $this->island->slug,
            'area' => $this->area->slug,
            'listing' => $this->slug.'-'.$this->public_ref,
        ]);
    }

    /**
     * The banner shows whenever there is a link. For affiliate links, only while it has not
     * been switched off or found broken. Owner and agent links are never checked, so an old
     * check result does not hide them.
     */
    public function showsCheckAvailability(): bool
    {
        if ($this->deeplink_url === null) {
            return false;
        }

        return ! $this->isAffiliateListing() || $this->deeplink_status !== 'broken';
    }

    /** The booking calendar: owner and agent properties on a plan that includes it (paid plans). */
    public function hasCalendar(): bool
    {
        if ($this->isAffiliateListing()) {
            return false;
        }

        return (bool) Plan::where('code', $this->plan_code ?: 'free')->value('has_booking_calendar');
    }

    public function calendarFeeds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarFeed::class)->orderBy('id');
    }

    public function calendarBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarBooking::class)->orderBy('check_in');
    }

    /** Extra photos after the main one, in order. */
    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Photos this listing's plan allows, counting the main photo (Free 3, paid 12; affiliates 1). */
    public function photoLimit(): int
    {
        if ($this->isAffiliateListing()) {
            return 1;
        }

        return Plan::where('code', $this->plan_code ?: 'free')->first()?->photoLimit() ?? 3;
    }

    /** Extra photos to show visitors: processed ones, within the plan's limit. */
    public function visiblePhotos(): \Illuminate\Support\Collection
    {
        return $this->photos->filter(fn (ListingPhoto $p) => $p->image_path !== null)->take(max(0, $this->photoLimit() - 1))->values();
    }

    /** Plain words for the link's state on admin screens. */
    public function deeplinkStatusLabel(): string
    {
        if ($this->deeplink_url === null) {
            return 'No link';
        }
        if (! $this->isAffiliateListing()) {
            return 'Lister looks after it';
        }

        return ['unchecked' => 'Not checked', 'ok' => 'Working', 'broken' => 'Broken'][$this->deeplink_status] ?? 'Not checked';
    }

    /** Tracked link for the Check availability banner. */
    public function goUrl(string $source, ?string $lang = null): string
    {
        return route('go', ['ref' => $this->public_ref, 's' => $source, 'l' => $lang ?? app()->getLocale()]);
    }

    public function imageUrl(int $width = 1600): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $path = preg_replace('/-1600\.webp$/', "-{$width}.webp", $this->image_path);

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }

    /** srcset for the WebP copies made by ProcessListingImage. */
    public function imageSrcset(): ?string
    {
        if (! $this->image_path || ! str_ends_with($this->image_path, '-1600.webp')) {
            return null;
        }

        return collect(\App\Jobs\ProcessListingImage::WIDTHS)
            ->map(fn ($w) => $this->imageUrl($w)." {$w}w")
            ->implode(', ');
    }

    public function guidePrice(): ?string
    {
        if ($this->min_price_week_cents === null) {
            return null;
        }

        $symbol = $this->currency === 'GBP' ? '£' : '€';

        return $symbol.number_format($this->min_price_week_cents / 100);
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }
}
