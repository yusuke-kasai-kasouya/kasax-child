// assets/js/modules/scroll-control.js
jQuery(function($) {
	$('a[href^="#"]').click(function(){

		//づらすサイズ
		var adjust = 45;

		var speed = 300;
		var href= $(this).attr("href");
		var target = $(	href == "#" || href == "" ? 'html' : href	);
		var position = target.offset(	).top	- adjust;
		$("html, body").animate({scrollTop:position}, speed, "swing");

		return false;
  	});
} )