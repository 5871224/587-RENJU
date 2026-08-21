/* 黃星音效：Web Audio API 音階（每題從 C4 依次） */
var NOTE_FREQS = {
  'C4': 261.63, 'D4': 293.66, 'E4': 329.63, 'F4': 349.23, 'G4': 392.00, 'A4': 440.00, 'B4': 493.88,
  'C5': 523.25, 'D5': 587.33, 'E5': 659.25, 'F5': 698.46, 'G5': 783.99, 'A5': 880.00, 'B5': 987.77,
  'C6': 1046.50, 'D6': 1174.66, 'E6': 1318.51, 'F6': 1396.91, 'G6': 1567.98
}
var YELLOW_STAR_NOTES = ['C4', 'D4', 'E4', 'F4', 'G4', 'A4', 'B4', 'C5', 'D5', 'E5', 'F5', 'G5', 'A5', 'B5', 'C6', 'D6', 'E6', 'F6', 'G6']

var WIN_CHORDS = [
  ['C4', 'E4', 'G4'],   // 1
  ['D4', 'F4', 'A4'],   // 2
  ['E4', 'G4', 'B4'],   // 3
  ['F4', 'A4', 'C5'],   // 4
  ['G4', 'B4', 'D5'],   // 5
  ['A4', 'C5', 'E5'],   // 6
  ['B4', 'D5', 'F5'],   // 7
  ['C5', 'E5', 'G5'],   // 8
  ['D5', 'F5', 'A5'],   // 9
  ['E5', 'G5', 'B5'],   // 10
  ['F5', 'A5', 'C6'],   // 11
  ['G5', 'B5', 'D6'],   // 12
  ['A5', 'C6', 'E6'],   // 13
  ['B5', 'D6', 'F6'],   // 14
  ['C6', 'E6', 'G6']    // 15
]

function getAudioContextReady() {
  var ctx = window.__puzzleAudioContext
  if (!ctx) { window.__puzzleAudioContext = new (window.AudioContext || window.webkitAudioContext)(); ctx = window.__puzzleAudioContext }
  if (ctx.state === 'suspended') { return ctx.resume().then(function () { return ctx }) }
  return Promise.resolve(ctx)
}

function playWinChord($board) {
  getAudioContextReady().then(function (ctx) {
    try {
      var chordIndex = Math.min($board.data('yellowStarNoteIndex') || 0, 15)  // 黃星
      var notes = WIN_CHORDS[chordIndex]
      var dur = 0.5
      var gainNode = ctx.createGain()
      gainNode.gain.setValueAtTime(1.0, ctx.currentTime)
      gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + dur)
      gainNode.connect(ctx.destination)
      for (var i = 0; i < notes.length; i++) {
        var osc = ctx.createOscillator()
        osc.type = 'sine'
        osc.frequency.value = NOTE_FREQS[notes[i]]
        osc.connect(gainNode)
        osc.start(ctx.currentTime)
        osc.stop(ctx.currentTime + dur)
      }
    } catch (e) { }
  }).catch(function () { })
}

function playYellowStarNote($board) {
  getAudioContextReady().then(function (ctx) {
    try {
      var idx = $board.data('yellowStarNoteIndex') || 0
      var freq = idx >= 15 ? NOTE_FREQS['B5'] : NOTE_FREQS[YELLOW_STAR_NOTES[idx]]
      $board.data('yellowStarNoteIndex', idx + 1)
      var osc = ctx.createOscillator()
      var gain = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq
      osc.connect(gain)
      gain.connect(ctx.destination)
      gain.gain.setValueAtTime(1.0, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.2)
    } catch (e) { }
  }).catch(function () { })
}

function playPlacementC4($board) {
  getAudioContextReady().then(function (ctx) {
    try {
      var freq = NOTE_FREQS['C4']
      var osc = ctx.createOscillator()
      var gain = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq
      osc.connect(gain)
      gain.connect(ctx.destination)
      gain.gain.setValueAtTime(1.0, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.2)
    } catch (e) { }
  }).catch(function () { })
}

function playErrorSound($board) {
  getAudioContextReady().then(function (ctx) {
    try {
      var startTime = ctx.currentTime
      var thud = ctx.createOscillator()
      var thudGain = ctx.createGain()
      thud.connect(thudGain)
      thudGain.connect(ctx.destination)
      thud.frequency.value = 150
      thud.type = 'triangle'
      thudGain.gain.setValueAtTime(1.0, startTime)
      thudGain.gain.exponentialRampToValueAtTime(0.01, startTime + 0.1)
      thud.start(startTime)
      thud.stop(startTime + 0.1)
      var noiseBuffer = ctx.createBuffer(1, ctx.sampleRate * 0.05, ctx.sampleRate)
      var noiseData = noiseBuffer.getChannelData(0)
      for (var i = 0; i < noiseData.length; i++) {
        noiseData[i] = (Math.random() * 2 - 1) * 0.2
      }
      var noiseSource = ctx.createBufferSource()
      noiseSource.buffer = noiseBuffer
      var noiseGain = ctx.createGain()
      noiseGain.gain.setValueAtTime(1.0, startTime)
      noiseGain.gain.exponentialRampToValueAtTime(0.01, startTime + 0.05)
      noiseSource.connect(noiseGain)
      noiseGain.connect(ctx.destination)
      noiseSource.start(startTime)
    } catch (e) { }
  }).catch(function () { })
}

function playError5Sound($board) {
  getAudioContextReady().then(function (ctx) {
    try {
      var startTime = ctx.currentTime
      var sequence = [
        { freq: 659.25, time: 0, duration: 0.15 },
        { freq: 523.25, time: 0.1, duration: 0.2 }
      ]
      for (var i = 0; i < sequence.length; i++) {
        var note = sequence[i]
        var oscillator = ctx.createOscillator()
        var gainNode = ctx.createGain()
        oscillator.connect(gainNode)
        gainNode.connect(ctx.destination)
        oscillator.frequency.value = note.freq
        oscillator.type = 'triangle'
        var noteStart = startTime + note.time
        gainNode.gain.setValueAtTime(0, noteStart)
        gainNode.gain.linearRampToValueAtTime(1.0, noteStart + 0.01)
        gainNode.gain.exponentialRampToValueAtTime(0.01, noteStart + note.duration)
        oscillator.start(noteStart)
        oscillator.stop(noteStart + note.duration)
      }
    } catch (e) { }
  }).catch(function () { })
}

function board() {
  $('.board587').data({
    'ALLBU': '4',         //棋譜用,紀錄全譜
    'NOWBU': '0',         //棋譜用,紀錄目前盤面譜
    'NEXTBU': '',         //棋譜用,紀錄目前盤面的次一手含分支
    'no': 0,              //紀錄解題數
    'renew': 0,           //紀錄重新次數?
    'alltime': 0,         //紀錄總時間
    'stand': 0,            //紀錄每題標準答題時間
    'TIME': 0,             //紀錄答題開始時間
    'timerId': null        //紀錄即時倒計時定時器 ID
  })

  var ALLBOARD = $("<table class='boardtable'><tr class='TDtitle'><td colspan='2'> \
    <div class='progress rc' style='clear: both'><div class='curRate rc' style='width: 100%; background: rgb(20, 220, 20);'>60.00</div></div> \
    <div class='progress rc'><div class='timeRate rc' style='width: 100%; background: #4DA4FF;'></div></div> \
    <table class='starRate rc' style='width: 100%; border-collapse: collapse; height: 35px; margin-top: 2px;'> \
      <tr> \
        <td id='gstar_cell' style='width: 33.3%;'><font color='#27f72e'>★ 0</font></td> \
        <td id='bstar_cell' style='width: 33.3%;'><font color='#2dbcff'>★ 0</font></td> \
        <td id='ystar_cell' style='width: 33.3%;'><font color='#ffee00'>★ 0</font></td> \
      </tr> \
    </table> \
    </td></tr> \
    <tr><td class='TDboard'><table class='board'></table></td><td class='TDcomment' ></td></tr></table>")
  $('.board587').append(ALLBOARD)
}


