<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Beranda') · {{ config('app.name') }}</title><link rel="stylesheet" href="{{ asset('assets/tailwind.css') }}"><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script src="{{ asset('assets/app.js') }}" defer></script></head>
<body><a class="skip-link" href="#main">Lewati navigasi</a>
@php($role=auth()->user()->role)
<header class="topbar no-print"><div class="topbar-inner"><a class="brand" href="{{ route('dashboard') }}">@include('layouts.icon',['name'=>'cap'])<span><strong>SIAKAD</strong><small>Portal Akademik</small></span></a><button type="button" class="nav-toggle" aria-label="Buka navigasi" aria-expanded="false" data-nav-toggle>@include('layouts.icon',['name'=>'menu'])</button>
<nav class="main-nav" id="main-nav" aria-label="Navigasi utama">
<a class="{{ request()->routeIs('dashboard')?'selected':'' }}" href="{{ route('dashboard') }}">@include('layouts.icon',['name'=>'home']) Beranda</a>
<a class="{{ request()->routeIs('profile')?'selected':'' }}" href="{{ route('profile') }}">@include('layouts.icon',['name'=>'user']) Biodata</a>
@if(in_array($role,['mahasiswa','admin','keuangan']))<a href="{{ route('finance.index') }}">@include('layouts.icon',['name'=>'card']) Pembayaran</a> @endif
@if($role!=='keuangan')<details class="nav-dropdown"><summary>@include('layouts.icon',['name'=>'book']) Akademik <span class="caret"></span></summary><div class="dropdown-panel">
@if($role==='mahasiswa')<a href="{{ route('planner.index') }}">Smart Study Planner</a><a href="{{ route('krs.index') }}">Kartu Rencana Studi</a><a href="{{ route('grades') }}">Nilai & Presensi</a> @endif
<a href="{{ route('schedule') }}">Jadwal Kuliah</a>@if(in_array($role,['admin','dosen','kaprodi']))<a href="{{ route('reports') }}">Laporan & Mahasiswa</a> @endif
@if($role==='dosen')<a href="{{ route('teaching.index') }}">Kelas Mengajar</a><a href="{{ route('dashboard') }}#review">Persetujuan PA</a> @endif
</div></details><a href="{{ route('thesis.index') }}">@include('layouts.icon',['name'=>'cap']) Tugas Akhir</a> @endif
@if($role==='admin')<details class="nav-dropdown"><summary>@include('layouts.icon',['name'=>'grid']) Master Data <span class="caret"></span></summary><div class="dropdown-panel">@foreach(\App\Support\MasterRegistry::all() as $key=>$spec)<a href="{{ route('master.index',$key) }}">{{ $spec['title'] }}</a> @endforeach</div></details> @endif
</nav><div class="nav-end"><span class="role-tag">{{ ucfirst($role) }}</span><form action="{{ route('logout') }}" method="post">@csrf<button class="logout" type="submit">@include('layouts.icon',['name'=>'logout']) Keluar</button></form></div></div></header>
<main id="main" class="page">
@if(session('success'))<div class="alert success no-print" role="status">@include('layouts.icon',['name'=>'check']) <span>{{ session('success') }}</span><button class="dismiss" aria-label="Tutup pemberitahuan" data-dismiss>×</button></div> @endif
@if($errors->any())<div class="alert error" role="alert"><div><strong>Data belum dapat disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li> @endforeach</ul></div></div> @endif
@yield('content')
</main><footer class="page-footer"><span>© {{ date('Y') }} SIAKAD · Portal Akademik</span><span>{{ $activeTerm?->nama ?? 'Semester belum diatur' }}</span></footer>
<dialog id="confirm-dialog"><form method="dialog"><h2>Hapus data ini?</h2><p>Data yang masih digunakan pada proses akademik tidak dapat dihapus.</p><div class="actions"><button value="cancel" class="btn secondary">Batal</button><button value="confirm" class="btn danger">Hapus data</button></div></form></dialog>
</body></html>
