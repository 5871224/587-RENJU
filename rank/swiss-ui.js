(function(){
  function clearPairs(){document.querySelectorAll('.cross-result.pair-focus').forEach(function(el){el.classList.remove('pair-focus');});}
  function focusPair(el){clearPairs();var pair=el.getAttribute('data-pair');if(!pair)return;document.querySelectorAll('.cross-result[data-pair="'+pair+'"]').forEach(function(x){x.classList.add('pair-focus');});}
  document.addEventListener('mouseover',function(e){var cell=e.target.closest&&e.target.closest('.cross-result[data-pair]');if(cell)focusPair(cell);});
  document.addEventListener('mouseout',function(e){var cell=e.target.closest&&e.target.closest('.cross-result[data-pair]');if(cell&&!cell.contains(e.relatedTarget))clearPairs();});
  document.addEventListener('click',function(e){var cell=e.target.closest&&e.target.closest('.cross-result[data-pair]');if(cell){focusPair(cell);return;}var head=e.target.closest&&e.target.closest('[data-tooltip]');if(head){showTip(head);return;}removeTip();});
  document.addEventListener('mouseover',function(e){var head=e.target.closest&&e.target.closest('[data-tooltip]');if(head&&window.matchMedia('(hover:hover)').matches)showTip(head);});
  document.addEventListener('mouseout',function(e){var head=e.target.closest&&e.target.closest('[data-tooltip]');if(head&&window.matchMedia('(hover:hover)').matches)removeTip();});
  function removeTip(){var old=document.querySelector('.swiss-tooltip');if(old)old.remove();}
  function showTip(el){removeTip();var text=el.getAttribute('data-tooltip');if(!text)return;var tip=document.createElement('div');tip.className='swiss-tooltip';tip.textContent=text;document.body.appendChild(tip);var r=el.getBoundingClientRect(),w=tip.offsetWidth,h=tip.offsetHeight;var left=Math.min(window.innerWidth-w-8,Math.max(8,r.left+r.width/2-w/2));var top=r.bottom+8;if(top+h>window.innerHeight-8)top=Math.max(8,r.top-h-8);tip.style.left=left+'px';tip.style.top=top+'px';}
})();
