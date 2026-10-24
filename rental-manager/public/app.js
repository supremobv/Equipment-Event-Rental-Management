document.querySelector('[data-toggle-menu]')?.addEventListener('click',()=>document.querySelector('#sidebar').classList.toggle('open'));
document.querySelectorAll('[data-dismiss]').forEach(button=>button.addEventListener('click',()=>button.closest('.alert').remove()));
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
const builder=document.querySelector('[data-booking-builder]');
if(builder){
 const list=builder.querySelector('[data-items]'),template=document.querySelector('#item-template');let count=Number(builder.dataset.nextIndex||0);
 const estimate=()=>{const start=new Date(builder.querySelector('[name=starts_on]').value+'T00:00:00Z'),end=new Date(builder.querySelector('[name=ends_on]').value+'T00:00:00Z');const days=Math.max(1,Math.round((end-start)/86400000)+1)||1;let total=0;list.querySelectorAll('.item-row').forEach(row=>{const select=row.querySelector('select'),qty=Number(row.querySelector('input').value)||0;total+=Number(select.selectedOptions[0]?.dataset.rate||0)*qty*days});const el=builder.querySelector('[data-estimate]');if(el)el.textContent=new Intl.NumberFormat('en',{minimumFractionDigits:2,maximumFractionDigits:2}).format(total/100);const dayLabel=builder.querySelector('[data-days]');if(dayLabel)dayLabel.textContent=days;};
 builder.querySelector('[data-add-item]').addEventListener('click',()=>{const fragment=template.content.cloneNode(true);fragment.querySelector('select').name=`items[${count}][equipment_id]`;fragment.querySelector('input').name=`items[${count}][quantity]`;count++;list.appendChild(fragment);estimate()});
 list.addEventListener('click',e=>{if(e.target.closest('[data-remove-item]')){e.target.closest('.item-row').remove();estimate()}});builder.addEventListener('input',estimate);builder.addEventListener('change',estimate);estimate();
}
document.querySelectorAll('form[method="post"]').forEach(form=>form.addEventListener('submit',event=>{if(event.defaultPrevented)return;const button=event.submitter;if(button){setTimeout(()=>{button.disabled=true},0);setTimeout(()=>{button.disabled=false},10000)}}));
