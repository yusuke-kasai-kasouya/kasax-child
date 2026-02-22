jQuery(document).ready(function($) {
    //短縮形。jQuery(function ($) {。メモ。2023-02-26

    //処理が終了するまで、非表示。2023-03-03
    $( '.__js_show' ).show();
    $( '.__js_show_content' ).show();

    // ローダー
    $("#loader").fadeOut();


    /**
     * クリップボードコピー。
     * 非推奨形式なので改修が必要になる。2023-02-28
     */
    $('.__js_copy_clipboard').click(function() {

        var clipboard = $('<textarea></textarea>');

        //prev()前要素取得。
        clipboard.val($(this).prev().html());

        $(this).append(clipboard);

        clipboard.select();
        document.execCommand('copy');
        clipboard.remove();

    });



    /**
     * アコーディオン引き出し制御 (2026年型リファクタリング)
     * .__js_accordion_trigger : クリック要素
     * .__js_accordion_target  : 開閉される中身
     * .is-opened              : 開閉状態を示す状態クラス
     */
    const $trigger = $('.js_accordion_trigger');
    const $target  = $('.js_accordion_target');
    const activeClass = 'is-opened';

    // 初期状態は非表示
    $target.hide();

    $trigger.on('click', function() {
        const $this = $(this);
        const $next = $this.next();

        // 他の開いている要素を閉じる（排他制御）
        $trigger.not($this).removeClass(activeClass);
        $trigger.not($this).next().slideUp(300);

        // 自身の状態を切り替え
        $this.toggleClass(activeClass);
        $next.slideToggle(100);
    });




    /**
     * 基本形。2023年型
     */
    $("._op__z").css("display", "none");
    $("._op__a").click(function(){
        $("._op__a").not(this).removeClass("_op__o");
        $("._op__a").not(this).next().slideUp(300);
        $(this).toggleClass("_op__o");  //クラスを付与。
        $(this).next().slideToggle(100);
    });



    /**
     * テスト用。説明用。
     */
    $(".answer").css("display", "none");
    // 質問の答えをあらかじめ非表示

    $(".question").click(function(){
        //質問をクリック

        $(".question").not(this).removeClass("open");
        //クリックしたquestion以外の全てのopenを取る

        $(".question").not(this).next().slideUp(300);
        //クリックされたquestion以外のanswerを閉じる

        $(this).toggleClass("open");
        //thisにopenクラスを付与

        $(this).next().slideToggle(100);
        //thisのcontentを展開、開いていれば閉じる
    });





    /**
     * 基本形。
     */
    $("._op_z").css("display", "none");
    $("._op_a").click(function () {
        $("._op_a").not(this).removeClass("_op_o");
        $("._op_a").not(this).next().slideUp(300);
        $(this).toggleClass("_op_o");
        $(this).next().slideToggle(100);
    });


    //click・引き出し・position:absolute・▼open表示なし;
    $("._op_z_non").css("display", "none");
    $("._op_a_non").click(function () {
        $("._op_a_non").not(this).removeClass("_op_o_non");
        $("._op_a_non").not(this).next().slideUp(300);
        $(this).toggleClass("_op_o_non");
        $(this).next().slideToggle(100);
    });


    //■■■click・引き出し・position:absolute;
    $("._op_top_z").css("display", "none");
    $("._op_top_a").click(function () {
        $("._op_top_a").not(this).next().slideUp(300);
        $(this).next().slideToggle(100);
    });


    //引き出し・ブロック型
    $("._op_z_block").css("display", "none");
    $("._op_a_block").click(function (){
        $("._op_a_block").not(this).removeClass("open");
        $("._op_a_block").not(this).next().slideUp(300);
        $(this).toggleClass("open");
        $(this).next().slideToggle(100);
    });




    //テスト用
    //引き出し
    $(".answer1").css("display", "none");

    $(".question1").click(function () {

        $(".question1").not(this).removeClass("open");
        $(".question1").not(this).next().slideUp(300);
        $(this).toggleClass("open");
        $(this).next().slideToggle(100);

    });


    //テスト用
    //■■■ click・question2　■■■
    $(".answer2").css("display", "none");
    // 質問の答えをあらかじめ非表示

    //質問をクリック
    $(".question2").click(function () {

        $(".question2").not(this).removeClass("open2");
        //クリックしたquestion以外の全てのopenを取る

        $(".question2").not(this).next().slideUp(300);
        //クリックされたquestion以外のanswerを閉じる

        $(this).toggleClass("open2");
        //thisにopenクラスを付与

        $(this).next().slideToggle(100);
        //thisのcontentを展開、開いていれば閉じる

    });


    //■■■click・E・question3
    $(".answer3").css("display", "none");
    $(".question3").click(function () {
        $(".question3").not(this).removeClass("open3");
        $(".question3").not(this).next().slideUp(300);
        $(this).toggleClass("open3");
        $(this).next().slideToggle(100);
    });



    //■■■click・Edit
    $(".answer_d").css("display", "none");
    // 質問の答えをあらかじめ非表示

    //質問をクリック
    $(".question_d").click(function () {

        //$(".question2").not(this).removeClass("open2");
        //クリックしたquestion以外の全てのopenを取る

        $(".question_d").not(this).next().slideUp(300);
        //クリックされたquestion以外のanswerを閉じる

        //$(this).toggleClass("open2");
        //thisにopenクラスを付与

        $(this).next().slideToggle(100);
        //thisのcontentを展開、開いていれば閉じる

    });


    //■■■✘

    //■■■click・Edit
    $(".answer_fulltime").css("display", "none");
    // 質問の答えをあらかじめ非表示

    //質問をクリック
    $(".question_fulltime").click(function () {

        //$(".question2").not(this).removeClass("open2");
        //クリックしたquestion以外の全てのopenを取る

        $(".question_fulltime").not(this).next().slideUp(300);
        //クリックされたquestion以外のanswerを閉じる

        //$(this).toggleClass("open2");
        //thisにopenクラスを付与

        $(this).next().slideToggle(100);
        //thisのcontentを展開、開いていれば閉じる

    });



    // outline用
    $('#outline').on('hover',function(){
        $('.displayArea').toggleClass('__absolute_displayArea').fadeToggle(600);
    });


    // Click系・2019/08/11
        $('.__js_click_hidden').on('click',function(){
            $('.__test_js').toggleClass('__test_js_hidden');
            $('.__test_js').toggleClass('__test_js_hidden2');
        });

    // Click系・2019/08/11
        $('.__js_click_reload').on('click',function(){
            $('.__reload_js').toggleClass('__reload1');
        $('.__reload_js').toggleClass('__reload2');
    });


    // hover系・抜粋。未使用2023-08-24
    $('.__js_edit').on('hover',function(){
        $('.__edit_js_back').toggleClass('__hidden_back');
    });

    //未使用2023-08-24
        $('.__js_hidden').on('hover',function(){
            $(this).next().toggleClass('__hidden');
        });


    //5秒で消える。2025-04-02
    setTimeout(function() {
        $('#error-message5').fadeOut('slow', function() {
        $(this).remove();
        });
    }, 5000);

    setTimeout(function() {
        $('#error-message2').fadeOut('slow', function() {
        $(this).remove();
        });
    }, 2000);



    //リロードボタン
    $('.__js_click_reload2').click(function() {
        location.reload();
    });


    //■■■ホバー系■■■

    /**
     * ホバー引き出し。
     * 多分使っていない。2021/10/05
     */
    $('.__js_hober2_q').on('hover',function(){
            $('.__js_hober2_a').toggleClass('__js_hober2_a1');
            $('.__js_hober2_a').toggleClass('__js_hober2_a2');
        });


    // hover系・UpperLINKフキだし
    $(function () {
        $('.__js_hover_UpperLINKq').hover(function() {
        $(this).next('.__js_hover_UpperLINKa').show(100);
        }, function(){
        $(this).next('.__js_hover_UpperLINKa').hide(300);
        });
    });

    //フキだし・最下部。2023-08-03
    $(function () {
        $('.__js_hover12_q').hover(function() {
        $(this).next('.__js_hover1_a').show(100);
        }, function(){
        $(this).next('.__js_hover1_a').hide(300);
        });
    });


    // 引き出し型1c・フキだし・children・引き出し型。汎用型
    //使っていない。2023-08-27
    $(function () {
        $('.__js_hover1c_q').hover(function() {
        $(this).next('.__js_hover1c_a').show(100);
        }, function(){
        $(this).next('.__js_hover1c_a').hide(300);
        });
    });







    //読み込み
    //ボタンをクリックした時の処理
    $(document).on('click', '.gnavi a', function(event) {

        //$(".contents").on('click', '.gnavi a', function(event) {

        //var id = $('.id').val();  //実験要素。

        //処理のブロック
        event.preventDefault();

        //.gnavi aのhrefにあるリンク先を保存
        var link = $(this).attr("href");
        //alert(id);

        //リンク先が今と同じであれば遷移させない
        if(link == lastpage){

        return false;

        }else{

        $content.fadeOut(600, function() {

            getPage(link);
        });
        //今のリンク先を保存
        lastpage = link;
        }

        // 遷移可能であればローディング表示させる
        $("#loader").show();

    });

    //ページを表示させる場所の設定
    var $content = $('.displayArea');

    //初期表示
    var lastpage = "";

    //ページを取得してくる
    function getPage(elm){

        $.ajax({

        //type: 'GET',  //別で使っている。
        type: 'post', // getかpostを指定(デフォルトは前者)
        url: elm,
        dataType: 'html',

        //dataType: 'json', // 「json」を指定するとresponseがJSONとしてパースされたオブジェクトになる
        data: { // 送信データを指定(getの場合は自動的にurlの後ろにクエリとして付加される)
            text: $('.text').val(),
            id: $('.id').val(),
            //id: $id.val(),
        },


        success: function(data){
            $("#loader").fadeOut();
            $content.html(data).fadeIn(600);
        },

        error:function() {
            alert('問題が発生しました。');
        }

        });
    }

    });






    /**
     * yomikomi用
     */
    jQuery(function ($) {

    $(document).on('click', '.gnavi_r a', function(event) {
        event.preventDefault();
        var link = $(this).attr("href");
        if(link == lastpage){

        return false;

        }else{

        $content.fadeOut(600, function() {

            getPage(link);
        });

        lastpage = link;
        }

        $("#loader").show();

    });


    var $content = $('.displayArea_right');

    var lastpage = "";

    function getPage(elm){

        $.ajax({

        //type: 'GET',  //別で使っている。
        type: 'post', // getかpostを指定(デフォルトは前者)
        url: elm,
        dataType: 'html',

        //dataType: 'json', // 「json」を指定するとresponseがJSONとしてパースされたオブジェクトになる
        data: { // 送信データを指定(getの場合は自動的にurlの後ろにクエリとして付加される)
            text: $('.text').val(),
            id: $('.id').val(),
            //id: $id.val(),


        },


        success: function(data){
            $("#loader").fadeOut();
            $content.html(data).fadeIn(600);
        },

        error:function() {
            alert('問題が発生しました。');
        }

        });
    }


});


