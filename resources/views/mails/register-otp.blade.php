@extends('mails.layout')

@section('content')
Hi {{ $user->name }} <br>

Ini adalah OTP Register Anda: {{ $user->otp_register }}
@endsection