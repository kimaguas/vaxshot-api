<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; background: #f5f5f5;">
    <div style="max-width: 620px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px; border: 1px solid #e5e7eb;">

        <!-- Header -->
        <div style="text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 16px; margin-bottom: 24px;">
            @if(file_exists(public_path('images/logo.png')))
            <img src="{{ $message->embed(public_path('images/logo.png')) }}"
                 style="height: 70px; display: block; margin: 0 auto 10px;" alt="Vaxshot Logo">
            @endif
            <h2 style="color: #1d4ed8; margin: 0; font-size: 20px; font-weight: bold;">VAXSHOT PHARMACEUTICAL PRODUCTS TRADING</h2>
            <p style="margin: 4px 0 0; color: #555; font-size: 12px;">
                #11 Heroes Ave. Kalayaan Village, City of San Fernando, Pampanga<br>
                accounts@vaxshotcorp.com &bull; kimharoldaguas.vaxshot@gmail.com<br>
                0968-408-8401 &bull; 045-860-1751
            </p>
        </div>

        @if($resolvedBody)
            <!-- Template body with placeholders resolved -->
            <div style="font-size: 14px; line-height: 1.7; color: #333;">
                {!! nl2br(e($resolvedBody)) !!}
            </div>
        @else
            <!-- Default fallback body -->
            <p>Dear <strong>{{ $quotation->contact_name ?? $quotation->customer_name }}</strong>,</p>
            <p>Please find attached our quotation
               <strong>{{ $quotation->quotation_number }}</strong>
               dated <strong>{{ $quotation->quotation_date->format('F d, Y') }}</strong>.</p>

            @if($quotation->valid_until)
            <p>This quotation is valid until <strong>{{ $quotation->valid_until->format('F d, Y') }}</strong>.</p>
            @endif

            <div style="background: #f0f5ff; border: 1px solid #d0ddf5; border-radius: 6px; padding: 16px; margin: 24px 0;">
                <p style="margin: 0; font-size: 14px; color: #1d4ed8;">
                    <strong>Total Amount: &#8369;{{ number_format($quotation->total_amount, 2) }}</strong>
                </p>
            </div>

            <p>Please refer to the attached PDF for the full breakdown.</p>
        @endif

        @if($quotation->notes)
        <p style="background: #fafafa; border-left: 3px solid #d1d5db; padding: 12px; font-size: 13px; color: #6b7280; margin-top: 16px;">
            <strong>Notes:</strong> {{ $quotation->notes }}
        </p>
        @endif

        <!-- Signature -->
        <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
            @if($resolvedSignature)
                <p style="margin: 0; font-size: 14px; white-space: pre-line;">{{ $resolvedSignature }}</p>
            @else
                <p style="margin: 0;">Best regards,<br>
                <strong>VaxshotApp Team</strong><br>
                Vaxshot Pharmaceutical Products Trading</p>
            @endif
        </div>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">
        <p style="font-size: 11px; color: #9ca3af; margin: 0; text-align: center;">
            This is an automated email. Please do not reply directly to this message.
        </p>
    </div>
</body>
</html>
