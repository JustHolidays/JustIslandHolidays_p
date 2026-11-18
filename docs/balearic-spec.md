# Balearic Holiday Rentals Directory: Technical Spec

Nov 17, 2026 · @Andy

## Overview

This is a holiday rental directory covering Mallorca, Menorca, Ibiza and Formentera. Owners and agents pay to list properties, visitors search and send enquiries direct to the owner, and the platform itself takes no bookings or payments from guests. The brand and legal name is Just Island Holidays

**Revenue model**

- Subscription listing tiers for owners and agents (free, Standard, Featured).
- Display advertising from local businesses (car hire, transfers, restaurants, excursions).
- Affiliate links to flights, car hire and hotels.
- Affiliate listings that send visitors to the affiliate's own booking page through a Check availability banner. The platform earns commission through the affiliates' own affiliate programmes when visitors book. This sits outside plans and billing.

**Goals**

- Launch the MVP on Menorca and Mallorca, then add Ibiza and Formentera once there are about 100 live listings.
- Rank for island, resort and property-type searches in English first, then Spanish and German.
- Give owners a self-service dashboard so listings need no manual admin work.
- Meet Spanish and Balearic tourist-rental registration rules and GDPR from day one.

**Non-goals for the MVP**

- Taking bookings or payments from guests.
- Syncing calendars with Airbnb, Booking.com or other channel managers (phase 3).
- A native mobile app. The site must be fully responsive instead.
- Scraping listings from other sites. Content comes from owners, or from booking providers who have agreed to its use (the admin URL import for affiliate listings).

## Scope and phasing

The MVP is a working directory for two islands with paid tiers, built in about 12 weeks. Later phases add islands, languages and owner tooling.

| Phase | Scope | Islands |
| --- | --- | --- |
| 1. MVP | Public site, search, listing pages with a single image and a Check availability deeplink (with click tracking and a nightly link check), enquiries, owner and affiliate listings, PayPal tiers, admin, English only | Menorca, Mallorca |
| 2. Growth | Spanish and German, ad manager, reviews, saved favourites, compare list, email alerts | Add Ibiza, Formentera |
| 3. Scale | Automated commission import from programme feeds, agent bulk import (CSV and feed), owner analytics | All four |
| 4. Optional | Instant-book via a payments partner, guest accounts, mobile app | All four |

## Users and roles

There are six roles, enforced in the app by Laravel policies and middleware.

| Role | Who | Can do |
| --- | --- | --- |
| Visitor | Anyone, no account | Browse, search, filter, view listings, send enquiries, use the compare list (stored in the browser) |
| Owner | Private property owner | Manage own listings, enquiries and the Check availability link, and buy or change a tier |
| Agent | Agency or manager with many properties | Everything an owner can do, plus an agency profile, team members and bulk tools (phase 3) |
| Affiliate | Partner whose properties link out to their own booking page. Account created by a super admin | Hold listings with a Check availability deeplink. Super admins keep private affiliate notes on the account |
| Island admin | Staff member assigned to one or more islands, with their own login | Moderate listings, verify licences, and manage ads and content, for their islands only |
| Super admin | Platform owner or senior staff | Everything an island admin can do on every island, plus staff accounts, island assignments, tiers, billing and the audit log |

Visitors never need an account. Owners and agents sign up with email and password, and verify their email before publishing.

**Admin roles**

Admin work is split into two roles, each with its own login. An island admin looks after one or more islands. A super admin oversees everything and is the only role that can create admin accounts.

| Capability | Island admin | Super admin |
| --- | --- | --- |
| Review, approve or reject listings | Own islands. Rejection is recommended to a super admin, not done | All islands |
| Verify licence numbers | Own islands | All islands |
| Pause or archive listings | Own islands | All islands |
| See owners and enquiries | For listings on own islands | All |
| Manage ads and CMS pages | Those tied to own islands | All, including ads and pages tied to no island |
| Edit island and resort descriptions | Own islands | All islands |
| Create islands and areas | No | Yes |
| Create admin accounts, change roles, assign islands | No | Yes |
| Plans, prices, tier overrides and refunds | No | Yes |
| Export or delete user data on request | No | Yes |
| View the audit log | No | Yes |
| Create and edit affiliate accounts and affiliate data | No | Yes |
| See click and commission reports | Own islands, read-only | All islands |
| Import commission reports and set commission status | No | Yes |

- Every admin signs in with their own account, with mandatory two-factor authentication. Logins are never shared.
- A super admin creates the account in `/admin/staff` and ticks the islands. An island admin needs at least one island and may cover several, for example Ibiza and Formentera.
- An island admin sees only their islands' listings, owners, enquiries, ads and pages. With more than one island, an island selector in the admin header switches between them.
- A super admin covers any island that has no island admin available, because super admins see every island.
- New listings and licence checks on an island notify that island's admins. If an island has no admin assigned, the notice goes to the super admins.
- Removing an island takes effect on the admin's next request, and deactivating the account ends its sessions.
- Every admin action is written to the audit log with the admin's name. Keep at least two super admin accounts so no single person is a bottleneck.

**Affiliates**

- `affiliate` is a value of `users.role`. Only a super admin creates or edits affiliate accounts, in `/admin/affiliates`. Affiliates cannot sign themselves up.
- The `users.affiliate_data` text field holds the super admin's notes on the affiliate, such as programme, partner ids, commission terms and contact details. It is never shown publicly, and island admins cannot see it.
- An affiliate's listings carry a `deeplink_url`, which visitors see as a Check availability banner that opens the affiliate's own booking page. Admins check each link before approving the listing.
- The `affiliate` role is separate from the `affiliate_links` table, which holds the platform's own travel partner links such as car hire and flights.
- Affiliates are outside billing. They have no plan, subscription or payments, and the checkout, customer portal and billing endpoints refuse affiliate accounts.
- Commission is paid by the affiliate programmes, not through this platform. Each deeplink must carry the affiliate's own tracking id so bookings are credited, and super admins keep the programme terms in the private affiliate notes.
- Affiliates see click and commission figures for their own listings in `/account/clicks`. See Click tracking.
- Affiliate listings do not need a licence number or licence verification, so they have no `listing_licences` row. They still need an admin to check the deeplink before they go live.

## Functional requirements

The MVP has three surfaces: the public site, the owner dashboard and the admin console.

**Public site**

- Home page with an island picker, a search bar, featured listings and recent listings.
- Island and resort landing pages with editorial copy, a map and filtered listings.
- Search results with filters, sort, a list and map toggle, and pagination.
- Listing page with the single image, title and summary, critical details (type, sleeps, bedrooms, bathrooms, pool, pets, distance to beach, guide price), a map, licence number (if the listing has one), a Check availability banner when the listing has a deeplink URL, a similar-listings strip and an enquiry form.
- Compare list (up to 4 listings), held in browser storage.
- Static pages (about, contact, terms, privacy, owner information).

**Enquiries**

- The visitor enters name, email, phone (optional), dates, guests and a message.
- The enquiry is stored and emailed to the owner, with the visitor in reply-to. Spam control uses a honeypot field, ALTCHA and rate limits.
- Owners see enquiries in the dashboard and mark them as new, replied or closed.

**Owner dashboard**

- Create and edit listings in a step-by-step form: basics and critical details, location, image, deeplink URL, licence (not for affiliates), review.
- Single image upload with alt text.
- Optional guide price per week, and a deeplink URL (https only) that visitors see as a Check availability banner.
- Enquiry inbox, listing status (draft, pending, live, paused, rejected) and a tier upgrade page.
- Subscription billing through PayPal.

**Admin console**

- Moderation queue for new and edited listings, with approve or reject and a reason, and a check that the deeplink URL opens the right property.
- Licence verification workflow, plus user, listing and enquiry search.
- Ad slot manager and CMS for resort and island pages, limited to the admin's islands. Tier and promotion overrides, staff accounts, island assignments and the audit log are for super admins only.
- Island admins see and act only on their assigned islands. Super admins see every island and manage staff accounts.
- Affiliate accounts: create and edit affiliates and their affiliate data (super admin only).
- Click and commission reports, limited to the admin's islands. Super admins also import commission reports.

## Administration pages

The admin area is one back office at `/admin` with 19 pages. Island admins use it for their own islands, and super admins use it for everything. This section defines each page: who can open it, what it shows and what can be done there.

**Rules for every admin page**

- **Access:** the route needs the role `island_admin` or `super_admin`, and a completed two-factor sign-in. Pages that are super admin only refuse island admins, and their links are hidden.
- **Scope:** every list and detail page goes through `AdminScope`. An island admin who opens another island's record by URL is refused, not shown an empty page.
- **Island selector:** an admin with more than one island sees a selector in the header, with an option for all of their islands. The choice is kept for the session.
- **Lists:** 25 rows a page, a search box, sortable columns and filters that live in the URL, so a filtered view can be bookmarked or shared.
- **Actions:** anything that rejects, pauses, deactivates or deletes asks for confirmation. Rejecting, pausing and deactivating also need a reason, which is emailed to the person affected.
- **Audit:** every change writes a row to `audit_log` with the admin, the action, the record and a before and after.
- **Language and devices:** the admin area is in English only for the MVP. It is built for desktop and works on a tablet.

### Page map

Fourteen pages are needed at launch and five follow. The roadmap's admin workstream (weeks 8 to 10) is short for 14 pages, so either give it more time or move the lower-priority launch pages, such as content pages and plans, to the weeks after launch.

