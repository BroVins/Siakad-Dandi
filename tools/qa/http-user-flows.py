import requests,sqlite3,json,traceback,re,subprocess,os
from bs4 import BeautifulSoup
from pathlib import Path
from datetime import date,timedelta
BASE=os.environ.get('SIAKAD_BASE_URL','http://127.0.0.1:8000').rstrip('/')
ROOT=Path(os.environ.get('SIAKAD_QA_DIR','_qa-output')); ROOT.mkdir(parents=True,exist_ok=True)
DATABASE=Path(os.environ.get('SIAKAD_TEST_DATABASE','database/database_qa.sqlite'))
if os.environ.get('SIAKAD_HTTP_QA')!='1' or not BASE.startswith(('http://127.0.0.1:','http://localhost:')):
 raise SystemExit('Only explicit local disposable QA is allowed. Set SIAKAD_HTTP_QA=1.')
OUT=ROOT/'browser';OUT.mkdir(exist_ok=True)
results=[];server_errors=[]

def query(sql):
 with sqlite3.connect(DATABASE) as c:return c.execute(sql).fetchall()

def get(session,path):
 r=session.get(BASE+path);check(r);return r

def check(r):
 if r.status_code>=500:server_errors.append({'url':r.url,'status':r.status_code})
 assert r.status_code==200,(r.status_code,r.url,r.text[:200])
 assert not BeautifulSoup(r.text,'html.parser').select('.alert.error'),BeautifulSoup(r.text,'html.parser').get_text(' ',strip=True)[-2000:]

def submit(session,response,action,values=None,button=None):
 soup=BeautifulSoup(response.text,'html.parser')
 form=next((f for f in soup.select('form') if (f.get('action') or '').endswith(action)),None)
 assert form is not None,('form not found',action)
 data={}
 for inp in form.select('input[name],textarea[name],select[name]'):
  name=inp['name']
  if inp.has_attr('disabled'):continue
  if inp.get('type') in ('checkbox','radio') and not inp.has_attr('checked'):continue
  if inp.name=='select':
   option=inp.select_one('option[selected]') or inp.select_one('option');val=option.get('value',option.get_text()) if option else ''
  elif inp.name=='textarea':val=inp.get_text()
  else:val=inp.get('value','')
  if name.endswith('[]'):data.setdefault(name,[]).append(val)
  else:data[name]=val
 data.update(values or {})
 if button:data[button[0]]=button[1]
 r=session.post(form['action'],data=data);check(r);return r

def login(role,password='DemoSiakad2026!'):
 s=requests.Session();r=get(s,'/login');r=submit(s,r,'/login',{'email':role+'@siakad.test','password':password});assert r.url==BASE+'/dashboard';return s,r

def record(name,fn):
 try:fn();results.append({'name':name,'passed':True});print('PASS',name,flush=True)
 except Exception as e:results.append({'name':name,'passed':False,'error':str(e)});print('FAIL',name,str(e),flush=True);raise

