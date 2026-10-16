@extends('layouts.app')
@section('title',$spec['title'])
@section('content')<div class="page-heading"><div><h1>{{ $readonly?'Detail':($record->exists?'Edit':'Tambah') }} {{ $spec['title'] }}</h1><p>Lengkapi data sesuai administrasi kampus.</p></div><a class="btn secondary" href="{{ route('master.index',$entity) }}">Kembali</a></div><section class="panel panel-body"><form method="post" action="{{ $record->exists?route('master.update',[$entity,$record->id]):route('master.store',$entity) }}">@csrf @if($record->exists)@method('PUT') @endif<fieldset {{ $readonly?'disabled':'' }}><div class="form-grid">@foreach($spec['fields'] as $key=>$f)
@php
$value=old($key,$f[1]==='password'?'':$record->$key);
if(is_bool($value))$value=(int)$value;
if($value instanceof \DateTimeInterface)$value=$value->format('Y-m-d');
if($f[1]==='time' && $value)$value=substr($value,0,5);
if($f[1]==='multiple')$value=old($key,$record->exists?$record->prasyarat->pluck('id')->all():[]);
@endphp
@if(!($readonly && $f[1]==='password'))<label>{{ $f[0] }}@if($f[1]==='select' || $f[1]==='multiple')<select name="{{ $key }}{{ $f[1]==='multiple'?'[]':'' }}" {{ $f[1]==='multiple'?'multiple size=6':'' }} {{ str_starts_with($f[2],'required')?'required':'' }}>@if($f[1]!=='multiple')<option value="">Pilih…</option> @endif @foreach($options[$key] as $id=>$label)<option value="{{ $id }}" @selected($f[1]==='multiple'?in_array($id,$value??[]):(string)$value===(string)$id)>{{ $label }}</option> @endforeach</select>@elseif($f[1]==='textarea')<textarea name="{{ $key }}" rows="5" required>{{ $value }}</textarea> @else<input type="{{ $f[1] }}" name="{{ $key }}" value="{{ $value }}" {{ str_starts_with($f[2],'required')?'required':'' }} @if($f[1]==='password') autocomplete="new-password" minlength="12" @endif> @endif @error($key)<small class="field-error">{{ $message }}</small>@enderror</label> @endif @endforeach</div></fieldset>@unless($readonly)<div class="form-footer"><button class="btn">Simpan data</button><a href="{{ route('master.index',$entity) }}" class="btn secondary">Batal</a></div> @endunless</form></section> @endsection
