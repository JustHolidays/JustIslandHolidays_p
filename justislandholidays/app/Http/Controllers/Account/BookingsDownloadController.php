<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CalendarBooking;
use App\Models\Listing;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download the booking list (20 Nov 2026): every stay on the owner's or agency's calendars
 * that overlaps the chosen dates, as a PDF (to print or send) or an Excel spreadsheet.
 * Same columns as the Bookings page. Text is always written as text, so a guest reference
 * starting with "=" cannot become a spreadsheet formula.
 */
class BookingsDownloadController extends Controller
{
    public const MAX_DAYS = 1100;   // about three years

    public const HEADINGS = ['Property', 'Arrival', 'Departure', 'Nights', 'Guest', 'Enquiry', 'Source'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'format' => ['required', 'in:pdf,xlsx'],
        ], [
            'from.required' => 'Choose the first date.',
            'to.required' => 'Choose the last date.',
            'to.after_or_equal' => 'The last date must be on or after the first date.',
            'format.in' => 'Choose PDF or Excel.',
        ]);
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->startOfDay();
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            return back()->withErrors(['to' => 'Choose up to three years at a time.'])->withInput();
        }

        $rows = $this->rows($request, $from, $to);
        $name = 'bookings-'.$from->toDateString().'-to-'.$to->toDateString().'.'.$data['format'];
        Audit::record($request->user(), 'bookings.download', 'user', $request->user()->id, [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => $data['format'], 'stays' => $rows->count(),
        ]);

        return $data['format'] === 'pdf'
            ? $this->pdf($rows, $from, $to, $name, $request)
            : $this->xlsx($rows, $name);
    }

    /**
     * Stays that overlap the dates (a guest there on any night from $from to $to), earliest first.
     *
     * @return Collection<int, array{property: string, in: CarbonImmutable, out: CarbonImmutable, nights: int, guest: string, enquiry: string, source: string}>
     */
    private function rows(Request $request, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $user = $request->user();
        $listingIds = Listing::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)
                ->when($user->agency_id, fn ($q) => $q->orWhere('agency_id', $user->agency_id)))
            ->pluck('id');

        return CalendarBooking::whereIn('listing_id', $listingIds)
            ->where('check_in', '<=', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')->orderBy('id')
            ->with(['listing.translations', 'feed', 'enquiry'])
            ->get()
            ->map(fn (CalendarBooking $b) => [
                'property' => $b->listing?->title('en') ?? '',
                'in' => $b->check_in->toImmutable(),
                'out' => $b->check_out->toImmutable(),
                'nights' => (int) $b->check_in->diffInDays($b->check_out),
                'guest' => $b->enquiry?->guest_name ?? $b->guest_ref ?? '',
                'enquiry' => $b->enquiry?->reference() ?? '',
                'source' => $b->sourceLabel(),
            ]);
    }

    private function pdf(Collection $rows, CarbonImmutable $from, CarbonImmutable $to, string $name, Request $request): Response
    {
        return Pdf::loadView('account.bookings-pdf', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'who' => $request->user()->name,
            'headings' => self::HEADINGS,
        ])->setOption('isFontSubsettingEnabled', true)   // only the letters used: a small file
            ->setPaper('a4', 'landscape')->download($name);
    }

    private function xlsx(Collection $rows, string $name): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'bookings');
        $bold = new Style(fontBold: true);
        $date = new Style(format: 'd mmm yyyy');

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(new Row(array_map(fn ($h) => new StringCell($h, $bold), self::HEADINGS)));
        foreach ($rows as $r) {
            $writer->addRow(new Row([
                new StringCell($r['property']),
                new DateTimeCell($r['in'], $date),
                new DateTimeCell($r['out'], $date),
                new NumericCell($r['nights']),
                new StringCell($r['guest']),
                new StringCell($r['enquiry']),
                new StringCell($r['source']),
            ]));
        }
        $writer->close();

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
