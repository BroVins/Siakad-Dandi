@php
$labels=['draft'=>'Draft','submitted'=>'Menunggu review','approved'=>'Disetujui','rejected'=>'Perlu revisi','aktif'=>'Aktif','lulus'=>'Lulus','cuti'=>'Cuti','nonaktif'=>'Nonaktif','pengajuan'=>'Pengajuan','proposal'=>'Proposal','penelitian'=>'Penelitian','sidang'=>'Sidang','selesai'=>'Selesai'];
@endphp
<span class="badge badge-{{ $status }}">{{ $labels[$status] ?? ucfirst($status) }}</span>
