<?php
/**
 * data\php\ContentProcessor.php
 */

$regex_replacement_rules =  [

    // A. グローバル置換（全ポスト共通）
    [
        'type' => 'global',
        'rules' => [
            '/\n　　/' => "\n❌❌",
            '/\n　/'   => "\n❌",

            //上が優先
            '/・・・/' => '<span style="opacity:0;display:inline-block;margin-right:1em;"></span>・<span style="opacity:0;display:inline-block;margin-right:-1em;">・</span><span style="opacity:0;display:inline-block;margin-right:-1em;">・</span>',
            '/・・/' => '<span style="opacity:0;display:inline-block;margin-right:.5em;"></span>・<span style="opacity:0;display:inline-block;margin-right:-1em;">・</span>',

            '/(☓|✗|✘)/' => '<span style="color:red;">$1</span>',

            '/-＿/' => '<div style="opacity:0;display:block;margin-right:.5em;"></div>',
            '/－＿|ー＿/' => '<hr>',
            '/__＿/' => '<hr>',


            //URL置換を更に置換
            '@🔗https?://ja\.wikipedia\.org/wiki/(.+)@u' => '<span class="__koku __a_hover" style="background-color:hsla(ヾ色相ヾ,50%,15%,1);border-bottom:1px solid hsla(ヾ色相ヾ,50%,50%,1);">🔗wikipedia：$1</span>' ,

            //｛  グレー  ｝
            '/｛(.*?)｝/' 				=>  '<span class="" style="color:hsla(0,0%,80%,.5);margin:0 0 0 2px;font-size:x-small;">$1</span>',

            //【  青　隅付き括弧 】
            '/【(.*?)】/' 				=>  '<span class="__kakko_sumi">【$1】</span>',


            //［ 270系・逆・文中用　］
            '/［(.*?)］/' 				=>  '<span class="__kaku_kakko0"><span style="margin-left:-5px; opacity:0;">［</span>$1<span style="margin-left:-5px;opacity:0;">］</span></span>',

            //〚 270系・逆・トップ目立たせる系  〛
            '/〚(.*?)〛/'					=>  '<span class="__kaku_kakko0 __kaku_kakko2"><span style=" opacity: 0">〚</span>$1<span style=" opacity: 0">〛</span></span>',

            // 〔 色置換・反転・postカラー 〕
            //'/〔(.*?)〕/' 				=> '<span class="__kou0" style="ヾ色置換・薄ヾ background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp); border:1px solid hsla(ヾ色相ヾ,100%,50%,1);"><span style="margin-left:-5px; opacity:0;">〔</span>$1<span style="margin-left:-5px;opacity:0;">〕</span></span>',
            //'/〔(.*?)〕/' 				=> '<span class="ヾ色置換ヾ">$1</span>',
            '/〔(.*?)〕/' 				=> '<span  class="__kou0" style="ヾ色置換・薄ヾ background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp)); border:1px solid hsla(ヾ色相ヾ,100%,50%,1);"><span style="margin-left:-5px; opacity:0;">〔</span>$1<span style="margin-left:-5px;opacity:0;">〕</span></span>',

            //〘 色置換・枠・薄い・postカラー 〙
            '/〘(.*?)〙/' 				=> '<span class="__kou0" style="ヾ色置換ヾ background-color:hsl(var(--kx-hue),var(--kx-sat),var(--kx-lum)"><span style=" opacity: 0">〘</span>$1<span style=" opacity: 0">〙</span></span>',


            //赤
            '/《(.*?)》/'					=> '<span class="__yama0">$1</span>',

            //背景・赤・薄い系
            '/«(.*?)»/' 					=> '<span class="__yama2">$1</span>',

            //背景・黄・薄い系
            '/‹(.*?)›/' 					=> '<span class="__yama1"><span style=" opacity: 0">‹</span>$1<span style=" opacity: 0">›</span></span>',

            //シングル引用符
            //'/‘(.*?)’(.*\n|)/'	=> '<div class="__inyou_d1">$1</div><p>',

            //置換
            //'/“(.*?)”(.*\n|)/'	=> '<div class="__inyou_d2">$1</div><p>',



            //■装飾系
            '/(掴み|緊張|開放|落ち)＿/'
            =>	'<span style="margin-bottom:0px;">$1：</span>',


            '/(<h[1-6]>)(■|◆|▼)/'  =>	'$1<span class="__kxct_sikaku __text_shadow_normal" style="ヾBASEヾcolor:ヾ色hsla普通ヾ;">$2&nbsp;</span>',


            '/＿引き出し＿(.*[\s\S]*?)(＿引き出しend＿.*|$)/' =>
            '
            <div class="__hidden_box">
                <input type="checkbox" class="option-input01">
                <div><p>$1<hr class="__hidden_box"></div>
            </div>
            <p>
            ',


            '/＿引き出しL＿(.*[\s\S]*?)(＿引き出しend＿.*|$)/' =>
            '
            <div class="__hidden_box">
                <input type="checkbox" class="option-input02">
                <div><p>$1<hr class="__hidden_box"></div>
            </div>
            <p>
            ',

            '/タグ：([^<\n]*)/' =>
            '<div style="line-height:1em;margin:0 0 -1em 0;color:hsla(0,100%,100%,.25);font-size:x-small;text-align:right;">$0</div>',
        ],
    ],


    [
        'type' => 'prod_character_core',
        'rules' => [
            '/：＜(.*?)＞/' 	=>   '：<span class="__small __font_weight_normal">$1</span>',
        ],
    ],



];


