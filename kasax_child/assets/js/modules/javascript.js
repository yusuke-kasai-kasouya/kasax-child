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


    //■ホバー系■

    // hover系・UpperLINKフキだし
    $(function () {
        $('.__js_hover_UpperLINKq').hover(function() {
        $(this).next('.__js_hover_UpperLINKa').show(100);
        }, function(){
        $(this).next('.__js_hover_UpperLINKa').hide(300);
        });
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








//サイズ変更系(保存用・未使用・php内にメインは記述。2019-08-18)
jQuery(document).ready(function() {
    var $textarea = jQuery('#textarea');
    var lineHeight = parseInt($textarea.css('lineHeight'));
    $textarea.on('input', function(e) {
        var lines = (jQuery(this).val() + '\n').match(/\n/g).length;
        jQuery(this).height(lineHeight * lines);
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
