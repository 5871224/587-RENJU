(function(){
  'use strict';

  const pageSize=500;
  const params=new URLSearchParams(location.search);
  const returnScrollKey='renju-rank:return-scroll:'+String(params.get('view')||'dashboard')+':'+String(params.get('field_search')||'')+':'+String(params.get('q')||'');
  let activeInlineEdit=null;

  const inlineStyle=document.createElement('style');
  inlineStyle.textContent='table.data tr.inline-editing{background:#fffbea}table.data tr.inline-editing:hover{background:#fffbea}.inline-edit-input{display:block;width:100%;min-width:90px;min-height:32px;padding:5px 7px;border:1px solid #94a3b8;border-radius:6px;background:#fff;color:inherit;font:inherit;line-height:1.35}.inline-edit-input:focus{outline:2px solid rgba(23,105,170,.2);border-color:#1769aa}.inline-edit-actions{display:flex;align-items:center;gap:7px;white-space:nowrap}.inline-edit-actions .btn{min-height:30px;padding:4px 9px;font-size:13px}';
  document.head.appendChild(inlineStyle);

  function saveReturnScroll(){
    try{sessionStorage.setItem(returnScrollKey,String(Math.max(0,window.scrollY||0)));}catch(e){}
  }

  function restoreReturnScroll(){
    if(params.has('edit')||params.has('new'))return;
    let raw='';
    try{raw=sessionStorage.getItem(returnScrollKey)||'';sessionStorage.removeItem(returnScrollKey);}catch(e){}
    const y=parseInt(raw,10);
    if(!Number.isFinite(y)||y<0)return;
    requestAnimationFrame(function(){requestAnimationFrame(function(){window.scrollTo(0,y);});});
  }

  function hiddenField(form,name,value){
    const input=document.createElement('input');
    input.type='hidden';
    input.name=name;
    input.value=value==null?'':String(value);
    form.appendChild(input);
  }

  function cancelInlineEdit(){
    if(!activeInlineEdit)return;
    const state=activeInlineEdit;
    activeInlineEdit=null;
    if(state.form&&state.form.parentNode)state.form.parentNode.removeChild(state.form);
    state.row.innerHTML=state.originalHtml;
    state.row.classList.remove('inline-editing');
    preservePageOnEditLinks();
  }

  function startInlineEdit(link){
    const row=link.closest('tr');
    const targetTable=link.closest('table.data');
    if(!row||!targetTable||!row.cells.length)return false;

    if(activeInlineEdit){
      if(activeInlineEdit.row===row)return true;
      cancelInlineEdit();
    }

    let editUrl;
    try{editUrl=new URL(link.href,location.href);}catch(e){return false;}
    const token=editUrl.searchParams.get('edit')||'';
    if(!token)return false;

    const headers=Array.from(targetTable.querySelectorAll('thead th'));
    const dataCells=Array.from(row.cells).slice(0,-1);
    if(!dataCells.length||headers.length!==dataCells.length+1)return false;

    const originalHtml=row.innerHTML;
    const form=document.createElement('form');
    form.method='post';
    form.action=location.pathname;
    form.id='inline-edit-'+Date.now()+'-'+Math.random().toString(36).slice(2);
    form.className='inline-edit-submit-form';
    form.hidden=true;
    hiddenField(form,'action','update');
    hiddenField(form,'view',params.get('view')||'');
    hiddenField(form,'q',params.get('q')||'');
    hiddenField(form,'field_search',params.get('field_search')||'');
    hiddenField(form,'original',token);
    form.addEventListener('submit',function(e){
      if(!confirm('確定要更新這筆資料嗎？')){e.preventDefault();return;}
      saveReturnScroll();
    });
    document.body.appendChild(form);

    dataCells.forEach(function(cell,index){
      const column=headers[index].textContent.trim();
      const value=cell.textContent;
      const input=document.createElement('input');
      input.type='text';
      input.className='inline-edit-input';
      input.name='field['+column+']';
      input.value=value;
      input.setAttribute('form',form.id);
      input.setAttribute('aria-label',column);
      input.autocomplete='off';
      cell.textContent='';
      cell.appendChild(input);
    });

    const actions=row.cells[row.cells.length-1];
    actions.className='actions-cell inline-edit-actions';
    actions.textContent='';

    const save=document.createElement('button');
    save.type='submit';
    save.className='btn primary';
    save.textContent='儲存';
    save.setAttribute('form',form.id);
    actions.appendChild(save);

    const cancel=document.createElement('button');
    cancel.type='button';
    cancel.className='btn inline-edit-cancel';
    cancel.textContent='取消';
    actions.appendChild(cancel);

    row.classList.add('inline-editing');
    activeInlineEdit={row:row,form:form,originalHtml:originalHtml};
    const first=row.querySelector('.inline-edit-input');
    if(first){first.focus();first.select();}
    return true;
  }

  document.addEventListener('click',function(e){
    const cancel=e.target.closest&&e.target.closest('.inline-edit-cancel');
    if(cancel){
      e.preventDefault();
      cancelInlineEdit();
      return;
    }

    const link=e.target.closest&&e.target.closest('a[href*="edit="]');
    if(link){
      e.preventDefault();
      startInlineEdit(link);
    }
  });
  document.querySelectorAll('form.delete-form').forEach(function(form){
    form.addEventListener('submit',function(e){if(!e.defaultPrevented)saveReturnScroll();});
  });

  const tables=Array.from(document.querySelectorAll('table.data'));
  const table=tables.find(function(t){
    const headers=t.querySelectorAll('thead th');
    return headers.length && headers[headers.length-1].textContent.trim()==='操作';
  });

  function normalizedText(cell){
    if(cell){
      const input=cell.querySelector('.inline-edit-input');
      if(input)return input.value.replace(/\u00a0/g,' ').trim();
    }
    return (cell ? cell.textContent : '').replace(/\u00a0/g,' ').trim();
  }

  function columnKind(rows,column){
    const values=rows.map(function(row){return normalizedText(row.cells[column]);}).filter(Boolean);
    if(values.length && values.every(function(v){return /^[-+]?\d+(?:\.\d+)?$/.test(v);})){return 'number';}
    if(values.length && values.every(function(v){return /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/.test(v);})){return 'date';}
    return 'text';
  }

  function compareValues(a,b,kind){
    if(a==='' && b==='') return 0;
    if(a==='') return 1;
    if(b==='') return -1;
    if(kind==='number') return Number(a)-Number(b);
    if(kind==='date') return a<b?-1:(a>b?1:0);
    return a.localeCompare(b,'zh-Hant-u-kn-true',{numeric:true,sensitivity:'base'});
  }

  function markSortableHeaders(target,onSort){
    const headers=Array.from(target.querySelectorAll('thead th'));
    headers.forEach(function(th,column){
      if(th.textContent.trim()==='操作') return;
      th.classList.add('sortable');
      th.tabIndex=0;
      th.setAttribute('role','button');
      th.setAttribute('title','點擊排序');
      th.setAttribute('aria-sort','none');
      const activate=function(){onSort(column,th);};
      th.addEventListener('click',activate);
      th.addEventListener('keydown',function(e){
        if(e.key==='Enter'||e.key===' '){e.preventDefault();activate();}
      });
    });
    return headers;
  }

  function setHeaderState(headers,column,direction){
    headers.forEach(function(th,i){
      th.removeAttribute('data-sort-dir');
      if(th.classList.contains('sortable')) th.setAttribute('aria-sort','none');
      if(i===column){
        th.setAttribute('data-sort-dir',direction);
        th.setAttribute('aria-sort',direction==='asc'?'ascending':'descending');
      }
    });
  }

  // 首頁等沒有分頁控制的資料表，也提供欄位點擊排序。
  tables.filter(function(t){return t!==table;}).forEach(function(simpleTable){
    const tbody=simpleTable.tBodies[0];
    if(!tbody) return;
    let simpleRows=Array.from(tbody.rows);
    let sortColumn=-1;
    let sortDirection='asc';
    const headers=markSortableHeaders(simpleTable,function(column){
      sortDirection=(sortColumn===column&&sortDirection==='asc')?'desc':'asc';
      if(sortColumn!==column) sortDirection='asc';
      sortColumn=column;
      const kind=columnKind(simpleRows,column);
      simpleRows=simpleRows.map(function(row,index){return {row:row,index:index};}).sort(function(a,b){
        const av=normalizedText(a.row.cells[column]);
        const bv=normalizedText(b.row.cells[column]);
        const cmp=compareValues(av,bv,kind);
        return cmp===0?a.index-b.index:(sortDirection==='asc'?cmp:-cmp);
      }).map(function(item){return item.row;});
      simpleRows.forEach(function(row){tbody.appendChild(row);});
      setHeaderState(headers,sortColumn,sortDirection);
    });
  });

  if(!table){restoreReturnScroll();return;}
  const tbody=table.tBodies[0];
  if(!tbody){restoreReturnScroll();return;}
  let rows=Array.from(tbody.rows);
  if(!rows.length){restoreReturnScroll();return;}

  const totalPages=Math.max(1,Math.ceil(rows.length/pageSize));
  const baseStorageKey='renju-rank:'+String(params.get('view')||'')+':'+String(params.get('field_search')||'')+':'+String(params.get('q')||'');
  const pageStorageKey=baseStorageKey+':page';
  const sortStorageKey=baseStorageKey+':sort';
  let requested=parseInt(params.get('p')||'',10);
  if(!Number.isFinite(requested)||requested<1){
    try{requested=parseInt(sessionStorage.getItem(pageStorageKey)||'1',10);}catch(e){requested=1;}
  }
  let current=Math.min(totalPages,Math.max(1,Number.isFinite(requested)?requested:1));
  let sortColumn=-1;
  let sortDirection='asc';
  const wrap=table.closest('.table-wrap');
  if(!wrap){restoreReturnScroll();return;}

  let topPager=null;
  let bottomPager=null;
  if(totalPages>1){
    topPager=document.createElement('div');
    topPager.className='pager top';
    bottomPager=document.createElement('div');
    bottomPager.className='pager bottom';
    wrap.parentNode.insertBefore(topPager,wrap);
    wrap.parentNode.insertBefore(bottomPager,wrap.nextSibling);
  }

  function button(label,page,disabled,currentButton){
    const b=document.createElement('button');
    b.type='button';
    b.textContent=label;
    b.disabled=!!disabled;
    if(currentButton) b.classList.add('current');
    if(!disabled&&!currentButton) b.addEventListener('click',function(){showPage(page,true);});
    return b;
  }

  function pageSet(){
    const result=[];
    function add(n){if(n>=1&&n<=totalPages&&!result.includes(n)) result.push(n);}
    add(1);add(current-2);add(current-1);add(current);add(current+1);add(current+2);add(totalPages);
    return result.sort(function(a,b){return a-b;});
  }

  function renderPager(el){
    if(!el) return;
    el.innerHTML='';
    const first=(current-1)*pageSize+1;
    const last=Math.min(rows.length,current*pageSize);
    const info=document.createElement('div');
    info.className='pager-info';
    info.textContent='第 '+current+' / '+totalPages+' 頁 · 顯示 '+first+'–'+last+' 筆 · 共 '+rows.length+' 筆';
    const controls=document.createElement('div');
    controls.className='pager-controls';
    controls.appendChild(button('«',1,current===1,false));
    controls.appendChild(button('‹',current-1,current===1,false));
    const pages=pageSet();
    let previous=0;
    pages.forEach(function(p){
      if(previous&&p-previous>1){const dots=document.createElement('span');dots.className='pager-ellipsis';dots.textContent='…';controls.appendChild(dots);}
      controls.appendChild(button(String(p),p,false,p===current));
      previous=p;
    });
    controls.appendChild(button('›',current+1,current===totalPages,false));
    controls.appendChild(button('»',totalPages,current===totalPages,false));
    el.appendChild(info);el.appendChild(controls);
  }

  function preservePageOnEditLinks(){
    table.querySelectorAll('a[href*="view="][href*="edit="]').forEach(function(link){
      try{
        const u=new URL(link.href,location.href);
        u.searchParams.set('p',String(current));
        if(sortColumn>=0){u.searchParams.set('sort',String(sortColumn));u.searchParams.set('dir',sortDirection);}
        link.href=u.pathname+u.search+u.hash;
      }catch(e){}
    });
  }

  function updateAddress(){
    const u=new URL(location.href);
    u.searchParams.set('p',String(current));
    if(sortColumn>=0){u.searchParams.set('sort',String(sortColumn));u.searchParams.set('dir',sortDirection);}
    else{u.searchParams.delete('sort');u.searchParams.delete('dir');}
    history.replaceState(null,'',u.pathname+u.search+u.hash);
  }

  function showPage(page,scroll){
    if(activeInlineEdit)cancelInlineEdit();
    current=Math.min(totalPages,Math.max(1,page));
    const start=(current-1)*pageSize,end=start+pageSize;
    rows.forEach(function(row,i){row.hidden=!(i>=start&&i<end);});
    renderPager(topPager);renderPager(bottomPager);preservePageOnEditLinks();
    try{sessionStorage.setItem(pageStorageKey,String(current));}catch(e){}
    updateAddress();
    if(scroll){
      const target=topPager||wrap;
      target.scrollIntoView({block:'start'});
    }
  }

  const headers=markSortableHeaders(table,function(column){
    if(activeInlineEdit)cancelInlineEdit();
    const nextDirection=(sortColumn===column&&sortDirection==='asc')?'desc':'asc';
    sortRows(column,nextDirection,true);
  });

  function sortRows(column,direction,userAction){
    if(column<0||column>=headers.length-1) return;
    sortColumn=column;
    sortDirection=direction==='desc'?'desc':'asc';
    const kind=columnKind(rows,column);
    rows=rows.map(function(row,index){return {row:row,index:index};}).sort(function(a,b){
      const av=normalizedText(a.row.cells[column]);
      const bv=normalizedText(b.row.cells[column]);
      const cmp=compareValues(av,bv,kind);
      return cmp===0?a.index-b.index:(sortDirection==='asc'?cmp:-cmp);
    }).map(function(item){return item.row;});
    rows.forEach(function(row){tbody.appendChild(row);});
    setHeaderState(headers,sortColumn,sortDirection);
    try{sessionStorage.setItem(sortStorageKey,JSON.stringify({column:sortColumn,direction:sortDirection}));}catch(e){}
    if(userAction){
      current=1;
      showPage(1,true);
    }
  }

  let initialSortColumn=parseInt(params.get('sort')||'',10);
  let initialSortDirection=params.get('dir')==='desc'?'desc':'asc';
  if(!Number.isFinite(initialSortColumn)||initialSortColumn<0||initialSortColumn>=headers.length-1){
    try{
      const saved=JSON.parse(sessionStorage.getItem(sortStorageKey)||'null');
      if(saved&&Number.isFinite(Number(saved.column))&&Number(saved.column)>=0&&Number(saved.column)<headers.length-1){
        initialSortColumn=Number(saved.column);
        initialSortDirection=saved.direction==='desc'?'desc':'asc';
      }
    }catch(e){}
  }
  if(Number.isFinite(initialSortColumn)&&initialSortColumn>=0&&initialSortColumn<headers.length-1){
    sortRows(initialSortColumn,initialSortDirection,false);
  }

  showPage(current,false);
  restoreReturnScroll();
})();