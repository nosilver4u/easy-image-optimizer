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
	function HSregister() {
		if (typeof(HS) !== 'undefined' ) {
			$('.easyio-overrides-nav').click(function() {
				HS.beacon.ready(function() {
					event.preventDefault();
					HS.beacon.show('59710ce4042863033a1b45a6');
				});
			});
			$('.easyio-docs-root').click(function() {
				HS.beacon.ready(function() {
					event.preventDefault();
					HS.beacon.open();
				});
			});
			$('.easyio-help-beacon-multi').click(function() {
				var hsids = $(this).attr('data-beacon-articles');
				hsids = hsids.split(',');
				HS.beacon.ready(function() {
					event.preventDefault();
					HS.beacon.suggest(hsids);
					HS.beacon.open();
				});
			});
			$('.easyio-help-beacon-single').click(function() {
				var hsid = $(this).attr('data-beacon-article');
				HS.beacon.ready(function() {
					event.preventDefault();
					HS.beacon.show(hsid);
				});
			});
		}
	}
	HSregister();
	$('#easyio-general-settings').show();
	$('li.easyio-general-nav').addClass('easyio-selected');
	$('#easyio-support-settings').hide();
	$('#easyio-contribute-settings').hide();
	$('.easyio-general-nav').click(function() {
		$('.easyio-tab-nav li').removeClass('easyio-selected');
		$('li.easyio-general-nav').addClass('easyio-selected');
		$('.easyio-tab a').blur();
		$('#easyio-general-settings').show();
		$('#easyio-support-settings').hide();
		$('#easyio-contribute-settings').hide();
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
		$('#easyio-contribute-settings').hide();
	});
	$('.easyio-contribute-nav').click(function() {
		$('.easyio-tab-nav li').removeClass('easyio-selected');
		$('li.easyio-contribute-nav').addClass('easyio-selected');
		$('.easyio-tab a').blur();
		$('#easyio-general-settings').hide();
		$('#easyio-support-settings').hide();
		$('#easyio-contribute-settings').show();
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
