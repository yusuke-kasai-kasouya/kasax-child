<?php
/**
 * templates\layout\header-bar.php
 */
?>

<div class= "__header_bar_clone2 <?php echo $class ?>" style= "<?php echo $colormgr['style_base']; ?>">
</div>

<div class= "__header_bar <?php echo $class; ?>" style= "<?php echo $colormgr['style_base']; ?>">

    <div style="display: inline-block;">
        <?php echo $upper_symbol; ?>
    </div>

    <div class= "__menu">

        <?php echo $menu ?>

    </div>

    <div class="__editor __js_show">
        <?php echo $editor ?>
    </div>
</div>

<div class= "__header_bar_clone <?php echo $class  ?>">
</div>

<style>

.__header_bar_clone2,
.__header_bar_clone,
.__header_bar{
	height: 30px;
    z-index: 5 ;
}

.__header_bar_clone2{
    background: hsla(var(--kx-hue), 50%, 50%, .8);
    border-bottom: 6px solid hsla(var(--kx-hue), 100%, 20%, 1);
}

.__header_bar2{
	margin:5px 0 0 0;
	padding: 0px 0 0 0;
	height:19px;
}


.__header_bar_clone2,
.__header_bar{
	position: fixed ;
	margin-bottom :20px;
    padding: 0 10px;
	line-height:140%;
    margin-left: auto;
    margin-right: auto;
}

.__header_bar{
	z-index: 5;
	margin:0 0 20px 0px;
	word-break: keep-all;
	font-size:15px;
	line-height:140%;
    background:hsla(var(--kx-hue),50%,50%,.2);
}

.__menu{
    display: inline-block;
}

.__header_bar .__menu{
	text-align: left;
}


.__header_bar .__editor{
	float: right;
	margin: 0 0px 0 0;
}

.__header_bar_clone2.__is_wide_layout,
.__header_bar.__is_wide_layout{
	width:100%;
}

.__header_bar,
.__header_bar a:link,
.__header_bar a:visited,
.__header_bar a:hover,
.__header_bar a:active{
	display:inline-block;
}


.__header_bar_container li::before {
	content: "";
	margin: 0px 5px 0px 0px;
}

.__header_bar_container li{
	margin: 0px 5px 0px 5px;
	padding: 0px 0 3px 20px;
	/*background:hsla(0,0%,0%,.8);*/
	text-align: left;
	white-space: nowrap;
}

.__header_bar_container ul li ul{
	background:hsla(0,0%,0%,.7);
	display:block;
	/*background-color: red;*/

}

.__header_bar_container  ul li ul li ul{
	margin: 1.5em 0 0 50px;
	display:block;
	background:hsla(0,0%,100%,.2);


}

@media screen and (min-width:200px) {
	.__header_bar_clone2.__is_normal_layout,
	.__header_bar.__is_normal_layout{
		width:200px;
	}

}
/* ..【300px-768px】*/
@media screen and (min-width:300px) and ( max-width:768px) {
.site{overflow-x: hidden;}

	.__header_bar_clone2.__is_normal_layout,
	.__header_bar.__is_normal_layout{
		width:768px;
	}

}

/* 768-960 */
@media screen and (min-width:768px) and ( max-width:956px) {
	.__header_bar_clone2.__is_normal_layout,
	.__header_bar.__is_normal_layout{
		width:768px;
	}
}

/* 960-1030 */
@media screen and (min-width:957px) and ( max-width:1360px) {
	.__header_bar_clone2.__is_normal_layout,
	.__header_bar.__is_normal_layout{
		width:960px;
	}
}

/* 1030px～ */
@media screen and (min-width:1360px) {
	.__header_bar_clone2.__is_normal_layout,
	.__header_bar.__is_normal_layout{
		width:1200px;
	}
}

</style>