/**
 * header-bar用
 */
jQuery(function ($) {

  $(document).on('click', '.gnavi_top a', function(event) {
    event.preventDefault();
    var link = $(this).attr("href");
    if(link == lastpage){

      return false;

    }else{

      $content.fadeOut(600, function() {

        getPage(link);
      });

      lastpage = link;
    }

    $("#loader").show();

  });


  var $content = $('.displayArea_top');

  var lastpage = "";

  function getPage(elm){

    $.ajax({

      //type: 'GET',  //別で使っている。
      type: 'post', // getかpostを指定(デフォルトは前者)
      url: elm,
      dataType: 'html',

      //dataType: 'json', // 「json」を指定するとresponseがJSONとしてパースされたオブジェクトになる
      data: { // 送信データを指定(getの場合は自動的にurlの後ろにクエリとして付加される)
          text: $('.text').val(),
          id: $('.id').val(),
          //id: $id.val(),


      },


      success: function(data){
        $("#loader").fadeOut();
        $content.html(data).fadeIn(600);
      },

      error:function() {
        alert('問題が発生しました。');
      }

    });
  }


});


/**
 * header-bar-relation/関連用用
 */
jQuery(function ($) {

  $(document).on('click', '.gnavi_top_relation a', function(event) {
    event.preventDefault();
    var link = $(this).attr("href");
    if(link == lastpage){

      return false;

    }else{

      $content.fadeOut(600, function() {

        getPage(link);
      });

      lastpage = link;
    }

    $("#loader").show();

  });


  var $content = $('.displayArea_top_relation');

  var lastpage = "";

  function getPage(elm){

    $.ajax({
      type: 'post',
      url: elm,
      dataType: 'html',
      data: {
        text: $('.text').val(),
        id: $('.id').val(),
      },

      success: function(data){
        $("#loader").fadeOut();
        $content.html(data).fadeIn(600);
      },

      error:function() {
        alert('問題が発生しました。');
      }

    });
  }


});





