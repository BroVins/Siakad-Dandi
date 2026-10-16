@extends('layouts.app')
@section('title','Biodata')
@section('content')<div class="page-heading"><div><h1>Biodata</h1><p>Informasi akun dan identitas akademik.</p></div></div><section class="panel panel-body"><dl class="definition-grid"><dt>Nama akun</dt><dd>{{ $user->name }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd><dt>Peran</dt><dd>{{ ucfirst($user->role) }}</dd>@if($m)<dt>NIM</dt><dd>{{ $m->nim }}</dd><dt>Program studi</dt><dd>{{ $m->prodi->nama_prodi }}</dd><dt>Angkatan / semester</dt><dd>{{ $m->angkatan }} / {{ $m->semester }}</dd><dt>Dosen PA</dt><dd>{{ $m->dosenPA?->nama ?? 'Belum ditetapkan' }}</dd><dt>Status</dt><dd>@include('layouts.status',['status'=>$m->status])</dd> @endif</dl><p class="muted">Perubahan identitas akademik dan email dikelola oleh Admin Akademik.</p></section>@if($m)<section class="panel panel-body"><h2>Informasi kontak</h2><form class="form-stack" method="post" action="{{ route('profile.update') }}">@csrf @method('PATCH')<label>Telepon<input name="telepon" value="{{ old('telepon',$m->telepon) }}" maxlength="30"></label><label>Alamat<textarea name="alamat" maxlength="1000" rows="3">{{ old('alamat',$m->alamat) }}</textarea></label><button class="btn">Simpan kontak</button></form></section> @endif <section class="panel panel-body">
<h2>Keamanan akun</h2><p class="muted">Gunakan kata sandi unik, minimal 12 karakter dan maksimal 72 byte. Perubahan menonaktifkan sesi lain serta token API lama.</p>
<form method="post" action="{{ route('profile.password') }}" class="form-stack">
@csrf @method('PATCH')
<label>Kata sandi saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Kata sandi baru<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Konfirmasi kata sandi baru<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
<button class="btn">Ubah kata sandi</button>
</form></section>
@endsection