| Page | Route | Island admin | Super admin | Built |
| --- | --- | --- | --- | --- |
| Dashboard | `/admin` | Own islands | All islands | Launch |
| Listings | `/admin/listings` | Own islands: review, edit, pause | All islands | Launch |
| Listing review | `/admin/listings/{id}` | Own islands | All islands | Launch |
| Licence checks | `/admin/licences` | Own islands | All islands | Launch |
| Deeplink monitor | `/admin/deeplinks` | Own islands | All islands | Launch |
| Owners and agents | `/admin/users` | Own islands, read-only | Create, edit, deactivate | Launch |
| Affiliates | `/admin/affiliates` | No access | Full access | Launch |
| Staff | `/admin/staff` | No access | Full access | Launch |
| Click reports | `/admin/clicks` | Own islands | All islands | Launch |
| Islands and resorts | `/admin/islands` | Edit text for own islands | Create, reorder, activate, edit | Launch |
| Content pages | `/admin/pages` | Pages tied to own islands | All, including site-wide pages | Launch |
| Appearance | /admin/appearance | No access | Colours and home page hero images | Launch |
| Plans and subscriptions | `/admin/plans` | No access | Full access | Launch |
| Audit log | `/admin/audit` | No access | Full access | Launch |
| Enquiries | `/admin/enquiries` | Own islands, read-only | All islands | Launch |
| Commissions | `/admin/commissions` | Own islands, read-only | All islands, import and edit | After launch |
| Ads | `/admin/ads` | Ads tied to own islands | All, including ad slots | After launch |
| Amenities | `/admin/amenities` | No access | Full access | After launch |
| Data requests | `/admin/privacy` | No access | Full access | After launch |

Amenities are loaded by a database seeder at launch, and data requests are handled by a super admin with a documented manual process until that page exists.

### Moderation pages

**Dashboard (`/admin`)**

- Cards for listings awaiting review, licences awaiting a check, broken deeplinks, and clicks in the last 7 days against the 7 before.
- Super admins also see pending commission by currency and the latest audit entries.
- Each card links to the matching list, already filtered.

**Listings (`/admin/listings`)**

- The default view is listings pending review, oldest first. Listings older than the review target (48 hours) are highlighted.
- Columns: image, title, island and resort, lister and their role, property type, submitted date, licence status (owner and agent listings only) and deeplink status.
- Filters: status, island, resort, lister role, property type, licence status, deeplink status, plus a search on title or lister email.
- Bulk actions: pause and archive. Approving and rejecting are done one listing at a time, because each needs a review.
- **Recommendations:** an island admin cannot reject a listing. They choose Recommend rejection and give a reason. It appears in a Recommended for rejection tab for super admins, who either reject it or send it back.

**Add an affiliate listing from a URL (`/admin/listings/import`)**

- Opened from the Add button on the Listings page. Island admins can add listings on their own islands, and super admins on any island. The admin chooses which affiliate the listing belongs to.
- **Fetch:** the admin pastes the property's page address and the server reads the page title, description, main image and any structured data it carries (schema.org and Open Graph tags). It fills what it finds and marks the rest as needing input, so a page that gives little still produces a usable blank draft.
- **Draft page:** the form shows the fields on the left and a live preview of the public listing page on the right. Title, island, area, type, sleeps, bedrooms, bathrooms, guide price, summary and image description are all editable.
- **Deeplink field:** a separate, clearly marked field for the affiliate link from the provider. It must be https, has a Test link button that records no click, and the Check availability button on the preview stays off until it is filled.
- **Image:** the fetched image is copied to the platform's own storage, resized, and used only after the admin ticks a box confirming permission to use it. Otherwise the admin uploads a different image. Hot-linking the provider's image is never done.
- **Safe fetching:** https only, a short timeout and a size cap, no requests to private or internal addresses, and a limited number of redirects. The page address is kept on the listing for admins and is never shown to visitors.
- **Save:** the admin can save a draft, send it for review, or approve and publish. Publishing needs a working deeplink, an image with a description, an area and the image permission box ticked. The listing then follows the normal rules.

**Listing review (`/admin/listings/{id}`)**

- The left side shows the listing as visitors will see it: image, title and summary, details, and the map pin at its offset location. The right side holds the review panel.
- The review checklist: image present with alt text, title and summary in English, details complete, island and resort correct, guide price sensible. Owner and agent listings also need a verified licence, and any listing with a deeplink needs a checked link.
- **Test link** opens the destination in a new tab through an admin-only link, so no visitor click is recorded. The panel shows the destination host, the link status and when it was last checked.
- **Approve** is blocked until the checklist is met. **Reject** needs a reason, which is emailed to the lister. Only a super admin can reject. An island admin uses Recommend rejection instead, with a reason. **Pause** and **Archive** also need a reason. **Edit** lets an admin correct details, and every edit is audited.
- A history tab lists every status change with who made it and when.

Listing status changes, all made through the single `ListingWorkflow` class:

| From | To | Who |
| --- | --- | --- |
| Draft | Pending review | Lister, on submit |
| Pending review | Live | Island admin for that island, or super admin |
| Pending review | Rejected | Super admin, with a reason. An island admin can only recommend it |
| Rejected | Draft | Lister, to edit and resubmit |
| Live | Paused | Lister, island admin or super admin |
| Paused | Live | Lister, island admin or super admin |
| Live or paused | Archived | Lister, island admin or super admin |
| Live | Pending review | Automatically, when a lister changes the deeplink URL or licence number (proposed) |

The last row stops a link or licence number being swapped after approval without a new check.

**Licence checks (`/admin/licences`)**

- A queue of owner and agent listings whose licence status is pending. Affiliate listings never appear here.
- Columns: listing, lister, island, registration number, national registry number, submitted date and a link to the uploaded document if there is one.
- The numbers sit beside the document so the admin can compare them with the official register. The register's address is a setting, not hard-coded. The check is a manual lookup: the admin finds the regional tourism registration number in the official register of the autonomous community where the property is (the Balearic Islands, for all four islands) and records the result. Nothing is checked automatically.
- **Verify** records who verified it and when. **Reject** needs a note, notifies the lister and keeps the listing from going live.

**Deeplink monitor (`/admin/deeplinks`)**

- Lists listings that have a deeplink, with status (unchecked, ok, broken), last checked time and clicks in the last 7 days, so the most-used broken links come first.
- The nightly checker marks a link broken when the destination returns an error. Whether to also flag a redirect to a different host is an open point.
- Actions: **Recheck now**, **Switch off** (marks the link broken, so the banner is hidden and `/go/{ref}` shows the listing page), **Pause listing**, and a message to the lister.

### People pages

**Staff (`/admin/staff`, super admin only)**

- Lists every admin with name, email, role, islands, last sign-in, two-factor status and active or deactivated.
- **Create** takes name, email, role (island admin or super admin) and, for island admins, one or more islands. The new admin gets an invitation email with a link to set a password, and must set up two-factor authentication at first sign-in.
- **Edit** changes role and islands, deactivates the account, resets two-factor, or signs the admin out everywhere. A change to islands applies on the admin's next request.
- The last active super admin cannot be deactivated or demoted, and nobody can change their own role. Every change is audited.

**Affiliates (`/admin/affiliates`, super admin only)**

- Lists affiliates with name, company, number of listings and live listings, clicks in the last 30 days, pending commission and active or deactivated.
- **Create** takes name, email, phone, an optional company link and the private notes field. The affiliate gets an invitation email to set a password and then manages their listings and click report from their account. Affiliates cannot sign themselves up.
- The private notes hold the programme, partner ids, commission terms and contact details. They are never shown publicly, and island admins cannot open this page or see the notes.
- The affiliate's page shows their listings, clicks, commission by programme and notes. Deactivating an affiliate pauses all of their listings.

**Owners and agents (`/admin/users`)**

- Lists owners and agents with name, email, role, agency, number of listings, plan and last sign-in.
- Super admins can edit details, change agency, change role and deactivate. Island admins see a read-only list of the owners and agents who have listings on their islands, and can open those listings.
- Island admins cannot suspend or deactivate an owner or agent. They can recommend it, with a reason, and a super admin decides.
- Deactivating an account pauses its listings. Deleting personal data goes through the Data requests page.

**Enquiries (`/admin/enquiries`, read-only)**

- Lists enquiries with date, listing, guest name, email, message and status (new, replied, closed, spam). It exists for handling complaints and spam, not for replying, because owners answer enquiries from their own inbox.
- Recent enquiries are flagged and listed first. An enquiry counts as recent for a set number of days (a setting, proposed at 7), so admins can see new contacts at a glance. Island admins see only enquiries for their own islands.
- Admins can mark an enquiry as spam. Guest details are personal data, so every time an admin opens an enquiry the view is written to the audit log (proposed).

### Reports and money pages

**Click reports (`/admin/clicks`)**

- Summary cards for clicks, unique clicks and the top listings, over a date range that defaults to the last 30 days and can be grouped by day, week or month.
- A table that can be grouped by listing, affiliate, island or source page. Filters: island, affiliate, listing, source page, language and device.
- Bots are excluded by default. An **Include bots** switch shows them separately, for investigating suspicious traffic.
- A click-level view lists raw events from the last 13 months: time, listing, source page, device, and the bot and unique flags. The IP hash is never shown.
- A conversion figure compares matched commission rows with unique clicks. **Export CSV** is available to island admins for their islands and to super admins for everything.

**Commissions (`/admin/commissions`)**

- Lists commission rows with programme reference, affiliate, listing, booking date, booking value, commission, currency, status, matched click id and report date. Totals are shown by status and month, per currency, with no conversion between currencies.
- **Import** (super admin): choose the affiliate, upload the programme's CSV, then map its columns to programme reference, booking date, booking value, commission, currency, status and the optional click id. A preview shows how many rows will match a click, how many will not and how many are duplicates (a programme reference is unique per affiliate). Confirming imports the new rows.
- Rows that match no click are kept without a listing, so a super admin can match them by hand. The column mapping is chosen again on each import.
- **Edit** (super admin): move a row from pending to approved to paid, or mark it rejected, and match it to a listing or click by hand. Every change is audited.
- Island admins see the rows for their own islands, read-only. Affiliates see only their own rows, in their account.