function button() {

  var REFRESH = $('<button>').attr({
    'id': 'refresh',
    'type': 'button'
  });

  var GIVEUP = $('<button>').attr({
    'id': 'giveup',
    'type': 'button'
  });

  var SET = $("<input class='edit' type='button' value='Set' check='0' style='display: none;'><br /><div class='setdiv' style='display: none;'><hr>\
    <input class='CBtitle' type='button' value='Title' check='1'>　\
    <input class='CBcomment' type='button' value='Comment' check='1' style='width: 95px'>　\
    <input class='Tboardsize' type='text' value='15' style='width: 30px'>BoardSize\
    <br />\
    <input class='CBnum' type='button' value='①' check='1'>　\
    <input class='Tnumminus' type='text' value='0' style='width: 30px'>NumMinus　\
    <input class='Tnumhide' type='text' value='0' style='width: 30px'>NumHide</div>");

  var CONTROLS = $("<div class='controls'></div>").append(REFRESH, GIVEUP);
  $('.board587').append(CONTROLS, SET)
}

function win() {
  $('.timeRate').stop()   //暫停時間
  $('.board587').data('EDIT', false)   //禁止落子
  if ($('.timeRate').css('width') != '0px') {  //若每題標準時間沒用完,則給藍星
    puzzlearr[$('.board587').data('no')][7] += 'B'
    $('.timeRate').html("<font style='color:#5ed9ff;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>★</font>")
  }
  if (puzzlearr[$('.board587').data('no')][8] != 'F' && puzzlearr[$('.board587').data('no')][8] != '') {  //沒有放棄給黃星
    puzzlearr[$('.board587').data('no')][7] += 'Y'
    $('font').stop(true, true).show()
  }
  clearInterval($('.board587').data('timerId')) //停止即時倒計時
  updateStars() //更新星星統計
}

function updateStars() {
  var gstar = 0
  var bstar = 0
  var ystar = 0
  var currentNo = $('.board587').data('no')

  if (currentNo > 0) {
    for (var i = 1; i <= currentNo; i++) {
      if (!puzzlearr[i]) continue
      // 排除棄權 (P)
      if (puzzlearr[i][7].indexOf('P') === -1) {
        // 綠星：只要不是棄權，且 (該題已記錄時間 OR 該題是目前正在贏的題目)
        if (puzzlearr[i][5] > 0 || (i === currentNo && $('.board587').data('EDIT') === false)) {
          gstar++
        }

        if (puzzlearr[i][7].indexOf('B') !== -1) {
          bstar++
        }

        if (puzzlearr[i][7].indexOf('Y') !== -1) {
          ystar += puzzlearr[i][2] * 1 - 1
        }
      } else {
        // 如果標記了 P，則不計入綠星
      }
    }
  }

  $('#gstar_cell').html("<font color='#27f72e'>★ " + gstar + "</font>")
  $('#bstar_cell').html("<font color='#2dbcff'>★ " + bstar + "</font>")
  $('#ystar_cell').html("<font color='#ffee00'>★ " + ystar + "</font>")
}

function startCountdown() {
  var $board = $('.board587')
  clearInterval($board.data('timerId'))
  var timerId = setInterval(function () {
    var startTime = $board.data('TIME')
    if (!startTime) return
    var elapsedThisPuzzle = (new Date().getTime() - startTime - 500) / 1000
    if (elapsedThisPuzzle < 0) elapsedThisPuzzle = 0
    var totalElapsed = $board.data('alltime') + elapsedThisPuzzle
    var remaining = 60 - totalElapsed
    if (remaining < 0) remaining = 0

    // 更新文字與進度條
    $('.curRate').html(remaining.toFixed(2))
    $('.curRate').css('width', (remaining / 60 * 100) + '%')

    if (remaining <= 0) {
      clearInterval(timerId)
      $('.curRate').html('0.00')
      $('.curRate').css('width', '0%')
    }
  }, 10)
  $board.data('timerId', timerId)
}


