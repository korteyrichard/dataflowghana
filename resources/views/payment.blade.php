<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div class="container">
        <h2>Make Payment</h2>
        
        @if(session('error'))
            <div style="color: red;">{{ session('error') }}</div>
        @endif
        
        <form method="POST" action="{{ route('payment.initialize') }}">
            @csrf
            <div>
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Amount (₦):</label>
                <input type="number" name="amount" required>
            </div>
            <button type="submit">Pay Now</button>
        </form>
    </div>
</body>
</html>