### Content and advertising pages

**Islands and resorts (`/admin/islands`)**

- A tree of islands with their resorts and towns underneath. Each node shows its name, slug, number of live listings and whether it is active.
- Island admins can edit the introduction text, per language, for their own islands and the resorts under them.
- Super admins can also create and rename islands and resorts, set the map centre and sort order, and switch an island on or off. An island with `is_active` off does not appear on the site.
- A slug cannot be changed once its island or resort has live listings, to protect existing URLs.

**Content pages (`/admin/pages`)**

- Lists pages with island, kind (static or guide), published or not, and the languages present.
- The editor takes a title, a Markdown body with a live preview, a meta title and meta description, one tab per language, and a publish switch. A slug is unique within an island, or among site-wide pages.
- Island admins edit guides tied to their own islands. Site-wide pages such as about, terms and privacy are for super admins only.

**Ads (`/admin/ads`, after launch)**

- Two lists: advertisers (name, contact, notes) and ads (image, slot, island targeting, dates, status, weight, impressions, clicks and click rate).
- Creating an ad takes the advertiser, slot (with its required image size shown), island or all islands, image with alt text, https target URL, start and end dates, weight and status. A preview shows the ad in its slot.
- Island admins see and create only ads targeted at their own islands, and cannot choose all islands. Super admins also manage the slots themselves: code, size and description.

**Amenities (`/admin/amenities`, super admin only, after launch)**

- Lists amenities with code, category, names per language, icon, whether it is a search filter and sort order.
- An amenity that is in use cannot be deleted. Until this page exists, amenities come from a database seeder.

### Platform pages (super admin only)

**Appearance (`/admin/appearance`)**

- Super admins only. The settings are site-wide, not tied to an island, and island admins cannot open the page.
- **Colours:** five colours (brand, button, page background, section band and text), each with a colour picker and a hex field. The public site reads them as CSS custom properties, so a change needs no code release. The original colours are stored, so a reset is one click.
- **Contrast check:** the page shows the contrast ratio beside each colour. Text and button colours need at least 4.5 to 1, and Publish stays disabled until they pass.
- **Logo and favicon:** the logo replaces the brand name at the top of every public page, with a description for alt text, and the brand name is shown as text when there is no logo. Proposed limits: SVG or PNG, up to 1 MB, shown about 40 pixels tall. The favicon is a square PNG or SVG of at least 512 pixels; the smaller sizes browsers and phones need are generated at upload. Both are stored in `site_settings` (keys `brand.logo` and `brand.favicon`) and follow the same draft and publish flow as colours.
- **Hero images:** up to five images for the top of the home page. Each needs a description, which becomes its alt text, and can be reordered, switched off or removed. A setting chooses between one image at random on each visit or the first image only. Proposed limits: JPG or WebP, at least 2400 pixels wide, up to 5 MB. Smaller copies for phones and tablets are made at upload.
- **Draft and publish:** edits are saved as a draft with a preview link, and go live only when a super admin publishes. Every publish is written to the audit log with who and when, and the previous values are kept so a change can be undone.

**Plans and subscriptions (`/admin/plans`)**

- Plans: edit code, name, audience (owner or agent), listing limit, ranking boost, featured flag, whether direct contact details are shown, monthly and yearly prices and whether the plan is active.
- Subscriptions: a list of accounts with plan, status, next billing date and cancellation, with a link to the payment provider's record.
- Overrides: grant a plan or a featured period to a single listing by hand, with a reason. The change goes through `ListingWorkflow` and is audited.
- Refunds are made through the payment provider and recorded here. Affiliates never appear on this page, because they are outside billing.

**Data requests (`/admin/privacy`, after launch)**

- Search by email address to see the data held: the user account, listings and enquiries sent by or about that address.
- Actions: **Export** a person's data as a file, and **Delete or anonymise** it. Deleting an account soft-deletes it, and the nightly job purges it after 30 days.
- Each request is logged with the date received and the date completed, with a reminder as the legal deadline approaches. Until this page exists, a super admin follows a documented manual process.

**Audit log (`/admin/audit`)**

- A read-only list of audit entries with time, admin, action, record and detail. Filters: admin, action, record type and date range. **Export CSV** is available.
- Entries can never be edited or deleted from the screen. How long they are kept is still to be decided.
- Island admins do not get this page. They see the history of a listing on its review page instead.

## Architecture and stack

The platform is a Laravel app in front of MySQL 8, with PayPal for payments, so a small team can build and run it on standard PHP hosting.

&#91;embedded content: architecture · browsers, Laravel app, MySQL, outside services\]

MySQL (highlighted) holds all listing, account and billing data. Only the Laravel app connects to it, and Laravel policies decide who can read or change each record.

**Stack choices**