function readset() {

  if ($('.board587').data('no') > 0) {
    puzzlearr[$('.board587').data('no')][5] = (new Date().getTime() - $('.board587').data('TIME') - 500) / 1000 //紀錄解題時間
    if (puzzlearr[$('.board587').data('no')][6]) {  //本是紀錄本題開始到落第一子時間
      puzzlearr[$('.board587').data('no')][6] = puzzlearr[$('.board587').data('no')][5] - puzzlearr[$('.board587').data('no')][6]  //改成下第一子到解完題的時間
    }

    var point = Math.round(puzzlearr[$('.board587').data('no')][5] / puzzlearr[$('.board587').data('no')][2] * 60)  //用顏色表示上一題表現

    if (point > 1000) { point = 1000 }

    if (point <= 200) {
      var fontcolor = "rgb(220," + (20 + point) + ", 20);'"
    } else if (point <= 400) {
      var fontcolor = "rgb(" + (420 - point) + ", 220, 20);'"
    } else if (point <= 600) {
      var fontcolor = "rgb(20, 220," + (point - 380) + ");'"
    } else if (point <= 800) {
      var fontcolor = "rgb(20," + (820 - point) + ", 220);'"
    } else {
      var fontcolor = "rgb(" + (point - 780) + ", 20, 220);'"
    }
    // $('.curRate').attr('style','width:'+ (Math.round($('.board587').data('no')-1)/puzzlen*100)+'%;background:'+fontcolor)
    // $('.curRate').animate({width:(Math.round($('.board587').data('no')/puzzlen*100)+'%')})
    // $('.curRate').html($('.board587').data('no')+'/'+puzzlen)
    $('.curRate').attr('style', 'width:' + ((60 - $('.board587').data('alltime')) / 60 * 100) + '%;background:' + fontcolor) //剩餘時間條
    $('.board587').data('alltime', $('.board587').data('alltime') + puzzlearr[$('.board587').data('no')][5]) //紀錄總用時
    // $('.curRate').animate({ width: ((60 - $('.board587').data('alltime')) / 60 * 100 + '%') }) // 移除 animate，改由 startCountdown 即時控制
    $('.curRate').html((60 - $('.board587').data('alltime')).toFixed(2))
  }

  $('.board587').data('no', $('.board587').data('no') + 1) //題號+1
  $('.board587').data('yellowStarNoteIndex', 0) // 每題黃星音階從 C4 重新開始

  if ($('.board587').data('no') > puzzlen || ($('.board587').data('alltime') >= 60 && $('.board587').data('no') > 0)) { //題號超過總題數,或時間已用盡時,則結束算分
    clearInterval($('.board587').data('timerId')) // 停止即時倒計時
    var result = ''
    var standtime = 0
    var onemovetime = 0
    var totaltime = 0
    var standonemovetime = 0
    var ystar = 0
    var bstar = 0
    var gstar = 0
    var right = 0
    for (var i = 1; i <= $('.board587').data('no') - 1; i++) {
      var rowContent = "";
      gstar++
      if (puzzlearr[i][7].indexOf('P') != -1) {
        rowContent = "<td style='text-align:right'>" + i + "：</td><td></td><td>　<font color='#FF0000'>✖</font>";
        gstar--
      } else {
        right += (puzzlearr[i][4] / 1.037) / ($('.board587').data('no') - 1) / puzzlearr[i][2]
        rowContent = "<td style='text-align:right'>" + i + "：</td><td style='text-align:right'>" + puzzlearr[i][5].toFixed(3) + "</td><td><font color='#27f72e'>　★</font>"

        if (puzzlearr[i][7].indexOf('B') != -1) {
          rowContent += "<font color='#2dbcff'>★</font>"
          bstar++
        }

        if (puzzlearr[i][7].indexOf('Y') != -1) {
          rowContent += "<font color='#ffee00'>★" + (puzzlearr[i][2] * 1 - 1) + "</font>"
          ystar += puzzlearr[i][2] * 1 - 1
        }

        if (puzzlearr[i][7].indexOf('R') != -1) {
          rowContent += "<font color=red>↻</font>"
        }

        onemovetime += puzzlearr[i][6] * 1
        standonemovetime += puzzlearr[i][4] * 1

        standtime += puzzlearr[i][3] * 1 + puzzlearr[i][4] * 1
      }
      totaltime += puzzlearr[i][5] * 1
      result += "<tr>" + rowContent + "</td><td>　<a href='https://587.renju.org.tw/5.php?" + puzzlearr[i][1] + "&heading=F&comment=F&num=0' target='new'>" + "<font size='3' color='#ffffff'>題目:" + puzzlearr[i][0] + "</font></a></td></tr>"
    }
    var exact = Math.round(right * 100 * Math.pow(0.95, $('.board587').data('renew')))
    var speed = Math.round(Math.pow(standtime / totaltime, (1 / 2)) * (1 + bstar / ($('.board587').data('no') - 1)) * 100)
    var depth = 100
    if (isNaN(speed)) {
      speed = 0
    }

    if (onemovetime != 0) {
      depth = Math.round(Math.pow((standonemovetime + ystar * 0.5) / onemovetime, (1 / 1.5)) * 100)
    } else if (exact == 0) {
      depth = 0
    }

    var point = Math.round(totaltime / standtime * 60)
    var AT = Math.round(totaltime * 1000) / 1000
    if (gstar != 0) {
      var AVG = Math.round(totaltime / gstar * 1000) / 1000
    } else {
      var AVG = 0
    }


    if (point > 1000) { point = 1000 }

    if (point <= 200) {
      var fontcolor = " style='color: rgb(220," + (20 + point) + ", 20);'"
    } else if (point <= 400) {
      var fontcolor = " style='color: rgb(" + (420 - point) + ", 220, 20);'"
    } else if (point <= 600) {
      var fontcolor = " style='color: rgb(20, 220," + (point - 380) + ");'"
    } else if (point <= 800) {
      var fontcolor = " style='color: rgb(20," + (820 - point) + ", 220);'"
    } else {
      var fontcolor = " style='color: rgb(" + (point - 780) + ", 20, 220);'"
    }
    if (!puzzleS) { puzzleS = 1 }
    if (!puzzleB) { puzzleB = 1 }

    $.ajax({
      url: "puzzlerank.php",
      type: "POST",
      data: {
        TYPE: puzzletype,
        NAME: nickname,
        G: exact,
        GS: gstar,
        B: speed,
        BS: bstar,
        Y: depth,
        YS: ystar,
        S: Math.round(exact * speed * depth / 100),
        AT: AT,
        AVG: AVG,
        LEVEL: puzzleS + '~' + puzzleB
      },
      dataType: "json",
      success: function (RANK) {

        $('.board587').data('RANK', RANK)
        end()

      },
      error: function (RANK) {
        console.log('error')
        console.log(RANK)
        end()
      }
    });

    function end() {
      $('.board587').data('EDIT', false)
      $('.board587').fadeOut('7000', function () {
        $('#result').html('準度： <font color=#27f72e><b>' + exact + '</b>　★ × ' + gstar + '</font><br/ > \
                          速度： <font color=#2dbcff><b>'+ speed + '</b>　★ × ' + bstar + '</font><br/ > \
                          深度： <font color=#ffee00><b>'+ depth + '</b>　★ × ' + ystar + '</font><br/ > \
                          <b>總分： <font color=#ff8307>'+ Math.round(exact * speed * depth / 100) + '</font><br/ > \
                          排名： <font color=#ff44fb>'+ $('.board587').data('RANK')[0] + '</font></b> / ' + $('.board587').data('RANK')[1] + '　<font color=#ff44fb>(' + Math.round($('.board587').data('RANK')[0] * 1000 / $('.board587').data('RANK')[1]) / 10 + ' %)</font><hr/ > \
                          <font'+ fontcolor + '>用時： <b>' + AT + '</b><br/ > \
                        '+ '平均： <b>' + AVG + '</b></font><br/ ><table>' + result + '</table>')

        $('#result').fadeIn('5000');
        $("#TL").fadeIn('5000');
        $('#again').fadeIn('5000');
        $('#menu').fadeIn('5000');

      })
    }

    return
  }

  function getUrlParam(name) {
    if (puzzlearr == null) { return undefined; }
    var reg = new RegExp('(^|&)' + name + '=([^&]*)(&|$)');
    var r = puzzlearr[$('.board587').data('no')][1].match(reg);
    if (r != null) return (r[2]);
    return undefined;
  }

  var boardsize = getUrlParam('boardsize');
  var move = getUrlParam('move');
  var num = getUrlParam('num');
  var numminus = getUrlParam('numminus');
  var numhide = getUrlParam('numhide');
  // var edit = getUrlParam('edit');
  var heading = getUrlParam('heading');
  var comment = getUrlParam('comment');


  if (!isNaN(boardsize)) {
    boardsize = Math.floor(boardsize)

    if (boardsize < 2) {
      boardsize = 2
    } else if (boardsize > 19) {
      boardsize = 19
    }

  } else {
    boardsize = 15
  }
  $('.boardtable').newboard(boardsize)

  var random = Math.floor(Math.random() * 4) //隨機盤面方向
  for (var i = 0; i < random; i++) {
    $('table').counterclockwise()
  }
  var random = Math.floor(Math.random() * 2)
  for (var i = 0; i < random; i++) {
    $('table').swapleftright()
  }


  // if (edit == 'T') {
  //   $("[value='Edit']").attr('check', '1')
  //   $(this).children('.edit').removeAttr('style')
  //   $(this).find('textarea').prop('readonly', false)
  // }

  if (heading == 'F') {
    $('.TDtitle').attr('style', 'display: none;')
    $('.CBtitle').attr('check', '0')
  }

  if (comment == 'F') {
    $('.TDcomment').attr('style', 'display: none;')
    $('.CBcomment').attr('check', '0')
  }

  $('.CBnum').attr('check', '0') //不顯示手順

  if (move != '') {
    $('.board587').data('ALLBU', move)
    $('.boardtable').readBU()
    $('.boardtable').resetboard()
    $('.boardtable').nextstep(false, true)
    $('.boardtable').sharetext()
    $('.board587').data('BW', NOW.length % 2)
    // $('.alert').stop(true,true).hide()
    if (NOW.length % 2 == 1) {
      $('.board587').addClass('black-turn')

    } else {
      $('.board587').removeClass('black-turn')

    }
    $('.board587').data('EDIT', true)

  }

  $('.board587').each(function () {

    if ($(this).attr('boardsize')) {
      boardsize = $(this).attr('boardsize')

      if ((!isNaN(Math.floor(boardsize))) || boardsize < 2 || boardsize > 19) {
        $(this).children('.boardtable').newboard(boardsize)

      }
    }

    if ($(this).attr('heading') == 'F') {
      $(this).find('.TDtitle').attr('style', 'display: none;')
      $(this).find('.CBtitle').attr('check', '0')
    }

    if ($(this).attr('comment') == 'F') {
      $(this).find('.TDcomment').attr('style', 'display: none;')
      $(this).find('.CBcomment').attr('check', '0')
    }

    if ($(this).attr('num') == '0') {
      $(this).find('.CBnum').attr('check', '0')
      $(this).find('.Tnumhide').prop('disabled', true)
      $(this).find('.Tnumminus').prop('disabled', true)
    }

    if ($(this).attr('numminus')) {
      $(this).find('.Tnumminus').val($(this).attr('numminus'))
    }

    if ($(this).attr('numhide')) {
      $(this).find('.Tnumhide').val($(this).attr('numhide'))
    }

    if ($(this).attr('move')) {
      $(this).data('ALLBU', $(this).attr('move'))
      $(this).children('.boardtable').readBU()
      $(this).children('.boardtable').resetboard()
      $(this).children('.boardtable').nextstep(false, true)
      $(this).children('.boardtable').sharetext()

    }

    if ($(this).attr('edit') == 'T' || $(this).data('ALLBU') == '4') {
      //$("[value='Edit']").attr('check', '1')
      $(this).children('.edit').removeAttr('style')
      $(this).find('textarea').prop('readonly', false)
    }

  })

  //$('.alert').show().delay(1500).fadeOut();
  $('.board587').data('stand', Math.round((puzzlearr[$('.board587').data('no')][3] * 1 + puzzlearr[$('.board587').data('no')][4] * 1) * 1000) / 1000)
  $('.timeRate').stop(true, true).css({ width: '100%' }).html($('.board587').data('stand'))
  $('.timeRate').animate({ width: '0%' }, $('.board587').data('stand') * 1000)
  $('.board587').data('TIME', new Date().getTime())
  startCountdown()
}