// 記号に対応するHTMLテンプレート
$html_templates = [
    '●' => '$1<span class="__kxct_maru"></span><span class="__waku __text_shadow_black1_01" style="font-size:Medium;border:2px solid hsla(ヾ色相ヾ,100%,50%,1);background:hsla(ヾ色相ヾ,100%,20%,.8);display:inline-block;padding:0 12px;">$2</span>$3',
    '■' => '<div style="height:10px;">&nbsp;</div>$1<span class="__kxct_sikaku1 __text_shadow_normal" style="ヾBASEヾcolor:ヾ色hsla普通ヾ;">■</span><span class="__kxct_sikaku_text" style="ヾBASEヾbackground-color:ヾ色hsla普通ヾ;border:2px solid hsla(ヾ色相ヾ,100%,50%,.75);">$2</span>$3',
    '◆' => '<div style="height:10px;">&nbsp;</div>$1<span class="__kxct_sikaku2 __text_shadow_normal" style="ヾBASEヾcolor:ヾ色hsla普通ヾ;">◆</span><span class="__kxct_sikaku_text" style="background-color:hsla(ヾ色相ヾ,66%,50%,.1);border:2px solid hsla(ヾ色相ヾ,100%,50%,.5);">$2</span>$3',
];

// 複雑構文置換（旧 preg2a）
$symbol_expansion_rules = [
    [
        'type' => 'global', // 全一致用フラグとして扱う
        'rules' => [
            '/(<p>|\s)●(.*?)(?=　|<\/p|<br \/>)/' => '●',
            '/(^|\n|\])●(.*?)(\n|\s|<br \/>|　)/' => '●',
            '/(<p>|\s)■(.*?)(?=　|<\/p|<br \/>)/' => '■',
            '/(^|\n|\])■(.*?)(\n|\s|<br \/>|　)/' => '■',
            '/(<p>|\s)◆(.*?)(?=　|<\/p|<br \/>)/' => '◆',
            '/(^|\n|\])◆(.*?)(\n|\s|<br \/>|　)/' => '◆',
        ]
    ],
];


$color_replacement_rules =[
    // 正規表現 => [ 置換パターン, スタイル組合せ, [H,S,L,A], [R,P,L], class ]
    [
        'type' => 'global',
        'rules' => [

            '/★/'	      => [ '$0'				 ,'字'				    ,[0]	 ],
            '/☆|※/'    => [ '$0'				,'字,B'			     ,[0]		],
            //'/\*\d/'   	 => [ '$0'				,'字,B,size_xs'	 ,[200] ],
            '/＃(\d)/'   => [ '注釈：$1'	,'字,B'          ,[45]	],

            '/＿＿(＿＿|)/'   =>[ 'N/A'  , '字,B,透明'  ,[180	,30	,50	]	,[0 ,0]  ] ,


            //新規追加post用。フォーマット。2024-08-08
            '/＿(.*\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})＿(.*)/'
            =>[	'NEW：$1　$2'  ,'B,字'	,[0	,100,60	]	  ] ,

            '/\d{2}(2\d)-(\d{2})-(\d{2})(_\d{2}:\d{2}:\d{2}|)(_\w{1,}|)/'
            =>[	'$1-$2$3$4'  ,'字,size_xs'	,[0	,1,25	]  ] ,

            '/(\d{4}\/\d{2}\/\d{2}( \d{2}:\d{2}:\d{2}|))＿/'
            =>[	'$1'  ,'字,size_s'	,[0	,1,30	]	  ] ,

            '/(例(：.*|))＿/'              =>[ '$1'	      ,'逆,丸5,B,size_s'  ,[180	  ,100 	,25	] ,[2 ,4] ] ,
        ],
    ],

    [
        'type' => ['prod_root','strat_root','material_root'],
        'rules' => [
            '/(SampleA)＿/'      =>[ '$1' ,'丸5,逆,B' ,[45 ,40 ,33	]	,[5 ,4] ] ,
        ],
    ],
];

