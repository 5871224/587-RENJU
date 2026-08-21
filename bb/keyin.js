function board() {
  $(".board587").data({
    "ALLBU": "4",
    "NOWBU": "0",
    "NEXTBU": ""
  })

  var TITLE = $("<textarea></textarea>").attr({
    "class": "title",
    "XY": "11",
    "readonly": true
  });
  var COMMENT = $("<textarea></textarea>").attr({
    "class": "comment",
    "XY": "12",
    "readonly": true
  });

  var ALLBOARD = $("<table class='boardtable'><tr class='TDtitle'><td colspan='2'></td></tr><tr><td class='TDboard'><table class='board'></table></td><td class='TDcomment' ></td></tr></table>")
  $(".board587").append(ALLBOARD)
  $(".board587").find(".TDtitle").find("td").append(TITLE)
  $(".board587").find(".TDcomment").append(COMMENT)
}


function button() {
  var BACK = $("<input>").attr({
    "class": "read",
    "type": "button",
    "value": "<"
  });
  var BACK1 = $("<input>").attr({
    "class": "read",
    "type": "button",
    "value": "<<"
  });
  var NEXT = $("<input>").attr({
    "class": "read",
    "type": "button",
    "value": ">"
  });
  var NEXT1 = $("<input>").attr({
    "class": "read",
    "type": "button",
    "value": ">>"
  });
  var START = $("<input>").attr({
    "class": "edit",
    "type": "button",
    "value": "!",
    "style": "display: none;"
  });
  var DELETE = $("<input>").attr({
    "class": "edit",
    "type": "button",
    "value": "X",
    "style": "display: none;"
  });
  var REFRESH = $("<input>").attr({
    "class": "read",
    "type": "button",
    "value": "↻"
  });
  var NEW = $("<input>").attr({
    "class": "edit",
    "type": "button",
    "value": "New",
    "onclick": "window.open('http://587.renju.org.tw/5.htm')",
    "style": "display: none;"
  });
  var EDIT = $("<input class='read' type='button' value='Edit' check='0'>")

  var MARK = $("<br /><input>").attr({
    "class": "edit",
    "type": "button",
    "value": "A",
    "check": "0",
    "style": "display: none;"
  });
  var MARK1 = $("<input>").attr({
    "class": "mark1",
    "type": "button",
    "value": "ABC",
    "style": "display: none;"
  });
  var MARKTEXT = $("<input>").attr({
    "class": "marktext",
    "type": "text",
    "value": "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
    "size": "30",
    "style": "display: none;"
  });
  var SHARE = $("<input class='share' type='button' value='Share' check='0'>")

  var swapleftright = $("<input class='edit' type='button' value='↔' style='display: none;'>")
  var counterclockwise = $("<input class='edit' type='button' value='↶' style='display: none;'>")


  var SHAREDIV = $("<div class='sharediv' style='display: none;'><hr>\
    <input type='button' value='Image'><input class='tiny' type='button' style='width: 95px' value='TinyURL'><input type='button' value='Copy'>\
    <textarea class='sharetext' onkeyup='autogrow(this)</textarea>'>\
    </div>")

  var SET = $("<input class='edit' type='button' value='Set' check='0' style='display: none;'><br /><div class='setdiv' style='display: none;'><hr>\
    <input class='CBtitle' type='button' value='Title' check='1'>　\
    <input class='CBcomment' type='button' value='Comment' check='1' style='width: 95px'>　\
    <input class='Tboardsize' type='text' value='15' style='width: 30px'>BoardSize\
    <br />\
    <input class='CBnum' type='button' value='①' check='1'>　\
    <input class='Tnumminus' type='text' value='0' style='width: 30px'>NumMinus　\
    <input class='Tnumhide' type='text' value='0' style='width: 30px'>NumHide</div>");
  var from = $("<form action='keyinsql.php' method='post' target='_blank'> \
    <input id='readsql' type='button' value='讀取'> \
    type: <select id='db' name='db' style='width: 80px'>\
      <option value='VC4'>VC4</option>\
      <option value='X33'>X33</option>\
      <option value='X43'>X43</option>\
      <option value='X44'>X44</option>\
      <option value='1M43'>1M43</option>\
    </select> \
    no: <input id='no' type='text' style='width: 60px' name='no'> \
    <textarea id='puzzle' type='text' name='puzzle' style='display: none;'></textarea> \
    level: <input id='level' type='text' style='width: 60px' name='level'><br/ >\
    <input id='nop' type='button' value='<='> \
    <input id='non' readsql type='button' value='=>'> \
     　　　<input id='submit' type='button' value='更新'>　　　<input id='insert' type='button' value='新增'> </form>")

  var sqlquery = $("<div class='sqlquerydiv' style='margin-top:5px;'>\
    <hr>\
    SQL查詢 WHERE: <br/>\
    <textarea id='sqlwhere' style='width:95%; height:40px; font-size:0.9rem;'></textarea><br/>\
    <input id='sqlsearch' type='button' value='查詢'>\
    <span id='sqlcount' style='margin-left:10px; font-size:0.9rem;'></span>\
    <div id='sqlresult' style='max-height:500px; overflow-y:auto; margin-top:5px; font-size:0.85rem;'></div>\
    </div>")

  $(".board587").append(BACK1, BACK, NEXT, NEXT1, REFRESH, SHARE, EDIT, MARK, DELETE, NEW, START, swapleftright, counterclockwise, MARK1, MARKTEXT, SET, SHAREDIV, from, sqlquery)

}

