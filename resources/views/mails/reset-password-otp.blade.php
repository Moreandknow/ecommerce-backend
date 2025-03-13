@extends('mails.layout')

@section('content')
Hi {{ $user->name }} <br>

Anda akan melakukan reset password, masukan OTP berikut pada aplikasi: {{ $otp }}.<br>

Abaikan jika Anda tidak melakukan reset password.
@endsection
