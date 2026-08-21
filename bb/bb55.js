// 動態載入 html-to-image 庫，自動識別路徑以支援不同層級的網頁
(function () {
  if (typeof htmlToImage === 'undefined') {
    var scripts = document.getElementsByTagName('script');
    var path = "";
    for (var i = 0; i < scripts.length; i++) {
      if (scripts[i].src && scripts[i].src.match(/bb55\.js/)) {
        path = scripts[i].src.substring(0, scripts[i].src.lastIndexOf("/") + 1);
        break;
      }
    }
    // 如果找不到路徑，預設回退到 bb/ (假設 JS 在 bb 下)
    if (!path) path = "bb/";

    // 將路徑存儲到全域變量，以便其他函數使用
    window.bbPath = path;

    var script = document.createElement('script');
    script.src = path + "html-to-image.min.js";
    document.head.appendChild(script);
  }
})();

function board() {
  $(".board587").data({
    "ALLBU": "4",
    "NOWBU": "0",
    "NEXTBU": ""
  })

  var TITLE = $("<textarea oninput='autogrow(this)'></textarea>").attr({
    "class": "title",
    "XY": "11",
    "readonly": true,
    "placeholder": "標題"
  });
  var COMMENT = $("<textarea oninput='autogrow(this)'></textarea>").attr({
    "class": "comment",
    "XY": "12",
    "readonly": true,
    "placeholder": "每步解說"
  });

  var ALLBOARD = $("<table class='boardtable'><tr class='TDtitle'><td colspan='2'></td></tr><tr><td class='TDboard'><table class='board'></table></td><td class='TDcomment' ></td></tr></table>")
  $(".board587").append(ALLBOARD)
  $(".board587").find(".TDtitle").find("td").append(TITLE)
  $(".board587").find(".TDcomment").append(COMMENT)
}


