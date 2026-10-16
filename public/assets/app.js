'use strict';
const navButton=document.querySelector('[data-nav-toggle]');
navButton?.addEventListener('click',()=>{const open=document.getElementById('main-nav').classList.toggle('is-open');navButton.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('[data-dismiss]').forEach(button=>button.addEventListener('click',()=>button.closest('.alert').remove()));
document.querySelectorAll('[data-toggle]').forEach(button=>button.addEventListener('click',()=>{const target=document.getElementById(button.dataset.toggle);target.hidden=!target.hidden;button.setAttribute('aria-expanded',String(!target.hidden));}));
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
const dialog=document.getElementById('confirm-dialog');let pendingForm=null;
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();pendingForm=form;dialog.returnValue='cancel';dialog.showModal();}));
dialog?.addEventListener('close',()=>{if(dialog.returnValue==='confirm' && pendingForm && !pendingForm.dataset.submitting){pendingForm.dataset.submitting='true';pendingForm.submit();}pendingForm=null;});
document.querySelectorAll('[data-krs-limit]').forEach(form=>{const counter=document.getElementById('sks-counter');const update=()=>{const total=[...form.querySelectorAll('[data-sks]:checked')].reduce((sum,item)=>sum+Number(item.dataset.sks),0);counter.textContent=`${total} / ${form.dataset.krsLimit} SKS`;counter.style.color=total>Number(form.dataset.krsLimit)?'#ba2442':'';};form.addEventListener('change',update);update();});
document.addEventListener('click',event=>{document.querySelectorAll('.nav-dropdown[open]').forEach(menu=>{if(!menu.contains(event.target))menu.removeAttribute('open');});});
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.nav-dropdown[open]').forEach(menu=>menu.removeAttribute('open'));});

// Preserve the clicked action while preventing a second POST from rapid clicks.
document.addEventListener('submit', event => {
  const form=event.target;
  if (!(form instanceof HTMLFormElement) || form.method.toLowerCase()!=='post' || form.hasAttribute('data-confirm') || event.defaultPrevented) return;
  if (form.dataset.submitting) {event.preventDefault();return;}
  const submitter=event.submitter;
  if (submitter?.name) {
    const action=document.createElement('input');
    action.type='hidden';action.name=submitter.name;action.value=submitter.value;action.dataset.submitAction='true';
    form.append(action);
  }
  form.dataset.submitting='true';form.setAttribute('aria-busy','true');
  form.querySelectorAll('button:not([type]),button[type="submit"],input[type="submit"]').forEach(button => {
    if (!button.disabled) {button.dataset.disabledBySubmit='true';button.disabled=true;}
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-submitting]').forEach(form => {
    delete form.dataset.submitting;form.removeAttribute('aria-busy');
    form.querySelectorAll('[data-disabled-by-submit]').forEach(button => {button.disabled=false;delete button.dataset.disabledBySubmit;});
    form.querySelectorAll('[data-submit-action]').forEach(input => input.remove());
  });
});
