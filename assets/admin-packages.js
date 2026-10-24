(() => {
  'use strict';
  const form=document.getElementById('packageBulkForm');
  if(!form)return;
  const body=document.getElementById('packageTableBody');
  const search=document.getElementById('packageSearchInput');
  const filter=document.getElementById('packageFilter');
  const filters=Array.from(document.querySelectorAll('.package-filters a'));
  const status=document.getElementById('packageSaveStatus');
  const save=document.getElementById('savePackages');
  const add=document.getElementById('addPackage');
  const error=document.getElementById('packageEditorError');
  const empty=document.getElementById('packageEmpty');
  const original=new WeakMap();
  let sequence=0,saving=false,saved=false;
  const rows=()=>Array.from(body.querySelectorAll('[data-package-key]'));
  const snapshot=row=>JSON.stringify(Array.from(row.querySelectorAll('[data-field]')).map(field=>[field.dataset.field,field.type==='checkbox'?field.checked:field.value]));
  rows().forEach(row=>original.set(row,snapshot(row)));
  const changed=row=>row.dataset.new==='true'||row.dataset.removed==='true'||snapshot(row)!==original.get(row);
  const update=()=>{
    const edits=rows().filter(changed);
    const removed=edits.filter(row=>row.dataset.removed==='true').length;
    save.disabled=saving||!edits.length;
    rows().forEach(row=>row.classList.toggle('is-edited',changed(row)));
    status.textContent=edits.length?edits.length+' package '+(edits.length===1?'change':'changes')+' ready to save'+(removed?' · '+removed+' marked for removal':'')+'.':'No unsaved changes.';
  };
  const applyFilter=(updateUrl=true)=>{
    if(saving)return;
    const term=search.value.trim().toLowerCase();
    let visible=0;
    for(const row of rows()){
      const name=row.querySelector('[data-field=package_name]').value.toLowerCase();
      const min=row.querySelector('[data-field=guest_min]').value;
      const max=row.querySelector('[data-field=guest_max]').value;
      const active=row.querySelector('[data-field=is_active]').checked;
      row.hidden=row.dataset.new!=='true'&&(!((name+' '+min+' to '+max+' guests').includes(term))||(filter.value==='active'&&!active)||(filter.value==='inactive'&&active));
      if(!row.hidden)visible++;
    }
    empty.hidden=visible>0;
    filters.forEach(link=>link.classList.toggle('on',link.dataset.filter===filter.value));
    if(updateUrl){
      const url=new URL(location.href);url.searchParams.set('filter',filter.value);
      if(search.value.trim())url.searchParams.set('q',search.value.trim());else url.searchParams.delete('q');
      history.replaceState(null,'',url);
    }
  };
  const validateRange=row=>{
    const min=row.querySelector('[data-field=guest_min]');
    const max=row.querySelector('[data-field=guest_max]');
    const message=min.value!==''&&max.value!==''&&Number(max.value)<Number(min.value)?'Maximum guests must be greater than or equal to minimum guests.':'';
    const previous=max.validity.customError?max.validationMessage:'';
    max.setCustomValidity(message);
    if(previous!==message)max.dispatchEvent(new Event('change',{bubbles:true}));
  };
  body.addEventListener('input',event=>{
    const row=event.target.closest('[data-package-key]');if(!row)return;
    validateRange(row);update();
  });
  body.addEventListener('change',update);
  body.addEventListener('click',event=>{
    const button=event.target.closest('[data-package-remove]');if(!button||saving)return;
    const row=button.closest('[data-package-key]');
    if(row.dataset.new==='true'){row.remove();applyFilter(false);update();return;}
    const remove=row.dataset.removed!=='true';row.dataset.removed=String(remove);
    row.querySelectorAll('input[name],textarea[name]').forEach(field=>field.disabled=remove);
    row.querySelector('[data-delete-marker]')?.remove();
    if(remove){
      const marker=document.createElement('input');marker.type='hidden';marker.name='deleted_packages['+row.dataset.packageKey+']';marker.value=row.querySelector('input[type=hidden]').value;marker.dataset.deleteMarker='true';marker.setAttribute('form','packageBulkForm');row.cells[0].appendChild(marker);
    }
    button.textContent=remove?'Undo':'Remove';
    button.setAttribute('aria-label',remove?'Undo package removal':'Remove package');
    row.classList.toggle('pending-delete',remove);update();
  });
  add.addEventListener('click',()=>{
    const key='new_'+Date.now()+'_'+(++sequence);
    const template=document.createElement('template');template.innerHTML=document.getElementById('newPackageRow').innerHTML.replaceAll('__KEY__',key);
    const row=template.content.querySelector('tr');body.insertBefore(row,empty);
    const maxOrder=rows().filter(item=>item!==row).reduce((max,item)=>Math.max(max,Number(item.querySelector('[data-field=display_order]').value)||0),0);
    row.querySelector('[data-field=display_order]').value=String(Math.min(maxOrder+1,2147483647));
    filter.value='all';search.value='';applyFilter();update();row.querySelector('[data-field=package_name]').focus();
  });
  document.getElementById('packageSearch').addEventListener('submit',event=>{event.preventDefault();applyFilter();});
  search.addEventListener('input',()=>applyFilter());
  document.getElementById('clearPackageSearch').addEventListener('click',()=>{search.value='';applyFilter();search.focus();});
  filters.forEach(link=>link.addEventListener('click',event=>{event.preventDefault();if(saving)return;filter.value=link.dataset.filter;applyFilter();}));
  form.addEventListener('invalid',()=>{filter.value='all';search.value='';applyFilter();},true);
  form.addEventListener('submit',async event=>{
    event.preventDefault();if(saving||!rows().some(changed))return;
    error.hidden=true;
    const data=new FormData(form);
    for(const row of rows())if(!changed(row))row.querySelectorAll('[name]').forEach(field=>data.delete(field.name));
    // Keep the completeness marker last, so PHP input truncation cannot save a partial table.
    data.delete('submission_complete');data.append('submission_complete','1');
    saving=true;save.disabled=true;add.disabled=true;save.textContent='Saving changes...';
    const controlStates=new Map(Array.from(form.elements).map(field=>[field,field.disabled]));
    controlStates.forEach((disabled,field)=>field.disabled=true);
    search.disabled=true;document.getElementById('clearPackageSearch').disabled=true;
    try{
      const response=await fetch(form.getAttribute('action'),{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('Your session may have expired. Sign in in another tab, then try saving again. Your changes are still here.');
      const result=await response.json();
      if(!response.ok||!result.ok)throw new Error(result.error||'Your changes could not be saved. Please try again.');
      saved=true;location.reload();
    }catch(failure){
      saving=false;controlStates.forEach((disabled,field)=>field.disabled=disabled);
      search.disabled=false;document.getElementById('clearPackageSearch').disabled=false;
      save.textContent='Save all changes';add.disabled=false;update();
      error.textContent=failure.message||'Your changes could not be saved. Check your connection and try again.';error.hidden=false;error.focus();
    }
  });
  window.addEventListener('beforeunload',event=>{if(!saved&&rows().some(changed)){event.preventDefault();event.returnValue='';}});
  window.addEventListener('popstate',()=>{const params=new URL(location.href).searchParams;filter.value=['active','inactive'].includes(params.get('filter'))?params.get('filter'):'all';search.value=params.get('q')||'';applyFilter(false);});
  applyFilter(false);update();
})();
