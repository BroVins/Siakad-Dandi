@extends('layouts.app')
@section('title','Tagihan & Pembayaran')
@section('content')
<div class="page-heading"><div><h1>Tagihan & Pembayaran</h1><p>Tagihan, saldo, dan pembayaran manual yang dicatat petugas keuangan.</p></div></div>
@php($canManage=in_array(auth()->user()->role,['admin','keuangan']))
@if($canManage)
<details class="panel panel-body" @if(old('form_scope')==='bill') open @endif>
<summary>Buat tagihan mahasiswa</summary>
<form method="post" action="{{ route('finance.bill') }}">
@csrf
<input type="hidden" name="form_scope" value="bill">
<input type="hidden" name="request_key" value="{{ old('form_scope')==='bill'?old('request_key', (string) \Illuminate\Support\Str::uuid()):(string) \Illuminate\Support\Str::uuid() }}">
@php($billOld=fn($key,$default='')=>old('form_scope')==='bill'?old($key,$default):$default)
<div class="form-grid">
<label>Mahasiswa<select name="mahasiswa_id" required><option value="">Pilih mahasiswa</option>
@foreach($students as $m)<option value="{{ $m->id }}" @selected((string)$billOld('mahasiswa_id')===(string)$m->id)>{{ $m->nim }} · {{ $m->nama }}</option> @endforeach
</select></label>
<label>Semester<select name="semester_akademik_id" required><option value="">Pilih semester</option>
@foreach($terms as $t)<option value="{{ $t->id }}" @selected((string)$billOld('semester_akademik_id', $terms->firstWhere('aktif',true)?->id)===(string)$t->id)>{{ $t->nama }}</option> @endforeach
</select></label>
<label>Keterangan<input name="keterangan" required maxlength="200" value="{{ $billOld('keterangan') }}" placeholder="UKT / SPP semester"></label>
<label>Jumlah (Rp)<input type="number" name="jumlah" min="0.01" max="99999999999.99" step="0.01" required value="{{ $billOld('jumlah') }}"></label>
<label>Jatuh tempo<input type="date" name="jatuh_tempo" required value="{{ $billOld('jatuh_tempo') }}"></label>
</div><div class="form-footer"><button class="btn">Buat tagihan</button></div>
</form></details>
@endif
@forelse($rows as $t)
@php($paid=$t->paidCents())
@php($left=$t->remainingCents())
<section class="panel"><header class="panel-heading"><div><h2>{{ $t->keterangan }}</h2>
<p class="muted small-text">{{ $t->mahasiswa->nama }} · {{ $t->mahasiswa->nim }} · {{ $t->term->nama }}</p></div>
<span class="badge {{ $left===0?'approved':'submitted' }}">{{ $left===0?'Lunas':'Belum lunas' }}</span></header>
<div class="panel-body"><div class="finance-summary">
<div><small>Total tagihan</small><strong>Rp {{ \App\Support\Money::display(\App\Support\Money::cents($t->jumlah)) }}</strong></div>
<div><small>Sudah dibayar</small><strong>Rp {{ \App\Support\Money::display($paid) }}</strong></div>
<div><small>Sisa tagihan</small><strong>Rp {{ \App\Support\Money::display($left) }}</strong></div>
<div><small>Jatuh tempo</small><strong>{{ $t->jatuh_tempo }}</strong></div></div>
<div class="list">
@forelse($t->pembayaran as $p)
<a href="{{ route('finance.receipt',$p) }}"><span>{{ $p->tanggal }} · {{ $p->referensi }}</span><strong>Rp {{ \App\Support\Money::display(\App\Support\Money::cents($p->jumlah)) }} · Kuitansi</strong></a>
@empty<p class="muted">Belum ada pembayaran.</p> @endforelse
</div>
@if($left>0 && $canManage)
@php($scope='pay-'.$t->id)
@php($payOld=fn($key,$default='')=>old('form_scope')===$scope?old($key,$default):$default)
<details @if(old('form_scope')===$scope) open @endif><summary>Catat pembayaran manual</summary>
<form method="post" action="{{ route('finance.pay',$t) }}">@csrf
<input type="hidden" name="form_scope" value="{{ $scope }}">
<div class="form-grid">
<label>Nominal (Rp)<input type="number" name="jumlah" min="0.01" max="{{ \App\Support\Money::decimal($left) }}" step="0.01" required value="{{ $payOld('jumlah') }}"></label>
<label>Tanggal bayar<input type="date" name="tanggal" max="{{ date('Y-m-d') }}" required value="{{ $payOld('tanggal', date('Y-m-d')) }}"></label>
<label>Nomor referensi / kuitansi<input name="referensi" required maxlength="100" value="{{ $payOld('referensi') }}"></label>
</div><div class="form-footer"><button class="btn">Simpan pembayaran</button></div>
</form></details>
@endif
</div></section>
@empty<section class="panel empty">Belum ada tagihan.</section> @endforelse
<div class="pagination">{{ $rows->links() }}</div>
@endsection