function button() {
  var BACK = $("<button class='read back-btn' title='Back'><img src='" + (window.bbPath || "") + "svg/arrow_left.svg' width='20' style='vertical-align: middle;'></button>");
  var BACK1 = $("<button class='read back1-btn indent' title='Start'><img src='" + (window.bbPath || "") + "svg/double_arrow_left.svg' width='20' style='vertical-align: middle;'></button>");
  var NEXT = $("<button class='read next-btn' title='Next'><img src='" + (window.bbPath || "") + "svg/arrow_right.svg' width='20' style='vertical-align: middle;'></button>");
  var NEXT1 = $("<button class='read next1-btn' title='End'><img src='" + (window.bbPath || "") + "svg/double_arrow_right.svg' width='20' style='vertical-align: middle;'></button>");
  var START = $("<button class='edit start-btn' title='Set Flag' style='display: none;'><img src='" + (window.bbPath || "") + "svg/flag.svg' width='20' style='vertical-align: middle;'></button>");
  var DELETE = $("<button class='edit delete-btn' title='Delete' style='display: none;'><img src='" + (window.bbPath || "") + "svg/cancel.svg' width='20' style='vertical-align: middle;'></button>");
  var REFRESH = $("<button class='edit refresh-btn' title='Goto Flag' style='display: none;'><img src='" + (window.bbPath || "") + "svg/flag_check.svg' width='20' style='vertical-align: middle;'></button>");
  var NUM = $("<button class='edit CBnum-btn' check='1' title='Number' style='display: none;'><img src='" + (window.bbPath || "") + "svg/number.svg' width='20' style='vertical-align: middle;'></button>");
  var EDIT = $("<button class='read edit-btn' check='0' title='Edit'><img src='" + (window.bbPath || "") + "svg/edit.svg' width='20' style='vertical-align: middle;'></button>");

  var MARK = $("<br class='read'/><button class='edit mark-btn indent' check='0' title='Mark' style='display: none;'><img src='" + (window.bbPath || "") + "svg/font.svg' width='20' style='vertical-align: middle;'></button>");
  var MARK1 = $("<button class='mark1 mark1-btn' val='ABC' title='Toggle Preset' style='display: none;'><img src='" + (window.bbPath || "") + "svg/Aa.svg' width='20' style='vertical-align: middle;'></button>");
  var MARKTEXT = $("<input>").attr({
    "class": "marktext",
    "type": "text",
    "value": "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
    "size": "30",
    "style": "display: none; border: 1px solid #ccc; border-radius: 8px; padding-left: 5px;width: 320px; box-sizing: border-box;"
  });
  var SHARE = $("<button class='share share-btn' check='0' title='Share'><img src='" + (window.bbPath || "") + "svg/share.svg' width='20' style='vertical-align: middle;'></button>");
  var SHAREMENU = $("<div class='sharemenu' style='display: none;'>\
    <button class='image-btn' title='Image'><img src='" + (window.bbPath || "") + "svg/photo.svg' width='20' style='vertical-align: middle;'></button>\
    <button class='tiny tiny-btn' title='URL'><img src='" + (window.bbPath || "") + "svg/link.svg' width='20' style='vertical-align: middle;'></button>\
    </div>")

  var SHAREWRAP = $("<div class='share-wrapper'></div>")
  SHAREWRAP.append(SHARE, SHAREMENU)

  var SHAREDIV = $("<div class='sharediv' style='display: none;'>\
    <textarea class='sharetext' oninput='autogrow(this)'></textarea>\
    </div>")

  var SET = $("<button class='edit set-btn' check='0' title='Set'><img src='" + (window.bbPath || "") + "svg/settings.svg' width='20' style='vertical-align: middle;'></button>");
  var SETDIV = $("<div class='setdiv' style='display: none;'>\
    <div style='display: flex; flex-direction: column; background: #ffffff; border: 1px solid #666; width: calc(448px / 3); box-sizing: border-box; border-radius: 10px; overflow: hidden;'>\
      <div style='display: flex; flex-wrap: wrap; overflow: hidden; border-radius: 10px 10px 0 0;'>\
        <button class='CBtitle' check='1' title='Title'><img src='" + (window.bbPath || "") + "svg/dock_top.svg' width='20'></button>\
        <button class='CBcomment' check='1' title='Comment'><img src='" + (window.bbPath || "") + "svg/dock_left.svg' width='20'></button><br />\
        <button class='edit flip-btn' title='Flip'><img src='" + (window.bbPath || "") + "svg/flip.svg' width='20' style='vertical-align: middle;'></button>\
        <button class='edit rotate-btn' title='Turn 90°'><img src='" + (window.bbPath || "") + "svg/rotate_90.svg' width='20' style='vertical-align: middle;'></button>\
        <button class='CBforbidden' check='1' title='forbidden'><img src='" + (window.bbPath || "") + "svg/forbidden.svg' width='20'></button>\
      </div>\
      <div style='padding: 5px 8px; display: flex; flex-direction: column; gap: 8px;'>\
        <label style='display: flex; align-items: center; justify-content: space-between;'><img src='" + (window.bbPath || "") + "svg/grid_3x3.svg' width='20' style='margin-left: 18px;'><input class='Tboardsize' type='text' value='15' style='width: 50px; text-align: center; height: 30px; font-size: 18px; border: 1px solid #ccc; border-radius: 8px;'></label>\
        <label style='display: flex; align-items: center; justify-content: space-between;'><img src='" + (window.bbPath || "") + "svg/circle.svg' width='20' style='margin-left: 18px;'><input class='Tnumminus' type='text' value='0' style='width: 50px; text-align: center; height: 30px; font-size: 18px; border: 1px solid #ccc; border-radius: 8px;'></label>\
        <label style='display: flex; align-items: center; justify-content: space-between;'><img src='" + (window.bbPath || "") + "svg/circle_n.svg' width='20' style='margin-left: 18px; height: 20px; -webkit-mask-image: linear-gradient(to left, black, transparent); mask-image: linear-gradient(to left, black, transparent);'><input class='Tnumhide' type='text' value='0' style='width: 50px; text-align: center; height: 30px; font-size: 18px; border: 1px solid #ccc; border-radius: 8px;'></label>\
      </div>\
    </div>\
    </div>");

  var SETWRAP = $("<div class='edit set-wrapper' style='display: none;'></div>");
  SETWRAP.append(SET, SETDIV);

  var $buttonContainer = $("<div class='button-fixed-bottom'></div>");
  $buttonContainer.append(BACK1, BACK, NEXT, NEXT1, SHAREWRAP, EDIT, MARK, DELETE, START, REFRESH, NUM, MARK1, MARKTEXT, SETWRAP, SHAREDIV);
  $(".board587").append($buttonContainer)


  function getUrlParam(name) {

    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    var r = window.location.search.substr(1).match(reg);

    if (r != null) {
      return decodeURIComponent(r[2]).replace(/!!!!/g, '|').replace(/!!!/g, '^').replace(/!!/g, '%')
    } else {
      return undefined
    };
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
    $(".board587").find(".edit-btn").attr('check', '1')
    $(".board587").find('.edit').removeAttr("style")
    $(".board587").find('textarea').prop("readonly", false)
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
    $(".CBnum-btn").attr('check', "0")
    $(".Tnumhide").prop('disabled', true)
    $(".Tnumminus").prop('disabled', true)
  }

  if (!isNaN(numminus)) {
    $(".Tnumminus").val(numminus)
  }

  if (!isNaN(numhide)) {
    $(".Tnumhide").val(numhide)
  }
  if (move !== undefined && move !== "") {
    $(".board587").data("ALLBU", move)
    $('.boardtable').readBU()
    $('.boardtable').resetboard()
    $('.boardtable').nextstep()
    $('.boardtable').sharetext()
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
      $(this).find(".CBnum-btn").attr('check', "0")
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

    if ($(this).attr("edit") == "T" || $(this).data("ALLBU") == '4') {
      $(this).find(".edit-btn").attr('check', '1')
      $(this).find('.edit').removeAttr("style")
      $(this).find('textarea').prop("readonly", false)
    }

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


  $(".back1-btn").click(function () {
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


  $(".back-btn").click(function () {

    $(this).readBU()
    if (LAST != 0) {
      $(this).parents(".board587").data("NOWBU", NOWBU.slice(0, NOWBU.lastIndexOf("!")))
      $(this).nextstep()
    }
  })

  $(".next-btn").click(function () {
    $(this).readBU()

    if (NEXT.length == 1 && NEXT[0] != "") {
      $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[0])
      $(this).nextstep()
    } else if (NEXT.length > 1) {
      var $board = $(this).parents(".board587")
      $board.find("[BW='N']").removeClass("ani")
      requestAnimationFrame(function () {
        $board.find("[BW='N']").addClass("ani")
      })
    } else if (NEXT[0] == "") {
      var $board = $(this).parents(".board587")
      var lastXY = ALL[LAST].slice(1, 3)
      var $lastCell = $board.find("[XY=" + lastXY + "]")
      $lastCell.removeClass("ani")
      requestAnimationFrame(function () {
        $lastCell.addClass("ani")
      })
    }
  })

  $(".next1-btn").click(function () {
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
    } else if (NEXT.length > 1) {
      var $board = $(this).parents(".board587")
      $board.find("[BW='N']").removeClass("ani")
      requestAnimationFrame(function () {
        $board.find("[BW='N']").addClass("ani")
      })
    } else if (NEXT[0] == "") {
      var $board = $(this).parents(".board587")
      var lastXY = ALL[LAST].slice(1, 3)
      var $lastCell = $board.find("[XY=" + lastXY + "]")
      $lastCell.removeClass("ani")
      requestAnimationFrame(function () {
        $lastCell.addClass("ani")
      })
    }
  })

  $(".start-btn").click(function () {
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

  $(".refresh-btn").click(function () {
    $(this).readBU()
    $(this).resetboard()
    $(this).nextstep()
    $(this).sharetext()
  })

  $(".flip-btn").click(function () {
    $(this).swapleftright()
    $(this).nextstep()

  })

  $(".rotate-btn").click(function () {
    $(this).counterclockwise()
    $(this).nextstep()
  })

  $(".delete-btn").click(function () {
    $(this).readBU()
    if (confirm("Delete " + NOW[LAST] + " ？")) {

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

  $(".mark-btn").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".read").toggle()
    $(this).siblings(".edit").toggle()
    $(this).siblings(".marktext").toggle()
    $(this).siblings(".mark1").toggle()
    $(this).siblings(".share-wrapper").toggle()
  })

  $(".mark1-btn").click(function () {
    var $btn = $(this);
    var currentVal = $btn.attr("val");
    if (currentVal == "ABC") {
      $btn.siblings("[type='text']").val("△△△△△□□□□□□☆☆☆☆☆")
      $btn.attr("val", "△").find("img").attr("src", (window.bbPath || "") + "svg/star.svg");
    } else if (currentVal == "△") {
      $btn.siblings("[type='text']").val("↑↓←→↖↗↘↙①②③④⑤⑥⑦⑧⑨⑩")
      $btn.attr("val", "←").find("img").attr("src", (window.bbPath || "") + "svg/arrow.svg");
    } else if (currentVal == "←") {
      $btn.siblings("[type='text']").val("")
      $btn.attr("val", "Del").find("img").attr("src", (window.bbPath || "") + "svg/delete.svg");
    } else if (currentVal == "Del") {
      $btn.siblings("[type='text']").val("ABCDEFGHIJKLMNOPQRSTUVWXYZ")
      $btn.attr("val", "ABC").find("img").attr("src", (window.bbPath || "") + "svg/Aa.svg");
    }
  })

  $(".share-btn").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".sharemenu").toggle()
  })

  $(".edit-btn").click(function () {
    if ($(this).attr("check") == '1') {
      $(this).attr("check", '0')
      $(this).parents(".board587").find(".set-btn").attr("check", "0")
      $(this).parents(".board587").find(".setdiv").attr("style", "display: none;")
      $(this).parents(".board587").find(".edit").attr("style", "display: none;")
      $(this).parents(".board587").find('textarea').prop("readonly", true)
    } else {
      $(this).attr("check", '1')
      $(this).parents(".board587").find(".edit").removeAttr("style")
      $(this).parents(".board587").find('textarea').prop("readonly", false)
    }
    $(this).parents(".board587").find(".boardtable").nextstep()
  })

  $(".CBnum-btn").click(function () {

    var board = $(this).parents(".board587");
    if ($(this).attr('check') == '0') {
      board.find(".Tnumhide").removeAttr('disabled')
      board.find(".Tnumminus").removeAttr('disabled')

    } else {
      board.find(".Tnumhide").prop('disabled', true)
      board.find(".Tnumminus").prop('disabled', true)

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

  $(".CBforbidden").click(function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).parents(".board587").find(".boardtable").nextstep()
  })

  $(".image-btn").click(function () {
    var $board = $(this).parents(".board587");
    var node = $board.find("table.boardtable")[0];
    var $textareas = $board.find("textarea");

    // 截圖前暫時移除 placeholder，避免被截圖捕捉
    var placeholders = [];
    $textareas.each(function () {
      placeholders.push($(this).attr("placeholder"));
      $(this).attr("placeholder", "");
    });

    htmlToImage.toPng(node)
      .then(function (dataUrl) {
        var link = document.createElement('a');
        link.download = 'renju.png';
        link.href = dataUrl;
        link.click();

        // 恢復 placeholder
        $textareas.each(function (index) {
          $(this).attr("placeholder", placeholders[index]);
        });
      })
      .catch(function (error) {
        console.error('oops, something went wrong!', error);
        // 發生錯誤也要恢復 placeholder
        $textareas.each(function (index) {
          $(this).attr("placeholder", placeholders[index]);
        });
      });
  })

  $("[value='Copy']").click(function () {

    $(this).siblings(".sharetext").select()
    document.execCommand('copy')
  })

  $(".board587").delegate(".set-btn", "click", function () {
    $(this).attr("check", Math.abs($(this).attr("check") - 1))
    $(this).siblings(".setdiv").toggle()
  })

  $(".tiny-btn").click(function () {
    var $thistiny = $(this)
    var $board = $(this).parents(".board587")
    // 使用局部變量讀取數據，避免污染全域變量 ALL 和 LAST
    var allStr = $board.data("ALLBU") + "";
    var nowStr = $board.data("NOWBU") + "";

    // 簡單的數據驗證
    if (!allStr || allStr === "undefined") {
      console.error("無法讀取棋盤數據 (ALLBU)");
      alert("棋盤數據錯誤，無法分享");
      return;
    }

    var localALL = allStr.split("!");
    var localNOW = nowStr.split("!");
    if (localNOW.length === 0) localNOW = ["0"];
    var localLAST = localNOW[localNOW.length - 1] * 1;

    // 修改局部數據
    for (var i = 0; i < localALL.length; i++) {
      if (localALL[i]) {
        var val = localALL[i].slice(0, 1);
        if (val > 3) {
          localALL[i] = (val - 4) + localALL[i].slice(1);
          break;
        }
      }
    }

    if (localALL[localLAST]) {
      var lastVal = localALL[localLAST].slice(0, 1);
      localALL[localLAST] = (lastVal % 4 + 4) + localALL[localLAST].slice(1);
    }

    // 將修改後的數據存回棋盤
    $board.data("ALLBU", localALL.join("!"));

    // 生成分享文字
    $(this).sharetext();

    htmlToImage.toBlob($board.find("td.TDboard")[0])
      .then(function (blob) {
        var formData = new FormData();
        var sharetext = $board.find('.sharetext').val()
        if (!sharetext) {
          console.error("Share text is empty!");
          alert("無法生成棋譜數據");
          return;
        }

        console.log(sharetext.replace(/%/g, '!!').replace(/\^/g, '!!!').replace(/\|/g, '!!!!'))
        formData.append('image', blob, 'renju-png.png');
        formData.append('MOVE', sharetext);
        formData.append('URL', sharetext.replace(/%/g, '!!').replace(/\^/g, '!!!').replace(/\|/g, '!!!!'));
        formData.append('TITLE', $board.find('.title').val().replace(/\n/g, '-'));

        $.ajax({
          url: (window.bbPath || "") + 'upload.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function (response) {
            console.log("Upload response:", response)
            if (!response || response.trim() === "") {
              alert("伺服器返回空數據，縮址失敗");
              return;
            }
            var shortUrl = response.trim()
            $board.find('.sharetext').val(shortUrl)

            // 複製到剪貼簿
            navigator.clipboard.writeText(shortUrl).then(function () {
              alert('網址已複製！\n' + shortUrl)
            }).catch(function (err) {
              // 如果 clipboard API 失敗，使用備用方法
              var tempInput = document.createElement('textarea')
              tempInput.value = shortUrl
              document.body.appendChild(tempInput)
              tempInput.select()
              document.execCommand('copy')
              document.body.removeChild(tempInput)
              alert('網址已複製！\n' + shortUrl)
            })
          },
          error: function (xhr, status, error) {
            console.error("Upload error:", status, error);
            alert('生成短網址失敗，請檢查網絡或稍後再試')
          }
        });
      })
      .catch(function (err) {
        console.error("Image generation failed:", err);
        alert("無法生成預覽圖，請重試");
      });

  })

}