try:
 student,r=login('mahasiswa');record('Login melalui HTTP nyata, token CSRF, dan sesi cookie',lambda: check(r))
 def profile():
  r=get(student,'/biodata');submit(student,r,'/biodata',{'telepon':'081234567890','alamat':'Alamat uji HTTP'});r=get(student,'/biodata');assert BeautifulSoup(r.text,'html.parser').select_one('[name="telepon"]')['value']=='081234567890'
 record('Biodata kontak persisten setelah reload',profile)
 def planner():
  global roadmap_id,class_ids
  r=get(student,'/planner');r=submit(student,r,'/planner',{'target_lulus':'8'});assert 'Kelas rekomendasi' in r.text
  roadmap_id=query('select max(id) from roadmap')[0][0];class_ids=[str(x[0]) for x in query(f'select kelas_id from roadmap_detail where roadmap_id={roadmap_id} and semester_target=5 order by id')]
  submit(student,r,f'/planner/{roadmap_id}/submit');assert query(f'select status from roadmap where id={roadmap_id}')[0][0]=='submitted'
 record('Roadmap rekomendasi kelas diajukan ke PA',planner)
 teacher,_=login('dosen')
 def approve_roadmap():
  submit(teacher,get(teacher,f'/roadmap/{roadmap_id}'),f'/roadmap/{roadmap_id}/review',button=('status','approved'));assert query(f'select status from roadmap where id={roadmap_id}')[0][0]=='approved'
 record('PA membuka dan menyetujui roadmap',approve_roadmap)
 def krs():
  global krs_id
  r=get(student,'/krs');(OUT/'krs-draft.html').write_text(r.text)
  r=submit(student,r,'/krs',{'kelas_ids[]':class_ids},('action','draft'))
  assert len(BeautifulSoup(r.text,'html.parser').select('[name="kelas_ids[]"][checked]'))==6
  r=submit(student,r,'/krs',{'kelas_ids[]':class_ids},('action','submit'));krs_id=query("select max(id) from krs where status='submitted'")[0][0]
  r=submit(student,r,f'/krs/{krs_id}/withdraw');r=submit(student,r,'/krs',{'kelas_ids[]':class_ids},('action','submit'))
  assert query(f'select status from krs where id={krs_id}')[0][0]=='submitted'
 record('KRS simpan draft, ajukan, tarik, dan ajukan ulang memakai formulir HTML',krs)
 def approve_krs():
  submit(teacher,get(teacher,f'/krs/{krs_id}'),f'/krs/{krs_id}/review',button=('status','approved'));r=get(student,'/jadwal');assert len(BeautifulSoup(r.text,'html.parser').select('.schedule-item'))==6
  r=get(student,f'/krs/{krs_id}');assert 'Dokumen ini belum merupakan KRS final.' not in r.text;(OUT/'krs-final.html').write_text(r.text)
 record('KRS final mengisi jadwal mahasiswa dan dokumen cetak',approve_krs)
 student_id=query("select id from mahasiswa where user_id=(select id from users where email='mahasiswa@siakad.test')")[0][0]
 def teaching():
  path=f'/mengajar/{class_ids[0]}';r=get(teacher,path)
  r=submit(teacher,r,path+'/nilai',{f'nilai[{student_id}]':'86.50'},('publication','publish'))
  r=submit(teacher,r,path+'/nilai',{f'nilai[{student_id}]':'87.25'},('publication','keep'))
  r=submit(teacher,r,path+'/presensi',{f'status[{student_id}]':'hadir'})
  assert BeautifulSoup(r.text,'html.parser').select_one(f'select[name="status[{student_id}]"] option[selected]')['value']=='hadir'
  submit(teacher,r,path+'/presensi',{f'status[{student_id}]':'izin'})
  r=get(student,'/nilai');assert '87.25' in r.text and 'Izin' in r.text
 record('Nilai publikasi, edit tanpa unpublish, presensi edit, dan hasil pada mahasiswa',teaching)
 finance,_=login('keuangan')
 def payments():
  r=submit(finance,get(finance,'/pembayaran'),'/tagihan',{'mahasiswa_id':str(student_id),'keterangan':'Tagihan HTTP pecahan','jumlah':'100.50','jatuh_tempo':(date.today()+timedelta(days=30)).isoformat()})
  bill_id=query("select id from tagihan where keterangan='Tagihan HTTP pecahan'")[0][0]
  r=submit(finance,r,f'/tagihan/{bill_id}/bayar',{'jumlah':'100.50','referensi':'HTTP-10050'})
  r=get(student,'/pembayaran');soup=BeautifulSoup(r.text,'html.parser');link=next(x['href'] for x in soup.select('a[href]') if 'HTTP-10050' in x.get_text());r=student.get(link);check(r);assert '100,50' in r.text and 'HTTP-10050' in r.text
 record('Tagihan, pelunasan nominal pecahan, dan kuitansi mahasiswa',payments)
 def thesis():
  r=get(student,'/skripsi');soup=BeautifulSoup(r.text,'html.parser');path=next(f['action'].removeprefix(BASE) for f in soup.select('form') if f['action'].endswith('/bimbingan'))
  submit(student,r,path,{'catatan':'Bimbingan uji HTTP: metodologi.'});r=get(teacher,'/skripsi');soup=BeautifulSoup(r.text,'html.parser');note=next(n for n in soup.select('.guidance-note') if 'Bimbingan uji HTTP:' in n.get_text());path=note.select_one('form')['action'].removeprefix(BASE)
  submit(teacher,r,path,{'umpan_balik':'Umpan balik uji HTTP diterima.'});assert 'Umpan balik uji HTTP diterima.' in get(student,'/skripsi').text
 record('Catatan bimbingan dan umpan balik lintas peran',thesis)
 admin,_=login('admin')
 def master():
  r=submit(admin,get(admin,'/master/fakultas/create'),'/master/fakultas',{'nama':'Fakultas QA HTTP'});idx=query("select id from fakultas where nama='Fakultas QA HTTP'")[0][0]
  r=submit(admin,get(admin,f'/master/fakultas/{idx}/edit'),f'/master/fakultas/{idx}',{'nama':'Fakultas QA HTTP Revisi'});(OUT/'master.html').write_text(r.text)
  submit(admin,r,f'/master/fakultas/{idx}');assert query(f'select count(*) from fakultas where id={idx}')[0][0]==0
 record('Admin create, update, dan delete master melalui action formulir',master)
 def csrf():
  r=student.post(BASE+'/logout',data={});assert r.status_code==419,r.status_code;assert get(student,'/dashboard').url==BASE+'/dashboard'
 record('Perubahan tanpa token CSRF ditolak dan sesi tetap aktif',csrf)
 def cache():
  r=get(student,'/biodata');assert 'no-store' in r.headers['cache-control'];assert r.headers['x-frame-options']=='DENY'
 record('Header anti-cache privat dan anti-framing pada HTTP',cache)
 (OUT/'planner.html').write_text(get(student,'/planner').text);(OUT/'dashboard.html').write_text(get(student,'/dashboard').text)
 def password():
  r=submit(student,get(student,'/biodata'),'/biodata/password',{'current_password':'DemoSiakad2026!','password':'HttpNewPass2026!','password_confirmation':'HttpNewPass2026!'});assert r.url==BASE+'/biodata'
  r=submit(student,r,'/logout');assert r.url==BASE+'/login';login('mahasiswa','HttpNewPass2026!')
 record('Ganti kata sandi, sesi aktif bertahan, logout, lalu login dengan sandi baru',password)
 record('Tidak ada respons HTTP 5xx selama skenario',lambda: (_ for _ in ()).throw(AssertionError(server_errors)) if server_errors else None)
finally:
 (ROOT/'http-results.json').write_text(json.dumps({'runtime':'requests HTTP client; real Laravel web routes, CSRF and file sessions; '+os.environ.get('SIAKAD_QA_DB_RUNTIME','SQLite; verify the PDO runtime on the test server')+'; not browser end-to-end','results':results,'http_5xx':server_errors},indent=2,ensure_ascii=False))
print('HTTP checks',len(results),'failures',sum(not x['passed'] for x in results))
