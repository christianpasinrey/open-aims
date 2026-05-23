@php
    /**
     * Reusable transactional email layout.
     *
     * @var string       $heading     Main heading (the event).
     * @var string|null  $intro       Short paragraph under the heading.
     * @var string|null  $badge       Optional monospace badge (e.g. issue identifier).
     * @var string       $headline    The subject of the email (issue title / project name).
     * @var array<int,array{label:string,value:string}>  $meta  Label/value rows.
     * @var string|null  $statusFrom  Optional "from" status for a state change.
     * @var string|null  $statusTo    Optional "to" status for a state change.
     * @var string       $actionUrl
     * @var string       $actionText
     * @var string|null  $footnote
     */
    $accent = '#6366f1';
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; -webkit-font-smoothing:antialiased; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <span style="display:none!important; visibility:hidden; opacity:0; color:transparent; height:0; width:0;">{{ $heading }} — {{ $headline }}</span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; width:100%;">

                    {{-- Wordmark --}}
                    <tr>
                        <td style="padding:0 4px 16px;">
                            <span style="font-size:15px; font-weight:600; letter-spacing:-0.2px; color:#17181a;">{{ config('app.name') }}</span>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff; border:1px solid #e6e8eb; border-radius:14px; padding:32px;">

                            <h1 style="margin:0 0 8px; font-size:19px; line-height:1.35; font-weight:600; letter-spacing:-0.3px; color:#17181a;">{{ $heading }}</h1>

                            @if (!empty($intro))
                                <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#6b7280;">{{ $intro }}</p>
                            @endif

                            {{-- Item card --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafbfc; border:1px solid #eceef1; border-radius:10px; margin:0 0 24px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        @if (!empty($badge))
                                            <span style="display:inline-block; font-family:'SF Mono',ui-monospace,Menlo,Consolas,monospace; font-size:12px; font-weight:600; color:#4b5563; background-color:#eef0f3; border-radius:6px; padding:3px 8px; margin:0 0 10px;">{{ $badge }}</span>
                                        @endif

                                        <div style="font-size:16px; line-height:1.45; font-weight:600; letter-spacing:-0.2px; color:#17181a;">{{ $headline }}</div>

                                        @if (!empty($statusFrom) || !empty($statusTo))
                                            <div style="margin:14px 0 0;">
                                                <span style="display:inline-block; font-size:13px; font-weight:500; color:#6b7280; background-color:#eef0f3; border-radius:6px; padding:4px 10px;">{{ $statusFrom ?? '—' }}</span>
                                                <span style="display:inline-block; color:#9aa1ab; padding:0 6px; font-size:13px;">&rarr;</span>
                                                <span style="display:inline-block; font-size:13px; font-weight:600; color:#ffffff; background-color:{{ $accent }}; border-radius:6px; padding:4px 10px;">{{ $statusTo ?? '—' }}</span>
                                            </div>
                                        @endif

                                        @if (!empty($meta))
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 0; border-top:1px solid #eceef1;">
                                                @foreach ($meta as $row)
                                                    <tr>
                                                        <td style="padding:8px 0 0; font-size:13px; color:#9aa1ab; width:42%; vertical-align:top;">{{ $row['label'] }}</td>
                                                        <td style="padding:8px 0 0; font-size:13px; color:#374151; font-weight:500; text-align:right; vertical-align:top;">{{ $row['value'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            {{-- Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:8px; background-color:{{ $accent }};">
                                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:600; letter-spacing:-0.1px; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 4px 0;">
                            <p style="margin:0 0 6px; font-size:12px; line-height:1.5; color:#9aa1ab;">
                                @if (!empty($footnote))
                                    {{ $footnote }}
                                @else
                                    Recibes este correo por tu actividad en {{ config('app.name') }}.
                                @endif
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.5; color:#c2c7cf;">
                                Si el botón no funciona, copia este enlace: <span style="color:#9aa1ab;">{{ $actionUrl }}</span>
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