function clickboard() {

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

    //是否為左鍵
    if (event.which == '1') {
      //是否非標記模式
      if ($(this).parents(".board587").find(".mark1").attr("style") == "display: none;") {
        //是否點擊位置已在分支
        if ($(this).attr("BW") == "N") {

          for (var i = 0; i < NEXT.length; i++) {
            if (!ALL[NEXT[i]]) continue
            var NEXTXY = ALL[NEXT[i]].slice(1, 3)

            if ($(this).attr("XY") == NEXTXY) {
              $(this).parents(".board587").data("NOWBU", NOWBU + "!" + NEXT[i])
            }
          }
          //未在分支，是否非編輯狀態
        } else if ($(this).parents(".board587").find(".title").attr("readonly")) {
          //執行下一步按鈕
          $(this).parents(".board587").find(".next-btn").trigger("click")
          return

          //未在分支，且非編輯狀態，判斷是否為空點或為pass點
        } else if (!$(this).attr("BW") || $(this).attr("XY") == "00") {
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
  if (window.innerWidth > 800 && textarea.classList.contains('comment')) {
    textarea.style.height = '100%';
    return;
  }
  textarea.style.height = 'auto';
  textarea.style.height = textarea.scrollHeight + 'px';
}


(function ($) {

  function normalizeBoardData($board) {
    var all = (($board.data("ALLBU") || "4") + "").split("!")
    if (!all.length || !all[0]) all = ["4"]

    var now = (($board.data("NOWBU") || "0") + "").split("!")
    var invalidNow = !now.length
    for (var i = 0; i < now.length; i++) {
      var idx = now[i] * 1
      if (isNaN(idx) || idx < 0 || idx >= all.length || all[idx] === undefined) {
        invalidNow = true
        break
      }
    }

    if (invalidNow) {
      var marked = 0
      for (var j = 0; j < all.length; j++) {
        if (all[j] && all[j].slice(0, 1) > 3) {
          marked = j
          break
        }
      }
      now = [marked + ""]

      var tree = 0
      for (var k = marked - 1; k >= 0; k--) {
        if (!all[k]) continue
        var type = all[k].slice(0, 1) % 4
        if (type == 0) {
          tree += 1
        } else if (type == 1) {
          if (tree == 0) now.unshift(k + "")
        } else if (type == 3) {
          if (tree == 0) {
            now.unshift(k + "")
          } else {
            tree -= 1
          }
        }
      }

      $board.data("NOWBU", now.join("!"))
    }

    $board.data("ALLBU", all.join("!"))
    return { all: all, now: now }
  }

  $.fn.readBU = function () {

    var $thisdiv = $(this).closest(".board587")
    var normalized = normalizeBoardData($thisdiv)

    ALLBU = $thisdiv.data("ALLBU") + ""
    NOWBU = $thisdiv.data("NOWBU") + ""
    NEXTBU = $thisdiv.data("NEXTBU")
    ALL = normalized.all
    NOW = normalized.now
    NEXT = ((NEXTBU || "") + "").split("!")
    LAST = NOW[NOW.length - 1] * 1
    if (isNaN(LAST) || !ALL[LAST]) {
      LAST = 0
      NOW = ["0"]
      $thisdiv.data("NOWBU", "0")
    }

  }


  $.fn.resetboard = function () {

    var $thisdiv = $(this).closest(".board587")
    if (!ALL || !ALL[0]) ALL = ["4"]
    var title = unescape(ALL[0].split("^")[0]).slice(1)
    $thisdiv.find(".title").val(title)

    for (var i = 0; i < ALL.length; i++) {
      if (ALL[i] && ALL[i].slice(0, 1) > 3) {
        break
      }
    }
    if (i == ALL.length) {
      i = 0
    }
    $thisdiv.data("NOWBU", i)

    var TREE = 0

    for (i -= 1; i >= 0; i--) {

      if (!ALL[i]) {
        continue
      } else if (ALL[i].slice(0, 1) % 4 == 0) {
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

    //判斷有無標記,有則用^合在一起
    if (LASTDATA[1] == "") {
      ALL[LAST] = LASTDATA[0]
    } else {

      ALL[LAST] = LASTDATA.join("^")
    }

    $this.parents(".board587").data("ALLBU", ALL.join("!"))
  }

  $.fn.range = function (BW, X, Y, boardCache) {
    var $board = $(this).closest(".board587")
    var boardsize = $board.find(".Tboardsize").val() * 1
    if (BW == "B") {
      var R = 5
      var P1 = "1"
      var P2 = "0"
    } else {
      var R = 4
      var P1 = "0"
      var P2 = "1"
    }
    function getCell(x, y) {
      var xy = String.fromCharCode(x + 64) + String.fromCharCode(y + 64)
      if (boardCache && boardCache[xy] !== undefined) return boardCache[xy]
      var N = $board.find("[XY='" + xy + "']").attr("BW")
      if (!N || N == "N") return "2"
      return N
    }
    var s0 = "", s1 = "", s2 = "", s3 = "", N = ""
    var I
    for (I = -R; I <= R; I++) {
      if (X + I < 1) I = 1 - X
      if (X + I > boardsize) break
      N = (I === 0) ? P1 : getCell(X + I, Y)
      if (I > 0 && N === P2) break
      s0 += N
    }
    for (I = -R; I <= R; I++) {
      if (Y + I < 1) I = 1 - Y
      if (Y + I > boardsize) break
      N = (I === 0) ? P1 : getCell(X, Y + I)
      if (I > 0 && N === P2) break
      s1 += N
    }
    for (I = -R; I <= R; I++) {
      if (Y + I < 1) I = 1 - Y
      if (X + I < 1) I = 1 - X
      if (Y + I > boardsize || X + I > boardsize) break
      N = (I === 0) ? P1 : getCell(X + I, Y + I)
      if (I > 0 && N === P2) break
      s2 += N
    }
    for (I = -R; I <= R; I++) {
      if (Y + I < 1) I = 1 - Y
      if (X - I > boardsize) I = X - boardsize
      if (Y + I > boardsize || X - I < 1) break
      N = (I === 0) ? P1 : getCell(X - I, Y + I)
      if (I > 0 && N === P2) break
      s3 += N
    }
    return [s0, s1, s2, s3]
  }

  $.fn.blacktype = function (CODE) {
    var V4 = ["21111", "11112", "12111", "11121", "11211"]
    var V3 = ["221112", "212112", "211212", "211122"]
    if (CODE.indexOf("111111") != -1) {
      var GG = "6"
    } else if (CODE.indexOf("11111") != -1) {
      var GG = "5"
    } else {
      var F = 0
      for (var I = 0; I <= 4; I++) {
        var K = V4[I]
        var KK = CODE.indexOf(K)
        if (KK != -1) {
          if (CODE.substring(KK - 1, KK) != "1" && CODE.substring(KK + 5, KK + 6) != "1") F = F + 1
          if (KK < 3) {
            var KKK = CODE.indexOf(K, KK + 2)
            if (KKK > 2 && KKK < 6) {
              if (CODE.substring(KKK - 1, KKK) != "1" && CODE.substring(KKK + 5, KKK + 6) != "1") F = F + 1
            }
          }
          if (F > 1) {
            if (CODE.indexOf("211112") != -1) {
              var GG = "O4"
            } else {
              var GG = "44"
            }
            break
          }
        }
      }
      if (F == 1) {
        var GG = "4"
      } else {
        for (var I = 0; I <= 3; I++) {
          var L = V3[I]
          var LL = CODE.indexOf(L)
          if (LL != -1) {
            if (CODE.substring(LL - 1, LL) != "1" && CODE.substring(LL + 6, LL + 7) != "1") {
              var GG = "O3"
              break
            }
          }
        }
      }
    }
    return GG
  }

  $.fn.forbidden = function (Y1, X1, boardCache, depth) {
    var $board = $(this).closest(".board587")
    var boardsize = $board.find(".Tboardsize").val() * 1
    var NOWXY, OLDBW, xy
    if (depth === 1 && Y1 !== undefined && X1 !== undefined) {
      var Y = Y1
      var X = X1
      xy = String.fromCharCode(X + 64) + String.fromCharCode(Y + 64)
      NOWXY = "[XY='" + xy + "']"
      OLDBW = $board.find(NOWXY).attr("BW")
      if (boardCache) { boardCache[xy] = "1" }
      else { $board.find(NOWXY).attr("BW", "1") }
    } else {
      X = $(this).attr("XY").charCodeAt(0) - 64
      Y = $(this).attr("XY").charCodeAt(1) - 64
      xy = $(this).attr("XY")
      OLDBW = boardCache ? boardCache[xy] : $(this).attr("BW")
      if (boardCache) { boardCache[xy] = "1" }
      else { $(this).attr("BW", 1) }
    }
    var ss = $(this).range("B", X, Y, boardCache)
    var SIX = false
    var FIVE = false
    var FOUR = ""
    var THREE = ""
    for (var I = 0; I <= 3; I++) {
      var BT = $(this).blacktype(ss[I])
      if (BT == "6") SIX = true
      else if (BT == "5") { FIVE = true; break }
      else if (BT == "44") FOUR = FOUR + "44"
      else if (BT == "4" || BT == "O4") FOUR = FOUR + "4"
      else if (BT == "O3") THREE = THREE + I
    }
    var open3 = 0
    var FB = false
    if (FIVE == true) {
      FB = (depth === 1)
    } else if (SIX == true || FOUR.slice(0, 2) == "44") {
      FB = true
    } else if (THREE.length > 1) {
      for (var J = 0; J < THREE.length; J++) {
        var T = THREE.substring(J, J + 1)
        var L = ss[T]
        var LLEN = L.length
        var II = 1
        var I = -5
        if (T == 0) { if (X + I < 1) I = 1 - X }
        else if (T == 1) { if (Y + I < 1) I = 1 - Y }
        else if (T == 2) { if (Y + I < 1) I = 1 - Y; if (X + I < 1) I = 1 - X }
        else if (T == 3) { if (Y + I < 1) I = 1 - Y; if (X - I > boardsize) I = X - boardsize }
        I = I + 1
        do {
          if (L.substring(II, II + 1) == "2") {
            var L1 = L.slice(0, II) + "1" + L.slice(II + 1)
            if ($(this).blacktype(L1) == "O4") {
              var nY1, nX1
              if (T == 0) { nY1 = Y; nX1 = X + I }
              else if (T == 1) { nY1 = Y + I; nX1 = X }
              else if (T == 2) { nY1 = Y + I; nX1 = X + I }
              else { nY1 = Y + I; nX1 = X - I }
              if ($(this).forbidden(nY1, nX1, boardCache, 1) != true) { open3++; break }
            }
          }
          I = I + 1
          II = II + 1
        } while (II < LLEN)
        if (open3 == 2) { FB = true }
        else if (THREE.length - J + open3 < 2) { break }
      }
    }
    if (boardCache) {
      if (xy) boardCache[xy] = (OLDBW !== undefined && OLDBW !== "") ? OLDBW : "2"
    } else {
      if (NOWXY) {
        if (OLDBW) $board.find(NOWXY).attr("BW", OLDBW)
        else $board.find(NOWXY).removeAttr("BW")
      } else {
        if (OLDBW) $(this).attr("BW", OLDBW)
        else $(this).removeAttr("BW")
      }
    }
    if (FB && !boardCache && !NOWXY) {
      $board.find("[XY='" + String.fromCharCode(X + 64) + String.fromCharCode(Y + 64) + "']").removeAttr("BW").html("<b><font color='#FF0000'>╳</font></b>")
    }
    if (FB) return true
    if (FIVE) return 5
    if (FOUR == "4") return 4
    return false
  }

  function getForbiddenPoints($board) {
    var boardCache = {}
    $board.find("[XY]").each(function () {
      var xy = $(this).attr("XY")
      if (xy && xy.length >= 2 && xy !== "00") {
        var bw = $(this).attr("BW")
        boardCache[xy] = (!bw || bw === "N") ? "2" : bw
      }
    })
    var forbiddenPoints = []
    for (var xy in boardCache) {
      if (boardCache[xy] !== "2") continue
      var X = xy.charCodeAt(0) - 64
      var Y = xy.charCodeAt(1) - 64
      var $cell = $board.find("[XY='" + xy + "']")
      if ($cell.length && $cell.forbidden(Y, X, boardCache, 0) === true) forbiddenPoints.push(xy)
    }
    return forbiddenPoints
  }

  $.fn.nextstep = function () {
    var $thisdiv = $(this).parents(".board587")

    $(this).nummark()

    $thisdiv.data("NEXTBU", "")
    if (!ALL[LAST]) return

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

      // 次一手落點：編輯模式一定顯示；唯讀模式僅在 2 個以上候選時顯示
      var isEdit = $thisdiv.find(".edit-btn").attr("check") == '1'
      var showNext = isEdit || NEXT1.length >= 2
      if (showNext) {
        for (var i = 0; i < NEXT1.length; i++) {
          if (!ALL[NEXT1[i]]) continue
          var NEXTXY = ALL[NEXT1[i]].slice(1, 3)
          $thisdiv.find("[XY=" + NEXTXY + "]").attr("BW", "N")
        }
      }

    }

    // 不論黑下或白下，依設定標記黑棋禁手點（有盤面標記的格不畫 ╳）
    var $cbForbidden = $thisdiv.find(".CBforbidden")
    if ($cbForbidden.length && $cbForbidden.attr("check") == "1") {
      var markedXY = {}
      var lastMark = ALL[LAST] ? ALL[LAST].split("^")[1] : ""
      if (lastMark) {
        var markList = lastMark.split("|")
        for (var m = 0; m < markList.length; m++) {
          markedXY[markList[m].slice(0, 2)] = true
        }
      }
      var forbiddenList = getForbiddenPoints($thisdiv)
      for (var f = 0; f < forbiddenList.length; f++) {
        if (markedXY[forbiddenList[f]]) continue
        $thisdiv.find("[XY='" + forbiddenList[f] + "']").removeAttr("BW").html("<b><font color='#FF0000'>╳</font></b>")
      }
    }

  }

  $.fn.nummark = function () {
    $(this).readBU()

    var $thisdiv = $(this).parents(".board587")

    $thisdiv.find("[XY]").removeAttr("BW").removeClass("ani").text("")
    $thisdiv.find(".comment").val("")
    for (var i = 1; i < NOW.length; i++) {
      if (!ALL[NOW[i]]) continue
      var STEPXY = ALL[NOW[i]].slice(1, 3)
      if (STEPXY) {
        var $td = $thisdiv.find("[XY=" + STEPXY + "]");
        $td.attr("BW", i % 2).text("");
        if (i == NOW.length - 1) $td.addClass("ani");
      }
      //KEY手順
      if ($thisdiv.find(".CBnum-btn").attr('check') == '1') {

        if (i - $thisdiv.find(".Tnumminus").val() > 0 && i > $thisdiv.find(".Tnumhide").val()) {

          if (i != NOW.length - 1) {
            $thisdiv.find("[XY=" + STEPXY + "]").text(i - $thisdiv.find(".Tnumminus").val())
          } else {
            $thisdiv.find("[XY=" + STEPXY + "]").html("<span style='color: #ff75fffa'>" + (i - $thisdiv.find(".Tnumminus").val()) + "</span>")
          }

        }
      }

    }

    //KEY標記
    var LASTDATA = ALL[LAST] ? ALL[LAST].split("^")[1] : ""
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
    // 手機模式下 .val() 更新後讓 COMMENT 高度依內容調整
    $thisdiv.find(".comment").each(function () { autogrow(this); })
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
    if ($thisdiv.find(".CBnum-btn").attr('check') == '0') {
      SETMODE += "&num=0"
    }
    if ($thisdiv.find(".Tnumminus").val() != "0") {
      SETMODE += "&numminus=" + $thisdiv.find(".Tnumminus").val()
    }
    if ($thisdiv.find(".Tnumhide").val() != "0") {
      SETMODE += "&numhide=" + $thisdiv.find(".Tnumhide").val()
    }
    $thisdiv.find(".sharetext").val("https://587.renju.org.tw/5.php?move=" + $thisdiv.data("ALLBU") + SETMODE)
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
  board(), button(), clickboard(), clickbutton(), textareablur()
})