function readset() {

  function getUrlParam(name) {

    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    if ($("#no").val()) {
      var r = $('.board587').data('TYPE')[$("#no").val()][1].match(reg);
    } else {
      var r = window.location.search.substr(1).match(reg);
    }

    if (r != null) return (r[2]);
    return undefined;
  }

  var boardsize = getUrlParam('boardsize');
  var move = getUrlParam('move');
  var num = getUrlParam('num');
  var numminus = getUrlParam('numminus');
  var numhide = getUrlParam('numhide');
  var edit = getUrlParam('edit');
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


  if (edit == 'T') {
    $("[value='Edit']").attr('check', '1')
    $(this).children('.edit').removeAttr("style")
    $(this).find('textarea').prop("readonly", false)
  }

  if (heading == 'F') {
    $('.TDtitle').attr("style", "display: none;")
    $('.CBtitle').attr('check', "0")
  }

  if (comment == 'F') {
    $('.TDcomment').attr("style", "display: none;")
    $('.CBcomment').attr('check', "0")
  }

  if (num == '0') {
    $(".CBnum").attr('check', "0")
    $(".Tnumhide").prop('disabled', true)
    $(".Tnumminus").prop('disabled', true)
  }

  if (!isNaN(numminus)) {
    $(".Tnumminus").val(numminus)
  }

  if (!isNaN(numhide)) {
    $(".Tnumhide").val(numhide)
  }
  if (move != "") {
    $(".board587").data("ALLBU", move)
    $('.boardtable').readBU()
    $('.boardtable').resetboard()
    $('.boardtable').nextstep()
    $('.boardtable').sharetext()
    if ($('#no').val()) {
      $('#level').val($('.board587').data('TYPE')[$('#no').val()][2])
    }
  }

  $(".board587").each(function () {

    if ($(this).attr("boardsize")) {
      boardsize = $(this).attr("boardsize")

      if ((!isNaN(Math.floor(boardsize))) || boardsize < 2 || boardsize > 19) {
        $(this).children('.boardtable').newboard(boardsize)

      }
    }

    if ($(this).attr("heading") == "F") {
      $(this).find('.TDtitle').attr("style", "display: none;")
      $(this).find('.CBtitle').attr('check', '0')
    }

    if ($(this).attr("comment") == "F") {
      $(this).find('.TDcomment').attr("style", "display: none;")
      $(this).find('.CBcomment').attr('check', '0')
    }

    if ($(this).attr("num") == '0') {
      $(this).find(".CBnum").attr('check', "0")
      $(this).find(".Tnumhide").prop('disabled', true)
      $(this).find(".Tnumminus").prop('disabled', true)
    }

    if ($(this).attr("numminus")) {
      $(this).find(".Tnumminus").val($(this).attr("numminus"))
    }

    if ($(this).attr("numhide")) {
      $(this).find(".Tnumhide").val($(this).attr("numhide"))
    }

    if ($(this).attr("move")) {
      $(this).data("ALLBU", $(this).attr("move"))
      $(this).children('.boardtable').readBU()
      $(this).children('.boardtable').resetboard()
      $(this).children('.boardtable').nextstep()
      $(this).children('.boardtable').sharetext()
    }
    //是否顯示edit按鈕
    // if ($(this).attr("edit") == "T" || $(this).data("ALLBU")=='4') { 
    $("[value='Edit']").attr('check', '1')
    $(this).children('.edit').removeAttr("style")
    $(this).find('textarea').prop("readonly", false)
    // }

  })



}


