<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation for Order #{{ $orderNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; padding:0; background-color:#f4f4f4; }
        .container { max-width:600px; margin:20px auto; background-color:#ffffff; padding:20px; border:1px solid #dddddd; }
        .header { font-size:18px; font-weight:bold; margin-bottom:10px; }
        .details { margin-bottom:20px; }
        .item { margin-bottom:5px; }
        .total { font-weight:bold; margin-top:10px; }
        .footer { margin-top:20px; font-size:12px; color:#666666; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">Payment Confirmation for Order #{{ $orderNumber }}</div>

    <p>Dear {{ trim(($customerFirstName ?? '') . ' ' . ($customerLastName ?? '')) ?: ($customerName ?? 'Customer') }},</p>

    <p>We have successfully received your payment. Thank you for your trust!</p>

    <div class="details">
        <strong>Order Details:</strong><br>
        - Order Number: #{{ $orderNumber }}<br><br>

        @if(!empty($items))
            <strong>Items:</strong><br>
            @foreach($items as $it)
                <div class="item">- {{ $it['name'] }}: {{ (int) $it['qty'] }} x ${{ number_format((float)$it['price'], 2) }}</div>
            @endforeach
            <br>
        @endif

        <div class="total">Total Paid: {{ $totalWithCurrency }}</div>
    </div>

    @if($descriptor)
        <p>On your bank statement, the charge will appear as <strong>{{ $descriptor }}</strong>.</p>
    @endif

    <p>Your order is being processed. You will receive another email with tracking information once it ships.</p>

    <p>If you have any questions, feel free to reply to this email.</p>

    <div class="footer">
        Best regards,<br>
        {{ $companyName }}
    </div>
</div>
</body>
</html>
