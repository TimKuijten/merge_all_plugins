(function(){
  const state = {page:1, search:'', client:'', process:'', stage:''};
  const tbody = document.getElementById('k-rows');
  const pager = document.getElementById('k-page');

  function statusStep(status){
    if(!status) return 1;
    status = status.toLowerCase();
    if(status.includes('reject')) return 'rejected';
    const step1 = ['identificado','contactado','lista larga','long list'];
    const step2 = ['entrevista','shortlist'];
    const step3 = ['oferta','placement','colocado'];
    if(step3.some(s=>status.includes(s))) return 3;
    if(step2.some(s=>status.includes(s))) return 2;
    return 1;
  }

  function renderRows(items){
    tbody.innerHTML='';
    items.forEach(item=>{
      const tr=document.createElement('tr');
      const cb=document.createElement('td');
      cb.className='checkbox';
      cb.innerHTML='<input type="checkbox" class="k-rowcheck" value="'+item.id+'">';
      const name=document.createElement('td');
      name.innerHTML='<a href="#" class="k-candidate" data-id="'+item.id+'">'+item.meta.first_name+' '+item.meta.last_name+'</a>';
      const intNo=document.createElement('td');
      intNo.textContent=item.meta.int_no||'';
      const stage=document.createElement('td');
      const step=statusStep(item.status);
      if(step==='rejected'){
        stage.innerHTML='<span class="chip chip--rejected">Rechazado</span>';
      }else{
        stage.innerHTML='<span class="k-progress">'
          +'<span class="k-step'+(step>=1?' is-done':'')+'"></span>'
          +'<span class="k-step'+(step>=2?' is-done':'')+(step===2?' is-current':'')+'"></span>'
          +'<span class="k-step'+(step>=3?' is-done':'')+(step===3?' is-current':'')+'"></span>'
          +'</span>';
      }
      const actions=document.createElement('td');
      actions.innerHTML='<button class="btn btn--ghost">Ver</button>';
      tr.append(cb,name,intNo,stage,actions);
      tbody.appendChild(tr);
    });
  }

  function fetchData(){
    const params=new URLSearchParams({
      action:'kvt_get_candidates',
      _ajax_nonce:KVT.nonce,
      search:state.search,
      client:state.client,
      process:state.process,
      stage:state.stage,
      page:state.page
    });
    fetch(KVT.ajaxurl,{method:'POST',body:params}).then(r=>r.json()).then(res=>{
      if(res.success){
        renderRows(res.data.items);
        pager.textContent=state.page+' / '+res.data.pages;
      }
    });
  }

  let to;
  const searchInput=document.getElementById('k-search');
  if(searchInput){
    searchInput.addEventListener('input',e=>{
      state.search=e.target.value;
      clearTimeout(to);
      to=setTimeout(()=>{state.page=1;fetchData();},300);
    });
  }

  document.getElementById('k-prev').addEventListener('click',()=>{
    if(state.page>1){state.page--;fetchData();}
  });
  document.getElementById('k-next').addEventListener('click',()=>{
    state.page++;fetchData();
  });

  ['k-filter-client','k-filter-process','k-filter-stage'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){
      el.addEventListener('change',e=>{
        state[id.replace('k-filter-','')] = e.target.value;
        state.page=1;fetchData();
      });
    }
  });

  const toggle=document.getElementById('k-toggle-activity');
  if(toggle){
    toggle.addEventListener('click',()=>{
      document.getElementById('k-sidebar').classList.toggle('is-open');
    });
  }

  fetchData();
})();