/**
 * side-list/関連用用
 */
jQuery(function ($) {

  $(document).on('click', '.gnavi_side_list a', function(event) {
    event.preventDefault();
    var link = $(this).attr("href");
    if(link == lastpage){

      return false;

    }else{

      $content.fadeOut(600, function() {

        getPage(link);
      });

      lastpage = link;
    }

    $("#loader").show();

  });


  var $content = $('.displayArea_side_list');

  var lastpage = "";

  function getPage(elm){

    $.ajax({
      type: 'post',
      url: elm,
      dataType: 'html',
      data: {
        text: $('.text').val(),
        id: $('.id').val(),
      },

      success: function(data){
        $("#loader").fadeOut();
        $content.html(data).fadeIn(600);
      },

      error:function() {
        alert('問題が発生しました。');
      }

    });
  }


});




/**
 * header-bar-relation/関連用用
 */
jQuery(function ($) {

  $(document).on('click', '.gnavi_chara_etc a', function(event) {
    event.preventDefault();
    var link = $(this).attr("href");
    if(link == lastpage){

      return false;

    }else{

      $content.fadeOut(600, function() {

        getPage(link);
      });

      lastpage = link;
    }

    $("#loader").show();

  });


  var $content = $('.displayArea_gnavi_chara_etc');

  var lastpage = "";

  function getPage(elm){

    $.ajax({
      type: 'post',
      url: elm,
      dataType: 'html',
      data: {
        text: $('.text').val(),
        id: $('.id').val(),
      },

      success: function(data){
        $("#loader").fadeOut();
        $content.html(data).fadeIn(600);
      },

      error:function() {
        alert('問題が発生しました。');
      }
    });
  }
});


