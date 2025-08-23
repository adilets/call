<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder for Order #{{ $orderNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f4f4f4; }
        .container { max-width:600px; margin:20px auto; background:#fff; padding:20px; border:1px solid #ddd; }
        .header { font-size:18px; font-weight:bold; margin-bottom:10px; }
        .details { margin-bottom:20px; }
        .item { margin-bottom:4px; }
        .total { font-weight:bold; margin-top:10px; }
        .payment-link { margin:20px 0; }
        .footer { margin-top:20px; font-size:12px; color:#666; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">Payment Reminder for Order #{{ $orderNumber }}</div>

    <p>Dear Customer,</p>
    <p>Thank you for your order with us!</p>

    <div class="details">
        <strong>Order Details:</strong><br>
        - Order Number: #{{ $orderNumber }}<br><br>

        @if($items && count($items))
            <strong>Items:</strong><br>
            @foreach($items as $it)
                <div class="item">- {{ $it['name'] }}: {{ (int)$it['qty'] }} × ${{ number_format((float)$it['price'], 2) }}</div>
            @endforeach
            <br>
        @endif

        <div class="total">Total Amount: {{ $totalWithCurrency }}</div>
    </div>

    <div class="payment-link">
        To complete your payment, please click on the link below:<br>
        <a href="{{ $paymentUrl }}">Pay here</a>
    </div>

    @if($linkValidHuman)
        <p>This link is valid for {{ $linkValidHuman }} from the date of this email.</p>
    @endif

    <p>If you have any questions, feel free to reply to this email.</p>

    <div class="footer">
        Best regards,<br>
        {{ $companyName }}
    </div>
</div>
</body>
</html>