| Layer | Choice | Why |
| --- | --- | --- |
| App | PHP 8.3 or later, Laravel 12 with server-rendered views, Tailwind CSS | Crawlable pages for SEO, with auth, validation, queues and scheduling built in |
| Hosting | Nginx and PHP-FPM on a managed PHP host with a UK data centre, with a CDN and DDoS protection included | Standard, inexpensive and easy to move |
| Database | MySQL 8.0.16 or later (InnoDB, utf8mb4), managed or self-hosted | Relational data, FULLTEXT search, JSON columns and check constraints |
| Auth | Laravel Fortify | Email and password, mandatory email verification, two-factor for admins |
| Files | S3-compatible object storage behind a CDN | Listing and ad images public, licence documents in a private bucket |
| Payments | PayPal Subscriptions, webhooks | No card data on our servers |
| Email | Transactional provider (the owner's existing UK email provider) | Enquiry delivery with reply-to set to the visitor |
| Maps | Mapbox GL or Google Maps | Listing maps and the search map view |
| Forms and validation | Laravel form requests, honeypot field, ALTCHA | Server-side validation and spam control |
| Translation | Laravel localisation | Language-prefixed routes and language files |
| Observability | GlitchTip, Plausible | Error tracking and cookieless analytics |
| Testing | PHPUnit or Pest, Laravel Dusk or Playwright | Logic, a test for every access policy, and end-to-end flows |

## Database schema

The schema targets MySQL 8.0.16 or later (InnoDB, utf8mb4) and is built with Laravel migrations. The SQL below is the equivalent DDL. It has 30 tables in seven groups, plus Laravel's standard framework tables (sessions, jobs, cache, password resets), which are not listed.

Laravel connects to MySQL through PDO rather than MySQLi. The schema is identical either way, and a MySQLi-based script or tool can use the same database.

| Group | Tables |
| --- | --- |
| Geography | islands, areas |
| Accounts | agencies, users, island\_admins |
| Billing | plans, subscriptions, subscription\_payments, paypal\_events |
| Listings | listings, listing\_private, listing\_translations, amenities, listing\_amenities, listing\_licences, admin\_recommendations |
| Enquiries and tracking | enquiries, deeplink\_clicks, deeplink\_clicks\_daily, affiliate\_commissions |
| Advertising | advertisers, ad\_slots, ads, ad\_stats\_daily, affiliate\_links |
| Content and operations | pages, page\_translations, audit\_log, site\_settings, hero\_images |

**Design decisions**

- Keys are `BIGINT UNSIGNED` auto-increment (small integers for lookup tables). Listing URLs use the listing id plus a slug.
- Short translatable text (names, intros) is JSON keyed by language. Long listing text lives in `listing_translations`.
- Money is stored as integer cents with a currency code, never floats.
- MySQL has no row-level security, so access rules live in Laravel policies. Owner-private data (exact location, address, contact details) sits in `listing_private` so no public query can touch it by accident.
- MySQL has no array columns, partial indexes or exclusion constraints. Amenities, views and accessibility features all use the `listing_amenities` junction table, indexes include `status`, and overlapping rates are rejected by application validation.
- Derived columns (price range, plan, rank score) are kept in step by Laravel model observers and a nightly reconcile command, not by database triggers.
- Listings and users are soft-deleted with `deleted_at` (Eloquent `SoftDeletes`).
- `CHECK` constraints are enforced from MySQL 8.0.16. Use that version or later on every environment.

### Database and conventions

```sql
CREATE DATABASE balearic_rentals
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE balearic_rentals;

-- Every table below uses ENGINE=InnoDB (the default) and inherits the database charset.
-- Timestamps use created_at / updated_at; Laravel sets them, the defaults cover raw inserts.
```

### Geography

```sql
CREATE TABLE islands (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(60) NOT NULL UNIQUE,
  name        JSON NOT NULL,                  -- {"en":"Menorca","es":"Menorca","de":"Menorca"}
  intro       JSON NULL,
  centre_lat  DECIMAL(9,6) NULL,
  centre_lng  DECIMAL(9,6) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 0,  -- only active islands show on the site
  sort_order  SMALLINT NOT NULL DEFAULT 0
);

CREATE TABLE areas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  island_id   SMALLINT UNSIGNED NOT NULL,
  slug        VARCHAR(80) NOT NULL,
  kind        ENUM('beach_resort','coastal_resort','town') NOT NULL,
  name        JSON NOT NULL,
  intro       JSON NULL,
  centre_lat  DECIMAL(9,6) NULL,
  centre_lng  DECIMAL(9,6) NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_areas_slug (island_id, slug),
  CONSTRAINT fk_areas_island FOREIGN KEY (island_id) REFERENCES islands (id)
);
```

### Accounts

```sql
CREATE TABLE agencies (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(160) NOT NULL,
  slug         VARCHAR(160) NOT NULL UNIQUE,
  email        VARCHAR(190) NOT NULL,
  phone        VARCHAR(40) NULL,
  website_url  VARCHAR(255) NULL,
  logo_path    VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Laravel's users table, extended with role, agency and consent fields
CREATE TABLE users (
  id                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role                       ENUM('owner','agent','affiliate','island_admin','super_admin') NOT NULL DEFAULT 'owner',
  name                       VARCHAR(160) NOT NULL,
  email                      VARCHAR(190) NOT NULL UNIQUE,
  email_verified_at          TIMESTAMP NULL,
  password                   VARCHAR(255) NOT NULL,
  two_factor_secret          TEXT NULL,            -- required for island and super admins (Laravel Fortify)
  two_factor_recovery_codes  TEXT NULL,
  two_factor_confirmed_at    TIMESTAMP NULL,
  remember_token             VARCHAR(100) NULL,
  phone                      VARCHAR(40) NULL,
  agency_id                  BIGINT UNSIGNED NULL,
  affiliate_data             TEXT NULL,            -- super admin's private notes on an affiliate; never public
  preferred_lang             ENUM('en','es','de') NOT NULL DEFAULT 'en',
  marketing_consent          TINYINT(1) NOT NULL DEFAULT 0,
  consented_at               TIMESTAMP NULL,
  last_login_at              TIMESTAMP NULL,       -- shown on the Staff and Owners and agents lists
  deactivated_at             TIMESTAMP NULL,       -- deactivated accounts cannot sign in; not purged like deleted_at
  created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at                 TIMESTAMP NULL,
  KEY idx_users_agency (agency_id),
  CONSTRAINT fk_users_agency FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE SET NULL
);
```

### Island admin assignments

Island admins and super admins are both rows in `users`, told apart by `role`. The `island_admins` table records which islands each island admin looks after. Super admins need no rows, because they see every island. The app enforces that an island admin has at least one row.

```sql
CREATE TABLE island_admins (
  user_id      BIGINT UNSIGNED NOT NULL,
  island_id    SMALLINT UNSIGNED NOT NULL,
  assigned_by  BIGINT UNSIGNED NOT NULL,          -- the super admin who made the assignment
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, island_id),
  KEY idx_island_admins_island (island_id),
  CONSTRAINT fk_ia_user   FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ia_island FOREIGN KEY (island_id)   REFERENCES islands (id),
  CONSTRAINT fk_ia_by     FOREIGN KEY (assigned_by) REFERENCES users (id)
);
```

### Billing (PayPal)

```sql
CREATE TABLE plans (
  id                    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code                  VARCHAR(40) NOT NULL UNIQUE,     -- 'free', 'standard', 'featured', 'agent_plus'
  name                  VARCHAR(80) NOT NULL,
  audience              ENUM('owner','agent') NOT NULL,
  max_listings          INT UNSIGNED NULL,               -- NULL = unlimited
  is_featured           TINYINT(1) NOT NULL DEFAULT 0,   -- shows in the featured strips
  search_boost          SMALLINT NOT NULL DEFAULT 0,     -- added to the ranking score
  show_direct_contact   TINYINT(1) NOT NULL DEFAULT 0,   -- phone and website on the listing page
  currency              CHAR(3) NOT NULL DEFAULT 'EUR',
  price_month_cents     INT UNSIGNED NULL,
  price_year_cents      INT UNSIGNED NULL,
  paypal_plan_id_month  VARCHAR(40) NULL,                -- PayPal billing plan ids
  paypal_plan_id_year   VARCHAR(40) NULL,
  is_active             TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE subscriptions (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id                 BIGINT UNSIGNED NULL,
  agency_id               BIGINT UNSIGNED NULL,
  plan_id                 SMALLINT UNSIGNED NOT NULL,
  billing_interval        ENUM('month','year') NOT NULL,
  status                  ENUM('approval_pending','approved','active','suspended','cancelled','expired') NOT NULL,
  paypal_subscription_id  VARCHAR(40) NULL UNIQUE,       -- I-... id from PayPal
  paypal_payer_id         VARCHAR(40) NULL,
  next_billing_at         TIMESTAMP NULL,
  cancelled_at            TIMESTAMP NULL,
  ends_at                 TIMESTAMP NULL,                -- access runs until this moment after a cancellation
  created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sub_user (user_id, status),
  KEY idx_sub_agency (agency_id, status),
  CONSTRAINT fk_sub_user   FOREIGN KEY (user_id)   REFERENCES users (id),
  CONSTRAINT fk_sub_agency FOREIGN KEY (agency_id) REFERENCES agencies (id),
  CONSTRAINT fk_sub_plan   FOREIGN KEY (plan_id)   REFERENCES plans (id),
  CONSTRAINT chk_sub_payer CHECK ((user_id IS NULL) <> (agency_id IS NULL))  -- billed to one owner or one agency; affiliates never have a subscription
);

CREATE TABLE subscription_payments (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subscription_id        BIGINT UNSIGNED NOT NULL,
  paypal_transaction_id  VARCHAR(40) NOT NULL UNIQUE,
  amount_cents           INT UNSIGNED NOT NULL,
  currency               CHAR(3) NOT NULL,
  status                 ENUM('completed','pending','refunded','failed') NOT NULL,
  paid_at                TIMESTAMP NULL,
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pay_sub (subscription_id, paid_at),
  CONSTRAINT fk_pay_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions (id)
);

-- Idempotency log for PayPal webhooks
CREATE TABLE paypal_events (
  id           VARCHAR(60) NOT NULL PRIMARY KEY,         -- PayPal webhook event id
  event_type   VARCHAR(80) NOT NULL,
  received_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### Listings

```sql
CREATE TABLE listings (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id              BIGINT UNSIGNED NOT NULL,      -- the owner, agent or affiliate who holds the listing
  agency_id             BIGINT UNSIGNED NULL,
  island_id             SMALLINT UNSIGNED NOT NULL,
  area_id               INT UNSIGNED NOT NULL,
  slug                  VARCHAR(120) NOT NULL,
  public_ref            VARCHAR(12) NOT NULL UNIQUE,   -- short random code used in listing URLs and /go/{ref}
  property_type         ENUM('villa','apartment','townhouse','finca','bungalow','studio') NOT NULL,
  status                ENUM('draft','pending_review','live','paused','rejected','archived') NOT NULL DEFAULT 'draft',
  rejection_reason      VARCHAR(500) NULL,
  bedrooms              TINYINT UNSIGNED NOT NULL,
  bathrooms             TINYINT UNSIGNED NOT NULL,
  sleeps                TINYINT UNSIGNED NOT NULL,
  has_private_pool      TINYINT(1) NOT NULL DEFAULT 0,
  pets_allowed          TINYINT(1) NULL DEFAULT NULL,
  distance_to_beach_m   INT UNSIGNED NULL,
  image_path            VARCHAR(255) NULL,             -- the single listing image, in file storage
  image_alt             VARCHAR(160) NULL,
  source_url            VARCHAR(2048) NULL,            -- property page an affiliate listing was imported from; admins only
  deeplink_url          VARCHAR(2048) NULL,            -- shown as the Check availability banner; https only
  deeplink_status       ENUM('unchecked','ok','broken') NOT NULL DEFAULT 'unchecked',
  deeplink_checked_at   TIMESTAMP NULL,
  deeplink_subid_param  VARCHAR(30) NULL,              -- query parameter that carries our click id, if the programme supports one
  display_lat           DECIMAL(9,6) NOT NULL,         -- offset about 300 m; the only public coordinates
  display_lng           DECIMAL(9,6) NOT NULL,
  currency              CHAR(3) NOT NULL DEFAULT 'EUR',
  min_price_week_cents  INT UNSIGNED NULL,             -- optional guide price per week, entered by the lister
  plan_code             VARCHAR(40) NOT NULL DEFAULT 'free',   -- copied from the active subscription; stays 'free' for affiliate listings, which have no plan
  featured_until        TIMESTAMP NULL,
  rank_score            INT NOT NULL DEFAULT 0,        -- plan boost + completeness + freshness
  published_at          TIMESTAMP NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            TIMESTAMP NULL,
  UNIQUE KEY uq_listings_slug (island_id, slug),
  KEY idx_live_area  (status, island_id, area_id, rank_score),
  KEY idx_live_price (status, island_id, min_price_week_cents),
  KEY idx_live_size  (status, sleeps, bedrooms),
  KEY idx_geo        (display_lat, display_lng),
  KEY idx_owner      (owner_id),
  KEY idx_agency     (agency_id),
  KEY idx_deeplink   (deeplink_status, deeplink_checked_at),
  CONSTRAINT chk_sleeps   CHECK (sleeps BETWEEN 1 AND 40),
  CONSTRAINT chk_currency CHECK (currency IN ('EUR','GBP')),
  CONSTRAINT chk_deeplink_https CHECK (deeplink_url IS NULL OR deeplink_url LIKE 'https://%'),
  CONSTRAINT fk_listings_owner  FOREIGN KEY (owner_id)  REFERENCES users (id),
  CONSTRAINT fk_listings_agency FOREIGN KEY (agency_id) REFERENCES agencies (id),
  CONSTRAINT fk_listings_island FOREIGN KEY (island_id) REFERENCES islands (id),
  CONSTRAINT fk_listings_area   FOREIGN KEY (area_id)   REFERENCES areas (id)
);

-- Exact location, address and contact details. Never exposed to the public.
CREATE TABLE listing_private (
  listing_id     BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  lat            DECIMAL(9,6) NOT NULL,
  lng            DECIMAL(9,6) NOT NULL,
  address_line   VARCHAR(255) NULL,
  postcode       VARCHAR(20) NULL,
  contact_email  VARCHAR(190) NOT NULL,       -- enquiries are routed here
  contact_phone  VARCHAR(40) NULL,
  website_url    VARCHAR(255) NULL,
  CONSTRAINT fk_private_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
);

-- Only the critical text: a title and a short summary
CREATE TABLE listing_translations (
  listing_id             BIGINT UNSIGNED NOT NULL,
  lang                   ENUM('en','es','de') NOT NULL,
  title                  VARCHAR(160) NOT NULL,
  summary                VARCHAR(500) NULL,
  is_machine_translated  TINYINT(1) NOT NULL DEFAULT 0,   -- 1 until the lister reviews it
  PRIMARY KEY (listing_id, lang),
  FULLTEXT KEY ft_listing_text (title, summary),
  CONSTRAINT fk_trans_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
);
```

### Amenities and licences

```sql
-- Amenities also hold views and accessibility features (category 'view' and 'accessibility'),
-- so every multi-value filter uses one table
CREATE TABLE amenities (
  id             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(50) NOT NULL UNIQUE,   -- 'wifi', 'air_con', 'heated_pool', 'sea_view', 'wheelchair_friendly'
  category       ENUM('essentials','outdoor','family','services','view','accessibility') NOT NULL,
  name           JSON NOT NULL,
  icon           VARCHAR(60) NULL,
  is_filterable  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order     SMALLINT NOT NULL DEFAULT 0
);

CREATE TABLE listing_amenities (
  listing_id  BIGINT UNSIGNED NOT NULL,
  amenity_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (listing_id, amenity_id),
  KEY idx_amenity_listing (amenity_id, listing_id),
  CONSTRAINT fk_la_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
  CONSTRAINT fk_la_amenity FOREIGN KEY (amenity_id) REFERENCES amenities (id)
);

CREATE TABLE listing_licences (
  listing_id            BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  registration_number   VARCHAR(60) NULL,        -- Balearic tourist-stay licence number
  national_registry_no  VARCHAR(60) NULL,        -- Spanish national registry number, where required
  status                ENUM('not_provided','pending','verified','rejected') NOT NULL DEFAULT 'not_provided',
  document_path         VARCHAR(255) NULL,       -- optional upload, private bucket
  verified_by           BIGINT UNSIGNED NULL,
  verified_at           TIMESTAMP NULL,
  notes                 VARCHAR(500) NULL,
  CONSTRAINT fk_lic_listing  FOREIGN KEY (listing_id)  REFERENCES listings (id) ON DELETE CASCADE,
  CONSTRAINT fk_lic_verifier FOREIGN KEY (verified_by) REFERENCES users (id)
);
```

An island admin cannot reject a listing or suspend a user, so a recommendation is saved here for a super admin to accept or decline.

```sql
CREATE TABLE admin_recommendations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  action          ENUM('reject_listing','suspend_user') NOT NULL,
  listing_id      BIGINT UNSIGNED NULL,           -- set for reject_listing
  user_id         BIGINT UNSIGNED NULL,           -- set for suspend_user
  island_id       SMALLINT UNSIGNED NOT NULL,     -- so the super admin queue can filter by island
  reason          VARCHAR(500) NOT NULL,
  recommended_by  BIGINT UNSIGNED NOT NULL,       -- the island admin
  status          ENUM('open','accepted','declined') NOT NULL DEFAULT 'open',
  decided_by      BIGINT UNSIGNED NULL,           -- the super admin
  decision_note   VARCHAR(500) NULL,
  decided_at      TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rec_open (status, island_id, created_at),
  CONSTRAINT chk_rec_target CHECK ((listing_id IS NULL) <> (user_id IS NULL)),
  CONSTRAINT fk_rec_listing FOREIGN KEY (listing_id)     REFERENCES listings (id),
  CONSTRAINT fk_rec_user    FOREIGN KEY (user_id)        REFERENCES users (id),
  CONSTRAINT fk_rec_island  FOREIGN KEY (island_id)      REFERENCES islands (id),
  CONSTRAINT fk_rec_by      FOREIGN KEY (recommended_by) REFERENCES users (id),
  CONSTRAINT fk_rec_decider FOREIGN KEY (decided_by)     REFERENCES users (id)
);
```

### Enquiries

```sql
CREATE TABLE enquiries (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id        BIGINT UNSIGNED NOT NULL,
  owner_id          BIGINT UNSIGNED NOT NULL,    -- recipient at the time of sending
  guest_name        VARCHAR(120) NOT NULL,
  guest_email       VARCHAR(190) NOT NULL,
  guest_phone       VARCHAR(40) NULL,
  check_in          DATE NULL,
  check_out         DATE NULL,
  adults            TINYINT UNSIGNED NULL,
  children          TINYINT UNSIGNED NULL,
  message           TEXT NOT NULL,
  lang              ENUM('en','es','de') NOT NULL DEFAULT 'en',
  status            ENUM('new','replied','closed','spam') NOT NULL DEFAULT 'new',
  consent_given_at  TIMESTAMP NOT NULL,          -- guest ticked the privacy notice
  ip_hash           CHAR(64) NULL,               -- salted hash for rate limits, never the raw IP
  email_sent_at     TIMESTAMP NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_enq_inbox   (owner_id, status, created_at),
  KEY idx_enq_listing (listing_id, created_at),
  CONSTRAINT chk_enq_dates  CHECK (check_in IS NULL OR check_out IS NULL OR check_out > check_in),
  CONSTRAINT chk_enq_adults CHECK (adults IS NULL OR adults >= 1),
  CONSTRAINT chk_enq_msg    CHECK (CHAR_LENGTH(message) <= 4000),
  CONSTRAINT fk_enq_listing FOREIGN KEY (listing_id) REFERENCES listings (id),
  CONSTRAINT fk_enq_owner   FOREIGN KEY (owner_id)   REFERENCES users (id)
);
```

### Click tracking and commissions

Visitors never see the raw `deeplink_url`. The banner points to `/go/{ref}`, which records the click in `deeplink_clicks`, adds the click id to the link where the listing's `deeplink_subid_param` is set, then redirects. An admin can switch a link off at once. `deeplink_clicks_daily` holds daily totals, rebuilt nightly from the events, and `affiliate_commissions` holds the commission that the programmes report.

```sql
-- One row per click on a Check availability banner. Raw events are kept for 13 months (proposed).
CREATE TABLE deeplink_clicks (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  click_id      CHAR(26) NOT NULL UNIQUE,         -- random id (ULID) added to the outgoing link
  listing_id    BIGINT UNSIGNED NOT NULL,
  affiliate_id  BIGINT UNSIGNED NOT NULL,         -- the user who holds the listing
  island_id     SMALLINT UNSIGNED NOT NULL,       -- copied from the listing, so island reports need no join
  clicked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lang          ENUM('en','es','de') NOT NULL,
  source_page   ENUM('home','island','area','search','listing','other') NOT NULL,
  device        ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  ip_hash       CHAR(64) NOT NULL,                -- salted daily hash, never the raw IP
  is_bot        TINYINT(1) NOT NULL DEFAULT 0,
  is_unique     TINYINT(1) NOT NULL DEFAULT 1,    -- 0 for a repeat from the same visitor within 30 minutes
  target_url    VARCHAR(2048) NOT NULL,           -- where the visitor was sent, as it was at the time
  KEY idx_clicks_listing   (listing_id, clicked_at),
  KEY idx_clicks_affiliate (affiliate_id, clicked_at),
  KEY idx_clicks_island    (island_id, clicked_at),
  KEY idx_clicks_dedupe    (ip_hash, listing_id, clicked_at),
  CONSTRAINT fk_clicks_listing   FOREIGN KEY (listing_id)   REFERENCES listings (id),
  CONSTRAINT fk_clicks_affiliate FOREIGN KEY (affiliate_id) REFERENCES users (id)
);

