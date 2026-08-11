<!DOCTYPE html>
<html lang="{{ $recipient->locale ?: 'el' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $copy['subject'] }}</title>
</head>
<body style="margin:0;padding:0;background:#0b1120;color:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="background:#10192d;border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:28px;">
            <div style="font-size:12px;letter-spacing:.24em;text-transform:uppercase;color:#f2cb70;margin-bottom:14px;">
                {{ $copy['eyebrow'] }}
            </div>
            <h1 style="margin:0 0 12px;font-size:28px;line-height:1.2;color:#ffffff;">
                {{ $copy['title'] }}
            </h1>
            <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#cbd5e1;">
                {{ $copy['body'] }}
            </p>

            <div style="background:#18233a;border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:18px 20px;margin-bottom:24px;">
                <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#94a3b8;margin-bottom:10px;">
                    {{ $copy['listing_label'] }}
                </div>
                <div style="font-size:18px;line-height:1.4;font-weight:700;color:#ffffff;">
                    {{ $listing->title_snapshot ?: $listing->product?->title }}
                </div>
                @if ($listing->price !== null)
                    <div style="margin-top:10px;font-size:16px;font-weight:700;color:#f2cb70;">
                        €{{ number_format((float) $listing->price, 2, '.', ',') }}
                    </div>
                @endif
            </div>

            <a
                href="{{ $listingUrl }}"
                style="display:inline-block;padding:14px 20px;border-radius:14px;background:#f2cb70;color:#09111d;text-decoration:none;font-weight:700;"
            >
                {{ $copy['cta'] }}
            </a>

            <p style="margin:22px 0 0;font-size:13px;line-height:1.7;color:#94a3b8;">
                {{ $copy['footer'] }}
            </p>
        </div>
    </div>
</body>
</html>
