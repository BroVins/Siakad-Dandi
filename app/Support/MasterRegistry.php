<?php
namespace App\Support;
use App\Models\{User,Fakultas,ProgramStudi,Dosen,Mahasiswa,MataKuliah,Kurikulum,SemesterAkademik,Kelas,Pengumuman};
class MasterRegistry {
 public static function all(): array {
  // name => [label, input type, validation, optional lookup model/column or enum]
  return [
   'fakultas'=>['title'=>'Fakultas','model'=>Fakultas::class,'search'=>['nama'],'fields'=>['nama'=>['Nama fakultas','text','required|string|max:150']]],
   'prodi'=>['title'=>'Program Studi','model'=>ProgramStudi::class,'search'=>['nama_prodi'],'fields'=>['nama_prodi'=>['Nama prodi','text','required|string|max:150'],'fakultas_id'=>['Fakultas','select','required|exists:fakultas,id',[Fakultas::class,'nama']],'jenjang'=>['Jenjang','select','required|in:D3,D4,S1,S2,S3',['D3','D4','S1','S2','S3']],'sks_lulus'=>['Syarat SKS lulus','number','required|integer|min:1|max:250'],'batas_semester'=>['Batas masa studi (semester)','number','required|integer|min:2|max:20']]],
   'users'=>['title'=>'Pengguna','model'=>User::class,'search'=>['name','email'],'fields'=>['name'=>['Nama','text','required|string|max:150'],'email'=>['Email','email','required|email|max:150'],'password'=>['Kata sandi (kosongkan saat edit untuk mempertahankan)','password','nullable|string|min:12'],'role'=>['Peran','select','required|in:admin,mahasiswa,dosen,kaprodi,keuangan',['admin','mahasiswa','dosen','kaprodi','keuangan']],'prodi_id'=>['Prodi (wajib untuk Kaprodi)','select','nullable|exists:program_studi,id',[ProgramStudi::class,'nama_prodi']]]],
   'dosen'=>['title'=>'Dosen','model'=>Dosen::class,'search'=>['nidn','nama'],'fields'=>['user_id'=>['Akun dosen','select','required|exists:users,id',[User::class,'email','role','dosen']],'nidn'=>['NIDN','text','required|string|max:30'],'nama'=>['Nama dosen','text','required|string|max:150'],'jabatan'=>['Jabatan','text','nullable|string|max:100']]],
   'mahasiswa'=>['title'=>'Mahasiswa','model'=>Mahasiswa::class,'search'=>['nim','nama'],'fields'=>['user_id'=>['Akun mahasiswa','select','required|exists:users,id',[User::class,'email','role','mahasiswa']],'nim'=>['NIM','text','required|string|max:30'],'nama'=>['Nama mahasiswa','text','required|string|max:150'],'prodi_id'=>['Program studi','select','required|exists:program_studi,id',[ProgramStudi::class,'nama_prodi']],'angkatan'=>['Angkatan','number','required|integer|min:1990|max:2100'],'semester'=>['Semester aktif','number','required|integer|min:1|max:20'],'dosen_pa_id'=>['Dosen PA','select','nullable|exists:dosen,id',[Dosen::class,'nama']],'status'=>['Status','select','required|in:aktif,cuti,lulus,nonaktif',['aktif','cuti','lulus','nonaktif']]]],
   'mata-kuliah'=>['title'=>'Mata Kuliah','model'=>MataKuliah::class,'search'=>['kode','nama'],'fields'=>['kode'=>['Kode MK','text','required|string|max:20'],'nama'=>['Nama mata kuliah','text','required|string|max:150'],'sks'=>['SKS','number','required|integer|min:1|max:6'],'semester'=>['Semester anjuran','number','required|integer|min:1|max:20'],'jenis'=>['Jenis','select','required|in:wajib,pilihan',['wajib','pilihan']],'prasyarat_ids'=>['Prasyarat (pilih beberapa dengan Ctrl/Cmd)','multiple','nullable|array',[MataKuliah::class,'nama']]]],
   'kurikulum'=>['title'=>'Kurikulum','model'=>Kurikulum::class,'search'=>[],'fields'=>['prodi_id'=>['Program studi','select','required|exists:program_studi,id',[ProgramStudi::class,'nama_prodi']],'mata_kuliah_id'=>['Mata kuliah','select','required|exists:mata_kuliah,id',[MataKuliah::class,'nama']],'semester'=>['Semester penawaran (ganjil/genap mengikuti paritas)','number','required|integer|min:1|max:20'],'status_wajib'=>['Wajib dalam kurikulum','select','required|boolean',['Tidak','Ya']]]],
   'semester'=>['title'=>'Semester Akademik','model'=>SemesterAkademik::class,'search'=>['nama','tahun_akademik'],'fields'=>['nama'=>['Nama semester','text','required|string|max:100'],'tahun_akademik'=>['Tahun akademik, contoh 2026/2027','text','required|regex:/^\d{4}\/\d{4}$/'],'periode'=>['Periode','select','required|in:ganjil,genap',['ganjil','genap']],'krs_mulai'=>['Pembukaan KRS','date','required|date'],'krs_selesai'=>['Penutupan KRS','date','required|date|after_or_equal:krs_mulai'],'aktif'=>['Semester aktif','select','required|boolean',['Tidak','Ya']]]],
   'kelas'=>['title'=>'Kelas & Jadwal','model'=>Kelas::class,'search'=>['nama','ruang'],'fields'=>['semester_akademik_id'=>['Semester akademik','select','required|exists:semester_akademik,id',[SemesterAkademik::class,'nama']],'mata_kuliah_id'=>['Mata kuliah','select','required|exists:mata_kuliah,id',[MataKuliah::class,'nama']],'dosen_id'=>['Dosen pengampu','select','required|exists:dosen,id',[Dosen::class,'nama']],'nama'=>['Kelas','text','required|string|max:30'],'hari'=>['Hari','select','required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']],'mulai'=>['Jam mulai','time','required|date_format:H:i'],'selesai'=>['Jam selesai','time','required|date_format:H:i|after:mulai'],'ruang'=>['Ruang','text','required|string|max:40'],'kapasitas'=>['Kapasitas','number','required|integer|min:1|max:500']]],
   'pengumuman'=>['title'=>'Pengumuman','model'=>Pengumuman::class,'search'=>['judul','isi'],'fields'=>['judul'=>['Judul','text','required|string|max:150'],'isi'=>['Isi','textarea','required|string|max:5000'],'mulai'=>['Mulai tampil','date','required|date'],'selesai'=>['Akhir tampil','date','required|date|after_or_equal:mulai']]],
  ];
 }
 public static function get(string $key): array { $all=self::all();abort_unless(isset($all[$key]),404);return $all[$key]; }
 public static function options(array $spec): array {
  $out=[];
  foreach($spec['fields'] as $key=>$f) if(isset($f[3])){
   $s=$f[3];
   if(isset($s[0]) && class_exists($s[0])) { $q=$s[0]::query();if(isset($s[2]))$q->where($s[2],$s[3]);$out[$key]=$q->orderBy($s[1])->pluck($s[1],'id')->all(); }
   else $out[$key]=in_array($key,['aktif','status_wajib'])?$s:array_combine($s,$s);
  }
  return $out;
 }
}
