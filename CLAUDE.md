# Just Island Holidays: start here

A holiday rental directory for Mallorca, Menorca, Ibiza and Formentera. Owners and agents pay to list. Affiliates are added by a super admin and earn the platform commission through their own booking programmes. The platform takes no bookings or payments from guests.

The full specification is in `docs/balearic-spec.md`. Read it before building anything. This file lists the decisions already made, the build order, and what to assume where something is still open. If this file and the spec disagree, stop and ask.

## Business facts
- Brand and legal name: Just Island Holidays. Not VAT registered, so subscription prices carry no VAT.
- Registered office: 86 Manston Road, Ramsgate, CT11 0RJ, United Kingdom (used in the privacy notice and terms).
- Main domain (decided 18 Nov 2026): https://justislandholidays.co.uk. justislandholidays.uk and both www. hosts redirect to it (301) through `SITE_CANONICAL_HOST=justislandholidays.co.uk` in the server's `.env`. The hosting package moved from the staging address https://justislandholidays-uk.stackstaging.com/ to this domain, so until launch the server sets `SITE_BLOCK_INDEXING=true` to keep search engines out.

## Stack (decided)
- PHP 8.3 or later, Laravel 12 (only Laravel 12 is available now). Upgrade to Laravel 13 is roadmap item 1b, soon after launch and before Laravel 12 security fixes end on 24 Feb 2027. Do not use anything that blocks that upgrade.
- MySQL 8.0.16 or later (InnoDB, utf8mb4). Schema is in the spec: 30 tables. Build it as Laravel migrations.
- Server-rendered Blade views with Tailwind CSS, Laravel Fortify for auth (email verification, two-factor required for island admins and super admins).
- Admin two-factor (decided 19 Nov 2026): by default a one-time 6-digit code emailed at each sign-in (`App\Auth\SecondFactor`: hashed in the cache, 10 minutes, used once, voided after 5 wrong tries, 5 sends an hour). Each admin can switch to an authenticator app on their security page, which is stronger. The `RequireSecondFactor` middleware in the web group keeps an admin on `/account/verify-sign-in` until the session has done the step, however they signed in. Owners, agents and affiliates are unchanged (app optional).
- Payments: PayPal Subscriptions and webhooks, with an idempotency log in `paypal_events`. No card data on our servers.
- Hosting: managed PHP host with a UK data centre. The host provides the CDN and DDoS protection. No Cloudflare.
- Spam control: honeypot field, ALTCHA proof-of-work, per-IP-hash rate limits. No Turnstile.
- Errors: GlitchTip, self-hosted (Sentry-compatible SDK). Analytics: Plausible Community Edition or Matomo, self-hosted, cookieless.
- Email: the owner's existing UK transactional email provider. Enquiry emails use reply-to set to the visitor.
- Data stays in the UK.

## Roles
super admin, island admin (assigned to one or more islands in `island_admins`), owner, agent, affiliate.
- Every admin query goes through `AdminScope`. An island admin opening another island's record by URL must be refused, with a test for each.
- Island admins approve listings and verify licences for their islands. They cannot reject a listing or suspend an owner. They create a recommendation (`admin_recommendations`) with a reason, and a super admin accepts or declines it.
- A super admin covers any island with no island admin.
- Only a super admin creates affiliates and admin accounts, and only a super admin can open Appearance, Staff, Plans and Audit log.
- Affiliates have no plan, no invoices, and no licence number requirement.

