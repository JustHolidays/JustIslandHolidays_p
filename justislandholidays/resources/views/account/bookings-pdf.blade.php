<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bookings {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</title>
    <style>
        /* DejaVu Sans is built into the PDF library and has the € and £ signs and accents. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2933; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 0 0 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f1f5f9; border-bottom: 2px solid #94a3b8; padding: 5px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 5px; vertical-align: top; }
        .nowrap { white-space: nowrap; }
        .footer { margin-top: 12px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Bookings: {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</h1>
    <p>{{ $who }} · {{ trans_choice(':count stay|:count stays', $rows->count()) }} with at least one night in these dates · made {{ now()->format('j M Y, H:i') }}</p>

    @if ($rows->isEmpty())
        <p>No stays in these dates.</p>
    @else
        <table>
            <thead>
                <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $r['property'] }}</td>
                        <td class="nowrap">{{ $r['in']->format('D j M Y') }}</td>
                        <td class="nowrap">{{ $r['out']->format('D j M Y') }}</td>
                        <td>{{ $r['nights'] }}</td>
                        <td>{{ $r['guest'] ?: '–' }}</td>
                        <td class="nowrap">{{ $r['enquiry'] ?: '–' }}</td>
                        <td>{{ $r['source'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">Just Island Holidays · This list may include guests' names: keep it safe and only for managing these bookings.</p>
</body>
</html>
