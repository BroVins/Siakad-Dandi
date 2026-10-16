@extends('layouts.app')
@section('title','Tugas Akhir')
@section('content')
<div class="page-heading"><div><h1>Tugas Akhir</h1><p>Monitoring progres skripsi dan catatan bimbingan.</p></div></div>
@php($admin=auth()->user()->role==='admin')
@php($reviewer=in_array(auth()->user()->role,['admin','dosen']))
@php($formOld=fn($scope,$key,$default='')=>old('form_scope')===$scope?old($key,$default):$default)
@if($admin)
<details class="panel panel-body" @if(old('form_scope')==='thesis-create') open @endif>
<summary>Tetapkan topik dan pembimbing</summary>
<p class="muted">Daftar hanya memuat mahasiswa yang belum memiliki penugasan skripsi.</p>
<form method="post" action="{{ route('thesis.store') }}">@csrf
<input type="hidden" name="form_scope" value="thesis-create">
<div class="form-grid">
<label>Mahasiswa<select name="mahasiswa_id" required><option value="">Pilih mahasiswa</option>
@foreach($students as $m)<option value="{{ $m->id }}" @selected((string)$formOld('thesis-create','mahasiswa_id')===(string)$m->id)>{{ $m->nim }} · {{ $m->nama }}</option> @endforeach
</select></label>
<label>Dosen pembimbing<select name="dosen_id" required><option value="">Pilih pembimbing</option>
@foreach($lecturers as $d)<option value="{{ $d->id }}" @selected((string)$formOld('thesis-create','dosen_id')===(string)$d->id)>{{ $d->nama }}</option> @endforeach
</select></label>
<label class="full">Judul skripsi<input name="judul" required maxlength="255" value="{{ $formOld('thesis-create','judul') }}"></label>
</div><div class="form-footer"><button class="btn" @disabled($students->isEmpty() || $lecturers->isEmpty())>Simpan penugasan</button></div>
</form></details>
@endif
@forelse($rows as $s)
<section class="panel" id="thesis-{{ $s->id }}"><header class="panel-heading"><div><h2>{{ $s->judul }}</h2><p class="muted">{{ $s->mahasiswa->nama }} · {{ $s->mahasiswa->nim }}</p></div>@include('layouts.status',['status'=>$s->status])</header>
<div class="panel-body"><div class="split"><p>Pembimbing: <strong>{{ $s->dosen->nama }}</strong></p><strong>{{ $s->progres }}%</strong></div><div class="progress-track green"><span style="width:{{ $s->progres }}%"></span></div>
@if($reviewer)
@php($scope='thesis-progress-'.$s->id)
<details @if(old('form_scope')===$scope) open @endif><summary>Perbarui progres skripsi</summary>
<p class="muted">Progres 100% hanya untuk tahap selesai. Penugasan pembimbing hanya dapat diubah admin.</p>
<form method="post" action="{{ route('thesis.update',$s) }}">@csrf @method('PATCH')
<input type="hidden" name="form_scope" value="{{ $scope }}">
<div class="form-grid"><label class="full">Judul<input name="judul" value="{{ $formOld($scope,'judul',$s->judul) }}" required maxlength="255"></label>
@if($admin)
<label>Pembimbing<select name="dosen_id" required>
@foreach($lecturers as $d)<option value="{{ $d->id }}" @selected((string)$formOld($scope,'dosen_id',$s->dosen_id)===(string)$d->id)>{{ $d->nama }}</option> @endforeach
</select></label>
@endif
<label>Tahap<select name="status" required>
@foreach(['pengajuan','proposal','penelitian','sidang','selesai'] as $value)<option value="{{ $value }}" @selected($formOld($scope,'status',$s->status)===$value)>{{ ucfirst($value) }}</option> @endforeach
</select></label>
<label>Progres (%)<input type="number" name="progres" min="0" max="100" value="{{ $formOld($scope,'progres',$s->progres) }}" required></label>
</div><div class="form-footer"><button class="btn">Perbarui progres</button></div>
</form></details>
@endif
<h3 class="spaced">Catatan bimbingan</h3>
@forelse($s->bimbingan as $b)
<article class="guidance-note"><div class="split"><strong>{{ $b->user->name }}</strong><small>{{ $b->tanggal }}</small></div>
<p class="pre-line">{{ $b->catatan }}</p>
@if($b->umpan_balik)<p class="feedback"><strong>Umpan balik pembimbing:</strong> {{ $b->umpan_balik }}</p> @endif
@if($reviewer)
@php($scope='thesis-feedback-'.$b->id)
<form method="post" action="{{ route('thesis.feedback',[$s,$b]) }}" class="form-stack">@csrf @method('PATCH')
<input type="hidden" name="form_scope" value="{{ $scope }}">
<label>Umpan balik<textarea name="umpan_balik" required rows="2" maxlength="5000">{{ $formOld($scope,'umpan_balik',$b->umpan_balik) }}</textarea></label>
<button class="btn secondary small">Simpan umpan balik</button></form>
@endif
</article>
@empty<p class="muted">Belum ada catatan bimbingan.</p> @endforelse
@if(auth()->user()->role!=='kaprodi')
@php($scope='thesis-note-'.$s->id)
<form method="post" action="{{ route('thesis.note',$s) }}" class="form-stack spaced">@csrf
<input type="hidden" name="form_scope" value="{{ $scope }}">
<label>Tanggal bimbingan<input name="tanggal" type="date" required value="{{ $formOld($scope,'tanggal',date('Y-m-d')) }}" max="{{ date('Y-m-d') }}"></label>
<label>Catatan / perkembangan<textarea name="catatan" required rows="3" maxlength="5000">{{ $formOld($scope,'catatan') }}</textarea></label>
<button class="btn">Tambah catatan</button></form>
@endif
</div></section>
@empty<section class="panel empty">Belum ada penugasan skripsi. Hubungi admin akademik untuk penetapan topik dan pembimbing.</section> @endforelse
<div class="pagination">{{ $rows->links() }}</div>
@endsection
