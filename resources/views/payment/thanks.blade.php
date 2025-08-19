@extends('layouts.payment')

@section('content')
    <body class="bg-light d-flex flex-column justify-content-center align-items-center vh-100">

    <div class="card shadow-lg text-center p-5" style="max-width: 500px;">
        <div class="mb-4 text-success">
            <!-- Heroicon check-circle -->
            <svg xmlns="http://www.w3.org/2000/svg" class="bi bi-check-circle" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm3.97-8.03a.75.75 0 0 0-1.08-1.04L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.08 0l3.92-4.06z"/>
            </svg>
        </div>
        <h2 class="fw-bold">Thank You!</h2>
        <p class="mt-3">
            Your payment has been successfully processed.
            An email confirmation has been sent to you.
        </p>
    </div>

    </body>
@endsection