function clickbutton() {

  $(".board587").delegate("textarea", 'touchend', function (e) {
    $(this).click()
  })


  $(".board587").delegate(":button", 'touchend', function (e) {
    $("*").blur()
    e.preventDefault()
    $(this).click()
  })


  $(".TDboard").delegate("td:not([XY])", 'touchend', function (e) {
    $("*").blur()
    e.preventDefault()
    $(this).readBU()
    if (LAST != 0) {
      $(this).parents(".board587").data("NOWBU", NOWBU.slice(0, NOWBU.lastIndexOf("!")))
      $(this).nextstep()
    }
  })


  $("[value='<<']").click(function () {

    $(this).readBU()
    if (LAST != 0) {
      for (var i = NOW.length - 2; i > 0; i--) {
        if ((ALL[NOW[i + 1]].slice(0, 1) >= 2 || (NOW[i + 1] - NOW[i]) > 1) && ALL[NOW[i + 1]].slice(0, 1) < 4) {
          break
        } else if (ALL[NOW[i]].slice(0, 1) >= 2 && ALL[NOW[i]].slice(0, 1) > 3) {
          break
        }

      }
      NOW.splice(i + 1)
      $(this).parents(".board587").data("NOWBU", NOW.join("!"))
      $(this).nextstep()
    }
  })


  $("[value='<']").click(function () {

    $(this).readBU()
    if (LAST != 0) {
      $(this).parents(".board587").data("NOWBU", NOWBU.slice(0, NOWBU.lastIndexOf("!")))
      $(this).nextstep()
    }
  })

  $("[value='>']").click(function () {
    $(this).readBU()

    if (NEXT.length == 1 && NEXT[0] != "") {

      $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[0])
      $(this).nextstep()
    }
  })

  $("[value='>>']").click(function () {
    $(this).readBU()

    if (NEXT.length == 1 && NEXT[0] != "") {
      var NEXTMOVE = NOW.length
      for (var i = LAST + 1; i < ALL.length; i++) {

        if (ALL[i].slice(0, 1) >= 2) {
          if (ALL[i].slice(0, 1) > 3) {
            NOW[NEXTMOVE + i - (LAST + 1)] = i
            break
          } else {
            break
          }

        }

        NOW[NEXTMOVE + i - (LAST + 1)] = i
        if (ALL[i].slice(0, 1) == 0) {
          break
        }
      }

      $(this).parents(".board587").data("NOWBU", NOW.join("!"))
      $(this).nextstep()

    }
  })

  $("[value='!']").click(function () {
    $(this).readBU()
    for (var i = 0; i < ALL.length; i++) {
      if (ALL[i].slice(0, 1) > 3) {
        ALL[i] = ALL[i].slice(0, 1) - 4 + ALL[i].slice(1)
        break
      }
    }

    ALL[LAST] = ALL[LAST].slice(0, 1) % 4 + 4 + ALL[LAST].slice(1)

    $(this).parents(".board587").data("ALLBU", ALL.join("!"))

    $(this).sharetext()
  })

  $("[value='↻']").click(function () {

    $(this).readBU()
    $(this).resetboard()
    $(this).nextstep()
    $(this).sharetext()
  })

  $("[value='↔']").click(function () {

    $(this).swapleftright()
    $(this).nextstep()

  })

  $("[value='↶']").click(function () {

    $(this).counterclockwise()
    $(this).nextstep()
  })

  $("[value='X']").click(function () {

    if (confirm("Delete？")) {
      $(this).readBU()

      if (LAST == 0) {
        $(this).parents(".board587").find("[XY]").removeAttr("BW").text("")
        $(this).parents(".board587").data({
          "ALLBU": "4",
          "NOWBU": "0",
          "NEXTBU": ""
        })

      } else {

        $(this).parents(".board587").data("NOWBU", NOWBU.slice(0, NOWBU.lastIndexOf("!")))

        var insertB = 0
        for (var i = 0; i < LAST; i++) {
          insertB = insertB + ALL[i].length + 1
        }


        if (ALL[LAST].slice(0, 1) % 4 <= 1) {
          if (ALL[LAST - 1].slice(0, 1) % 4 % 2 == 1) {
            ALL[LAST - 1] = (ALL[LAST - 1].slice(0, 1) - 1) + ALL[LAST - 1].slice(1)

          } else if (ALL[LAST - 1].slice(0, 1) % 4 == 2) {
            ALL[LAST - 1] = (ALL[LAST - 1].slice(0, 1) - 2) + ALL[LAST - 1].slice(1)

          } else if (ALL[LAST - 1].slice(0, 1) % 4 == 0) {
            var TREE = 0

            for (var i = LAST - 2; i > 0; i--) {
              if (ALL[i].slice(0, 1) % 4 == 0) {
                if (TREE == 0) {
                  TREE += 1
                }

              } else if (ALL[i].slice(0, 1) % 4 == 3) {
                if (TREE == 0) {
                  ALL[i] = (1 + ALL[i].slice(1))
                  break

                } else {
                  TREE -= 1
                }

              }
            }
          }
          ALLBU = ALL.join("!")
        }

        var TREE = 0
        var DELTREE = 0
        for (var i = LAST; i < ALL.length; i++) {
          DELTREE = DELTREE + ALL[i].length + 1
          if (ALL[i].slice(0, 1) % 4 == 0) {
            if (TREE == 0) {
              break
            }
            TREE -= 1

          } else if (ALL[i].slice(0, 1) % 4 == 2) {
            if (i == LAST) {
              break
            }

          } else if (ALL[i].slice(0, 1) % 4 == 3) {
            if (i != LAST) {
              TREE += 1
            }
          }
        }
        $(this).parents(".board587").data("ALLBU", ALLBU.slice(0, insertB - 1) + ALLBU.slice(insertB + DELTREE - 1))
        $(this).nextstep()

      }
      $(this).sharetext()
    }
  })

  $("[value='A']").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".read").toggle()
    $(this).siblings(".edit").toggle()
    $(this).siblings(".marktext").toggle()
    $(this).siblings(".mark1").toggle()
    $(this).siblings(".share").toggle()
  })

  $(".mark1").click(function () {
    if ($(this).val() == "ABC") {
      $(this).siblings("[type='text']").val("△△△△△□□□□□□☆☆☆☆☆")
      $(this).val("△")
    } else if ($(this).val() == "△") {
      $(this).siblings("[type='text']").val("←↑→↓↖↙↗↘☹☺①②③④⑤⑥⑦⑧⑨⑩")
      $(this).val("↖")
    } else if ($(this).val() == "↖") {
      $(this).siblings("[type='text']").val("")
      $(this).val("Del")
    } else if ($(this).val() == "Del") {
      $(this).siblings("[type='text']").val("ABCDEFGHIJKLMNOPQRSTUVWXYZ")
      $(this).val("ABC")
    }
  })

  $("[value='Share']").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".sharediv").toggle()
  })

  $("[value='Edit']").click(function () {
    if ($(this).attr("check") == '1') {
      $(this).attr("check", '0')
      $(this).parents(".board587").find("[value='Set']").attr("check", "0")
      $(this).parents(".board587").find(".setdiv").attr("style", "display: none;")
      $(this).parents(".board587").find(".edit").attr("style", "display: none;")
      $(this).parents(".board587").find('textarea').prop("readonly", true)
    } else {
      $(this).attr("check", '1')
      $(this).parents(".board587").find(".edit").removeAttr("style")
      $(this).parents(".board587").find('textarea').prop("readonly", false)
    }

  })

  $(".CBnum").click(function () {

    if ($(this).attr('check') == '0') {
      $(this).siblings(".Tnumhide").removeAttr('disabled')
      $(this).siblings(".Tnumminus").removeAttr('disabled')

    } else {
      $(this).siblings(".Tnumhide").prop('disabled', true)
      $(this).siblings(".Tnumminus").prop('disabled', true)

    }
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).nummark()
    $(this).sharetext()
  })

  $(".CBtitle").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).parents(".board587").find(".TDtitle").toggle()
    $(this).sharetext()
  })

  $(".CBcomment").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).parents(".board587").find(".TDcomment").toggle()
    $(this).sharetext()
  })

  $("[value='Image']").click(function () {
    html2canvas(this.parentNode.parentNode.querySelector("table")).then(function (canvas) {
      $("body").append("<a id='auto' style='display: none;')></a>")
      $("#auto").attr('href', canvas.toDataURL("image/png"))
      $("#auto").attr('download', 'renju.png')
      lnk = document.getElementById("auto")
      lnk.click()
      $("#auto").remove()
    })
  })

  $("[value='Copy']").click(function () {

    $(this).siblings(".sharetext").select()
    document.execCommand('copy')
  })

  $("[value='Set']").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".setdiv").toggle()
  })

  $(".tiny").click(function () {
    $(this).readBU()

    for (var i = 0; i < ALL.length; i++) {
      if (ALL[i].slice(0, 1) > 3) {
        ALL[i] = ALL[i].slice(0, 1) - 4 + ALL[i].slice(1)
        break
      }
    }

    ALL[LAST] = ALL[LAST].slice(0, 1) % 4 + 4 + ALL[LAST].slice(1)

    $(this).parents(".board587").data("ALLBU", ALL.join("!"))

    $(this).sharetext()


    $(this).siblings('.sharetext').attr("shorturl", "")
    $.post('bb/tiny.php', {
      name: $(this).siblings('.sharetext').val()
    }, function (txt) {
      $('[shorturl]').val(txt)
      $('[shorturl]').removeAttr('shorturl')
    });

  })

  $("#readsql").click(function () {

    $.ajax({
      url: "readsql.php",
      type: "POST",
      data: { TYPE: $('#db').val() },
      dataType: "json",
      success: function (response) {


        $('.board587').data('TYPE', response)
        $("#no").val($('.board587').data('TYPE')[1][0])
        readset()
        $('[XY=11]').val('讀取' + $('#db').val() + '成功')
      },
      error: function () {
        $('[XY=11]').val('讀取' + $('#db').val() + '失敗')
      }
    });

  })

  $("#nop").click(function () {
    if ($("#no").val() > 1) {
      $("#no").val($("#no").val() - 1)
      readset()
    }
  })

  $("#non").click(function () {
    if ($('.board587').data('TYPE')[$("#no").val() * 1 + 1]) {
      $("#no").val($("#no").val() * 1 + 1)
      readset()
    }
  })

  $("#no").change(function () {
    readset()
  })

  // 全域變數用於儲存查詢結果與排序狀態
  var currentSqlData = [];
  var currentSortCol = null;
  var currentSortOrder = 'asc';

  function renderSqlTable(data) {
    var html = "";
    var count = 0;
    if (data && Object.keys(data).length > 0) {
      html = "<table style='width:100%; border-collapse:collapse; cursor:pointer;'>";
      html += "<tr style='background:#666; color:#fff;'>";
      html += "<th class='sortable' data-col='0' style='padding:2px 5px;'>No " + (currentSortCol == 0 ? (currentSortOrder == 'asc' ? '▲' : '▼') : '') + "</th>";
      html += "<th class='sortable' data-col='2' style='padding:2px 5px;'>Level " + (currentSortCol == 2 ? (currentSortOrder == 'asc' ? '▲' : '▼') : '') + "</th>";
      html += "<th class='sortable' data-col='3' style='padding:2px 5px;'>棋子數 " + (currentSortCol == 3 ? (currentSortOrder == 'asc' ? '▲' : '▼') : '') + "</th>";
      html += "</tr>";

      for (var key in data) {
        count++;
        var row = data[key];
        var stones = row[3] || "0";
        html += "<tr class='sqlrow' data-no='" + row[0] + "' data-puzzle='" + encodeURIComponent(row[1]) + "' data-level='" + row[2] + "' style='border-bottom:1px solid #ccc;' onmouseover=\"this.style.background='#fffbc0'\" onmouseout=\"this.style.background=''\">";
        html += "<td style='padding:2px 5px; text-align:center;'>" + row[0] + "</td>";
        html += "<td style='padding:2px 5px; text-align:center;'>" + (row[2] || "") + "</td>";
        html += "<td style='padding:2px 5px; text-align:center;'>" + stones + "</td>";
        html += "</tr>";
      }
      html += "</table>";
    }
    $("#sqlresult").html(html);
    $("#sqlcount").text("共 " + count + " 筆");
  }

  // SQL 查詢按鈕
  $("#sqlsearch").click(function () {
    var dbType = $('#db').val();
    var whereClause = $('#sqlwhere').val().trim();

    $.ajax({
      url: "keyinsql.php",
      type: "POST",
      data: { action: 'query', TYPE: dbType, WHERE: whereClause },
      dataType: "json",
      success: function (response) {
        // 將物件轉為陣列方便排序
        currentSqlData = [];
        for (var key in response) {
          currentSqlData.push(response[key]);
        }
        currentSortCol = null; // 重置排序
        renderSqlTable(currentSqlData);
      },
      error: function () {
        $("#sqlresult").html("<span style='color:red;'>查詢失敗，請確認 SQL 語法</span>");
        $("#sqlcount").text("");
      }
    });
  })

  // 點擊標題排序
  $(document).on("click", ".sortable", function () {
    var colIndex = $(this).data("col");

    if (currentSortCol == colIndex) {
      currentSortOrder = (currentSortOrder == 'asc' ? 'desc' : 'asc');
    } else {
      currentSortCol = colIndex;
      currentSortOrder = 'asc';
    }

    currentSqlData.sort(function (a, b) {
      var valA = a[colIndex];
      var valB = b[colIndex];

      // 嘗試轉為數字比較，若非數字則用字串
      var numA = parseFloat(valA);
      var numB = parseFloat(valB);

      if (!isNaN(numA) && !isNaN(numB)) {
        return (currentSortOrder == 'asc' ? numA - numB : numB - numA);
      } else {
        valA = (valA || "").toString();
        valB = (valB || "").toString();
        return (currentSortOrder == 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA));
      }
    });

    renderSqlTable(currentSqlData);
  })

  // 點擊查詢結果列表中的棋譜
  $(document).on("click", ".sqlrow", function () {
    var no = $(this).data("no");
    var puzzle = decodeURIComponent($(this).data("puzzle"));
    var level = $(this).data("level");

    // 將棋譜資料代入棋盤
    $("#no").val(no);
    $("#level").val(level);
    $(".board587").data("ALLBU", puzzle.replace(/^move=/, ""));

    // 解析 puzzle 中的參數
    var params = puzzle.split("&");
    var moveData = "";
    for (var i = 0; i < params.length; i++) {
      var kv = params[i].split("=");
      if (kv[0] === "move") {
        moveData = kv[1];
      }
    }
    if (moveData) {
      $(".board587").data("ALLBU", moveData);
    }

    $('.boardtable').readBU();
    $('.boardtable').resetboard();
    $('.boardtable').nextstep();
    $('.boardtable').sharetext();

    // 高亮選中行
    $(".sqlrow").css("background", "");
    $(this).css("background", "#b3d9ff");
  })

  $("#submit").click(function () {
    $("[value='!']").click()
    $(this).sharetext()

    $.post('keyinsql.php', {
      action: 'update',
      db: $('#db').val(),
      no: $('#no').val(),
      puzzle: $('#puzzle').val(),
      level: $('#level').val()
    }, function (txt) {
      $('[XY=11]').val(txt)
    });

    $.ajax({
      url: "keyinsql.php",
      type: "POST",
      data: { action: 'query', TYPE: $('#db').val(), WHERE: '' },
      dataType: "json",
      success: function (response) {

        $('.board587').data('TYPE', response)

      },
      error: function () {
      }
    });

  })

  $("#insert").click(function () {
    $("[value='!']").click()
    $(this).sharetext()
    $('#no').val('')
    $.post('keyinsql.php', {
      action: 'insert',
      db: $('#db').val(),
      no: $('#no').val(),
      puzzle: $('#puzzle').val(),
      level: $('#level').val()
    }, function (txt) {
      $('[XY=11]').val(txt)
    });

    $.ajax({
      url: "keyinsql.php",
      type: "POST",
      data: { action: 'query', TYPE: $('#db').val(), WHERE: '' },
      dataType: "json",
      success: function (response) {

        $('.board587').data('TYPE', response)
      },
      error: function () {
      }
    });

  })
}