-- Daily totals, rebuilt nightly from deeplink_clicks and kept after the raw events are deleted
CREATE TABLE deeplink_clicks_daily (
  listing_id     BIGINT UNSIGNED NOT NULL,
  day            DATE NOT NULL,
  clicks         INT UNSIGNED NOT NULL DEFAULT 0,   -- human clicks
  unique_clicks  INT UNSIGNED NOT NULL DEFAULT 0,
  bot_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (listing_id, day),
  CONSTRAINT fk_dc_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
);

-- Commission reported by the affiliate programmes, imported by a super admin
CREATE TABLE affiliate_commissions (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id           BIGINT UNSIGNED NULL,
  affiliate_id         BIGINT UNSIGNED NOT NULL,
  click_id             CHAR(26) NULL,               -- set when the programme returns our click id (no foreign key: old clicks are deleted)
  programme_ref        VARCHAR(80) NOT NULL,        -- the programme's own booking or transaction reference
  booking_value_cents  INT UNSIGNED NULL,
  commission_cents     INT UNSIGNED NOT NULL,
  currency             CHAR(3) NOT NULL DEFAULT 'EUR',
  status               ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  booked_at            DATE NULL,
  reported_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  imported_by          BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_commission_ref (affiliate_id, programme_ref),
  KEY idx_comm_listing (listing_id, status),
  KEY idx_comm_click   (click_id),
  CONSTRAINT fk_comm_listing   FOREIGN KEY (listing_id)   REFERENCES listings (id),
  CONSTRAINT fk_comm_affiliate FOREIGN KEY (affiliate_id) REFERENCES users (id),
  CONSTRAINT fk_comm_importer  FOREIGN KEY (imported_by)  REFERENCES users (id)
);
```

### Advertising and affiliate links

```sql
CREATE TABLE advertisers (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(160) NOT NULL,
  contact_email  VARCHAR(190) NULL,
  notes          TEXT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ad_slots (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(50) NOT NULL UNIQUE,     -- 'home_top', 'island_sidebar', 'listing_footer'
  width        INT UNSIGNED NOT NULL,
  height       INT UNSIGNED NOT NULL,
  description  VARCHAR(255) NULL
);

CREATE TABLE ads (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  advertiser_id  BIGINT UNSIGNED NOT NULL,
  slot_id        SMALLINT UNSIGNED NOT NULL,
  island_id      SMALLINT UNSIGNED NULL,        -- NULL = shown on every island
  image_path     VARCHAR(255) NOT NULL,
  alt            VARCHAR(190) NOT NULL,
  target_url     VARCHAR(500) NOT NULL,
  status         ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
  weight         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  starts_at      TIMESTAMP NOT NULL,
  ends_at        TIMESTAMP NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ads_active (status, slot_id, island_id, starts_at, ends_at),
  CONSTRAINT chk_ad_dates  CHECK (ends_at > starts_at),
  CONSTRAINT chk_ad_weight CHECK (weight > 0),
  CONSTRAINT fk_ads_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id),
  CONSTRAINT fk_ads_slot       FOREIGN KEY (slot_id)       REFERENCES ad_slots (id),
  CONSTRAINT fk_ads_island     FOREIGN KEY (island_id)     REFERENCES islands (id)
);

