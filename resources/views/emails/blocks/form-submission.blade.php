@if (! empty($rows))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 16px 0; border-collapse: collapse; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        @if ($title)
            <tr>
                <td colspan="2" style="padding: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #111827;">
                    {{ $title }}
                </td>
            </tr>
        @endif
        @foreach ($rows as $row)
            <tr>
                <td style="padding: 8px 12px 8px 0; vertical-align: top; font-size: 13px; color: #6b7280; border-top: 1px solid #e5e7eb; width: 40%;">
                    {{ $row['label'] }}
                </td>
                <td style="padding: 8px 0; vertical-align: top; font-size: 13px; color: #111827; border-top: 1px solid #e5e7eb;">
                    {!! nl2br(e($row['value'])) !!}
                </td>
            </tr>
        @endforeach
    </table>
@endif
