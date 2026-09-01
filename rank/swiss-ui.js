(function(){
  var scoreClasses=['score-win','score-draw','score-loss'];
  var pinnedSwissSummary=null;
  var pinnedSwissGame=null;

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
        if(!scoreNodes.length)return;

        var total=0;
        var count=0;
        scoreNodes.forEach(function(node){
          var score=parseFloat(node.textContent);
          if(!isNaN(score)){
            total+=score;
            count++;
          }
        });
        if(!count)return;

        var average=total/count;
        if(average>1)cell.classList.add('score-win');
        else if(average<1)cell.classList.add('score-loss');
        else cell.classList.add('score-draw');
      });
    });
  }

  function clearPairs(){
    document.querySelectorAll('.cross-result.pair-focus').forEach(function(el){el.classList.remove('pair-focus');});
    document.querySelectorAll('.swiss-cross .name.pair-name-focus').forEach(function(el){el.classList.remove('pair-name-focus');});
  }

  function focusPair(el){
    clearPairs();
    var pair=el.getAttribute('data-pair');
    if(!pair)return;
    document.querySelectorAll('.cross-result[data-pair="'+pair+'"]').forEach(function(x){
      x.classList.add('pair-focus');
      var row=x.closest('tr');
      var name=row&&row.querySelector('.name');
      if(name)name.classList.add('pair-name-focus');
    });
  }

  function clearSwissFocus(){
    document.querySelectorAll('.swiss-rank .swiss-focus').forEach(function(el){el.classList.remove('swiss-focus');});
    document.querySelectorAll('.swiss-rank .swiss-game-primary').forEach(function(el){
      el.classList.remove('swiss-game-primary','swiss-game-primary-pinned');
    });
    document.querySelectorAll('.swiss-rank .swiss-game-opponent').forEach(function(el){el.classList.remove('swiss-game-opponent');});
    document.querySelectorAll('.swiss-rank .swiss-hover-source').forEach(function(el){el.classList.remove('swiss-hover-source');});
    document.querySelectorAll('.swiss-rank .swiss-summary-anchor').forEach(function(el){
      el.classList.remove('swiss-summary-anchor','swiss-summary-pinned');
    });
    document.querySelectorAll('.swiss-rank .swiss-hover-result').forEach(function(el){
      el.classList.remove('swiss-hover-result');
      scoreClasses.forEach(function(cls){el.classList.remove(cls);});
    });
    document.querySelectorAll('.swiss-rank [data-swiss-original-text]').forEach(function(el){
      el.textContent=el.getAttribute('data-swiss-original-text');
      el.removeAttribute('data-swiss-original-text');
    });
  }

  function markSwissGameSide(table,key,playerId,className,pinned){
    if(!key||!playerId)return;
    table.querySelectorAll('.swiss-game-cell[data-game-key="'+key+'"][data-player="'+playerId+'"]').forEach(function(el){
      el.classList.add(className);
      if(pinned&&className==='swiss-game-primary')el.classList.add('swiss-game-primary-pinned');
    });
    var name=table.querySelector('.name[data-player-id="'+playerId+'"]');
    if(name){
      name.classList.add(className);
      if(pinned&&className==='swiss-game-primary')name.classList.add('swiss-game-primary-pinned');
    }
  }

  function focusSwissGame(cell,pinned){
    clearSwissFocus();
    var table=cell.closest('table.swiss-rank');
    if(!table)return;
    var key=cell.getAttribute('data-game-key');
    var player=cell.getAttribute('data-player');
    var opponent=cell.getAttribute('data-opponent');

    markSwissGameSide(table,key,player,'swiss-game-primary',!!pinned);
    if(opponent)markSwissGameSide(table,key,opponent,'swiss-game-opponent',false);
  }

  function pinSwissGame(cell){
    pinnedSwissGame=cell;
    focusSwissGame(cell,true);
  }

  function releasePinnedSwissGame(){
    pinnedSwissGame=null;
    clearSwissFocus();
  }

  function headerLabel(table,index){
    var head=table&&table.querySelector('thead tr');
    return head&&head.cells[index]?head.cells[index].textContent.trim():'';
  }

  function cellByLabel(row,label){
    var table=row&&row.closest('table.swiss-rank');
    if(!table)return null;
    var head=table.querySelector('thead tr');
    if(!head)return null;
    for(var i=0;i<head.cells.length;i++){
      if(head.cells[i].textContent.trim()===label)return row.cells[i]||null;
    }
    return null;
  }

  function playerRow(table,id){
    var name=table.querySelector('.name[data-player-id="'+id+'"]');
    return name?name.closest('tr'):null;
  }

  function playerIdFromRow(row){
    var name=row&&row.querySelector('.name[data-player-id]');
    return name?name.getAttribute('data-player-id'):'';
  }

  function opponentIds(cell){
    var raw=cell.getAttribute('data-opponents')||'';
    var out=[];
    raw.split(',').forEach(function(id){
      id=id.trim();
      if(id&&out.indexOf(id)<0)out.push(id);
    });
    return out;
  }

  function resultAgainst(row,opponentId){
    if(!row||!opponentId)return '';
    var cells=row.querySelectorAll('.round-score[data-opponent="'+opponentId+'"]');
    var total=0,count=0;
    cells.forEach(function(cell){
      var score=parseFloat(cell.textContent.trim());
      if(!isNaN(score)){total+=score;count++;}
    });
    if(!count)return '';
    var average=total/count;
    if(average>1.000001)return 'score-win';
    if(average<0.999999)return 'score-loss';
    return 'score-draw';
  }

  function markResult(cell,resultClass){
    if(!cell||!resultClass)return;
    cell.classList.add('swiss-hover-result',resultClass);
  }

  function formatHoverNumber(value){
    if(!isFinite(value))return '';
    var rounded=Math.round(value*1000000)/1000000;
    if(Math.abs(rounded-Math.round(rounded))<0.000001)return String(Math.round(rounded));
    return String(rounded).replace(/(\.\d*?)0+$/,'$1').replace(/\.$/,'');
  }

  function showWeightedValue(cell,resultClass){
    if(!cell)return;
    if(!cell.hasAttribute('data-swiss-original-text'))cell.setAttribute('data-swiss-original-text',cell.textContent.trim());
    var base=parseFloat(cell.getAttribute('data-swiss-original-text'));
    if(isNaN(base))return;
    if(resultClass==='score-loss')cell.textContent='0';
    else if(resultClass==='score-draw')cell.textContent=formatHoverNumber(base/2);
    else cell.textContent=formatHoverNumber(base);
  }

  function focusOpponentNames(table,sourceRow,ids){
    ids.forEach(function(id){
      var row=playerRow(table,id);
      var name=row&&row.querySelector('.name[data-player-id]');
      if(!name)return;
      name.classList.add('swiss-focus');
      markResult(name,resultAgainst(sourceRow,id));
    });
  }

  function focusOpponentColumn(table,sourceRow,ids,label,weighted){
    ids.forEach(function(id){
      var row=playerRow(table,id);
      var target=cellByLabel(row,label);
      if(!target)return;
      target.classList.add('swiss-focus');
      if(weighted){
        var resultClass=resultAgainst(sourceRow,id);
        markResult(target,resultClass);
        showWeightedValue(target,resultClass);
      }
    });
  }

  function sameValue(a,b,label){
    var ca=cellByLabel(a,label),cb=cellByLabel(b,label);
    if(!ca||!cb)return false;
    var va=parseFloat(ca.textContent.trim()),vb=parseFloat(cb.textContent.trim());
    if(isNaN(va)||isNaN(vb))return ca.textContent.trim()===cb.textContent.trim();
    return Math.abs(va-vb)<0.000001;
  }

  function focusHeadToHead(table,sourceRow){
    var tied=[];
    table.querySelectorAll('tbody tr').forEach(function(row){
      if(row===sourceRow)return;
      if(!sameValue(sourceRow,row,'總分'))return;
      if(!sameValue(sourceRow,row,'輔一'))return;
      if(!sameValue(sourceRow,row,'輔二'))return;
      var id=playerIdFromRow(row);
      if(id)tied.push(id);
    });

    tied.forEach(function(id){
      sourceRow.querySelectorAll('.round-score[data-opponent="'+id+'"]').forEach(function(scoreCell){
        var key=scoreCell.getAttribute('data-game-key');
        if(key){
          table.querySelectorAll('.round-score[data-game-key="'+key+'"]').forEach(function(cell){cell.classList.add('swiss-focus');});
        }else scoreCell.classList.add('swiss-focus');
      });
    });
  }

  function markSummaryAnchor(cell,sourceRow,pinned){
    cell.classList.add('swiss-summary-anchor');
    if(pinned)cell.classList.add('swiss-summary-pinned');

    var ownName=sourceRow.querySelector('.name[data-player-id]');
    if(!ownName)return;
    ownName.classList.add('swiss-summary-anchor');
    if(pinned)ownName.classList.add('swiss-summary-pinned');
  }

  function focusSwissOpponents(cell,pinned){
    clearSwissFocus();
    var table=cell.closest('table.swiss-rank');
    var sourceRow=cell.closest('tr');
    if(!table||!sourceRow)return;

    markSummaryAnchor(cell,sourceRow,!!pinned);

    var ids=opponentIds(cell);
    focusOpponentNames(table,sourceRow,ids);

    var label=headerLabel(table,cell.cellIndex);
    if(!/^輔[一二三四五六七]$/.test(label))return;
    cell.classList.add('swiss-hover-source');

    if(label==='輔一')focusOpponentColumn(table,sourceRow,ids,'總分',false);
    else if(label==='輔二')focusOpponentColumn(table,sourceRow,ids,'總分',true);
    else if(label==='輔三')focusHeadToHead(table,sourceRow);
    else if(label==='輔四')focusOpponentColumn(table,sourceRow,ids,'輔一',false);
    else if(label==='輔五')focusOpponentColumn(table,sourceRow,ids,'輔二',false);
    else if(label==='輔六')focusOpponentColumn(table,sourceRow,ids,'輔一',true);
    else if(label==='輔七')focusOpponentColumn(table,sourceRow,ids,'輔二',true);
  }

  function pinSwissSummary(cell){
    pinnedSwissSummary=cell;
    focusSwissOpponents(cell,true);
  }

  function releasePinnedSwissSummary(clickTarget){
    pinnedSwissSummary=null;
    clearSwissFocus();

    var summaryCell=clickTarget&&clickTarget.closest?clickTarget.closest('.swiss-summary-cell[data-opponents]'):null;
    if(summaryCell)focusSwissOpponents(summaryCell,false);
  }

  function hasPinnedSwissFocus(){
    return !!(pinnedSwissSummary||pinnedSwissGame);
  }

  document.addEventListener('mouseover',function(e){
    var cross=e.target.closest&&e.target.closest('.cross-result[data-pair]');
    if(cross){focusPair(cross);return;}

    var gameCell=e.target.closest&&e.target.closest('.swiss-game-cell[data-game-key]');
    if(gameCell){
      if(!hasPinnedSwissFocus())focusSwissGame(gameCell,false);
      return;
    }

    var summaryCell=e.target.closest&&e.target.closest('.swiss-summary-cell[data-opponents]');
    if(summaryCell){
      if(!hasPinnedSwissFocus())focusSwissOpponents(summaryCell,false);
      return;
    }

    var head=e.target.closest&&e.target.closest('[data-tooltip]');
    if(head&&window.matchMedia('(hover:hover)').matches)showTip(head);
  });

  document.addEventListener('mouseout',function(e){
    var cross=e.target.closest&&e.target.closest('.cross-result[data-pair]');
    if(cross&&!cross.contains(e.relatedTarget))clearPairs();

    var gameCell=e.target.closest&&e.target.closest('.swiss-game-cell[data-game-key]');
    if(gameCell&&!hasPinnedSwissFocus()){
      var next=e.relatedTarget&&e.relatedTarget.closest?e.relatedTarget.closest('.swiss-game-cell[data-game-key]'):null;
      if(!next||next.getAttribute('data-game-key')!==gameCell.getAttribute('data-game-key')||next.getAttribute('data-player')!==gameCell.getAttribute('data-player'))clearSwissFocus();
    }

    var summaryCell=e.target.closest&&e.target.closest('.swiss-summary-cell[data-opponents]');
    if(summaryCell&&!hasPinnedSwissFocus()){
      var nextSummary=e.relatedTarget&&e.relatedTarget.closest?e.relatedTarget.closest('.swiss-summary-cell[data-opponents]'):null;
      if(!nextSummary)clearSwissFocus();
    }

    var head=e.target.closest&&e.target.closest('[data-tooltip]');
    if(head&&window.matchMedia('(hover:hover)').matches)removeTip();
  });

  document.addEventListener('click',function(e){
    if(pinnedSwissGame){
      releasePinnedSwissGame();
      removeTip();
      return;
    }

    if(pinnedSwissSummary){
      releasePinnedSwissSummary(e.target);
      removeTip();
      return;
    }

    var gameCell=e.target.closest&&e.target.closest('.swiss-game-cell[data-game-key]');
    if(gameCell){
      pinSwissGame(gameCell);
      removeTip();
      return;
    }

    var summaryCell=e.target.closest&&e.target.closest('.swiss-summary-cell[data-opponents]');
    if(summaryCell){
      pinSwissSummary(summaryCell);
      removeTip();
      return;
    }

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
})();