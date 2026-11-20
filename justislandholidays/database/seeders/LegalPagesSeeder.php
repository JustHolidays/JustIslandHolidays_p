<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

/**
 * DRAFT legal and information pages (English), as editable site-wide content pages.
 * They are NOT legal advice. Every "[TO CONFIRM: …]" marks a fact or wording to settle;
 * `php artisan launch:check` fails while any marker remains or no legal review is recorded.
 * Existing pages are never overwritten, so edits made in the admin are safe.
 */
class LegalPagesSeeder extends Seeder
{
    public const SLUGS = ['privacy', 'terms', 'about', 'contact', 'accessibility'];

    public const DRAFT_NOTICE = "> **Draft for legal review.** This page has not been checked by a lawyer yet. Items marked “to confirm” are still to be settled.\n\n";

    public function run(): void
    {
        foreach ($this->pages() as $slug => [$title, $description, $body]) {
            $page = Page::firstOrCreate(
                ['slug' => $slug, 'island_id' => null],
                ['kind' => 'static', 'is_published' => true],
            );

            if (! PageTranslation::where('page_id', $page->id)->where('lang', 'en')->exists()) {
                PageTranslation::create([
                    'page_id' => $page->id,
                    'lang' => 'en',
                    'title' => $title,
                    'meta_description' => $description,
                    'body_md' => self::DRAFT_NOTICE.trim($body)."\n",
                ]);
            }
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    private function pages(): array
    {
        return [
            'privacy' => ['Privacy notice', 'How Just Island Holidays uses your personal data.', <<<'MD'
## Who we are

Just Island Holidays runs this website, a directory of holiday homes in the Balearic Islands. We are the controller of the personal data described here.

- **Name:** Just Island Holidays [TO CONFIRM: company name and number, or the sole trader's name]
- **Address:** 86 Manston Road, Ramsgate, CT11 0RJ, United Kingdom
- **Email:** [TO CONFIRM: contact email]

We do not take bookings or payments from guests. You book with the owner, or on the booking site a listing links to.

## If you send an enquiry

**What we collect:** your name, email address, phone number (if you give it), the dates and number of guests, your message, the language you used, and when you agreed to this notice. We also keep a scrambled code made from your internet address, which changes every day and cannot be turned back into the address. We use it only to stop spam and repeated messages.

**What happens to it:**

- We email your enquiry to the owner of the home, so they can reply to you directly. The owner then has their own copy and is responsible for how they use it.
- We also keep a copy. Our senior staff, and the staff member who looks after the island the home is on, can read stored enquiries to deal with complaints and spam. New enquiries are flagged for them for a few days. Each time one of them opens an enquiry, this is recorded.
- We keep enquiries for **24 months**, then delete them.

**Why we may do this:** to pass your message to the owner at your request, and our legitimate interest in handling complaints and preventing spam. [TO CONFIRM with adviser: lawful bases, and whether we and the owner are joint or separate controllers of enquiry data]

## If you click Check availability

Some homes link to the owner's or a partner's booking site. When you click, we record the time, the home, the page you were on, your language and type of device (phone, tablet or computer), and the same daily scrambled code described above. We never store your internet address. We add a random click number to the link so the booking site can tell us if a booking followed, which is how we may earn a commission.

The click itself sets no cookies. We keep click records for **13 months**; after that we keep only daily totals, which contain no personal data. The booking site you arrive at has its own privacy policy.

## If you have an account (owners, agents and partners)

We hold your name, email address, phone number, the kind of account you have, your agency (if any), your password (stored scrambled, we cannot read it), your two-factor sign-in settings, when you last signed in, and the details of your listings. The exact location, address and contact details of a listing are kept private; visitors see a map pin about 300 metres away and, on some paid plans, your phone number and website. Tourist licence numbers are shown on listings, as the rules require. A licence document you upload is seen only by our staff.

If you pay for a plan, payment is handled by PayPal. We receive your PayPal subscription and payer reference and the amounts paid. We never see or store card details.

We keep a log of changes our staff make to listings and accounts, so decisions can be checked later.

## If you have a free visitor account

You can create a free account to save holiday homes as favourites. We hold your name, email address, your password (stored scrambled, we cannot read it), when you last signed in, and the list of homes you saved. Owners and agents cannot see who saved their home. Your favourites are deleted with your account.

## Page views

We count how many times each property page is viewed, as one total per property per day. We do not store anything about you to do this: to avoid counting the same visit twice, a scrambled code made from your connection and browser is kept for 30 minutes only, then deleted.

## Cookies

We only use cookies that are needed for the site to work:

- a session cookie and a security cookie, set when you fill in a form or sign in, so the form works and cannot be misused;
- a “keep me signed in” cookie, only if you tick that box.

We use no advertising cookies. Visitor statistics are collected without cookies and without following you around the web, by [TO CONFIRM: Plausible or Matomo] running on our own server. Because of this we do not need to ask for cookie consent.

## Maps

Our maps are made of map images from [TO CONFIRM: map provider, for example MapTiler or Stadia Maps]. To show them, your browser asks that provider for the images, so it receives your internet address and the area of the map you are looking at. It sets no cookies. The map on a home's page shows a circle around the area, not the exact address.

## Who we share data with

- **Owners**, who receive the enquiries sent to them.
- **Service providers** who run parts of the site for us, under contracts that protect your data: our web host [TO CONFIRM: name], with a data centre in the United Kingdom, including its content delivery and attack protection service; our file storage provider [TO CONFIRM: name]; our email provider [TO CONFIRM: name], based in the United Kingdom; and PayPal for owners' payments.
- **Authorities**, where the law requires it. [TO CONFIRM with adviser: any reporting required by EU Regulation 2024/1028 on short-term rental data]

We do not sell personal data.

## Where your data is kept

Our servers and file storage are in the United Kingdom. [TO CONFIRM with the host: whether its content delivery network processes data outside the UK, and if so, the safeguards]

## How long we keep data

| Data | How long |
| --- | --- |
| Enquiries | 24 months |
| Check availability clicks | 13 months, then daily totals only |
| Accounts | Until you close the account; personal details are removed 30 days after it is deleted |
| Payment records | [TO CONFIRM: period required for accounts and tax] |
| Staff change log | [TO CONFIRM: period] |

## Your rights

You can ask us for a copy of your data, to correct it, to delete it, to restrict or object to how we use it, or to receive it in a format you can take elsewhere. Email us at [TO CONFIRM: contact email]. We will reply within one month.

If you are unhappy with how we have handled your data, please tell us first. You can also complain to the Information Commissioner's Office (ico.org.uk, 0303 123 1113). If you live in the European Union, you can contact the data protection authority in your country.

## Changes

We will update this page if how we use data changes, and change the date below.

Last updated: [TO CONFIRM: date of publication]
MD],

            'terms' => ['Terms of use', 'The terms for visitors and for owners, agents and partners who list homes.', <<<'MD'
These terms apply to everyone who uses Just Island Holidays. Part 2 also applies to owners, agents and partners who list homes.

Just Island Holidays [TO CONFIRM: company name and number, or the sole trader's name], 86 Manston Road, Ramsgate, CT11 0RJ, United Kingdom.

## Part 1: for everyone

**We are a directory.** We show holiday homes in the Balearic Islands and pass your enquiries to their owners. We do not take bookings or payments from guests, and we are not part of any booking or rental contract. That contract is between you and the owner, or the booking site you book on.

**Information comes from the people who list.** Owners, agents and partners write the descriptions, choose the photos and set the prices. We check each listing before it goes live, and we look up owners' and agents' tourist licence numbers in the official register, but we cannot promise every detail is right. Prices are guides from the owner; the owner confirms the final price and terms.

**Check availability links** take you to another company's booking site. Their terms apply there. We may earn a commission if you book, at no extra cost to you.

**Please do not** copy the site's content, send spam through the enquiry form, or try to break or overload the site.

**Report a problem.** If a listing looks wrong, misleading or uses your photos, tell us on the [contact page](/en/p/contact). We can pause a listing straight away while we look into it.

## Part 2: for owners, agents and partners

**Your account.** Keep your sign-in details safe. Tell us if they may have been used by someone else.

**Your licence.** Holiday lets in the Balearic Islands need a tourist licence. You are responsible for holding a valid licence for every home you list and for keeping it valid. We check the number before your listing goes live and may pause a listing if the licence is no longer valid. Partner listings that link to a booking site show the licence on that site.

**Your content.** You must own, or have permission to use, the photos and text you add. You give us a free, non-exclusive licence to show them on this site, and in our own promotion of it, while the listing is on the site. Your content must be accurate and must not mislead.

**Your booking link.** If you add a Check availability link, you are responsible for keeping it working and pointing at the right home. We do not check whether owners' and agents' links work, and a change to your link shows straight away.

**Our checks.** We review every new listing, changes to licence numbers, and changes to partners' booking links, usually within 48 hours. We may refuse, pause or remove a listing, or close an account, if it breaks these terms or the law. We will tell you why.

**Enquiries.** Use guests' details only to reply about their stay. Do not add them to mailing lists without their agreement.

**Plans and payment.** Paid plans are billed yearly through PayPal and renew automatically until you cancel. The Featured Plan is a single payment for 3, 6, 9 or 12 months from the day you pay, and does not renew. Prices are shown without VAT because Just Island Holidays is not VAT registered. If you cancel a yearly plan, it stays active until the end of the year you have paid for; when a plan ends, your listings move to the Free Plan. [TO CONFIRM: refund policy, and cooling-off rights if any apply]

**Our responsibility.** [TO CONFIRM with a lawyer: limitation of liability wording]

## Part 3: general

We may change these terms; we will give account holders notice of important changes. These terms are governed by the law of England and Wales [TO CONFIRM], and the courts of England and Wales have jurisdiction, without affecting any rights you have as a consumer where you live.

Last updated: [TO CONFIRM: date of publication]
MD],

            'about' => ['About us', 'Just Island Holidays: holiday homes in the Balearic Islands, with direct contact to owners.', <<<'MD'
Just Island Holidays helps you find a holiday home in Mallorca and Menorca, with Ibiza and Formentera to follow.

You contact owners directly. We do not take bookings, and we charge no booking fees. Some homes also link to a trusted booking site through a Check availability button.

Owners and agents pay to list. Before a listing goes live we check it, and we look up the owner's tourist licence number in the official Balearic register.

[TO CONFIRM: a few sentences about the people behind Just Island Holidays]
MD],

            'contact' => ['Contact us', 'How to contact Just Island Holidays, or report a listing.', <<<'MD'
**Email:** [TO CONFIRM: contact email]

**Post:** Just Island Holidays, 86 Manston Road, Ramsgate, CT11 0RJ, United Kingdom

## Asking about a home

Please use the enquiry form on the home's page. It goes straight to the owner, who will reply to you.

## Reporting a listing

If a listing is misleading, has the wrong licence number, or uses your photos or text without permission, email us with:

- the address of the listing page;
- what is wrong;
- for photos or text, proof that they are yours.

We can pause a listing straight away while we look into it, and we will tell you what we decide.

## Owners and agents

To list your home, see [list your property](/en/owners).
MD],

            'accessibility' => ['Accessibility statement', 'How accessible this website is, and how to tell us about problems.', <<<'MD'
We want everyone to be able to use Just Island Holidays. We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA.

## What we have done

- Every page can be used with a keyboard, and the focus is always visible.
- Every image has a text description, and every form field has a label.
- Text and buttons have strong colour contrast; the site checks this when its colours are changed.
- Buttons and links are large enough to tap easily.
- The site works when zoomed to 400% and on small screens.
- The spam check on forms is a simple tick box with no puzzles.

## Known limits

- The site is in English only for now. Spanish and German will follow.
- Owners write their own descriptions and choose their own photos. We ask them for photo descriptions and check them, but some may be less helpful than we would like.
- Check availability links go to other companies' booking sites, which we do not control.
- Maps can be moved and zoomed with the keyboard, but are hard to use with a screen reader. Every home on a map is also in the list on the same page, and each home's page names its resort.

## Tell us about a problem

If something is hard to use, email [TO CONFIRM: contact email]. Tell us the page and what went wrong, and we will reply within 5 working days.

If you are not happy with our reply, you can contact the Equality Advisory and Support Service (EASS).

This statement was written on [TO CONFIRM: date] and will be reviewed at least once a year.
MD],
        ];
    }
}