CREATE TABLE ad_stats_daily (
  ad_id        BIGINT UNSIGNED NOT NULL,
  stat_date    DATE NOT NULL,
  impressions  INT UNSIGNED NOT NULL DEFAULT 0,
  clicks       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ad_id, stat_date),
  CONSTRAINT fk_stats_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE
);

CREATE TABLE affiliate_links (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  partner       VARCHAR(80) NOT NULL,
  category      ENUM('flights','car_hire','hotels','transfers','insurance') NOT NULL,
  island_id     SMALLINT UNSIGNED NULL,
  url_template  VARCHAR(500) NOT NULL,          -- may use {island} and {lang}
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_aff_island FOREIGN KEY (island_id) REFERENCES islands (id)
);
```

### Content and operations

```sql
CREATE TABLE pages (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(120) NOT NULL,
  island_id     SMALLINT UNSIGNED NULL,
  kind          ENUM('static','guide') NOT NULL,
  is_published  TINYINT(1) NOT NULL DEFAULT 0,
  -- Virtual column so slugs are unique per island, and unique among site-wide pages (NULL island)
  island_key    SMALLINT UNSIGNED AS (IFNULL(island_id, 0)) VIRTUAL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pages_slug (island_key, slug),
  CONSTRAINT fk_pages_island FOREIGN KEY (island_id) REFERENCES islands (id)
);

CREATE TABLE page_translations (
  page_id           BIGINT UNSIGNED NOT NULL,
  lang              ENUM('en','es','de') NOT NULL,
  title             VARCHAR(190) NOT NULL,
  body_md           MEDIUMTEXT NOT NULL,
  meta_title        VARCHAR(190) NULL,
  meta_description  VARCHAR(300) NULL,
  PRIMARY KEY (page_id, lang),
  CONSTRAINT fk_pt_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id    BIGINT UNSIGNED NULL,             -- NULL for system actions
  action      VARCHAR(60) NOT NULL,             -- 'listing.approve', 'licence.verify', 'plan.override'
  entity      VARCHAR(60) NOT NULL,
  entity_id   VARCHAR(40) NULL,
  detail      JSON NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity, entity_id, created_at)
);
```

```sql
-- Site-wide settings edited on the Appearance page
CREATE TABLE site_settings (
  setting_key  VARCHAR(60) NOT NULL PRIMARY KEY,   -- 'theme.colours', 'home.hero_mode'
  value        JSON NOT NULL,                      -- what the public site uses
  draft_value  JSON NULL,                          -- saved but not yet published
  updated_by   BIGINT UNSIGNED NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id)
);

-- Home page hero images; at most five active, enforced in the app
CREATE TABLE hero_images (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  image_path   VARCHAR(255) NOT NULL,              -- in file storage; resized copies made at upload
  alt_text     VARCHAR(160) NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 0,      -- new uploads start off until published
  uploaded_by  BIGINT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hero_order (is_active, sort_order),
  CONSTRAINT fk_hero_user FOREIGN KEY (uploaded_by) REFERENCES users (id)
);
```

### Indexes

MySQL has no partial indexes, so every public-search index starts with `status`. The listing indexes are in the `CREATE TABLE` above. Each serves one query shape.

| Index | Serves |
| --- | --- |
| `listings (status, island_id, area_id, rank_score)` | Island and resort pages, default ranking |
| `listings (status, island_id, min_price_week_cents)` | Price filter and price sort |
| `listings (status, sleeps, bedrooms)` | Sleeps and bedroom filters |
| `listings (display_lat, display_lng)` | Map viewport bounding box |
| `listing_translations FULLTEXT (title, summary)` | Free-text search |
| `listing_amenities (amenity_id, listing_id)` | Amenity, view and accessibility filters |
| `enquiries (owner_id, status, created_at)` | Owner inbox |
| `ads (status, slot_id, island_id, starts_at, ends_at)` | Choosing the ad for a slot |

MySQL full-text search has no Spanish or German stemming and ignores words under 3 characters by default (`innodb_ft_min_token_size`). That is acceptable for the MVP. If search quality becomes a problem, add Meilisearch through Laravel Scout.

### Authorisation in Laravel

Without row-level security, every access rule is enforced in the application, so each rule below needs a test.

| Rule | Enforced by |
| --- | --- |
| The public sees only live, non-deleted listings | A `Listing::live()` scope used by every public query. `listing_private` is never loaded on public routes, with one exception: for owner and agent listings on a plan with `show_direct_contact`, the listing page reads only `contact_phone` and `website_url` (never the address, exact location or contact email). |
| Owners and agency members edit only their own listings | `ListingPolicy` (owner, same agency, island admin for that listing's island, or super admin) on every controller action |
| Owners cannot publish themselves or change plan, ranking or rejection fields | Those columns are guarded against mass assignment. A single `ListingWorkflow` class changes `status`, and only an island admin for the listing's island, or a super admin, can approve. |
| Only island admins (for their islands) and super admins verify licences | `LicencePolicy`. Owners submit numbers through an action that sets the status to `pending`. |
| Anyone can send an enquiry, only the recipient can read it | A public POST route with a honeypot, ALTCHA and throttle middleware. `EnquiryPolicy` limits recipients to changing `status`. |
| Users cannot change their own role | Role changes and island assignments are super-admin only, and profile forms validate an allow-list of fields. |
| Admin area | `role in (island_admin, super_admin)` middleware plus mandatory two-factor authentication, with every query limited to the admin's islands |
| Database accounts | The app account has data access only. Migrations run under a separate account with DDL rights, and reporting uses a read-only account. |

```php
class ListingPolicy
{
    public function update(User $user, Listing $listing): bool
    {
        return $user->role === 'super_admin'
            || ($user->role === 'island_admin' && $user->managesIsland($listing->island_id))
            || $listing->owner_id === $user->id
            || ($listing->agency_id !== null && $listing->agency_id === $user->agency_id);
    }
}

class Listing extends Model
{
    use SoftDeletes;