## Rules that are easy to get wrong
- Affiliate listings have only the critical details, one image and a `deeplink_url` shown as a Check availability banner. Owner and agent listings need a verified licence before approval. Affiliate listings need none, because the affiliate shows it on its own site.
- Visitors reach deeplinks through `/go/{ref}`, which logs the click (salted hash, never the raw IP) and redirects. Clicks older than 13 months are deleted, keeping daily totals.
- `listings.pets_allowed` is nullable. Hide the pets fact on the public page when it is null. Do not ask for pets on the affiliate import form.
- Licence checks are manual: an admin looks up the number in the official regional register and records the result. Nothing is checked automatically.
- Every enquiry is emailed to the owner and also stored. Admins read them in the read-only Enquiries section, with recent ones flagged. Opening one is written to the audit log. Delete enquiries after 24 months (proposed).
- Appearance (super admin only): five colours with contrast checks (text and button need 4.5 to 1 or publishing is blocked), up to five hero images each with alt text, logo with alt text, favicon, and a footer image with alt text (added 20 Nov 2026: the same image behind the whole footer on every public page, under a dark layer so the white text and buttons stay readable, footer at least 300 pixels tall; setting `footer.image`, WebP copies through `App\Support\HeaderImage`, placeholder until added). All of it follows a draft and publish flow and is audited. Footer links are orange buttons with white text (orange-700, like the island page resort buttons).
- URL import (`/admin/listings/import`): fetch only over https, with timeouts, a size cap, no private addresses and limited redirects. Copy the image to our own storage only after the permission box is ticked. No hot-linking. Providers have agreed in principle that all listing data can be used.
- Moderation target: 48 hours. Highlight anything older in the queue.
- Rejecting, pausing and deactivating need a reason, which is emailed to the person affected and written to the audit log.

## Build order
1. Project setup, migrations, seeders (islands, areas, amenities, plans as placeholders), Fortify, two-factor, roles and policies with tests.
2. Public site: home, island, search, listing page, enquiry form, `/go/{ref}` tracking.
3. Owner and agent dashboard, listing editor, PayPal subscriptions and webhooks.
4. Admin area: the 14 launch pages in the spec's page map first (Dashboard, Listings, Listing review, Licence checks, Deeplink monitor, Owners and agents, Affiliates, Staff, Click reports, Islands and resorts, Content pages, Appearance, Plans and subscriptions, Audit log), then the 5 that follow. Enquiries is also built at launch (confirmed by the owner, 18 Nov 2026), because the privacy notice says admins can read enquiries. Weeks 8 to 10 in the roadmap is too short for this many pages, so plan for more time or defer some.
5. Nightly jobs: deeplink checker, data retention, click daily totals.
6. Legal pages, accessibility pass, launch gate (licence verification working, legal review done).

MVP scope is Menorca and Mallorca, English only. Spanish and German, Ibiza and Formentera come in phase 2, but keep every text field and route ready for three languages. Launch island summaries in English, Spanish and German are drafted and need native-speaker review before use.

## Open items, with the default to build on
- Plan names, limits and prices: decided when the site is finished. Build with placeholder plans in the `plans` table, and keep prices out of code.
- Which booking sites deeplinks may point to: depends on the affiliate partnerships. Make the allowed hosts a setting, defaulting to any https URL, and warn on the Deeplink monitor page for hosts not on the list.
- Company or sole trader: affects wording only. Leave a placeholder in the privacy notice and terms.
- Resort page copy: not written yet. Build the pages and leave the text editable.
- EU Regulation 2024/1028: treat the directory as an online platform. What reporting it needs is to be confirmed with an adviser before launch. Do not build reporting yet.
- Whether the CDN processes data outside the UK: ask the host before launch.
- Trademark: run a UK Intellectual Property Office search for Just Island Holidays before the live domain goes public.

