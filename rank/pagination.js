(function(){
  'use strict';

  const pageSize=500;
  const tables=Array.from(document.querySelectorAll('table.data'));
  const table=tables.find(function(t){
    const headers=t.querySelectorAll('thead th');
    return headers.length && headers[headers.length-1].textContent.trim()==='操作';
  });

  function normalizedText(cell){
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

  if(!table) return;
  const tbody=table.tBodies[0];
  if(!tbody) return;
  let rows=Array.from(tbody.rows);
  if(!rows.length) return;

  const totalPages=Math.max(1,Math.ceil(rows.length/pageSize));
  const params=new URLSearchParams(location.search);
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
  if(!wrap) return;

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
})();