function clickboard() {

  // $(".TDboard").delegate("[XY]",'touchend', function(e) {
  //    $("*").blur()   
  //     e.preventDefault()

  //  $(this).readBU()
  //  $(this).parents(".board587").find(".comment").markcomnotes()

  //    //若不是標記模式
  //    if ($(this).parents(".board587").find(".mark1").attr("style") == "display: none;") {
  //      //若為次一手
  //      if ($(this).attr("BW") == "N") {

  //        for (var i = 0; i < NEXT.length; i++) {
  //          var NEXTXY = ALL[NEXT[i]].slice(1, 3)

  //          if ($(this).attr("XY") == NEXTXY) {
  //            $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[i])
  //          }
  //        }
  //      //若非編輯狀態
  //      } else if ($(this).parents(".board587").find(".title").attr("readonly")) {
  //          //判斷是否只有一個次一手
  //          if (NEXT.length == 1 && NEXT[0] != "") {
  //            $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[0])
  //            $(this).nextstep()            
  //          }
  //        return

  //      //判斷是否有子在該點
  //      } else if (!$(this).attr("BW")) {

  //          //插入新子
  //          var TREE
  //          if (ALL[LAST].slice(0, 1) % 4 % 2 == 0) {
  //            ALL[LAST] = (ALL[LAST].slice(0, 1) * 1 + 1) + ALL[LAST].slice(1)
  //            TREE = "0"
  //          } else {
  //            TREE = "2"
  //          }

  //          NEWMOVE = TREE + $(this).attr("XY")
  //          ALL.splice(LAST + 1, 0, NEWMOVE)
  //          $(this).parents(".board587").data("ALLBU", ALL.join("!"))

  //          //盤面譜加最新一子
  //          $(this).parents(".board587").data("NOWBU", NOWBU + "!" + (LAST + 1))


  //      }
  //      //若為標記模式
  //    } else {

  //      $(this).text($(this).parents(".board587").find(".marktext").val().slice(0, 1).trim())
  //      $(this).parents(".board587").find(".marktext").val($(this).parents(".board587").find(".marktext").val().slice(1))

  //      $(this).markcomnotes()

  //    }
  //    $(this).nextstep()
  //    $(this).sharetext()


  //   }) 

  $(".TDboard").mouseover(function (event) {
    document.oncontextmenu = function () {
      return false;
    }
  });

  $(".TDboard").mouseleave(function (event) {
    document.oncontextmenu = function () {
      return true;
    }
  });

  $(".TDboard").delegate("[XY]", "mousedown", function (event) {


    $(this).readBU()
    $(this).parents(".board587").find(".comment").markcomnotes()

    //若為左鍵
    if (event.which == '1') {
      //若不是標記模式
      if ($(this).parents(".board587").find(".mark1").attr("style") == "display: none;") {
        //若為次一手
        if ($(this).attr("BW") == "N") {

          for (var i = 0; i < NEXT.length; i++) {
            var NEXTXY = ALL[NEXT[i]].slice(1, 3)

            if ($(this).attr("XY") == NEXTXY) {
              $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[i])
            }
          }
          //若非編輯狀態
        } else if ($(this).parents(".board587").find(".title").attr("readonly")) {
          //判斷是否只有一個次一手
          if (NEXT.length == 1 && NEXT[0] != "") {
            $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[0])
            $(this).nextstep()
          }
          return

          //判斷是否有子在該點
        } else if (!$(this).attr("BW")) {

          //插入新子
          var TREE
          if (ALL[LAST].slice(0, 1) % 4 % 2 == 0) {
            ALL[LAST] = (ALL[LAST].slice(0, 1) * 1 + 1) + ALL[LAST].slice(1)
            TREE = "0"
          } else {
            TREE = "2"
          }

          NEWMOVE = TREE + $(this).attr("XY")
          ALL.splice(LAST + 1, 0, NEWMOVE)
          $(this).parents(".board587").data("ALLBU", ALL.join("!"))

          //盤面譜加最新一子
          $(this).parents(".board587").data("NOWBU", NOWBU + "!" + (LAST + 1))


        }
        //若為標記模式
      } else {

        $(this).text($(this).parents(".board587").find(".marktext").val().slice(0, 1).trim())
        $(this).parents(".board587").find(".marktext").val($(this).parents(".board587").find(".marktext").val().slice(1))

        $(this).markcomnotes()

      }
      $(this).nextstep()
      $(this).sharetext()
    } else if (event.which == 3) { //右鍵後退

      if ($(this).parents(".board587").find(".mark1").attr("style") == "display: none;") {
        if (LAST != 0) {
          $(this).parents(".board587").data("NOWBU", NOWBU.slice(0, NOWBU.lastIndexOf("!")))
          $(this).nextstep()

        }
      } else {
        $(this).text("")
        $(this).markcomnotes()
        $(this).nextstep()
        $(this).sharetext()
      }
    }

  })
}