$color_styles =
[
    '字'	  	=>	'color:hsla(ヾ色相ヾ	,ヾ彩度ヾ%	,ヾ明度ヾ%	,ヾ透明度ヾ);',
    'B'		  	=>	'font-weight:bold;',
    '斜'		  =>	'font-style:italic;',
    '逆'	  	=>	'color:white;	background:hsla(ヾ色相ヾ	,ヾ彩度ヾ%	,ヾ明度ヾ%	,ヾ透明度ヾ);',
    '薄'	  	=>	'color:hsla(ヾ色相ヾ	,100%	,10%	,1);	background:hsla(ヾ色相ヾ	,ヾ彩度ヾ%	,ヾ明度ヾ%	,ヾ透明度ヾ);border:1px solid hsla(ヾ色相ヾ,100%,33%,1);',
    '字色'	  =>	'color:hsla(ヾ色相ヾ	,100%	,85%	,1);',
    '薄影'  	=>	'color:#fff;	background:hsla(ヾ色相ヾ	,ヾ彩度ヾ%	,ヾ明度ヾ%	,ヾ透明度ヾ);',
    '丸5' 	  =>	'border-radius: 5px 5px 5px 5px / 5px 5px 5px 5px;',
    '丸10'  	=>	'border-radius: 10px 10px 10px 10px / 10px 10px 10px 10px;',
    '枠'	    =>	'border:1px solid hsla(ヾ色相ヾ,100%,45%,1);',
    '枠2'	    =>	'border:2px solid hsla(ヾ色相ヾ,100%,45%,1);',
    '透明'	  =>	'opacity: 0.4;',
    //'幅40'	  =>	'width:40px;display: inline-block;text-align: center;',//width: 150px;
    '幅50'	  =>	'width:50px;display: inline-block;text-align: center;',//width: 150px;
    '幅60'	  =>	'width:60px;display: inline-block;text-align: center;',//width: 150px;
    '幅70'	  =>	'width:70px;display: inline-block;text-align: center;',//width: 150px;
    '幅110'	  =>	'width:110px;display: inline-block;text-align: center;',//width: 150px;
    '幅125'	  =>	'width:125px;display: inline-block;text-align: center;',//width: 150px;
    'size_xl'	=>	'font-size:x-large;',
    'size_l'	=>	'font-size:large;',
    'size_s'	=>	'font-size:small;',
    'size_xs'	=>	'font-size:x-small;',
    'LH03'	  =>	'line-height: 3;',
    'LH02'	  =>	'line-height: 2;',
];


$shorthand_expansions = [
    '←＿'		        => "<span class=\"__kxct_triangle_left_small_red01\" style=\"display:inline-block;  margin:0 5px;\"></span>",
    '→＿'		        => "<span class=\"__kxct_triangle_right_small_red01\" style=\"display:inline-block;  margin:0 5px;\"></span>",
    '↓＿'				=>	"🔽",
    '×＿'		        => "<span class=\"\" style=\"display:inline-block;  margin:0 8px;\">×</span>",
    '⇒＿'		        => "<span class=\"\" style=\"display:inline-block;  margin:0 2px; font-weight:bold; \">⇒</span>",
    '‘'			   	    =>	"<span class=\"__color_red\">‘Error‘</span>"

];


return [
    'regex_replacement_rules'  => $regex_replacement_rules,
    'html_templates'           => $html_templates,
    'symbol_expansion_rules'   => $symbol_expansion_rules,
    'color_replacement_rules'  => $color_replacement_rules,
    'color_styles'             => $color_styles,
    'preg_kakujyoshi'          => 'が|を|に|へ|と|より|から|で|や|の|も|は',
    'shorthand_expansions'     => $shorthand_expansions,
];