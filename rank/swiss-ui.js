(function(){
  function setupCrossTables(){
    document.querySelectorAll('table.swiss-cross').forEach(function(table){
      var rows=table.querySelectorAll('tbody tr');
      var headers=table.querySelectorAll('thead th');

      if(headers.length){
        headers[0].textContent='編號';
        for(var i=0;i<rows.length;i++){
          var opponentHeader=headers[4+i];
          if(opponentHeader) opponentHeader.textContent=String(i+1);
        }
      }

      rows.forEach(function(row,rowIndex){
        if(row.cells.length) row.cells[0].textContent=String(rowIndex+1);
      });

      table.querySelectorAll('.cross-result').forEach(function(cell){
        cell.classList.remove('score-win','score-draw','score-loss');
        var scoreNodes=cell.querySelectorAll('.opening-score,.reply-score');
        if(!scoreNodes.length) return;

        var total=0;
        var count=0;
        scoreNodes.forEach(function(node){
          var score=parseFloat(node.textContent);
          if(!isNaN(score)){
            total+=score;
            count++;
          }
        });
        if(!count) return;

        var average=total/count;
        if(average>1) cell.classList.add('score-win');
        else if(average<1) cell.classList.add('score-loss');
        else cell.classList.add('score-draw');
      });
    });
  }

  function clearPairs(){
    document.querySelectorAll('.cross-result.pair-focus').forEach(function(el){el.classList.remove('pair-focus');});
  }
  function focusPair(el){
    clearPairs();
    var pair=el.getAttribute('data-pair');
    if(!pair)return;
    document.querySelectorAll('.cross-result[data-pair="'+pair+'"]').forEach(function(x){x.classList.add('pair-focus');});
  }

  function clearSwissFocus(){
    document.querySelectorAll('.swiss-rank .swiss-focus').forEach(function(el){el.classList.remove('swiss-focus');});
  }
  function focusSwissGame(cell){
    clearSwissFocus();
    var table=cell.closest('table.swiss-rank');
    if(!table)return;
    var key=cell.getAttribute('data-game-key');
    if(key){
      table.querySelectorAll('.swiss-game-cell[data-game-key="'+key+'"]').forEach(function(el){el.classList.add('swiss-focus');});
    }
    var player=cell.getAttribute('data-player');
    var opponent=cell.getAttribute('data-opponent');
    if(player){
      var own=table.querySelector('.name[data-player-id="'+player+'"]');
      if(own)own.classList.add('swiss-focus');
    }
    if(opponent){
      var other=table.querySelector('.name[data-player-id="'+opponent+'"]');
      if(other)other.classList.add('swiss-focus');
    }
  }
  function focusSwissOpponents(cell){
    clearSwissFocus();
    var table=cell.closest('table.swiss-rank');
    if(!table)return;
    var raw=cell.getAttribute('data-opponents')||'';
    raw.split(',').forEach(function(id){
      id=id.trim();
      if(!id)return;
      var name=table.querySelector('.name[data-player-id="'+id+'"]');
      if(name)name.classList.add('swiss-focus');
    });
  }

  function denExistingPlayerIds(){
    var ids={};
    document.querySelectorAll('.promotion-card .swiss-mini tbody tr td:first-child a[href*="PLAYER="]').forEach(function(link){
      var match=(link.getAttribute('href')||'').match(/[?&]PLAYER=(\d+)/);
      if(match)ids[match[1]]=true;
    });
    return ids;
  }

  function filterDenRow(row,existing){
    if(!row)return;
    var select=row.querySelector('[data-den-player]');
    if(!select)return;
    var selected=select.value;
    if(selected&&existing[selected]){
      row.remove();
      return;
    }
    Array.prototype.slice.call(select.options).forEach(function(opt){
      if(opt.value&&existing[opt.value])opt.remove();
    });
  }

  function setupDenModal(){
    var modal=document.getElementById('swiss-den-modal');
    if(!modal)return;
    var existing=denExistingPlayerIds();
    modal.querySelectorAll('[data-den-row]').forEach(function(row){filterDenRow(row,existing);});

    var template=document.getElementById('den-row-template');
    if(template&&template.content){
      template.content.querySelectorAll('[data-den-row]').forEach(function(row){filterDenRow(row,existing);});
    }

    var tbody=document.getElementById('den-modal-rows');
    if(tbody&&window.MutationObserver){
      new MutationObserver(function(mutations){
        mutations.forEach(function(mutation){
          mutation.addedNodes.forEach(function(node){
            if(node.nodeType!==1)return;
            if(node.matches&&node.matches('[data-den-row]'))filterDenRow(node,existing);
            else if(node.querySelectorAll)node.querySelectorAll('[data-den-row]').forEach(function(row){filterDenRow(row,existing);});
          });
        });
      }).observe(tbody,{childList:true});
    }
  }

  function ensureNavStyles(){
    if(document.getElementById('swiss-nav-style'))return;
    var link=document.createElement('link');
    link.id='swiss-nav-style';
    link.rel='stylesheet';
    link.href='swiss-nav.css?v=20260824a';
    document.head.appendChild(link);
  }

  function navButton(label,target,tooltip){
    var el=document.createElement(target?'a':'span');
    el.className='swiss-nav-btn'+(target?'':' is-disabled');
    el.textContent=label;
    if(target)el.href='swiss.php?TOUR='+encodeURIComponent(target);
    if(tooltip)el.title=tooltip;
    return el;
  }

  function setupTournamentNav(){
    var form=document.querySelector('form.swiss-form');
    if(!form||form.dataset.navReady==='1')return;
    var input=form.querySelector('input[name="TOUR"]');
    var tour=input?parseInt(input.value,10):0;
    if(!tour)return;

    ensureNavStyles();
    fetch('swiss-nav.php?TOUR='+encodeURIComponent(tour),{credentials:'same-origin'})
      .then(function(res){if(!res.ok)throw new Error('navigation');return res.json();})
      .then(function(data){
        if(!data||!data.ok)return;
        form.dataset.navReady='1';
        form.classList.add('swiss-nav-ready');

        var previous=data.previous||null;
        var next=data.next||null;
        var prevButton=navButton('上一場',previous&&previous['賽號'],previous&&previous['賽標']?'上一個賽標：'+previous['賽標']:'');
        form.insertBefore(prevButton,form.firstChild);
        form.appendChild(navButton('下一場',next&&next['賽號'],next&&next['賽標']?'下一個賽標：'+next['賽標']:''));

        var group=document.createElement('div');
        group.className='swiss-same-label';
        var title=document.createElement('div');
        title.className='swiss-same-label-title';
        title.textContent=data.label?'同賽標：'+data.label:'同組比賽：';
        group.appendChild(title);

        var list=document.createElement('div');
        list.className='swiss-same-label-list';
        (data.same||[]).forEach(function(item){
          var link=document.createElement('a');
          link.href='swiss.php?TOUR='+encodeURIComponent(item['賽號']);
          link.className='swiss-same-tour'+(parseInt(item['賽號'],10)===tour?' is-current':'');
          var no=document.createElement('span');
          no.className='swiss-same-tour-no';
          no.textContent=String(item['賽號']);
          var name=document.createElement('span');
          name.className='swiss-same-tour-name';
          name.textContent=item['賽名']||'';
          link.appendChild(no);
          if(name.textContent)link.appendChild(name);
          list.appendChild(link);
        });
        group.appendChild(list);
        form.insertAdjacentElement('afterend',group);
      })
      .catch(function(){});
  }

  document.addEventListener('mouseover',function(e){
    var cross=e.target.closest&&e.target.closest('.cross-result[data-pair]');
    if(cross){focusPair(cross);return;}

    var gameCell=e.target.closest&&e.target.closest('.swiss-game-cell[data-game-key]');
    if(gameCell){focusSwissGame(gameCell);return;}

    var summaryCell=e.target.closest&&e.target.closest('.swiss-summary-cell[data-opponents]');
    if(summaryCell){focusSwissOpponents(summaryCell);return;}

    var head=e.target.closest&&e.target.closest('[data-tooltip]');
    if(head&&window.matchMedia('(hover:hover)').matches)showTip(head);
  });

  document.addEventListener('mouseout',function(e){
    var cross=e.target.closest&&e.target.closest('.cross-result[data-pair]');
    if(cross&&!cross.contains(e.relatedTarget))clearPairs();

    var gameCell=e.target.closest&&e.target.closest('.swiss-game-cell[data-game-key]');
    if(gameCell){
      var next=e.relatedTarget&&e.relatedTarget.closest?e.relatedTarget.closest('.swiss-game-cell[data-game-key]'):null;
      if(!next||next.getAttribute('data-game-key')!==gameCell.getAttribute('data-game-key'))clearSwissFocus();
    }

    var summaryCell=e.target.closest&&e.target.closest('.swiss-summary-cell[data-opponents]');
    if(summaryCell){
      var nextSummary=e.relatedTarget&&e.relatedTarget.closest?e.relatedTarget.closest('.swiss-summary-cell[data-opponents]'):null;
      if(!nextSummary)clearSwissFocus();
    }

    var head=e.target.closest&&e.target.closest('[data-tooltip]');
    if(head&&window.matchMedia('(hover:hover)').matches)removeTip();
  });

  document.addEventListener('click',function(e){
    var cell=e.target.closest&&e.target.closest('.cross-result[data-pair]');
    if(cell){focusPair(cell);return;}
    var head=e.target.closest&&e.target.closest('[data-tooltip]');
    if(head){showTip(head);return;}
    removeTip();
  });

  function removeTip(){
    var old=document.querySelector('.swiss-tooltip');
    if(old)old.remove();
  }
  function showTip(el){
    removeTip();
    var text=el.getAttribute('data-tooltip');
    if(!text)return;
    var tip=document.createElement('div');
    tip.className='swiss-tooltip';
    tip.textContent=text;
    document.body.appendChild(tip);
    var r=el.getBoundingClientRect(),w=tip.offsetWidth,h=tip.offsetHeight;
    var left=Math.min(window.innerWidth-w-8,Math.max(8,r.left+r.width/2-w/2));
    var top=r.bottom+8;
    if(top+h>window.innerHeight-8)top=Math.max(8,r.top-h-8);
    tip.style.left=left+'px';
    tip.style.top=top+'px';
  }

  setupCrossTables();
  setupDenModal();
  setupTournamentNav();
})();
