@extends('mails.layout')

@section('content')
Hi {{ $order->seller->name }},<br>
<br>
Ada order baru dari {{ $order->user->name }}, silahkan cek aplikasi untuk melihat detail order.
<br><br>
@foreach ($order->items as $item)
{{ $item->product->name }} x {{ $item->qty }}<br>
@endforeach

@endsection