//サイズ変更系(保存用・未使用・php内にメインは記述。2019-08-18)
jQuery(document).ready(function() {
  var $textarea = jQuery('#textarea');
  var lineHeight = parseInt($textarea.css('lineHeight'));
  $textarea.on('input', function(e) {
    var lines = (jQuery(this).val() + '\n').match(/\n/g).length;
    jQuery(this).height(lineHeight * lines);
  });
});



//マウスで移動用。TESTショートコード。まだテスト段階。2023-06-21
jQuery(document).ready(function($){

  $(document).ready(function() {
    var dragging = false;
    var startMouseX, startMouseY;
    var startElementX, startElementY;

    $("#myDiv").mousedown(function(e) {
      dragging = true;
      startMouseX = e.clientX;
      startMouseY = e.clientY;
      startElementX = parseInt($(this).parent().css("left"));
      startElementY = parseInt($(this).parent().css("top"));
      $(this).parent().css("cursor", "move");
    });

    $(document).mousemove(function(e) {
      if (dragging) {
        var offsetX = e.clientX - startMouseX;
        var offsetY = e.clientY - startMouseY;
        var newElementX = startElementX + offsetX;
        var newElementY = startElementY + offsetY;
        $(document).find(".drag-and-drop").css({ "left": newElementX, "top": newElementY });
      }
    });

    $(document).mouseup(function() {
      if (dragging) {
        dragging = false;
        $(document).find(".drag-and-drop").css("cursor", "default");
      }
    });
  });

});

//確認用
var c999 = "#999";
jQuery(document).ready(function($){

	$('#javascript_test').text('javascript.js TEST OK!');
	$("#javascript_test").css("color",c999);
	$("#javascript_test").css("border","double");
  $("#javascript_test").css("border-color",c999);

	$("#javascript_test").hover(

    function() {
      $(this).css("color", "red");
    },

    function() {
      $(this).css("color", "blue");
    }

  );

});
