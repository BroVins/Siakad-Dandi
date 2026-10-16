@extends('layouts.app')
@section('title', 'Semester akademik belum siap')
@section('content')
<section class="panel panel-body empty">
    <h1>Semester akademik belum siap</h1>
    <p>Admin perlu menetapkan tepat satu semester aktif sebelum jadwal, planner, dan KRS dapat digunakan.</p>
    <p>Data dan riwayat akademik Anda tetap tersimpan.</p>
    <div class="actions">
        @if(auth()->user()->role === 'admin')
            <a class="btn" href="{{ route('master.index', 'semester') }}">Atur semester akademik</a>
        @endif
        <a class="btn secondary" href="{{ route('dashboard') }}">Kembali ke beranda</a>
    </div>
</section>
@endsection
