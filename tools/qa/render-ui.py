from playwright.sync_api import sync_playwright,expect
from bs4 import BeautifulSoup
from pathlib import Path
import json,os
ROOT=Path(__file__).resolve().parents[2]; OUT=Path(os.environ.get('SIAKAD_QA_DIR','_qa-output'))/'browser';results=[];errors=[]

def content(name):
 soup=BeautifulSoup((OUT/(name+'.html')).read_text(),'html.parser')
 for tag in soup.select('link,script'):tag.decompose()
 style=soup.new_tag('style');style.string=(ROOT/'public/assets/tailwind.css').read_text()+'\n'+(ROOT/'public/assets/app.css').read_text();soup.head.append(style)
 return str(soup)

def load(page,name):
 page.set_content(content(name),wait_until='domcontentloaded')
 page.add_script_tag(content='(() => {\n'+(ROOT/'public/assets/app.js').read_text()+'\n})();')

def record(name,fn):
 try:fn();results.append({'name':name,'passed':True});print('PASS',name,flush=True)
 except Exception as e:results.append({'name':name,'passed':False,'error':str(e)});print('FAIL',name,str(e),flush=True)

with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_EXECUTABLE'),headless=True,args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':1440,'height':960});page.on('pageerror',lambda e:errors.append(str(e)))
 def desktop():
  load(page,'krs-final');assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth');page.screenshot(path=str(OUT/'krs-final-desktop.png'),full_page=True)
 record('Render dokumen KRS final desktop 1440px',desktop)
 def counter():
  load(page,'krs-draft');box=page.locator('[name="kelas_ids[]"]:not([disabled])').first;box.check();expect(page.locator('#sks-counter')).to_have_text('3 / 24 SKS');box.uncheck();expect(page.locator('#sks-counter')).to_have_text('0 / 24 SKS')
 record('JavaScript penghitung SKS mengikuti checkbox pilih dan batal',counter)
 def navigation():
  page.set_viewport_size({'width':390,'height':844});load(page,'dashboard');expect(page.locator('#main-nav')).not_to_be_visible();page.get_by_role('button',name='Buka navigasi').click();expect(page.locator('#main-nav')).to_be_visible();expect(page.get_by_role('button',name='Buka navigasi')).to_have_attribute('aria-expanded','true');page.locator('summary').filter(has_text='Akademik').click();expect(page.get_by_role('link',name='Kartu Rencana Studi',exact=True)).to_be_visible();page.keyboard.press('Escape');expect(page.get_by_role('link',name='Kartu Rencana Studi',exact=True)).not_to_be_visible();page.get_by_role('button',name='Buka navigasi').click();expect(page.locator('#main-nav')).not_to_be_visible()
 record('Navigasi mobile, menu akademik, Escape, dan atribut aksesibilitas',navigation)
 def mobile_krs():
  load(page,'krs-draft');assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'page overflow';page.screenshot(path=str(OUT/'krs-mobile.png'),full_page=True)
 record('Render KRS mobile 390px tanpa overflow halaman',mobile_krs)
 def mobile_planner():
  load(page,'planner');assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'page overflow';page.screenshot(path=str(OUT/'planner-mobile.png'),full_page=True)
 record('Render planner mobile 390px tanpa overflow halaman',mobile_planner)
 def confirmation():
  page.set_viewport_size({'width':1440,'height':960});load(page,'master');row=page.locator('tr').filter(has_text='Fakultas QA HTTP Revisi');row.get_by_role('button',name='Hapus',exact=True).click();expect(page.locator('#confirm-dialog')).to_be_visible();page.locator('#confirm-dialog').get_by_role('button',name='Batal',exact=True).click();expect(page.locator('#confirm-dialog')).not_to_be_visible();expect(row).to_have_count(1)
 record('Dialog hapus hanya terbuka saat diminta dan dapat dibatalkan',confirmation)
 def alert():
  load(page,'master');button=page.locator('[data-dismiss]');assert button.count()>0;button.click();expect(page.locator('.alert.success')).to_have_count(0)
 record('Pemberitahuan sukses dapat ditutup',alert)
 def printing():
  load(page,'krs-final');page.evaluate('window.__printed=false; window.print=()=>{window.__printed=true}');page.locator('[data-print]').click();assert page.evaluate('window.__printed')
 record('Tombol cetak memanggil API print browser (tanpa membuka dialog sistem)',printing)
 def duplicate_submit():
  load(page,'krs-draft')
  result=page.evaluate('''() => {const f=document.querySelector('form[data-krs-limit]');const b=f.querySelector('button[value="submit"]'); const first=f.dispatchEvent(new SubmitEvent('submit',{bubbles:true,cancelable:true,submitter:b})); const second=f.dispatchEvent(new SubmitEvent('submit',{bubbles:true,cancelable:true,submitter:b})); return {first,second,disabled:b.disabled,value:f.querySelector('input[type="hidden"][name="action"]')?.value};}''')
  assert result=={'first':True,'second':False,'disabled':True,'value':'submit'},result
 record('Penjagaan submit ganda mempertahankan action tombol yang diklik',duplicate_submit)
 def no_js_error():assert not errors,errors
 record('Tidak ada exception JavaScript selama uji DOM terpisah',no_js_error)
 browser.close()
(OUT.parent/'ui-results.json').write_text(json.dumps({'runtime':'Chromium via Playwright set_content on actual server-rendered HTML; embedded local CSS/JS; browser navigation blocked by policy. Not a live browser E2E test.','results':results,'javascript_errors':errors},indent=2,ensure_ascii=False))
print('UI checks',len(results),'failures',sum(not r['passed'] for r in results))