function clickbutton() {

  $('.board587').delegate('*', 'touchend', function (e) {
    // $('*').blur()
    e.preventDefault()
    $(this).click()
  })

  $("#start").click(function () {
    if ($('#nickname').val().trim() != '') {

      var date = new Date();
      date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000)); // 設置 cookie 的有效期為 30 天
      document.cookie = "nickname=" + encodeURIComponent(nickname) + "; expires=" + date.toUTCString() + "; path=/; domain=.587.renju.org.tw;";

      $("#start").hide()
      $("#menu").hide()
      $("#TL").hide()
      $("#name").hide()
      $('.timeRate').stop(true, true).css({ width: '100%' }).val($('.board587').data('stand'))
      $('.timeRate').animate({ width: '0%' }, $('.board587').data('stand') * 1000)
      //$('.alert').show().delay(1500).fadeOut();
      $(".board587").fadeIn(function () {
        $(".board587").data("TIME", new Date().getTime())
        startCountdown()
      })
    } else {
      alert("請填Nickname");
    }
  });

  $("#refresh").click(function () {

    $(this).readBU()
    $(this).resetboard()
    $(this).nextstep()
    $(this).sharetext()
    $('.board587').data('renew', $('.board587').data('renew') + 1)
    $('.board587').data('EDIT', true)
    $('[XY]').text('')
    puzzlearr[$('.board587').data('no')][7] += 'R'

  })

  $('#giveup').click(function () {
    puzzlearr[$('.board587').data('no')][7] += 'P'  //紀錄PASS
    $('.board587').data('TIME', $('.board587').data('TIME') - 500)
    readset()
  })

  $('#again').click(function () {
    var $form = $('<form method="POST" action="puzzle.php"></form>');
    $form.append($('<input type="hidden" name="type">').val(puzzletype));

    if (puzzletype == 'VC4') {
      $form.append($('<input type="hidden" name="S">').val(puzzleS));
      $form.append($('<input type="hidden" name="B">').val(puzzleB));
    }

    $('body').append($form);
    $form.submit();
  })

  $('#nickname').change(function () {
    $('#nickname').val($('#nickname').val().slice(0, 20))
    nickname = $('#nickname').val();
  });

}


