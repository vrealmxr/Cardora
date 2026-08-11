<!DOCTYPE html>
<html lang="{{ $recipient->locale ?: 'el' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $content['subject'] ?? 'Cardora' }}</title>
</head>
<body style="margin:0;padding:0;background:#060d1a;color:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
    @php
        $baseUrl = rtrim((string) (config('app.url') ?: config('services.frontend.url')), '/');
        $logoUrl = ($baseUrl !== '' ? $baseUrl : '') . '/asset.php?f=logo-20260716.png';
    @endphp

    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">
            <tr>
                <td style="padding:0 0 12px 4px;">
                    <img src="{{ $logoUrl }}" alt="Cardora" style="height:38px;width:auto;display:block;">
                </td>
            </tr>
        </table>

        <div style="background:#101a30;border:1px solid rgba(255,255,255,.14);border-radius:24px;padding:28px;">
            @if (!empty($content['eyebrow']))
                <div style="font-size:11px;letter-spacing:.26em;text-transform:uppercase;color:#f2cb70;margin-bottom:14px;">
                    {{ $content['eyebrow'] }}
                </div>
            @endif

            <h1 style="margin:0 0 12px;font-size:30px;line-height:1.2;color:#ffffff;">
                {{ $content['title'] ?? 'Cardora' }}
            </h1>

            @if (!empty($content['body']))
                <p style="margin:0 0 22px;font-size:15px;line-height:1.8;color:#d4deee;">
                    {{ $content['body'] }}
                </p>
            @endif

            @if (!empty($content['details']) && is_array($content['details']))
                <div style="background:#162642;border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:18px 20px;margin-bottom:24px;">
                    @foreach ($content['details'] as $detail)
                        @if (!empty($detail['value']))
                            <div style="margin:0 0 12px;">
                                <div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#9fb2cf;">
                                    {{ $detail['label'] ?? '' }}
                                </div>
                                <div style="margin-top:6px;font-size:16px;line-height:1.5;font-weight:700;color:#ffffff;">
                                    {{ $detail['value'] }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (!empty($content['cta']) && !empty($content['url']))
                <a
                    href="{{ $content['url'] }}"
                    style="display:inline-block;padding:14px 22px;border-radius:14px;background:#f2cb70;color:#09111d;text-decoration:none;font-weight:700;"
                >
                    {{ $content['cta'] }}
                </a>
            @endif

            @if (!empty($content['footer']))
                <p style="margin:20px 0 0;font-size:13px;line-height:1.7;color:#9fb2cf;">
                    {{ $content['footer'] }}
                </p>
            @endif
        </div>

        <p style="margin:14px 6px 0;font-size:11px;line-height:1.6;color:#7f8ea6;">
            {{ app()->getLocale() === 'en' ? 'Cardora marketplace notifications' : 'Ειδοποιήσεις marketplace Cardora' }}
        </p>
    </div>
</body>
</html>
