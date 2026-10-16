<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{User,Fakultas,ProgramStudi,Dosen,Mahasiswa,MataKuliah,Kurikulum,SemesterAkademik,Kelas,Krs,Nilai,Tagihan,Pengumuman,Skripsi};
use App\Services\AcademicService;
class DemoSeeder extends Seeder {
 public function run(): void {
  if(app()->environment('production'))throw new \RuntimeException('DemoSeeder tidak boleh dijalankan pada production.');
  if(User::where('email','admin@siakad.test')->exists())return;
  DB::transaction(function(){
   $f=Fakultas::create(['nama'=>'Fakultas Ilmu Komputer']);
   $p=ProgramStudi::create(['nama_prodi'=>'Sistem Informasi','fakultas_id'=>$f->id,'jenjang'=>'S1','sks_lulus'=>144,'batas_semester'=>14]);
   $password=config('academic.demo_password');
   $make=fn($role,$name)=>User::create(['name'=>$name,'email'=>$role.'@siakad.test','password'=>$password,'role'=>$role,'prodi_id'=>$role==='kaprodi'?$p->id:null]);
   $admin=$make('admin','Admin Akademik');$lecturer=$make('dosen','Dr. Budi Santoso, M.Kom.');$make('kaprodi','Ketua Program Studi');$make('keuangan','Petugas Keuangan');$student=$make('mahasiswa','Aditya Nugraha');
   $d=Dosen::create(['user_id'=>$lecturer->id,'nidn'=>'0312345678','nama'=>$lecturer->name,'jabatan'=>'Dosen Tetap']);
   $m=Mahasiswa::create(['user_id'=>$student->id,'nim'=>'2024010001','nama'=>$student->name,'prodi_id'=>$p->id,'angkatan'=>2024,'semester'=>5,'dosen_pa_id'=>$d->id,'status'=>'aktif']);
   $other=User::create(['name'=>'Nadia Putri','email'=>'nadia@siakad.test','password'=>$password,'role'=>'mahasiswa']);
   $n=Mahasiswa::create(['user_id'=>$other->id,'nim'=>'2024010002','nama'=>$other->name,'prodi_id'=>$p->id,'angkatan'=>2024,'semester'=>5,'dosen_pa_id'=>$d->id,'status'=>'aktif']);
   $terms=[];
   foreach([[2024,'ganjil'],[2024,'genap'],[2025,'ganjil'],[2025,'genap'],[2026,'ganjil']] as $i=>[$year,$period]){
    $start=$period==='ganjil'?"$year-08-01":($year+1).'-02-01';
    $terms[$i+1]=SemesterAkademik::create(['nama'=>$year.'/'.($year+1).' '.ucfirst($period),'tahun_akademik'=>$year.'/'.($year+1),'periode'=>$period,'krs_mulai'=>$i===4?today()->subDays(7)->toDateString():$start,'krs_selesai'=>$i===4?today()->addDays(30)->toDateString():date('Y-m-d',strtotime($start.' +30 days')),'aktif'=>$i===4]);
   }
   $names=[
   1=>['Pendidikan Agama','Pancasila','Bahasa Indonesia','Matematika Diskrit','Pengantar Sistem Informasi','Algoritma dan Pemrograman'],
   2=>['Kewarganegaraan','Bahasa Inggris','Aljabar Linear','Struktur Data','Basis Data','Organisasi Komputer'],
   3=>['Statistika','Pemrograman Berorientasi Objek','Analisis Proses Bisnis','Sistem Operasi','Manajemen Basis Data','Interaksi Manusia dan Komputer'],
   4=>['Analisis dan Perancangan Sistem','Pemrograman Web','Jaringan Komputer','Riset Operasi','Manajemen Proyek','Kewirausahaan'],
   5=>['Rekayasa Perangkat Lunak','Kecerdasan Buatan','Keamanan Sistem Informasi','Metodologi Penelitian','Visualisasi Data','Pengembangan Aplikasi Mobile'],
   6=>['Arsitektur Enterprise','Audit Sistem Informasi','Data Mining','Sistem Pendukung Keputusan','Komputasi Awan','Bisnis Digital'],
   7=>['Tata Kelola TI','Integrasi Sistem','Kerja Praktik','Seminar Proposal','Analitik Bisnis','Manajemen Pengetahuan'],
   8=>['Skripsi','Etika Profesi','Proyek Sistem Informasi','Topik Khusus SI','Transformasi Digital']];
   $courses=[];$a=app(AcademicService::class);
   foreach($names as $sem=>$list)foreach($list as $i=>$name){
    $mk=MataKuliah::create(['kode'=>'SI'.$sem.str_pad((string)($i+1),2,'0',STR_PAD_LEFT),'nama'=>$name,'sks'=>$sem===8 && $i===0?6:3,'semester'=>$sem,'jenis'=>$sem>=5 && $i>=4?'pilihan':'wajib']);$courses[$sem][$i]=$mk;
    Kurikulum::create(['prodi_id'=>$p->id,'mata_kuliah_id'=>$mk->id,'semester'=>$sem,'status_wajib'=>$mk->jenis==='wajib']);
    if($sem>=3 && isset($courses[$sem-2][$i]))$mk->prasyarat()->attach($courses[$sem-2][$i]->id);
   }
   foreach([1,2,3,4,5] as $sem){
    foreach($courses[$sem] as $i=>$mk){$c=Kelas::create(['semester_akademik_id'=>$terms[$sem]->id,'mata_kuliah_id'=>$mk->id,'dosen_id'=>$d->id,'nama'=>'A','hari'=>['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$i],'mulai'=>'09:00','selesai'=>'11:00','ruang'=>'R-'.(301+$i),'kapasitas'=>40]);
     if($sem<=4){
      $k=Krs::firstOrCreate(['mahasiswa_id'=>$m->id,'semester_akademik_id'=>$terms[$sem]->id],['semester'=>$sem,'tahun_akademik'=>$terms[$sem]->tahun_akademik,'status'=>'approved','approved_by'=>$lecturer->id,'approved_at'=>now()]);$k->details()->create(['kelas_id'=>$c->id,'mata_kuliah_id'=>$mk->id]);
      $score=[90,87,82,88,77,86][$i];Nilai::create(['mahasiswa_id'=>$m->id,'mata_kuliah_id'=>$mk->id,'semester_akademik_id'=>$terms[$sem]->id,'kelas_id'=>$c->id,'nilai'=>$score,'published'=>true,...$a->grade($score)]);
     }
    }
   }
   // Retake/late-start offerings so a student without grades can still plan.
   foreach($courses[1] as $i=>$mk)Kelas::create(['semester_akademik_id'=>$terms[5]->id,'mata_kuliah_id'=>$mk->id,'dosen_id'=>$d->id,'nama'=>'A','hari'=>['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$i],'mulai'=>'13:00','selesai'=>'15:00','ruang'=>'R-'.(401+$i),'kapasitas'=>40]);
   Tagihan::create(['mahasiswa_id'=>$m->id,'semester_akademik_id'=>$terms[5]->id,'keterangan'=>'UKT Semester Ganjil','jumlah'=>7500000,'jatuh_tempo'=>today()->addDays(20)]);
   Pengumuman::create(['judul'=>'Pengisian KRS semester aktif','isi'=>'Susun roadmap melalui Smart Study Planner, ajukan ke Dosen PA, lalu isi KRS sesuai rencana yang disetujui. Periksa jadwal dan batas SKS sebelum mengajukan.','mulai'=>today()->subDays(7),'selesai'=>today()->addDays(30)]);
   Skripsi::create(['mahasiswa_id'=>$m->id,'dosen_id'=>$d->id,'judul'=>'Perancangan Sistem Rekomendasi Rencana Studi Mahasiswa','status'=>'pengajuan','progres'=>5]);
  });
 }
}