## Project layout and spec fixes (agreed 18 Nov 2026)
- The Laravel app is in `justislandholidays/`, served locally by Laravel Herd at http://justislandholidays.test on PHP 8.4. Composer's platform is pinned to PHP 8.3 to match the host.
- `users.last_login_at` and `users.deactivated_at` are added to the spec schema. Deactivating sets `deactivated_at`, ends the account's sessions and blocks sign-in; it never soft-deletes, because soft-deleted accounts are purged after 30 days.
- `listings.public_ref` (VARCHAR(12), unique, random) is added to the spec schema. It is the `{ref}` in listing URLs and `/go/{ref}`.
- The FULLTEXT index is on `listing_translations (title, summary)`. There is no description column.
- `islands` has `header_image_path` and `header_image_alt` (added 19 Nov 2026): a full-width header image under the menu on island pages, 400 pixels tall (240 on phones), stored as WebP copies by `App\Support\HeaderImage`. A placeholder in `public/images/placeholders` shows until one is added. Island admins edit it for their own islands, like the intro text. Resort and town pages have no header image.
- Property types are editable (agreed 19 Nov 2026): new `property_types` table (code, name JSON per language, sort_order, is_active), and `listings.property_type` is a VARCHAR(30) code instead of an ENUM. Super admins only (`can:manage-property-types`, `/admin/property-types`). The code never changes. A type in use cannot be deleted, only switched off; switched-off types leave search and new listings, but listings keep them. Use `App\Models\PropertyType` (`activeCodes()`, `allowedFor()`, `label()`), never a hard-coded list.
- `users.affiliate_logo_path` and `users.affiliate_logo_alt` (added 19 Nov 2026): an affiliate's logo, shown in a 100-pixel banner above Check availability on their property pages (fitted, not cropped; a "Partner logo" placeholder until one is added). Super admins upload it on the affiliate's admin page (`App\Support\AffiliateLogo`; PNG, JPG or WebP, no SVG). Removed when a deleted account is purged.
- Plans, photos and Featured Property (agreed 19 Nov 2026; migration `2026_11_19_000008`):
  - Names: "Free Plan", "Owners Plan" (was Standard), "Featured Plan" (was Featured), "Agent Plus" (codes unchanged: `free`, `standard`, `featured`, `agent_plus`; renamed by migrations `000008` and `000009`, which never overwrite a name chosen in admin). "Grant a plan" is now "Gift A Plan".
  - `plans` gained `header_image_path`/`_alt` (card image, 800 × 300, `App\Support\PlanImage`, placeholder until added), `max_photos` (Free 3, Owners 12, Featured Property 12, Agent Plus 12 per property; counts the main photo), `has_booking_calendar` (a "Booking calendar" line on the card only; no calendar is built yet) and `price_3m/6m/9m/12m_cents`.
  - Photos: `listings.image_path` stays the main photo; extra photos are in the new `listing_photos` table, added and removed in the editor's Photo step and shown as a gallery on the property page, only up to the plan's limit (extras over the limit are kept, not deleted). Affiliate listings keep one photo. This replaces the spec's "single image".
  - Billing is yearly only: monthly prices are no longer offered or edited (existing monthly subscriptions keep running). Paid plan prices show "for 1 year".
  - Featured Property is a one-off PayPal payment (Orders API, `PayPalClient::createOrder` and `captureOrder`) for 3, 6, 9 or 12 months from the day of payment, with no renewal. Each payment is a `featured_purchases` row and, once captured on return from PayPal, a `subscriptions` row with `billing_interval = 'one_off'` that ends on its end date.
  - The List your holiday home page has a brand-colour opening band, numbered steps, and plan cards with the card image, photo count, booking calendar line where set, and an orange price footer.
