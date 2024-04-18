jQuery(document).ready(function($) {
	$('#easyio-copy-debug').click(function() {
		selectText('easyio-debug-info');
		try {
			var successful = document.execCommand('copy');
			if ( successful ) {
				unselectText();
			}
		} catch(err) {
			console.log('browser cannot copy');
			console.log(err);
		}
	});
	if (typeof(Beacon) !== 'undefined' ) {
		Beacon( 'on', 'ready', function() {
			$('.easyio-overrides-nav').click(function() {
				event.preventDefault();
				Beacon('article', '59710ce4042863033a1b45a6', { type: 'modal' });
			});
			$('.easyio-docs-root').click(function() {
				event.preventDefault();
				Beacon('suggest', ['59bc5ad6042863033a1ce370','59de6631042863379ddc953c','5beee9932c7d3a31944e0d33','5d56e71c0428634552d84bd1','59c44349042863033a1d06d3']);
				Beacon('open');
			});
			$('.easyio-help-beacon-multi').click(function() {
				var hsids = $(this).attr('data-beacon-articles');
				hsids = hsids.split(',');
				event.preventDefault();
				Beacon('suggest', hsids);
				Beacon('navigate', '/answers/');
				Beacon('open');
			});
			$('.easyio-help-beacon-single').click(function() {
				var hsid = $(this).attr('data-beacon-article');
				event.preventDefault();
				Beacon('article', hsid, { type: 'modal' });
			});
		});
	}
	$('#easyio-general-settings').show();
	$('li.easyio-general-nav').addClass('easyio-selected');
	$('#easyio-support-settings').hide();
	$('.easyio-general-nav').click(function() {
		$('.easyio-tab-nav li').removeClass('easyio-selected');
		$('li.easyio-general-nav').addClass('easyio-selected');
		$('.easyio-tab a').blur();
		$('#easyio-general-settings').show();
		$('#easyio-support-settings').hide();
		if($('#easyio-activate').length){
			$('#easyio-hidden-submit').hide();
		}
	});
	$('.easyio-support-nav').click(function() {
		$('.easyio-tab-nav li').removeClass('easyio-selected');
		$('li.easyio-support-nav').addClass('easyio-selected');
		$('.easyio-tab a').blur();
		$('#easyio-general-settings').hide();
		$('#easyio-support-settings').show();
		$('#easyio-hidden-submit').show();
	});
	return false;
});
function selectText(containerid) {
	var debug_node = document.getElementById(containerid);
	if (document.selection) {
		var range = document.body.createTextRange();
		range.moveToElementText(debug_node);
		range.select();
	} else if (window.getSelection) {
		window.getSelection().selectAllChildren(debug_node);
	}
}
function unselectText() {
	var sel;
	if ( (sel = document.selection) && sel.empty) {
		sel.empty();
	} else if (window.getSelection) {
		window.getSelection().removeAllRanges();
	}
}
