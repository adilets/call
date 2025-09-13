@extends('layouts.payment')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-3">This payment link is no longer valid.</h3>
                        <p class="text-muted">Possible reasons:</p>
                        <ul class="list-unstyled text-start d-inline-block">
                            <li>• The link has expired</li>
                            <li>• The payment was already completed</li>
                            <li>• The link was cancelled</li>
                        </ul>
                        <p class="mt-4">
                            👉 Please request a new payment link or contact our support team if you need assistance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


