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
})();
