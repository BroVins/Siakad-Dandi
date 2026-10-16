@extends('layouts.auth')
@section('title','Reset kata sandi')
@section('content')<h2>Lupa kata sandi?</h2><p class="muted">Masukkan email terdaftar untuk menerima tautan reset.</p><form class="form-stack" method="post" action="{{ route('password.email') }}">@csrf<label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label><button class="btn" type="submit">Kirim tautan reset</button><a href="{{ route('login') }}">Kembali ke halaman masuk</a></form> @endsection