    // Owners can never mass-assign these
    protected $guarded = ['status', 'plan_code', 'featured_until', 'rank_score',
                          'rejection_reason', 'published_at'];

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');   // SoftDeletes already hides deleted rows
    }
}
```

Admin screens never rely on hidden links. Every admin query passes through an `AdminScope` that adds `island_id IN (...)` for island admins, and `User::managesIsland()` reads the `island_admins` rows once per request. Tests must cover an island admin opening another island's listing, enquiry, ad or page by URL, which must be refused.

## Search and filtering

Search runs in MySQL, with no separate search engine for the MVP. The indexes above keep queries fast up to roughly 20,000 live listings. Past that, add Meilisearch or Typesense through Laravel Scout, behind the same search class.

**Filters**

| Filter | Column or source | Notes |
| --- | --- | --- |
| Island, resort or town | `island_id`, `area_id` | Chosen from the URL path or a picker |
| Property type | `property_type` | Multi-select |
| Sleeps, bedrooms | `sleeps`, `bedrooms` | Minimum values (4+, 6+) |
| Price per week | `min_price_week_cents` | Range slider, converted to the visitor's currency |
| Amenities, views, accessibility | `listing_amenities` | One `EXISTS` check per selected item, so all selected must match |
| Pets, private pool | `pets_allowed`, `has_private_pool` | Toggles |
| Free text | `FULLTEXT` on `listing_translations` | Matched in the visitor's language, falling back to English, with a title prefix match for partial names |
| Map area | `display_lat`, `display_lng` | Bounding box from the map viewport |

**Ranking**

1. Featured listings whose `featured_until` is in the future come first, capped at 3 per results page.
2. The rest are ordered by `rank_score`, then by `published_at` descending.
3. `rank_score` is recalculated nightly: plan boost, plus listing completeness (image, guide price, deeplink, translations), plus a freshness bonus for listings updated in the last 90 days.

Visitors can also sort by price (low to high, high to low), newest, or sleeps.

**Implementation notes**

- Search is one `ListingSearch` class in Laravel, called by the search controller. It returns a page of results, a total count and facet counts per filter.
- Free text uses `MATCH (title, summary) AGAINST (? IN BOOLEAN MODE)`. MySQL has no typo tolerance, so misspelled names will miss until Scout is added.
- Results are cached through Laravel's cache (file or database store, Redis if the host offers it) for 60 seconds per unique query string.
- The map view returns up to 200 pins per viewport as lightweight JSON (id, slug, title, price, thumbnail, display coordinates).
- Availability is not filterable. Visitors check it on the affiliate's booking page through the Check availability banner. The date fields on the enquiry form are informational.

## API and routes

Public pages are server-rendered Laravel routes so every island, resort and listing has a crawlable URL. Mutations go through controllers that check Laravel policies, and the browser never connects to the database.

**Public URLs** (the language prefix `/{lang}` is `en`, `es` or `de`)

| Route | Page |
| --- | --- |
| `/{lang}` | Home |
| `/{lang}/{island}` | Island landing page, for example `/en/menorca` |
| `/{lang}/{island}/{area}` | Resort or town page with filtered listings |
| `/{lang}/{island}/{area}/{slug}-{ref}` | Listing page, for example `/en/menorca/cala-en-porter/villa-sunset-456` |
| `/{lang}/search` | Search results, with filters in the query string |
| `/{lang}/compare` | Compare list |
| `/{lang}/p/{slug}` | Static and guide pages |
| `/{lang}/owners` | Owner information and tier pricing |

The `{ref}` suffix is `public_ref`, so a listing URL still resolves if the slug changes. Old slugs return a 301 to the current one.

**Owner and admin routes** (no language prefix, `noindex`)

| Route | Purpose |
| --- | --- |
| `/account/login`, `/account/register` | Auth, email verification |
| `/account/listings`, `/account/listings/{id}/edit` | Listing manager and step form |
| `/account/enquiries` | Inbox |
| `/account/billing` | Plan and PayPal subscription management |
| `/admin/*` | Moderation queue, licences, ads and content for the admin's islands. Super admin only: staff and island assignments, users, audit log |
| /admin/staff | Super admin only: create admin accounts, set roles and assign islands |
| /admin/affiliates | Super admin only: create and edit affiliate accounts and their private notes |
| /account/clicks | Click and commission report for the lister's own listings |
| /admin/clicks | Click and commission reports for the admin's islands, with CSV export |

**Backend endpoints**

| Endpoint | Method | Auth | Purpose |
| --- | --- | --- | --- |
| `/api/search` | GET | Public | Calls `ListingSearch`, returns rows and facet counts |
| `/api/map` | GET | Public | Pins for a map bounding box |
| `/api/enquiries` | POST | Public, honeypot and ALTCHA | Validates, stores, emails the owner. Rate limit: 5 per IP hash per hour |
| `/api/listings/{id}/submit` | POST | Owner | Moves a draft to `pending_review` after completeness checks |
| `/api/listings/{id}/image` | POST | Owner | Signed upload URL for the single image, then a resize job |
| `/api/licences` | POST | Owner | Saves licence numbers, sets status to `pending` |
| `/api/billing/checkout` | POST | Owner | Creates a PayPal subscription for a plan |
| `/api/billing/portal` | POST | Owner | Opens the PayPal automatic payments page |
| `/api/webhooks/paypal` | POST | PayPal signature | Updates subscriptions and listing tiers, idempotent via `paypal_events` |
| `/api/ads/{id}/click` | GET | Public | Records a click, redirects to the target URL |
| `/api/admin/listings/{id}/approve` | POST | Island admin (own islands) or super admin | Approves, requires a verified licence for owner and agent listings, writes the audit log |
| `/api/admin/listings/{id}/reject` | POST | Super admin | Rejects with a reason and emails the owner |
| `/sitemap.xml`, `/robots.txt` | GET | Public | Generated per language with `hreflang` alternates |
| /api/admin/staff | POST | Super admin | Creates an admin account, sets its role and assigns islands |
| /go/{ref} | GET | Public | Records the click in deeplink\_clicks, adds the click id, then redirects to the listing's deeplink URL. Rate limited. Returns the listing page if the link is switched off |
| /api/admin/commissions/import | POST | Super admin | Imports a programme's commission CSV and matches each row to a click |

## Click tracking

The platform records every Check availability click before sending the visitor to the affiliate's site, so it can report clicks and check the commission the programmes pay it.

&#91;embedded content: click tracking and commission matching · 9 steps, 1 decision\]

A click counts only when the listing is live and its link is switched on. Where the programme supports it, the platform adds a click id to the outgoing link, so a commission report can be matched back to the click.

**What is recorded for each click**

| Field | Purpose |
| --- | --- |
| Click id (random, unique) | Added to the outgoing link and used to match commission |
| Listing, affiliate and island | Reports by affiliate, listing and island |
| Time, visitor language, page the click came from | Where and when clicks happen |
| Device type | Mobile and desktop split |
| Salted IP hash | De-duplication and bot checks. The raw IP is never stored |
| Bot and unique flags | Keep the reports honest |
| Target URL at the time | Proof of where the visitor was sent, even if the link is later edited |

**Counting rules**

- A click is unique if the same visitor (by IP hash) has not clicked the same listing in the last 30 minutes. This window is a proposal.
- Known bots, link prefetchers and clicks from signed-in admins are flagged and left out of reports.
- `/go/{ref}` is rate limited per IP hash. Repeated clicks above the limit are flagged as bots.
- Raw click events are kept for 13 months (proposed). A nightly job rolls them into `deeplink_clicks_daily`, which is kept, and then deletes the old events.
- The redirect sets no cookies and loads no tracking scripts. The privacy notice must still describe click logging and its retention.

**Who sees the reports**

| Who | Sees |
| --- | --- |
| Affiliate or owner | Clicks, unique clicks and commission status for their own listings, by day and listing, in `/account/clicks` |
| Island admin | The same for every listing on their islands, in `/admin/clicks`, read-only |
| Super admin | Everything, with CSV export. Imports commission reports and can change commission status |

**Commission matching**

- The platform earns commission through the affiliates' own programmes. It does not pay affiliates, and affiliates are not billed.
- A super admin imports each programme's commission report as CSV into `affiliate_commissions`. Each row is matched to a click by click id, or to the listing and booking date where the programme does not return the click id.
- Each commission row has a status of pending, approved, paid or rejected, so the platform can see what it is owed and what has been paid.
- A report shows clicks, bookings, conversion rate and commission per affiliate, listing and island.

## Tiers, payments and advertising

A free tier gets owners onto the site, and paid tiers sell visibility and direct contact. The prices below are placeholders to test, not researched market rates.

| Plan | Audience | Listings | Images | Placement | Direct contact shown | Price (placeholder) |
| --- | --- | --- | --- | --- | --- | --- |
| Free | Owner | 1 | 1 | Standard ranking | No, enquiry form only | €0 |
| Standard | Owner | 1 | 1 | Ranking boost | Phone and website | €120 per year |
| Featured | Owner | 1 | 1 | Boost, plus featured strips on home and island pages | Phone and website | €300 per year |
| Agent Plus | Agent | Up to 25 | 1 per listing | Boost, agency page, bulk tools in phase 3 | Phone and website | €900 per year |

Every listing now has one image, so the plans no longer differ on photo limits. Affiliates are outside the billing structure. They have no plan, subscription or payments, and their listings always use standard ranking with no featured placement. Plans and PayPal apply to owners and agents only.

**Payment flow**

1. The owner picks a plan in `/account/billing`. The server creates a PayPal subscription for the chosen plan, sent to PayPal for approval, with the owner's or agency's `subscription row`.
2. PayPal sends `BILLING.SUBSCRIPTION.ACTIVATED`, then `PAYMENT.SALE.COMPLETED` and `BILLING.SUBSCRIPTION.PAYMENT.FAILED` events to the webhook.
3. The webhook verifies the signature, skips any event id already in `paypal_events`, and upserts `subscriptions`.
4. A Laravel service recomputes `plan_code`, `featured_until` and `rank_score` for the account's listings. If a plan lapses, listings fall back to Free and extra listings beyond the plan limit are paused, not deleted.
5. Owners manage cards, invoices and cancellation in the PayPal account. The platform never stores card data.

Just Island Holidays is not VAT registered, so subscription prices carry no VAT. If that changes, VAT will need setting up in PayPal and on invoices.

**Advertising**

- Slots are fixed sizes (`home_top`, `island_sidebar`, `listing_footer`). The admin manager uploads an image, sets target URL, dates, island targeting and weight.
- The ad server picks one active ad per slot by weighted random selection among ads whose dates and island match.
- Impressions and clicks are counted in `ad_stats_daily`. Impressions are batched in memory and flushed every minute. Clicks pass through `/api/ads/{id}/click`.
- Ads load after the main content and use no third-party trackers, so the cookie banner needs no ad-consent category.
- Affiliate links come from `affiliate_links`, rendered in a "Plan your trip" block on island and listing pages, and marked as sponsored with `rel="sponsored nofollow"`.

## Compliance

No owner or agent listing goes live without a verified licence number, and personal data is handled under GDPR for both EU and UK visitors. The legal points below come from general knowledge, not a legal review. Have a Spanish tourism lawyer confirm them before launch.

**Tourist-rental registration**

- Balearic holiday lets need a tourist-stay licence, and Spain has introduced a national registry number for short-term rentals. Both are stored in `listing_licences` and shown on the listing page.
- Island admins and super admins verify the numbers against the official registers before approving. The approve endpoint refuses owner and agent listings whose licence status is not `verified`.
- Local rules limit new tourist-rental places, particularly in Mallorca, so the listing form must state clearly that the owner is responsible for holding a valid licence.
- EU Regulation 2024/1028 on short-term rental data sharing applies from May 2026. The directory is treated as an online platform under it. What reporting that requires is still to be confirmed with an adviser.
- Affiliate listings do not need a licence number or licence verification. The affiliate's own booking page is responsible for the rental's registration, and the listing page shows a licence number only if one is supplied. Admins still check the deeplink before approving.

**GDPR and privacy**

- Roles: the platform is the controller for owner accounts and a joint or separate controller with the owner for enquiry data. Document this in the privacy notice.
- The enquiry form shows a privacy notice link and records `consent_given_at`. Marketing email is opt-in and off by default.
- The privacy notice must say that every enquiry is emailed to the owner and also kept on the platform, where super admins and the island admin for that island can read it to handle complaints and spam, and that recent enquiries are flagged for them. It must also give the retention period below.
- Retention (proposed): enquiries are deleted 24 months after creation, and soft-deleted profiles are purged after 30 days. Click events are deleted after 13 months, keeping only daily totals. A nightly job enforces all three.
- Data subject requests: owners can export and delete their data from the dashboard. Super admins can run the same for visitors by email address.
- Processors needing data processing agreements: the hosting provider, the file storage provider, PayPal, the email provider, the host's CDN and DDoS service.
- Cookie consent: strictly necessary cookies only by default. Analytics uses a cookieless, privacy-first tool (Plausible Community Edition or Matomo, self-hosted on the UK server), which avoids a consent banner for it.
- Exact property coordinates and addresses are never public. Only the offset `display_location` is exposed, and the full address goes to guests only if the owner sends it.

**Content rights**

- Terms require owners to confirm they own, or hold permission for, the image and text they upload, and give the platform a licence to display them.
- A takedown form and process handles copyright and misleading-listing complaints. Listings can be paused instantly from the admin console.
- No scraping. Every image and description comes from the lister, or from a booking provider that has agreed to its use through the admin URL import.

**Consumer and advertising rules**

- Paid placements are labelled "Featured" or "Ad". Affiliate links are disclosed and carry `rel="sponsored"`.
- Prices are shown as owner-supplied guides, with a note that the owner confirms the final price and terms.
- Check availability links lead to third-party booking sites. They go through `/go/{ref}`, carry `rel="sponsored nofollow"`, and the site discloses where it may earn from them.
- Admins check every deeplink before a listing goes live. Links must use https, and a nightly job re-checks live links and flags broken ones for an admin to pause.

## Internationalisation and SEO

Organic search is the main acquisition channel, so every island, resort and listing gets a fast, indexable, correctly localised page.

**Languages and currency**

- Launch in English. Spanish and German follow in phase 2, using `Laravel's localisation files` and the same URL structure under `/es` and `/de`.
- Interface strings live in message files. Listing text lives in `listing_translations`, and a listing with no translation in the visitor's language falls back to English with a label.
- Owners can request machine translation of a listing into the other languages. It is created as a draft that the owner reviews before publishing, and is marked as machine-translated until then.
- Listing prices are stored in the owner's currency (EUR or GBP). A daily job fetches ECB rates, and the visitor's selected currency (default GBP for `/en`, EUR otherwise) converts for display with an "approx." label.

**SEO**

- Every public route is server-rendered and served through a full-page cache that is cleared when a listing changes, so pages are fast and crawlable without JavaScript.
- Each page has a unique title and meta description, a canonical URL, and `hreflang` alternates for every available language.
- Listing pages carry `LodgingBusiness` or `VacationRental` structured data (name, image, address locality, geo from the offset location, price range). Resort and island pages carry `BreadcrumbList`.
- Filter combinations are indexable only for island, resort, property type and sleeps. Other parameters carry `noindex, follow`, and canonical points to the clean URL.
- The sitemap is split by language and type, and regenerated hourly. Paused and rejected listings return 404 or 410.
- Image pipeline: uploads are resized to AVIF and WebP at several widths, served from a CDN with `loading="lazy"` below the fold and explicit width and height to avoid layout shift.
- Each island and resort page needs original editorial copy of at least 400 words, written in-house, because thin listing grids will not rank.

## Non-functional requirements

| Area | Requirement |
| --- | --- |
| Performance | Largest Contentful Paint under 2.5 s and Cumulative Layout Shift under 0.1 on mobile 4G for home, search and listing pages. Search API responds in under 400 ms at the 95th percentile. |
| Scale (year 1) | 5,000 live listings, 100,000 monthly visitors, 3,000 enquiries a month. The architecture should handle 10 times this without redesign. |
| Availability | 99.9% monthly uptime target for public pages. Public pages are served from the CDN cache if the database is briefly unavailable. |
| Security | HTTPS only with HSTS. Access rules enforced in Laravel policies, with a test for each. PayPal webhook signature checks. Secrets in the hosting provider's vault, never in the repo. Input validation with Laravel form requests on every endpoint. |
| Abuse control | A honeypot field, an ALTCHA proof-of-work challenge and per-IP-hash rate limits on enquiry, signup and login. Owners can mark enquiries as spam, and repeat offenders are blocked. |
| Auth | Laravel Fortify with email and password, mandatory email verification, and two-factor authentication (required for island admin and super admin accounts). |
| Accessibility | WCAG 2.2 AA. Keyboard-operable search and map, alt text on every image (listers are prompted for it), sufficient colour contrast. |
| Backups | Daily database backups with point-in-time recovery, retained for 14 days. Image storage versioned. Restore tested quarterly. |
| Monitoring | GlitchTip, self-hosted, for errors for errors, uptime checks on key pages, PayPal webhook failure alerts, a daily report of failed emails and unprocessed events. |
| Environments | Local, staging and production, each with its own MySQL database and file storage bucket. |
| Testing | Unit tests for search and pricing logic, end-to-end tests for search, enquiry and checkout flows, and feature tests for every Laravel access policy. |

## Roadmap and milestones

The MVP takes about 12 weeks, assuming two full-stack developers and a part-time designer. All durations here are rough estimates to be firmed up once the open questions are settled.

&#91;embedded content: MVP plan · 7 workstreams over 12 weeks, 3 gates\]

The public site and owner dashboard overlap, so owners can publish end to end by week 8. The launch gate in week 12 needs licence verification working and the legal review complete.

**After the MVP** (rough estimates)

| Phase | Duration | Main work | Start when |
| --- | --- | --- | --- |
| 1b. Upgrade | About 1 week | Move from Laravel 12 to Laravel 13 (PHP 8.3 or later): update dependencies, run the full test suite, then deploy | Soon after launch, and before Laravel 12 security fixes end on 24 February 2027 |
| 2. Growth | 6 to 8 weeks | Spanish and German, Ibiza and Formentera content and listings, ad manager, reviews, favourites, email alerts | About 100 live listings and the first paid subscriptions |
| 3. Scale | 8 to 10 weeks | Automated commission import from programme feeds, CSV and feed import for agents, owner analytics | Manual commission imports become a burden |
| 4. Optional | Not estimated | Instant-book via a payments partner, guest accounts, mobile app | Decided on evidence from phases 2 and 3 |

## Risks and open questions

The biggest risk is not technical: a directory with few listings gets no visitors, and one with no visitors gets no listings.

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Cold start: no listings, no traffic | High | Launch two islands only. Recruit the first 50 owners directly with free Featured placement for a year. Publish strong resort guides first for search traffic. |
| Licence rules change or are stricter than assumed | High | Take legal advice before launch. Keep licence checks in one place (`listing_licences` and the approve endpoint) so rules can change without a rebuild. |
| Spam and fake listings | Medium | Email verification, admin approval of every new listing, licence verification, honeypot, ALTCHA and rate limits. |
| Large incumbents rank ahead (Airbnb, Booking.com, Vrbo, villa agencies) | Medium | Compete on direct-to-owner contact, no booking fees, and local guides. Do not compete on breadth. |
| Owners leave after the first year | Medium | Show enquiry counts in the dashboard and send a monthly enquiries report, so owners see the value. |
| Hosting and storage cost grows with image traffic | Low | Serve images from a CDN with long cache headers, resize at upload, and review cost monthly. |
| Deeplinks point to broken, misleading or unsafe pages | Medium | Admins check every link before approval, links must use https, and all clicks go through /go/{ref} so a link can be switched off at once. A nightly job flags broken links. |

**Open questions to decide before build**

- [x] Domain: decided, the main address is justislandholidays.co.uk (held by the owner for several years); justislandholidays.uk redirects to it. The hosting package has moved there from the staging address justislandholidays-uk.stackstaging.com, and search engines are kept out until launch. Trademark, still open: the owner has used the Just Holidays name for 20 years without trademark issues; a quick UK IPO search for Just Island Holidays itself is still worth doing
- [ ] Legal entity: Just Island Holidays, not VAT registered. Registered office: 86 Manston Road, Ramsgate, CT11 0RJ, United Kingdom. Still to confirm: whether it trades as a company or a sole trader, which sets the controller identity in the privacy notice.
- [x] VAT approach for subscriptions: decided, no VAT is charged because Just Island Holidays is not VAT registered.
- [ ] Final plan names, limits and prices, after speaking to a sample of owners. To be decided when the website is complete; the build uses placeholder plans until then.
- [ ] Decided: launch summaries for each island are drafted in English, Spanish and German, and a native speaker of each should check them before they go live. Island admins edit island and resort text after that. Resort pages still need an author.
- [ ] Decided: each enquiry is emailed to the owner and also saved in the platform database. Admins can read it in the Enquiries section, where recent contacts are flagged, and the privacy notice says so.
- [ ] Decided: the directory is treated as an online platform under EU Regulation 2024/1028. Still to confirm with an adviser: exactly what reporting that requires, and whether affiliate listings are covered by the duty to show registration numbers.
- [ ] Decided: island admins and super admins run moderation and licence checks (target 48 hours, licence checks by hand).
- [ ] Hosting region. Decided: UK. The database and file storage stay in a UK data centre. Still to check: whether the host's CDN processes data outside the UK. Error tracking (GlitchTip), analytics and email are UK-based or self-hosted.
- [ ] Decided: island admins cannot reject a listing or suspend an owner on their own. They recommend it, with a reason, and a super admin decides.
- [ ] Decided: one island admin may cover several islands, and a super admin covers any island with no admin available.
- [ ] Which booking sites a deeplink may point to, and whether the platform adds its own tracking parameters to affiliate links. Open for now: this depends on which affiliate partnerships are signed, and the list will be fixed once they are.
- [ ] Decided: all the booking providers have agreed that all data for a listing, including its text and images, can be used, so the URL import can be used for them. Worth keeping that agreement in writing for each provider.
- [ ] Decided: affiliate listings need no licence number on this site, because the affiliate shows it on its own website, where the deeplink points. If one is supplied it is shown. Worth confirming with an adviser that this satisfies the EU rules for an online platform.