function textareablur() {
  $("textarea.title").change(function () {
    $(this).readBU()
    var ODATE = ALL[0].split("^")
    ODATE[0] = ODATE[0].slice(0, 1) + escape($(this).val())
    ALL[0] = ODATE.join("^")
    $(this).parents(".board587").data("ALLBU", ALL.join("!"))
    $(this).sharetext()
  })

  $("textarea.comment").change(function () {
    $(this).readBU()
    $(this).markcomnotes()
    $(this).sharetext()
  })

  $(".Tnumhide").change(function () {
    if ($(this).val() == "" || isNaN($(this).val())) {
      $(this).val("0")
    }
    $(this).parents(".board587").find("[value=' １ ']").attr("numHide", $(this).val())
    $(this).nextstep()
    $(this).sharetext()
  })

  $(".Tnumminus").change(function () {
    if ($(this).val() == "" || isNaN($(this).val())) {
      $(this).val("0")
    }
    $(this).parents(".board587").find("[value=' １ ']").attr("numminus", $(this).val())
    $(this).nextstep()
    $(this).sharetext()
  })

  $(".Tboardsize").change(function () {
    var boardsize = $(this).val()

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
    $(this).val(boardsize)
    $(this).newboard(boardsize)
    $(this).nextstep()
    $(this).sharetext()
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

  $.fn.readBU = function () {

    var $thisdiv = $(this).parents(".board587")

    ALLBU = $thisdiv.data("ALLBU")
    NOWBU = $thisdiv.data("NOWBU")
    NEXTBU = $thisdiv.data("NEXTBU")
    ALL = (ALLBU + '').split("!")
    NOW = (NOWBU + '').split("!")
    NEXT = (NEXTBU + '').split("!")
    LAST = NOW[NOW.length - 1] * 1

  }


  $.fn.resetboard = function () {

    var $thisdiv = $(this).parents(".board587")
    var title = unescape(ALL[0].split("^")[0]).slice(1)
    $thisdiv.find(".title").val(title)

    for (var i = 0; i < ALL.length; i++) {
      if (ALL[i].slice(0, 1) > 3) {
        break
      }
    }
    if (i == ALL.length) {
      i = 0
    }
    $thisdiv.data("NOWBU", i)

    var TREE = 0

    for (i -= 1; i >= 0; i--) {

      if (ALL[i].slice(0, 1) % 4 == 0) {
        TREE += 1
      } else if (ALL[i].slice(0, 1) % 4 == 1) {
        if (TREE == 0) {
          $thisdiv.data("NOWBU", i + "!" + $thisdiv.data("NOWBU"))
        }
      } else if (ALL[i].slice(0, 1) % 4 == 3) {
        if (TREE == 0) {
          $thisdiv.data("NOWBU", i + "!" + $thisdiv.data("NOWBU"))
        } else {
          TREE -= 1
        }
      }

    }


  }

  $.fn.markcomnotes = function () {
    var $this = $(this)
    var MARKXY = $this.attr("XY")
    var LASTDATA = ALL[LAST].split("^")
    //該子若有標記
    if (LASTDATA[1]) {

      var LASTMARK = LASTDATA[1].split("|")

      for (var i = 0; i < LASTMARK.length; i++) {

        if (LASTMARK[i].slice(0, 2) == MARKXY) {

          if (!$this.text() == "") {
            LASTMARK[i] = MARKXY + escape($this.text())
          } else if ($this.val() != "") {
            LASTMARK[i] = MARKXY + escape($this.val())
          } else {
            LASTMARK.splice(i, 1)
          }
          break
        }

      }


      if (i == LASTMARK.length) {
        if ($this.text() != "") {
          LASTMARK[i] = MARKXY + escape($this.text())
        } else if ($this.val() != "") {
          LASTMARK[i] = MARKXY + escape($this.val())
        }
      }

      LASTDATA[1] = LASTMARK.join("|")

    } else if ($this.text() != "") {
      LASTDATA[1] = MARKXY + escape($this.text())
    } else if ($this.val() != "") {
      LASTDATA[1] = MARKXY + escape($this.val())
    }


    if (LASTDATA[1] == "") {
      LASTDATA.splice(2)
    }

    ALL[LAST] = LASTDATA.join("^")
    $this.parents(".board587").data("ALLBU", ALL.join("!"))
  }


  $.fn.nextstep = function () {
    var $thisdiv = $(this).parents(".board587")

    $(this).nummark()

    $thisdiv.data("NEXTBU", "")

    //KEY次一手
    if (ALL[LAST].slice(0, 1) % 4 % 2 != 0) {
      var NEXT1 = (LAST + 1)
      var TREE = 0

      for (var i = LAST + 1; i <= ALL.length - 1; i++) {

        if (ALL[i].slice(0, 1) % 4 == 0) {
          if (TREE == 0) {
            break
          } else if (TREE == 1) {
            NEXT1 += "!" + (i + 1)
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
            NEXT1 += "!" + (i + 1)
          }

        } else if (ALL[i].slice(0, 1) % 4 == 3) {
          TREE += 1
        }
      }


      $thisdiv.data("NEXTBU", NEXT1)
      NEXT1 = (NEXT1 + '').split("!")

      for (var i = 0; i < NEXT1.length; i++) {
        var NEXTXY = ALL[NEXT1[i]].slice(1, 3)
        $thisdiv.find("[XY=" + NEXTXY + "]").attr("BW", "N")
      }


    }

  }

  $.fn.nummark = function () {
    $(this).readBU()

    var $thisdiv = $(this).parents(".board587")

    $thisdiv.find("[XY]").removeAttr("BW").text("")
    $thisdiv.find(".comment").val("")
    for (var i = 1; i < NOW.length; i++) {
      var STEPXY = ALL[NOW[i]].slice(1, 3)
      if (STEPXY != "00") {
        $thisdiv.find("[XY=" + STEPXY + "]").attr("BW", i % 2).text("")
      }
      //KEY手順
      if ($thisdiv.find(".CBnum").attr('check') == '1') {

        if (i - $thisdiv.find(".Tnumminus").val() > 0 && i > $thisdiv.find(".Tnumhide").val()) {

          if (i != NOW.length - 1) {
            $thisdiv.find("[XY=" + STEPXY + "]").text(i - $thisdiv.find(".Tnumminus").val())
          } else {
            //$thisdiv.find("[XY=" + STEPXY + "]").text(i - $thisdiv.find(".Tnumminus").val()) //拿掉最後一子上色
            $thisdiv.find("[XY=" + STEPXY + "]").html("<span style='color: #FF00FF'>" + (i - $thisdiv.find(".Tnumminus").val()) + "</span>") //最後一子上色
          }

        }
      }

    }

    //KEY標記
    var LASTDATA = ALL[LAST].split("^")[1]
    if (LASTDATA) {
      var MARKDATA = LASTDATA.split("|")
      for (var i = 0; i < MARKDATA.length; i++) {
        var MARKXY = MARKDATA[i].slice(0, 2)
        if ($thisdiv.find("[XY=" + MARKXY + "]").attr("BW")) {
          $thisdiv.find("[XY=" + MARKXY + "]").text(unescape(MARKDATA[i].slice(2)))
        } else if (MARKXY == "12") {
          $thisdiv.find("[XY=" + MARKXY + "]").val(unescape(MARKDATA[i].slice(2)))
        } else {
          $thisdiv.find("[XY=" + MARKXY + "]").html("<span class='m' style='padding:0px 3px'>" + unescape(MARKDATA[i].slice(2)) + "</span>")
        }
      }
    }


  }


  $.fn.sharetext = function () {

    var $thisdiv = $(this).parents(".board587")
    var SETMODE = ""
    if ($thisdiv.find(".Tboardsize").val() != "15") {
      SETMODE = "&boardsize=" + $thisdiv.find(".Tboardsize").val()
    }
    if ($thisdiv.find(".CBtitle").attr('check') == '0') {
      SETMODE += "&heading=F"
    }
    if ($thisdiv.find(".CBcomment").attr('check') == '0') {
      SETMODE += "&comment=F"
    }
    if ($thisdiv.find(".CBnum").attr('check') == '0') {
      SETMODE += "&num=0"
    }
    if ($thisdiv.find(".Tnumminus").val() != "0") {
      SETMODE += "&numminus=" + $thisdiv.find(".Tnumminus").val()
    }
    if ($thisdiv.find(".Tnumhide").val() != "0") {
      SETMODE += "&numhide=" + $thisdiv.find(".Tnumhide").val()
    }
    $thisdiv.find(".sharetext").val("move=" + $thisdiv.data("ALLBU") + SETMODE)
    $("#puzzle").val("move=" + $thisdiv.data("ALLBU") + SETMODE)
  }

  $.fn.newboard = function (boardsize) {
    var hi = Math.floor(448 / (boardsize * 1 + 1) * 0.55)
    var table = $("<table class='board' style='line-height:" + hi + "px; font-size:" + hi + "px;'></table>")

    for (var y = 0; y <= boardsize; y++) {
      var tr = $("<tr></tr>")
      for (var x = 0; x <= boardsize; x++) {
        if (x == 0 && y != 0) {
          z = y
        } else {
          z = ""
        }
        if (y == 0) {
          z = String.fromCharCode(x + 64)
        }
        var td = $("<td>" + z + "</td>")

        if ((x != 0 && y != 0)) {
          td.attr("XY", String.fromCharCode(x + 64) + String.fromCharCode(y + 64))

          if (x == (boardsize + 1) / 2 && y == (boardsize + 1) / 2) {
            td.attr("star", "1")
          }
          if (boardsize > 8 && (x == 4 || x == boardsize - 3) && (y == 4 || y == boardsize - 3)) {
            td.attr("star", "1")
          }


        } else if (x == 0 && y == 0) {
          td.attr("XY", "00").text("")
        }

        tr.append(td)
      }
      table.prepend(tr)
    }
    $(this).parents(".board587").find(".board").replaceWith(table)
    $(this).parents(".board587").find(".Tboardsize").val(boardsize)
  }


  $.fn.swapleftright = function () {
    var boardsize = $('.Tboardsize').val() * 1
    $(this).parents(".board587").find("[XY]").each(function () {
      if (!($(this).attr("XY").slice(0, 1) == "0" || $(this).attr("XY").slice(0, 1) == "1")) {
        $(this).attr("XY", String.fromCharCode(boardsize + 65 - $(this).attr("XY").slice(0, 1).charCodeAt() + 64) + $(this).attr("XY").slice(1, 2))
      }
    })
  }

  $.fn.counterclockwise = function () {
    var boardsize = $('.Tboardsize').val() * 1
    $(this).parents(".board587").find("[XY]").each(function () {
      if (!($(this).attr("XY").slice(0, 1) == "0" || $(this).attr("XY").slice(0, 1) == "1")) {
        $(this).attr("XY", String.fromCharCode(boardsize + 65 - $(this).attr("XY").slice(0, 1).charCodeAt() + 64) + $(this).attr("XY").slice(1, 2))
        $(this).attr("XY", $(this).attr("XY").slice(1, 2) + $(this).attr("XY").slice(0, 1))
      }
    })

  }

})(jQuery)


$(document).ready(function () {
  board(), button(), readset(), clickboard(), clickbutton(), textareablur()
  $("#db").val(type);
  $("#level").val(level);



})