- A From price per week is required for owner and agent properties (decided 19 Nov 2026): required in the owner editor, the admin Add a property form and admin Edit details, and `ListingChecklist` refuses sending or approving without one, so the property stays inactive. Affiliate listings may leave it out. Shown on the property page as "From Price". Properties that were already live without one are not paused automatically.
- Admins add properties for owners and agents (decided 19 Nov 2026): `/admin/listings/add` (`PropertyCreateController`), island admins for their own islands, super admins for all. The lister is found by the email of an existing, active owner or agent account. The form has everything the owner's editor needs (details, coordinates, photo with permission, licence number, optional booking link). If the admin ticks that they checked the licence in the official register, the licence is recorded as verified and the property goes live at once (through `ListingWorkflow`, so plan limits still apply and the lister is emailed); otherwise it waits for review. Facilities are left for the lister or a later admin edit.
- Our partners page (agreed 19 Nov 2026): `/{lang}/partners`, linked in the footer, lists every active affiliate by name with their logo (placeholder until one is added). New `users.affiliate_website_url` (https only), set by super admins on the affiliate's admin page; the logo links there through `/go/partner/{id}` (stateless), which counts clicks per day in the new `partner_clicks_daily` table (no visitor details). The admin affiliate page shows the counts. Website cleared when a deleted account is purged.
- Commission reports are not needed (decided 19 Nov 2026): no commission figures in the admin (affiliates list and page, dashboard, click report), and the spec's after-launch Commissions page is not built. The `affiliate_commissions` table stays in the schema, unused. Click tracking and the optional click id on affiliate links are unchanged.
- Affiliate Adverts (decided 19 Nov 2026, differs from the spec's Ads page): banners 75 pixels tall (300 × 75, 4 to 1), each for one island (no "all islands"), in one of three sections, each an ad slot (`AdSlotSeeder`, `App\Ads\IslandAdverts`):
  - Menu Advert Section (`menu_section`): 4 banners below the intro on the island page and every resort page of that island. With more than 4 running, 4 are picked at random (weighted, no repeats) on each page view; empty spaces show an "Advertise here" placeholder that is not a link.
  - Sidebar Section (`sidebar_section`): property pages on that island, directly below Check availability (at the end of the sidebar when a home has no Check availability button). Every running advert shows, in random order; one placeholder when there are none.
  - Both (`both_sections`): the advert appears in both sections.
  Super admins only (`can:manage-ads`, `/admin/ads`, "Affiliate Adverts" in the menu); island admins have no access. Clicks go through `/api/ads/{id}/click` (stateless) and are counted per day in `ad_stats_daily`; impressions are not counted yet. The old column slots `island_intro_1` to `_4` were merged into the Menu Advert Section by migration `2026_11_19_000005`.
- No Livewire. Search caching uses Laravel's cache store, not Redis by default.
- Affiliate listings show a licence number only if one is supplied.
- Paid plans with `show_direct_contact` show the owner's phone and website on the listing page (decided 18 Nov 2026). Only those two columns of `listing_private`, plus `show_phone`, are ever read on a public route, through `App\Listings\DirectContact`. Affiliate listings never show them.
  - The phone shows only if the owner chose "Yes, show it publicly" in the editor's Location and contact step (`listing_private.show_phone`, added 19 Nov 2026, default hidden, existing phones included). After deploying, run `php artisan listings:ask-phone-choice --dry-run`, then without `--dry-run`, to email paid owners with a hidden phone once.
- Local MySQL 8.4 runs as the Windows service `MySQL84`. The app uses database `justislandholidays`; tests use `justislandholidays_test`.
- Admin decisions made in build step 4 (18 Nov 2026), open to change:
  - "Grant a plan or featured period" is a complimentary subscription for the whole account (owner or agency) until a date, not a per-listing override, because plans are account-wide.
  - Logo and favicon accept PNG only. SVG served from our own domain can carry script, so it needs sanitising before it is allowed.
  - Staff and affiliate invitations are a set-your-password link valid for 7 days (`invites` password broker).
  - When a super admin accepts a rejection recommendation, their note is added to the island admin's reason, and the lister receives both.
  - The URL import never loads the provider's photo in the admin preview either (no hot-linking). It copies the photo only on save, after the permission box is ticked.
  - Deactivating an account pauses its live and waiting listings. Reactivating does not make them live again; the lister does that.
- Booking calendar (agreed 19 Nov 2026; migration `2026_11_19_000010`; replaces the spec's phase 3 calendar sync): for owner and agent properties on plans with `has_booking_calendar` (switched on for all paid plans). New `calendar_feeds` (iCal addresses, private) and `calendar_bookings` (check_in, check_out, feed_id null when the owner marked it; dates only, never guest details). The editor's Calendar step imports feeds (Airbnb, Booking.com, Vrbo, Google Calendar; webcal:// read as https, fetched through `SafeFetcher`) with Sync now, and owners book or free single nights by pressing a day, or enter a stay. Nights from a feed can only be changed at the source. The property page shows 3 months at a time, up to a year ahead; each day is split corner to corner (morning = night before, afternoon = night from that day), so check-in and check-out days show as half days. `App\Calendar\Availability`, `IcalParser`, `FeedSync`; nightly `calendars:sync` at 01:30. Owners can show the calendar on their own website with an iframe: `/embed/calendar/{ref}` (live, paid-plan listings only; stateless, no cookies, noindex and disallowed in robots.txt). It is the only page without `X-Frame-Options`; it sends `frame-ancestors *` instead. The iframe code is in the editor's Calendar step.
- Owner and agent links (agreed 20 Nov 2026; migration `2026_11_20_000002`): paid plans only (`Listing::onPaidPlan()`). On the Free Plan the editor's Link step explains this, a link kept from a paid plan stays but is hidden (and `/go/{ref}` goes to the property page), and it shows again on upgrade. New `listings.link_type` (`booking`, `website`, `facebook`, default `booking`, CHECK `chk_link_type`): the owner chooses it in the Link step (admins too on Add a property), and the button says Check availability, Visit Our Website or Visit Our Facebook (`Listing::linkLabel()`). Facebook links must be on facebook.com, fb.com or fb.me (`Listing::isFacebookUrl()`), and only the Facebook type may use them. Affiliates always have Check availability.
- Rates table (agreed 20 Nov 2026; migration `2026_11_20_000001`): for owner and agent properties on any plan but Free (`Listing::hasRates()`). New `listing_rates` table (start_date, end_date, both included; nightly_cents, weekly_cents, fortnight_cents for 14 nights, all nullable but at least one required, CHECKs `chk_rates_dates` and `chk_rates_price`). Owners add and remove periods in the editor's Rates step (`RatesController`; periods cannot overlap, up to 60). The property page shows periods not yet ended, in the listing's currency, with a dash for an empty price; rates kept from a paid plan are hidden on Free, not deleted.
  - While a property has a weekly rate still to come, its From price is the lowest of them (`App\Listings\Rates`, applied on every listing save and by the nightly `listings:reconcile`), so the Basics step shows it read-only. Without one, the owner types the From price as before.
- Nightly jobs (build step 5, `routes/console.php`, UK time): `calendars:sync` 01:30, `listings:reconcile` 02:00, `clicks:rollup` 02:30, `deeplinks:check` 03:00, `data:retention` 04:00. The live server needs cron to run `php artisan schedule:run` every minute, plus a queue worker. If the host has no worker, set `QUEUE_VIA_SCHEDULER=true` and cron works through the queue.
  - The nightly link check skips links that are already broken or were switched off, so an admin's switch-off sticks. Admins use Recheck now after a fix.
  - Only affiliate links are checked (decided 19 Nov 2026). Owner and agent links are the owner's or agent's responsibility: never checked (nightly, Recheck now or at approval), never switched off, and an old check result never hides their banner. The Deeplink monitor is per affiliate, under Affiliates in the admin menu. The terms and the listing editor tell owners and agents the link is theirs to keep working. Changing an owner's or agent's link goes live straight away, with no review; changing an affiliate's link still sends it back for review.
  - Purging a deleted account anonymises the user row and deletes their private listing details and licence documents, rather than removing the row, because listings, payments and the audit log still refer to it.
- Launch gate (build step 6): run `php artisan launch:check` on the live server. It fails until every automatic check passes and every manual item is confirmed with `--confirm=<item> --by="Name"`.
  - Automatic checks: licence register address set, two super admins with a verified email, legal pages free of "[TO CONFIRM" and draft notices, production settings, https, main domain, real mail, MySQL, live PayPal, separate secrets.
  - Manual items: legal review, Spanish and German text review, CDN location, EU 2024/1028, trademark search, cron and queue, backups.
  - The legal pages (`LegalPagesSeeder`) are drafts for a lawyer, not legal advice. The seeder never overwrites pages edited in the admin.
- MySQL-only DDL (CHECK constraints, FULLTEXT) runs only on the mysql driver. `php artisan test` runs on in-memory SQLite and skips the MySQL-only tests; `composer test:mysql` runs everything against MySQL. Run both before every commit.

## Working style
- Keep access rules in Laravel policies, with a test for each one.
- Write plain, short copy for visitors. No jargon in admin screens either.
- Meet WCAG 2.2 AA. Every image needs alt text, every form field a label, and buttons at least 44 pixels tall.
- Ask before changing the schema, roles or any decision above.