function clickboard() {

  $('.TDboard').mouseover(function (event) {
    document.oncontextmenu = function () {
      return false;
    }
  });

  $('.TDboard').mouseleave(function (event) {
    document.oncontextmenu = function () {
      return true;
    }
  });

  $('.TDboard').delegate('[XY]', 'mousedown touchstart', function (event) {
    var $board = $(this).parents('.board587')
    $(this).readBU()
    $board.find('.comment').markcomnotes()

    // 用戶下了一手後，清除盤面上的「？」與「╳」
    $board.find('[XY]').each(function () {
      var $cell = $(this)
      if ($cell.attr('BW')) return
      var html = $cell.html()
      if (html && (html.indexOf('？') !== -1 || html.indexOf('╳') !== -1)) {
        $cell.html('')
      }
    })

    //是否為左鍵
    if ((event.which == '1' || event.which == '0' || (!event.which)) && $board.data('EDIT')) {
      //是否點擊位置已在分支
      if ($(this).attr('BW') == 'N') {
        if (puzzletype == '1M43') { //此題為1M43，下在N點為勝
          playWinChord($board)
          setTimeout(readset, 500)
          win()
          $(this).html("<font style='color:#009C07;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
          return
        }
        //其他題型繼續
        for (var i = 0; i < NEXT.length; i++) {
          var NEXTXY = ALL[NEXT[i]].slice(1, 3)
          if ($(this).attr('XY') == NEXTXY) {
            $board.data('NOWBU', NOWBU + '!' + NEXT[i])
          }
        }
        //判斷是否有子在該點
      } else if (!$(this).attr('BW')) {
        if (puzzletype == '1M43') {
          $(this).html("<b><span style='color:red;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>？</span></b>")
          playErrorSound($board)
          return
        }

        if (NOW.length % 2 == 1) {   //此題黑勝
          var boardCache = {}
          $board.find('[XY]').each(function () {
            var xy = $(this).attr('XY')
            if (xy && xy.length >= 2) {
              var bw = $(this).attr('BW')
              boardCache[xy] = (!bw || bw == 'N') ? '2' : bw
            }
          })
          var BTYPE = $(this).forbidden(undefined, undefined, boardCache)

          if ($board.data('BW') == 1) {

            if (BTYPE == $board.data('win')) {
              playWinChord($board)
              setTimeout(readset, 500)
              win()
              $(this).html("<font style='color:#009C07;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
              return

            } else if (BTYPE.indexOf($board.data('limit')) != -1) {

              if (!puzzlearr[$board.data('no')][8]) {
                puzzlearr[$board.data('no')][8] = new Date().getTime()
                $(this).html("<font style='color:#ffb702;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
                $(this).find('font').fadeOut(1000)
                playYellowStarNote($board)
                puzzlearr[$board.data('no')][6] = (puzzlearr[$board.data('no')][8] - $board.data('TIME')) / 1000  //本題開始到落第一子的時間
              } else if (puzzlearr[$board.data('no')][8] != 'F') {

                if (new Date().getTime() - puzzlearr[$board.data('no')][8] < 1000) { //落子時間小於1秒,顯示黃星
                  puzzlearr[$board.data('no')][8] = new Date().getTime()
                  $(this).html("<font style='color:#ffb702;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
                  $(this).find('font').fadeOut(1000)
                  playYellowStarNote($board)
                } else {
                  puzzlearr[$board.data('no')][8] = 'F' //超過一秒,標記
                }
              }

            } else if (BTYPE == 'X') {
              return
            } else {
              $(this).html("<b><span style='color:red;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>？</span></b>")
              playErrorSound($board)
              return
            }
          } else {

            if (BTYPE == 'X') {   //判斷禁手
              playWinChord($board)
              setTimeout(readset, 500)
              win()
              $(this).html("<font style='color:#009C07;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
              return

            } else if (BTYPE == 5) {
              playError5Sound($board)
              $board.data('EDIT', false)
              $(this).attr('BW', 1).html("<b><font style='color:#FF0000;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>5</font></b>")
              return
            }
          }

        } else if (NOW.length % 2 == 0) { //此題白勝
          var WTYPE = $(this).whitetype()

          if ($board.data('BW') == 0) {
            if (WTYPE == $board.data('win')) {
              playWinChord($board)
              setTimeout(readset, 500)
              win()
              $(this).html("<font style='color:#009C07;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
              return

            } else if (WTYPE == $board.data('limit')) {

              if (!puzzlearr[$board.data('no')][8]) {
                puzzlearr[$board.data('no')][8] = new Date().getTime()
                $(this).html("<font style='color:#ffb702;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
                $(this).find('font').fadeOut(1000)
                playYellowStarNote($board)
                puzzlearr[$board.data('no')][6] = (puzzlearr[$board.data('no')][8] - $board.data('TIME')) / 1000

              } else if (puzzlearr[$board.data('no')][8] != 'F') {

                if (new Date().getTime() - puzzlearr[$board.data('no')][8] < 1000) {
                  puzzlearr[$board.data('no')][8] = new Date().getTime()
                  $(this).html("<font style='color:#ffb702;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'><b>★</b></font>")
                  $(this).find('font').fadeOut(1000)
                  playYellowStarNote($board)
                } else {
                  puzzlearr[$board.data('no')][8] = 'F'
                }
              }

            } else {

              $(this).html("<b><span style='color:red;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>？</span></b>")
              playErrorSound($board)
              return
            }
          } else if (WTYPE == 5) {
            playError5Sound($board)
            $board.data('EDIT', false)
            $(this).attr('BW', 0).html("<b><font style='color:#FF0000;text-shadow: rgb(46, 46, 46) 0px 3px 4px;'>5</font></b>")
            return
          }
        }
        //插入新子（超過一秒後到過關前，每子落子聲用 C4）
        if (puzzlearr[$board.data('no')][8] === 'F') {
          playPlacementC4($board)
        }
        var TREE
        if (ALL[LAST].slice(0, 1) % 4 % 2 == 0) {
          ALL[LAST] = (ALL[LAST].slice(0, 1) * 1 + 1) + ALL[LAST].slice(1)
          TREE = '0'
        } else {
          TREE = '2'
        }
        NEWMOVE = TREE + $(this).attr('XY')
        ALL.splice(LAST + 1, 0, NEWMOVE)
        $(this).parents('.board587').data('ALLBU', ALL.join('!'))

        //盤面譜加最新一子
        $(this).parents('.board587').data('NOWBU', NOWBU + '!' + (LAST + 1))
      } else { //若已有子在該點
        return
      }

      $(this).parents('.board587').find('.boardtable').nextstep(true)
      $(this).sharetext()

      if (event.which == '1' || event.which == '0') {
        var AUTO54 = $(this).AI54()
        if (AUTO54) {
          $(AUTO54).mousedown()
        }

      }
    }

  })
}


function autogrow(textarea) {

  var adjustedHeight = textarea.clientHeight;

  adjustedHeight = Math.max(textarea.scrollHeight, adjustedHeight);

  if (adjustedHeight != textarea.clientHeight) {
    textarea.style.height = adjustedHeight + 'px';

  }
}

(function ($) {

  // 模組級常數，避免 blacktype/whitetype 每次呼叫重複建立陣列
  var BLACKTYPE_V4 = ['21111', '11112', '12111', '11121', '11211']
  var BLACKTYPE_V3 = ['221112', '212112', '211212', '211122']
  var WHITETYPE_V4 = ['20000', '00002', '02000', '00020', '00200']

  $.fn.readBU = function () {

    var $thisdiv = $(this).parents('.board587')

    ALLBU = $thisdiv.data('ALLBU')
    NOWBU = $thisdiv.data('NOWBU')
    NEXTBU = $thisdiv.data('NEXTBU')
    ALL = (ALLBU + '').split('!')
    NOW = (NOWBU + '').split('!')
    NEXT = (NEXTBU + '').split('!')
    LAST = NOW[NOW.length - 1] * 1
  }


  $.fn.resetboard = function () {

    var $thisdiv = $(this).parents('.board587')
    var title = unescape(ALL[0].split('^')[0]).slice(1)
    $thisdiv.find('.title').val(title)

    for (var i = 0; i < ALL.length; i++) {
      if (ALL[i].slice(0, 1) > 3) {
        break
      }
    }
    if (i == ALL.length) {
      i = 0
    }
    $thisdiv.data('NOWBU', i)

    var TREE = 0

    for (i -= 1; i >= 0; i--) {

      if (ALL[i].slice(0, 1) % 4 == 0) {
        TREE += 1
      } else if (ALL[i].slice(0, 1) % 4 == 1) {
        if (TREE == 0) {
          $thisdiv.data('NOWBU', i + '!' + $thisdiv.data('NOWBU'))
        }
      } else if (ALL[i].slice(0, 1) % 4 == 3) {
        if (TREE == 0) {
          $thisdiv.data('NOWBU', i + '!' + $thisdiv.data('NOWBU'))
        } else {
          TREE -= 1
        }
      }
    }
  }

  $.fn.markcomnotes = function () {
    var $this = $(this)
    var MARKXY = $this.attr('XY')
    var LASTDATA = ALL[LAST].split('^')
    //該子若有標記
    if (LASTDATA[1]) {

      var LASTMARK = LASTDATA[1].split('|')

      for (var i = 0; i < LASTMARK.length; i++) {

        if (LASTMARK[i].slice(0, 2) == MARKXY) {

          if (!$this.text() == '') {
            LASTMARK[i] = MARKXY + escape($this.text())
          } else if ($this.val() != '') {
            LASTMARK[i] = MARKXY + escape($this.val())
          } else {
            LASTMARK.splice(i, 1)
          }
          break
        }
      }

      if (i == LASTMARK.length) {
        if ($this.text() != '') {
          LASTMARK[i] = MARKXY + escape($this.text())
        } else if ($this.val() != '') {
          LASTMARK[i] = MARKXY + escape($this.val())
        }
      }

      LASTDATA[1] = LASTMARK.join('|')

    } else if ($this.text() != '') {
      LASTDATA[1] = MARKXY + escape($this.text())
    } else if ($this.val() != '') {
      LASTDATA[1] = MARKXY + escape($this.val())
    }


    if (LASTDATA[1] == '') {
      LASTDATA.splice(2)
    }

    ALL[LAST] = LASTDATA.join('^')
    $this.parents('.board587').data('ALLBU', ALL.join('!'))
  }

  $.fn.nextstep = function (animate, animateAll) {
    var $thisdiv = $(this).parents('.board587')

    $(this).nummark(animate, animateAll)
    $thisdiv.data('NEXTBU', '')

    //KEY次一手
    if (ALL[LAST].slice(0, 1) % 4 % 2 != 0) {
      var NEXT1 = (LAST + 1)
      var TREE = 0

      for (var i = LAST + 1; i <= ALL.length - 1; i++) {

        if (ALL[i].slice(0, 1) % 4 == 0) {
          if (TREE == 0) {
            break
          } else if (TREE == 1) {
            NEXT1 += '!' + (i + 1)
            TREE -= 1
          } else {
            TREE -= 1
          }

        } else if (ALL[i].slice(0, 1) % 4 == 1) {
          if (TREE == 0) {
            break
          }

        } else if (ALL[i].slice(0, 1) % 4 == 2) {
          if (TREE == 0) {
            NEXT1 += '!' + (i + 1)
          }

        } else if (ALL[i].slice(0, 1) % 4 == 3) {
          TREE += 1
        }
      }


      $thisdiv.data('NEXTBU', NEXT1)
      NEXT1 = (NEXT1 + '').split('!')

      for (var i = 0; i < NEXT1.length; i++) {
        var NEXTXY = ALL[NEXT1[i]].slice(1, 3)
        $thisdiv.find('[XY=' + NEXTXY + ']').attr('BW', 'N')
      }
    }
  }

  $.fn.nummark = function (animateLast, animateAll) {
    $(this).readBU()

    var $thisdiv = $(this).parents('.board587')
    // $thisdiv.find('[XY]').removeAttr('BW').text('') 換下句
    $thisdiv.find('[XY]').removeAttr('BW').removeClass('ani')
    $thisdiv.find('.comment').val('')
    if (puzzletype == 'X33' || puzzletype == 'X44' || puzzletype == 'X43') {   //一步題目採隨機出現
      var randomX = Math.floor(Math.random() * 7) - 3
      var randomY = Math.floor(Math.random() * 7) - 3
    }

    for (var i = 1; i < NOW.length; i++) {
      var STEPXY = ALL[NOW[i]].slice(1, 3)
      if (STEPXY != '00') {
        if (puzzletype == 'X33' || puzzletype == 'X44' || puzzletype == 'X43') {
          STEPXY = String.fromCharCode(STEPXY.charCodeAt(0) + randomX) + String.fromCharCode(STEPXY.charCodeAt(1) + randomY)
        }
        var $cell = $thisdiv.find('[XY=' + STEPXY + ']')
        $cell.attr('BW', i % 2)
        if (animateAll || (animateLast === true && i === NOW.length - 1)) $cell.addClass('ani')
      }
      //KEY手順
      if ($thisdiv.find('.CBnum').attr('check') == '1') {

        if (i - $thisdiv.find('.Tnumminus').val() > 0 && i > $thisdiv.find('.Tnumhide').val()) {

          if (i != NOW.length - 1) {
            $thisdiv.find('[XY=' + STEPXY + ']').text(i - $thisdiv.find('.Tnumminus').val())
          } else {
            $thisdiv.find('[XY=' + STEPXY + ']').html("<span style='color: #FF00FF'>" + (i - $thisdiv.find('.Tnumminus').val()) + '</span>')
          }

        }
      }

    }

    //KEY標記
    var LASTDATA = ALL[LAST].split('^')[1]
    if (LASTDATA) {
      var MARKDATA = LASTDATA.split('|')
      for (var i = 0; i < MARKDATA.length; i++) {
        var MARKXY = MARKDATA[i].slice(0, 2)
        if ($thisdiv.find('[XY=' + MARKXY + ']').attr('BW')) {
          $thisdiv.find('[XY=' + MARKXY + ']').text(unescape(MARKDATA[i].slice(2)))
        } else if (MARKXY == '12') {
          $thisdiv.find('[XY=' + MARKXY + ']').val(unescape(MARKDATA[i].slice(2)))
        } else {
          $thisdiv.find('[XY=' + MARKXY + ']').html("<span class='m' style='padding:0px 3px'>" + unescape(MARKDATA[i].slice(2)) + '</span>')
        }
      }
    }


  }


  $.fn.sharetext = function () {
    // var $thisdiv = $(this).parents('.board587')
    // var SETMODE = ''
    // if ($thisdiv.find('.Tboardsize').val() != '15') {
    //   SETMODE = '&boardsize=' + $thisdiv.find('.Tboardsize').val()
    // }
    // if ($thisdiv.find('.CBtitle').attr('check') == '0') {
    //   SETMODE += '&heading=F'
    // }
    // if ($thisdiv.find('.CBcomment').attr('check') == '0') {
    //   SETMODE += '&comment=F'
    // }
    // if ($thisdiv.find('.CBnum').attr('check') == '0') {
    //   SETMODE += '&num=0'
    // }
    // if ($thisdiv.find('.Tnumminus').val() != '0') {
    //   SETMODE += '&numminus=' + $thisdiv.find('.Tnumminus').val()
    // }
    // if ($thisdiv.find('.Tnumhide').val() != '0') {
    //   SETMODE += '&numhide=' + $thisdiv.find('.Tnumhide').val()
    // }
    // $thisdiv.find('.sharetext').val('https://587.renju.org.tw/5.php?move=' + $thisdiv.data('ALLBU') + SETMODE)
  }

  $.fn.newboard = function (boardsize) {
    var hi1 = Math.floor(448 / (boardsize * 1) * 0.85)
    var hi2 = Math.floor(448 / (boardsize * 1) * 0.75)
    var cellPx = Math.floor(448 / (boardsize * 1))
    var table = $("<table class='board' style='line-height:" + hi1 + 'px; font-size:' + hi2 + "px; --cell-px:" + cellPx + "px'></table>")

    for (var y = 1; y <= boardsize; y++) {
      var tr = $('<tr></tr>')
      for (var x = 1; x <= boardsize; x++) {
        var td = $('<td></td>')
        td.attr('XY', String.fromCharCode(x + 64) + String.fromCharCode(y + 64))

        if (x == (boardsize + 1) / 2 && y == (boardsize + 1) / 2) {
          td.attr('star', '1')
        }
        if (boardsize > 8 && (x == 4 || x == boardsize - 3) && (y == 4 || y == boardsize - 3)) {
          td.attr('star', '1')
        }

        tr.append(td)
      }
      table.prepend(tr)
    }
    $(this).parents('.board587').find('.board').replaceWith(table)
    $(this).parents('.board587').find('.Tboardsize').val(boardsize)
  }


  $.fn.swapleftright = function () {
    var boardsize = $('.Tboardsize').val() * 1
    $(this).parents('.board587').find('[XY]').each(function () {
      if (!($(this).attr('XY').slice(0, 1) == '0' || $(this).attr('XY').slice(0, 1) == '1')) {
        $(this).attr('XY', String.fromCharCode(boardsize + 65 - $(this).attr('XY').slice(0, 1).charCodeAt() + 64) + $(this).attr('XY').slice(1, 2))
      }
    })
  }

  $.fn.counterclockwise = function () {
    var boardsize = $('.Tboardsize').val() * 1
    $(this).parents('.board587').find('[XY]').each(function () {
      if (!($(this).attr('XY').slice(0, 1) == '0' || $(this).attr('XY').slice(0, 1) == '1')) {
        $(this).attr('XY', String.fromCharCode(boardsize + 65 - $(this).attr('XY').slice(0, 1).charCodeAt() + 64) + $(this).attr('XY').slice(1, 2))
        $(this).attr('XY', $(this).attr('XY').slice(1, 2) + $(this).attr('XY').slice(0, 1))
      }
    })

  }

  $.fn.forbidden = function (Y1, X1, boardCache) {
    var $board = $(this).parents('.board587')
    var boardsize = $('.Tboardsize').val() * 1
    var NOWXY, OLDBW, xy
    if (Y1) {
      var Y = Y1
      var X = X1
      NOWXY = "[XY='" + String.fromCharCode(X + 64) + String.fromCharCode(Y + 64) + "']"
      OLDBW = $board.find(NOWXY).attr('BW')
      $board.find(NOWXY).attr('BW', '1')
      if (boardCache) { xy = String.fromCharCode(X + 64) + String.fromCharCode(Y + 64); boardCache[xy] = '1' }
    }
    else {
      $(this).attr('BW', 1)
      X = $(this).attr('XY').charCodeAt(0) - 64
      Y = $(this).attr('XY').charCodeAt(1) - 64
      if (boardCache) { xy = $(this).attr('XY'); boardCache[xy] = '1' }
    }

    var ss = $(this).range('B', X, Y, boardCache)

    var SIX = false
    var FIVE = false
    var FOUR = ''
    var THREE = ''

    for (var I = 0; I <= 3; I++) {
      var BT = $(this).blacktype(ss[I])

      if (BT == '6') { SIX = true }
      else if (BT == '5') {
        FIVE = true
        break
      }
      else if (BT == '44') { FOUR = FOUR + '44' }
      else if (BT == '4' || BT == 'O4') { FOUR = FOUR + '4' }
      else if (BT == 'O3') { THREE = THREE + I }

    }


    var open3 = 0
    var FB = false
    if (FIVE == true) {
      if (Y1) {
        FB = true
      } else {
        FB = false
      }

    } else if (SIX == true || FOUR.slice(0, 2) == '44') {
      FB = true

    } else if (THREE.length > 1) {
      for (var J = 0; J < THREE.length; J++) {
        var T = THREE.substring(J, J + 1)
        var L = ss[T]
        var LLEN = L.length
        var II = 1
        var I = -5
        if (T == 0) {
          if (X + I < 1) { I = 1 - X }
        } else if (T == 1) {
          if (Y + I < 1) { I = 1 - Y }
        } else if (T == 2) {
          if (Y + I < 1) { I = 1 - Y }
          if (X + I < 1) { I = 1 - X }
        } else if (T == 3) {
          if (Y + I < 1) { I = 1 - Y }
          if (X - I > boardsize) { I = X - boardsize }
        }

        I = I + 1

        do {
          if (L.substring(II, II + 1) == 2) {
            var L1 = L.slice(0, II) + 1 + L.slice(II + 1)

            if ($(this).blacktype(L1) == 'O4') {
              if (T == 0) {
                var Y1 = Y
                var X1 = X + I
              } else if (T == 1) {
                var Y1 = Y + I
                var X1 = X
              } else if (T == 2) {
                var Y1 = Y + I
                var X1 = X + I
              } else if (T == 3) {
                var Y1 = Y + I
                var X1 = X - I
              }
              if ($(this).forbidden(Y1, X1, boardCache) != 'X') {
                open3++
                break
              }

            }

          }
          I = I + 1
          II = II + 1
        } while (II < LLEN)

        if (open3 == 2) {
          FB = true
        } else if (THREE.length - J + open3 < 2) {
          break
        }
      }
    }

    if (NOWXY) {
      if (OLDBW) {
        $(NOWXY).attr('BW', OLDBW)
      } else {
        $(NOWXY).removeAttr('BW')
      }
    } else {
      $("[XY='" + String.fromCharCode(X + 64) + String.fromCharCode(Y + 64) + "']").removeAttr('BW')
    }

    if (FB && (!NOWXY)) {
      playErrorSound($board)
      $("[XY='" + String.fromCharCode(X + 64) + String.fromCharCode(Y + 64) + "']").removeAttr('BW').html("<b><font color='#FF0000'>╳</font></b>")
    }
    if (FB) {
      return 'X'
    } else if (FIVE) {
      return '5'
    } else if (FOUR == '4' && THREE.length == 1) {
      return '43'
    } else if (FOUR == '4') {
      return '4'
    }
    else {
      return 'O'
    }
  }


  $.fn.blacktype = function (CODE) { //code 11碼

    var V4 = ['21111', '11112', '12111', '11121', '11211']
    var V3 = ['221112', '212112', '211212', '211122']

    if (CODE.indexOf('111111') != -1) {
      var GG = '6'
    }
    else if (CODE.indexOf('11111') != -1) {
      var GG = '5'
    }
    else {
      var F = 0
      for (var I = 0; I <= 4; I++) {
        var K = V4[I]
        var KK = CODE.indexOf(K)

        if (KK != -1) {

          if (CODE.substring(KK - 1, KK) != '1' && CODE.substring(KK + 5, KK + 6) != '1') { F = F + 1 }

          if (KK < 3) {
            var KKK = CODE.indexOf(K, KK + 2)
            if (KKK > 2 && KKK < 6) {
              if (CODE.substring(KKK - 1, KKK) != '1' && CODE.substring(KKK + 5, KKK + 6) != '1') { F = F + 1 }
            }
          }
        }

        if (F > 1) {
          if (CODE.indexOf('211112') != -1) { //判斷是活4還是44
            var GG = 'O4'
          }
          else {
            var GG = '44'
          }
          break
        }
      };

      if (F == 1) {
        var GG = '4'
      }
      else {

        for (var I = 0; I <= 3; I++) {
          var L = V3[I]
          var LL = CODE.indexOf(L)

          if (LL != -1) {
            if (CODE.substring(LL - 1, LL) != '1' && CODE.substring(LL + 6, LL + 7) != '1') {
              var GG = 'O3'
              break
            }
          }
        }
      }
    }
    return GG
  }


  $.fn.whitetype = function () { //code 9碼   
    var X = $(this).attr('XY').charCodeAt(0) - 64
    var Y = $(this).attr('XY').charCodeAt(1) - 64
    var ss = $(this).range('W', X, Y)
    var V4 = WHITETYPE_V4

    var FIVE = false
    var FOUR = ''

    for (var I = 0; I <= 3; I++) {
      var CODE = ss[I]
      if (CODE.indexOf('00000') != -1) {
        var GG = '5'
        break
      } else {
        var F = 0
        for (var F = 0; F <= 4; F++) {
          var K = V4[F]
          var KK = CODE.indexOf(K)

          if (KK != -1) {
            var GG = '4'
            break
          }
        }
      }
    }
    return GG
  }

  $.fn.range = function (BW, X, Y, boardCache) {
    var boardsize = $('.Tboardsize').val() * 1
    if (BW == 'B') {
      var R = 5
      var P1 = '1'
      var P2 = '0'
    } else {
      var R = 4
      var P1 = '0'
      var P2 = '1'
    }
    function getCell(x, y) {
      var xy = String.fromCharCode(x + 64) + String.fromCharCode(y + 64)
      if (boardCache && boardCache[xy] !== undefined) {
        return boardCache[xy]
      }
      var N = $("[XY='" + xy + "']").attr('BW')
      if (!N || N == 'N') return '2'
      return N
    }
    var a0 = [], a1 = [], a2 = [], a3 = []
    var N
    for (var I = -R; I <= R; I++) {
      if (X + I < 1) { I = 1 - X }
      if (X + I > boardsize) { break }
      if (I === 0) { N = P1 }
      else {
        N = getCell(X + I, Y)
        if (I > 0 && N === P2) { break }
      }
      a0.push(N)
    }
    for (var I = -R; I <= R; I++) {
      if (Y + I < 1) { I = 1 - Y }
      if (Y + I > boardsize) { break }
      if (I === 0) { N = P1 }
      else {
        N = getCell(X, Y + I)
        if (I > 0 && N === P2) { break }
      }
      a1.push(N)
    }
    for (var I = -R; I <= R; I++) {
      if (Y + I < 1) { I = 1 - Y }
      if (X + I < 1) { I = 1 - X }
      if (Y + I > boardsize || X + I > boardsize) { break }
      if (I === 0) { N = P1 }
      else {
        N = getCell(X + I, Y + I)
        if (I > 0 && N === P2) { break }
      }
      a2.push(N)
    }
    for (var I = -R; I <= R; I++) {
      if (Y + I < 1) { I = 1 - Y }
      if (X - I > boardsize) { I = X - boardsize }
      if (Y + I > boardsize || X - I < 1) { break }
      if (I === 0) { N = P1 }
      else {
        N = getCell(X - I, Y + I)
        if (I > 0 && N === P2) { break }
      }
      a3.push(N)
    }
    return [a0.join(''), a1.join(''), a2.join(''), a3.join('')]
  }


  $.fn.AI54 = function () {
    var boardsize = $('.Tboardsize').val() * 1
    var BOARD = []
    // 【優化1】一次性讀取所有 DOM 數據，避免重複查詢
    var $board = $(this).parents('.board587')
    var $cells = $board.find('[XY]')
    var cellMap = {}

    $cells.each(function () {
      var xy = $(this).attr('XY')
      if (xy && xy.length >= 2) {
        var X = xy.charCodeAt(0) - 64
        var Y = xy.charCodeAt(1) - 64
        if (X >= 1 && X <= boardsize && Y >= 1 && Y <= boardsize) {
          var BW = $(this).attr('BW')
          var val = (BW == 'N' || !BW) ? '2' : BW
          cellMap[xy] = val
          if (!BOARD[Y]) BOARD[Y] = []
          BOARD[Y][X] = val
        }
      }
    })

    // 【優化2】使用陣列而非字串，提高操作效率
    var s0 = []  // 橫向
    var s1 = []  // 直向
    var s2 = []  // 左上斜
    var s3 = []  // 右上斜

    // 建立橫向和直向陣列
    for (var X = 1; X <= boardsize; X++) {
      s1[X] = []
    }

    for (var Y = 1; Y <= boardsize; Y++) {
      s0[Y] = []
      for (var X = 1; X <= boardsize; X++) {
        var val = (BOARD[Y] && BOARD[Y][X]) ? BOARD[Y][X] : '2'
        s0[Y].push(val)
        s1[X].push(val)
      }
    }

    // 建立斜向陣列並預先計算座標映射
    var diag2Map = {}  // 左上斜：I -> [{Y,X}, ...]
    var diag3Map = {}  // 右上斜：I -> [{Y,X}, ...]

    for (var I = -(boardsize - 5); I <= (boardsize - 5); I++) {
      s2[I] = []
      var coords = []
      var Y, X
      if (I <= 0) {
        Y = 1
        X = -I + 1
      } else {
        Y = I + 1
        X = 1
      }
      while (Y <= boardsize && X <= boardsize) {
        var val = (BOARD[Y] && BOARD[Y][X]) ? BOARD[Y][X] : '2'
        s2[I].push(val)
        coords.push({ Y: Y, X: X })
        Y++
        X++
      }
      diag2Map[I] = coords
    }

    for (var I = 6; I <= (boardsize - 2) * 2; I++) {
      s3[I] = []
      var coords = []
      var Y, X
      if (I <= (boardsize + 1)) {
        Y = 1
        X = I - 1
      } else {
        Y = I - boardsize
        X = boardsize
      }
      while (Y <= boardsize && X >= 1) {
        var val = (BOARD[Y] && BOARD[Y][X]) ? BOARD[Y][X] : '2'
        s3[I].push(val)
        coords.push({ Y: Y, X: X })
        Y++
        X--
      }
      diag3Map[I] = coords
    }

    // 【優化3】使用陣列檢查五連和六連，比字串 indexOf 快
    function checkFive(arr, pos, player) {
      var count = 0
      var start = Math.max(0, pos - 4)
      var end = Math.min(arr.length - 1, pos + 4)
      for (var i = start; i <= end; i++) {
        if (arr[i] == player) {
          count++
          if (count == 5) return true
        } else {
          count = 0
        }
      }
      return false
    }

    function checkSix(arr, pos, player) {
      var count = 0
      var start = Math.max(0, pos - 5)
      var end = Math.min(arr.length - 1, pos + 5)
      for (var i = start; i <= end; i++) {
        if (arr[i] == player) {
          count++
          if (count >= 6) return true
        } else {
          count = 0
        }
      }
      return false
    }

    var P1 = NOW.length % 2

    // 檢查順序：先檢查自己能否勝，再檢查對手
    for (var P = 1; P <= 2; P++) {
      P1 = Math.abs(P1 - 1)
      var BWC = (P1 == 0) ? '1' : '0'

      // 【優化4】統一檢查函數，減少重複代碼
      function checkLine(line, lineIndex, getCoords) {
        for (var pos = 0; pos < line.length; pos++) {
          if (line[pos] == '2') {
            // 模擬下子
            var testLine = line.slice()
            testLine[pos] = BWC

            // 檢查是否形成五連
            if (checkFive(testLine, pos, BWC)) {
              // 如果是黑子，還要檢查不是長連
              if (P1 == 1 || !checkSix(testLine, pos, BWC)) {
                var coords = getCoords(lineIndex, pos)
                return "[XY='" + String.fromCharCode(coords.X + 64) + String.fromCharCode(coords.Y + 64) + "']"
              }
            }
          }
        }
        return null
      }

      // 檢查橫向
      for (var Y = 1; Y <= boardsize; Y++) {
        var result = checkLine(s0[Y], Y, function (lineIdx, pos) {
          return { Y: lineIdx, X: pos + 1 }
        })
        if (result) return result
      }

      // 檢查直向
      for (var X = 1; X <= boardsize; X++) {
        var result = checkLine(s1[X], X, function (lineIdx, pos) {
          return { Y: pos + 1, X: lineIdx }
        })
        if (result) return result
      }

      // 檢查左上斜
      for (var I = -(boardsize - 5); I <= (boardsize - 5); I++) {
        var result = checkLine(s2[I], I, function (lineIdx, pos) {
          return diag2Map[lineIdx][pos]
        })
        if (result) return result
      }

      // 檢查右上斜
      for (var I = 6; I <= (boardsize - 2) * 2; I++) {
        var result = checkLine(s3[I], I, function (lineIdx, pos) {
          return diag3Map[lineIdx][pos]
        })
        if (result) return result
      }
    }
    return
  }

})(jQuery)

$(document).ready(function () {
  board(), button(), readset(), clickboard(), clickbutton()
  $('.board587').hide()
  if (puzzletype == 'VC4') {
    $('.board587').data('limit', '4')
    $('.board587').data('win', '5')
  } else if (puzzletype == 'VC5') {
    $('.board587').data('limit', '5')
    $('.board587').data('win', '5')
  } else if (puzzletype == 'X33' || puzzletype == 'X44') {
    $('.board587').data('limit', 'X')
    $('.board587').data('win', 'X')
  } else if (puzzletype == 'X43') {
    $('.board587').data('limit', '43')
    $('.board587').data('win', '43')
  }
})
