<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
            padding: 24px 28px;
        }

        /* ── Company Header ── */
        .company-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #1d4ed8;
        }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 0.5px;
            margin: 0 0 4px;
        }
        .company-info {
            font-size: 10px;
            color: #555;
            line-height: 1.6;
            margin: 0;
        }

        /* ── Customer Section ── */
        .customer-section {
            margin: 18px 0 12px;
        }
        .customer-name {
            font-size: 12px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 0 0 2px;
        }
        .contact-name {
            font-size: 12px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 0 0 2px;
        }

        /* ── Title ── */
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 16px 0 12px;
        }

        /* ── Body text ── */
        .body-text {
            font-size: 11px;
            line-height: 1.7;
            color: #333;
            margin-bottom: 16px;
        }

        /* ── Product Table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 20px;
        }
        table.items th {
            background: #1d4ed8;
            color: #ffffff;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.items th.right { text-align: right; }
        table.items td {
            padding: 8px 10px;
            border-bottom: 1px solid #d0ddf5;
            font-size: 11px;
            vertical-align: top;
        }
        table.items tr:nth-child(even) td {
            background: #f0f5ff;
        }
        table.items tr:nth-child(odd) td {
            background: #ffffff;
        }
        table.items td.price-tiers {
            font-size: 10px;
            line-height: 1.8;
        }

        /* ── Signature ── */
        .signature-section {
            margin-top: 28px;
            font-size: 11px;
            line-height: 1.8;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 36px;
            font-size: 9px;
            color: #aaa;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <!-- Company Header -->
    <div class="company-header">
        @if(file_exists(public_path('images/logo.png')))
        <div style="margin-bottom: 8px;">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo.png'))) }}"
                 style="height: 60px; display: block; margin: 0 auto;">
        </div>
        @endif
        @if(!empty($resolvedHeader))
            {!! $resolvedHeader !!}
        @else
            <div class="company-name">VAXSHOT PHARMACEUTICAL PRODUCTS TRADING</div>
            <div class="company-info">
                #11 Heroes Ave. Kalayaan Village, City of San Fernando, Pampanga<br>
                accounts@vaxshotcorp.com &bull; kimharoldaguas.vaxshot@gmail.com<br>
                0968-408-8401 &bull; 045-860-1751
            </div>
        @endif
    </div>

    <!-- Customer Info -->
    <div class="customer-section">
        <div class="customer-name">{{ strtoupper($quotation->customer_name) }}</div>
        @if($quotation->contact_name)
        <div class="contact-name">{{ strtoupper($quotation->contact_name) }}</div>
        @endif
    </div>

    <!-- Document Title -->
    <div class="doc-title">Price Quotation</div>

    <!-- Body / Intro Text -->
    @if($resolvedBody)
        <div class="body-text">
            {!! $resolvedBody !!}
        </div>
    @else
        <div class="body-text">
            Hello {{ $quotation->contact_name ? 'Mam/Sir' : 'there' }},<br><br>
            We are from Vaxshot Pharmaceutical Products Trading, a contracted distribution partner
            of <strong>GlaxoSmithKline (GSK), MSD, Sanofi, Pfizer, Abbott, Vizcarra</strong>, and other leading
            biological companies. We specialize in supplying high-quality vaccines exclusively to
            healthcare providers, clinics, hospitals, dispensing doctors, government units and
            corporate healthcare programs.<br><br>
            <strong>Proposal Overview</strong><br>
            Please give us the opportunity to submit our price proposal to provide access to more
            cost-effective vaccines for your patients and dependents.
        </div>
    @endif

    <!-- Product Table -->
    @if(($quotation->quotation_type ?? 'pricing') === 'with_total')
    {{-- Pricing with Total: Product Name | Indication | Price | Qty | Total --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 26%;">Product Name</th>
                <th style="width: 30%;">Indication</th>
                <th style="width: 16%;" class="right">Unit Price</th>
                <th style="width: 10%;" class="right">Qty</th>
                <th style="width: 18%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($quotation->items as $item)
            @php
                $supplierName = $item->product?->supplier?->company;
                $indication   = $item->description ?: $item->product?->indication;
                $expiryDate   = $item->expiry_date ?? $item->product?->expiry_date;
                $lineTotal    = $item->unit_price * $item->quantity;
                $grandTotal  += $lineTotal;
            @endphp
            <tr>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td>
                    @if($supplierName)
                        <strong>{{ $supplierName }}/</strong><br>
                    @endif
                    {{ $indication ?: '—' }}
                    @if($expiryDate)
                        <br><span style="font-size:9px; color:#888;">Exp: {{ \Carbon\Carbon::parse($expiryDate)->format('M Y') }}</span>
                    @endif
                </td>
                <td style="text-align:right;">&#8369;{{ number_format($item->unit_price, 2) }}</td>
                <td style="text-align:right;">{{ number_format($item->quantity) }}</td>
                <td style="text-align:right;"><strong>&#8369;{{ number_format($lineTotal, 2) }}</strong></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="4" style="text-align:right; font-weight:bold; border-bottom:none; padding-top:10px;">TOTAL</td>
                <td style="text-align:right; font-weight:bold; border-bottom:none; padding-top:10px;">&#8369;{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @else
    {{-- Pricing Only: Product Name | Indication | Price Tiers | Expiration Date --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 28%;">Product Name</th>
                <th style="width: 32%;">Indication</th>
                <th style="width: 24%;">Price Tiers</th>
                <th style="width: 16%;">Expiration Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
            <tr>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td>
                    @php
                        $supplierName = $item->product?->supplier?->company;
                        $indication   = $item->description ?: $item->product?->indication;
                    @endphp
                    @if($supplierName)
                        <strong>{{ $supplierName }}/</strong><br>
                    @endif
                    {{ $indication ?: '—' }}
                </td>
                <td class="price-tiers">
                    @if($item->use_flat_price)
                        &#8369;{{ number_format($item->unit_price, 2) }}
                    @elseif($item->product && $item->product->tiers->count())
                        @foreach($item->product->tiers as $tier)
                            {{ $tier->tier_label }}: &#8369;{{ number_format($tier->price, 2) }}<br>
                        @endforeach
                    @else
                        &#8369;{{ number_format($item->unit_price, 2) }}
                    @endif
                </td>
                @php
                    $expiryDate = $item->expiry_date ?? $item->product?->expiry_date;
                @endphp
                <td>{{ $expiryDate ? \Carbon\Carbon::parse($expiryDate)->format('M Y') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!$resolvedBody)
    <!-- Default Benefits Section -->
    <div class="body-text">
        <strong>Benefits of Partnering with Us</strong><br>
        <strong>Reliable Supply Chain:</strong> Timely delivery and replenishment of vaccine stocks.<br>
        <strong>Competitive Pricing:</strong> Cost-effective solutions to support your healthcare services.<br>
        <strong>Quality Assurance:</strong> Vaccines sourced from reputable manufacturers, adhering to stringent quality controls.<br>
        <strong>Logistics Support:</strong> Proper cold chain management to preserve vaccine integrity.<br>
        <strong>Dedicated Customer Support:</strong> A responsive team to address your inquiries and ensure seamless collaboration.<br><br>
        <strong>Next Step</strong><br>
        We would appreciate the opportunity to discuss this proposal further and explore how we can best support your vaccine supply needs.
        Please let us know a convenient time for a meeting or call.<br><br>
        We look forward to building a long-term and mutually beneficial partnership. Should you have any questions, feel free to reach out.<br><br>
        Thank you for your time and consideration.
    </div>
    @endif

    <!-- Signature -->
    <div class="signature-section">
        Best regards,<br><br>
        @if($resolvedSignature)
            {!! $resolvedSignature !!}
        @else
            <strong>Kim Harold Aguas</strong><br>
            Vaxshot Pharmaceutical Products Trading<br>
            Account Manager
        @endif
    </div>

    <div class="footer">
        {{ $quotation->quotation_number }} &bull; {{ $quotation->quotation_date->format('F d, Y') }}
    </div>

</body>
</html>
