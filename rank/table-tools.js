(function(){
  'use strict';
  window.RankTableTools={enhance:enhance};
  const collator=new Intl.Collator('zh-Hant',{numeric:true,sensitivity:'base'});
  const wrappers='.table-wrap,.swiss-scroll,.sri-table-scroll,.rgi-scroll,.rr-table-wrap,.rn-table-wrap,.swiss-modal-table';
  let currentPopup=null;

  function cellText(row,col){
    const cell=row.cells[col];
    if(!cell)return '';
    const input=cell.querySelector('.inline-edit-input');
    if(input)return String(input.value||'').trim();
    if(cell.hasAttribute('data-raw-value'))return String(cell.getAttribute('data-raw-value')||'').trim();
    return (cell.textContent||'').replace(/\u00a0/g,' ').trim();
  }
  function kindOf(rows,col){
    const values=rows.map(row=>cellText(row,col)).filter(Boolean);
    if(values.length&&values.every(v=>/^[+-]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?%?$/.test(v)))return 'number';
    if(values.length&&values.every(v=>/^\d{4}[-/]\d{1,2}[-/]\d{1,2}/.test(v)))return 'date';
    return 'text';
  }
  function compare(a,b,kind){
    if(!a&&!b)return 0;
    if(!a)return 1;
    if(!b)return -1;
    if(kind==='number')return Number(a.replace(/[,％%]/g,''))-Number(b.replace(/[,％%]/g,''));
    if(kind==='date')return collator.compare(a,b);
    return collator.compare(a,b);
  }
  function closePopup(){
    if(!currentPopup)return;
    const p=currentPopup;
    currentPopup=null;
    document.removeEventListener('pointerdown',p.outside);
    document.removeEventListener('keydown',p.keydown);
    window.removeEventListener('resize',p.reposition);
    window.removeEventListener('scroll',p.reposition,true);
    p.element.remove();
  }
  function enhance(table,settings){
    if(!table||table.dataset.rankToolsReady==='1')return null;
    const thead=table.tHead, tbody=table.tBodies[0];
    if(!thead||!tbody||!thead.rows.length)return null;
    const headers=Array.from(thead.rows[thead.rows.length-1].cells);
    if(!headers.length)return null;
    table.dataset.rankToolsReady='1';
    table.classList.add('rank-enhanced');
    const wrapper=table.closest(wrappers);
    if(wrapper)wrapper.classList.add('rank-table-scroll');
    const initialRows=Array.from(tbody.rows);
    const uniform=thead.rows.length===1&&headers.every(h=>h.colSpan===1)&&initialRows.every(r=>r.cells.length===headers.length&&Array.from(r.cells).every(c=>c.colSpan===1));
    if(!uniform||table.closest('.swiss-modal-table'))return null;
    const filters=new Map();
    const kinds=headers.map((_,i)=>kindOf(initialRows,i));
    const originalIndex=new Map(initialRows.map((row,index)=>[row,index]));
    const state={column:-1,direction:'asc',filters:filters};
    const onChange=settings&&settings.onChange;
    function filteredAndSorted(){
      const visible=initialRows.filter(row=>{
        for(const [col,f] of filters){
          const value=cellText(row,col);
          const term=f.term.toLocaleLowerCase('zh-Hant');
          const val=value.toLocaleLowerCase('zh-Hant');
          if(f.picks!==null&&!f.picks.has(value))return false;
          if(!term)continue;
          let matches=false;
          if(f.mode==='equals')matches=val===term;
          else if(f.mode==='starts')matches=val.startsWith(term);
          else if(f.mode==='not')matches=!val.includes(term);
          else if(f.mode==='gt'||f.mode==='lt'){
            const a=Number(value.replace(/,/g,'')), b=Number(f.term.replace(/,/g,''));
            matches=value!==''&&f.term!==''&&Number.isFinite(a)&&Number.isFinite(b)&&(f.mode==='gt'?a>b:a<b);
          }else matches=val.includes(term);
          if(!matches)return false;
        }
        return true;
      });
      if(state.column>=0){
        const col=state.column,dir=state.direction;
        visible.sort((a,b)=>{
          const cmp=compare(cellText(a,col),cellText(b,col),kinds[col]);
          return (dir==='desc'?-cmp:cmp)||originalIndex.get(a)-originalIndex.get(b);
        });
      }
      // 保留不符合條件的列：清除篩選即可還原，不破壞編輯表單。
      const keep=new Set(visible);
      const order=visible.concat(initialRows.filter(row=>!keep.has(row)));
      order.forEach(row=>tbody.appendChild(row));
      return visible;
    }
    function notify(action){
      headers.forEach((h,i)=>{
        h.removeAttribute('data-sort-dir');
        if(h.classList.contains('rank-sortable'))h.setAttribute('aria-sort','none');
        if(i===state.column){
          h.setAttribute('data-sort-dir',state.direction);
          h.setAttribute('aria-sort',state.direction==='asc'?'ascending':'descending');
        }
        h.classList.toggle('rank-filtered',filters.has(i));
      });
      const visible=filteredAndSorted(),set=new Set(visible);
      if(typeof onChange==='function')onChange(visible,{column:state.column,direction:state.direction},!!action);
      else initialRows.forEach(row=>{row.hidden=!set.has(row);});
    }
    function openFilter(th,col){
      closePopup();
      const pop=document.createElement('div');
      pop.className='rank-column-popover';
      pop.setAttribute('role','dialog');
      pop.setAttribute('aria-label',th.textContent.trim()+' 欄位篩選');
      const heading=document.createElement('div');heading.className='rank-filter-head';
      const title=document.createElement('span');title.className='rank-filter-title';title.textContent=th.textContent.trim()+' · 搜尋／篩選';
      const close=document.createElement('button');close.type='button';close.className='rank-filter-close';close.textContent='×';close.setAttribute('aria-label','關閉');close.onclick=closePopup;
      heading.append(title,close);pop.append(heading);
      const label=(value)=>{const el=document.createElement('label');el.className='rank-filter-label';el.textContent=value;pop.appendChild(el);};
      label('搜尋內容');
      const search=document.createElement('input');search.type='search';search.placeholder='輸入此欄關鍵字';search.autocomplete='off';pop.appendChild(search);
      label('搜尋方式');
      const mode=document.createElement('select');
      [['includes','包含'],['equals','完全相同'],['starts','開頭符合'],['not','不包含'],['gt','數值大於'],['lt','數值小於']].forEach(([v,n])=>{const opt=document.createElement('option');opt.value=v;opt.textContent=n;mode.appendChild(opt);});
      pop.appendChild(mode);
      label('選擇欄位值（可複選）');
      const values=[...new Set(initialRows.map(r=>cellText(r,col)))].sort((a,b)=>compare(a,b,kinds[col]));
      const choices=document.createElement('div');choices.className='rank-filter-options';
      const allChoice=document.createElement('label');allChoice.className='rank-filter-choice';
      const selectAll=document.createElement('input');selectAll.type='checkbox';selectAll.checked=true;
      allChoice.append(selectAll,document.createTextNode('全部'));choices.append(allChoice);
      const items=[];
      values.slice(0,180).forEach(v=>{
        const choice=document.createElement('label');choice.className='rank-filter-choice';
        const check=document.createElement('input');check.type='checkbox';check.checked=true;
        choice.append(check,document.createTextNode(v===''?'（空白）':v));choices.append(choice);
        items.push({check:check,value:v});
      });
      selectAll.addEventListener('change',()=>{items.forEach(item=>item.check.checked=selectAll.checked);});
      items.forEach(item=>item.check.addEventListener('change',()=>{selectAll.checked=items.every(i=>i.check.checked);}));
      pop.append(choices);
      if(values.length>180){const tip=document.createElement('div');tip.className='rank-filter-description';tip.textContent='選項僅列前 180 種值，其他值仍可用上方關鍵字搜尋。';pop.append(tip);}
      const previous=filters.get(col);
      if(previous){search.value=previous.term;mode.value=previous.mode; if(previous.picks!==null){items.forEach(item=>{item.check.checked=previous.picks.has(item.value);});selectAll.checked=items.every(i=>i.check.checked);}}
      const actions=document.createElement('div');actions.className='rank-filter-actions';
      const clear=document.createElement('button');clear.type='button';clear.className='rank-filter-btn';clear.textContent='清除本欄';
      clear.onclick=()=>{filters.delete(col);closePopup();notify(true);};
      const apply=document.createElement('button');apply.type='button';apply.className='rank-filter-btn rank-filter-apply';apply.textContent='套用篩選';
      apply.onclick=()=>{
        const term=search.value.trim();
        let picks=null;
        if(!items.every(i=>i.check.checked)){
          picks=new Set(items.filter(i=>i.check.checked).map(i=>i.value));
        }
        if(values.length>180&&picks===null)picks=null;
        if(term===''&&picks===null)filters.delete(col);
        else filters.set(col,{term:term,mode:mode.value,picks:picks});
        closePopup();notify(true);
      };
      search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();apply.click();}});
      actions.append(clear,apply);pop.append(actions);
      document.body.append(pop);
      function position(){
        const r=th.getBoundingClientRect();
        const left=Math.max(12,Math.min(r.left,window.innerWidth-pop.offsetWidth-12));
        const top=Math.max(12,Math.min(r.bottom+5,window.innerHeight-pop.offsetHeight-12));
        pop.style.left=left+'px';pop.style.top=top+'px';
      }
      const outside=e=>{if(!pop.contains(e.target)&&e.target!==th)closePopup();};
      const keydown=e=>{if(e.key==='Escape')closePopup();};
      currentPopup={element:pop,outside:outside,keydown:keydown,reposition:position};
      document.addEventListener('pointerdown',outside);
      document.addEventListener('keydown',keydown);
      window.addEventListener('resize',position);
      window.addEventListener('scroll',position,true);
      position();search.focus();
    }
    headers.forEach((th,col)=>{
      if(th.textContent.trim()==='操作'||th.hasAttribute('data-no-sort')||th.querySelector('button,input,select'))return;
      th.classList.add('rank-sortable');
      th.tabIndex=0;th.setAttribute('role','button');th.setAttribute('aria-sort','none');
      th.title='點一下：升降排序；點兩下：搜尋／篩選';
      let pending=0;
      th.addEventListener('click',e=>{
        if(e.detail>=2){clearTimeout(pending);pending=0;return;}
        clearTimeout(pending);
        pending=setTimeout(()=>{pending=0;state.direction=state.column===col&&state.direction==='asc'?'desc':'asc';state.column=col;notify(true);},240);
      });
      th.addEventListener('dblclick',e=>{e.preventDefault();clearTimeout(pending);pending=0;openFilter(th,col);});
      th.addEventListener('keydown',e=>{
        if(e.key==='Enter'||e.key===' '){e.preventDefault();state.direction=state.column===col&&state.direction==='asc'?'desc':'asc';state.column=col;notify(true);}
        else if((e.key==='ArrowDown'&&e.altKey)||e.key==='F2'){e.preventDefault();openFilter(th,col);}
      });
    });
    if(settings&&Number.isInteger(settings.initialSort)&&settings.initialSort>=0&&settings.initialSort<headers.length){
      state.column=settings.initialSort;
      state.direction=settings.initialDirection==='desc'?'desc':'asc';
    }
    notify(false);
    return {state:state,refresh:()=>notify(false)};
  }
  function autoInit(){
    document.querySelectorAll('table').forEach(table=>{
      if(!table.tHead||table.dataset.rankToolsReady==='1')return;
      enhance(table);
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',autoInit);
  else autoInit();
})();
