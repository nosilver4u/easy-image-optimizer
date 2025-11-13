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
	$('a#easyio-activate').on( 'click', function() {
		$('a#easyio-activate').hide();
		$('#easyio-activation-processing').show();
		activateExactDNSite();
		return false;
	});
	function activateExactDNSite() {
		var easyio_post_action = 'easyio_activate';
		var easyio_post_data = {
			action: easyio_post_action,
			_wpnonce: easyio_vars._wpnonce,
		};
		$.post(ajaxurl, easyio_post_data, function(response) {
			try {
				var easyio_response = JSON.parse(response);
			} catch (err) {
				$('#easyio-activation-processing').hide();
				$('#easyio-activation-result').html(easyio_vars.invalid_response);
				$('#easyio-activation-result').addClass('error');
				$('#easyio-activation-result').show();
				console.log( response );
				return false;
			}
			if ( easyio_response.error ) {
				$('#easyio-activation-processing').hide();
				$('a#easyio-activate').show();
				$('#easyio-activation-result').html(easyio_response.error);
				$('#easyio-activation-result').addClass('error');
				$('#easyio-activation-result').show();
			} else if ( ! easyio_response.success ) {
				$('#easyio-activation-processing').hide();
				$('#easyio-activation-result').html(easyio_vars.invalid_response);
				$('#easyio-activation-result').addClass('error');
				$('#easyio-activation-result').show();
				console.log( response );
			} else {
				$('#easyio-activation-processing').hide();
				$('#easyio-status').html(easyio_response.success);
				$('#exactdn_all_the_things').prop('checked', true);
				$('#easyio_lazy_load').prop('checked', true);
				$('#easyio_add_missing_dims').prop('disabled', false);
				$('.easyio-settings-table').show();
				$('#easyio-hidden-submit').show();
				$('table.easyio-inactive').hide();
			}
		});
		return false;
	}
	var easy_save_bar_width = $('#easyio-savings-fill').data('score');
	$('#easyio-savings-fill').animate( {
		width: easy_save_bar_width + '%',
	}, 1000 );
	var easy_bandwidth_bar_width = $('#easyio-bandwidth-fill').data('score');
	if ( easy_bandwidth_bar_width == 100 ) {
		$('#easyio-bandwidth-container .easyio-bar-fill').css('background-color', '#d63638');
		$('#easyio-bandwidth-flex a').css('color', '#d63638');
	}
	$('#easyio-bandwidth-fill').animate( {
		width: easy_bandwidth_bar_width + '%',
	}, 1000 );
	easyIORegisterStatsHandler();
	function easyIORegisterStatsHandler() {
		$('#easyio-show-stats').on('click', function(){
			var site_id = $(this).attr('data-site-id');
			var easyio_post_data = {
				action: 'easyio_get_site_stats',
				site_id: site_id,
				_wpnonce: easyio_vars._wpnonce,
			};      
			var statsContainerID = 'exactdn-stats-modal-' + site_id;
			var statsContainer = false;
			var statsExist = document.getElementById(statsContainerID);
			var closeIcon  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>';                   
			if ( ! statsExist ) {
				$('body').append('<div id="' + statsContainerID + '" style="display:none;" class="exactdn-stats-modal"><div class="exactdn-stats-modal-close">' + closeIcon + '</div><div class="exactdn-stats-modal-charts"></div><img class="exactdn-loading-image" style="display:block;margin-left: auto;margin-right:auto;width:20px;" src="' + easyio_vars.loading_image_url + '" /></div>');
				statsContainer = $('#' + statsContainerID);
				$(statsContainer).on('click', '.exactdn-stats-modal-close', function() {
					$('.exactdn-stats-modal').hide();
					document.body.classList.toggle('exactdn-body-unscroll');
				});
								
				$.post(ajaxurl, easyio_post_data, function(response) {
					//console.log( response );
					var is_json = true;
					try {
						var easyio_response = $.parseJSON(response);
					} catch (err) {
						is_json = false;
					}
					if ( ! is_json ) {
						statsContainer.children('.exactdn-stats-modal-charts').html(easyio_vars.invalid_response);
						$('.exactdn-loading-image').hide();
						console.log(response);
					} else if (easyio_response.error) {
						statsContainer.children('.exactdn-stats-modal-charts').html('<strong>Error (contact support if necessary):</strong> ' + easyio_response.error);
						$('.exactdn-loading-image').hide();
					} else if (easyio_response.html) {
						statsContainer.children('.exactdn-stats-modal-charts').html(easyio_response.html);
						if (easyio_response.pending) {
							console.log('need to fetch more stats, request pending');
							setTimeout(fetchExtraStats, 10000, site_id);
						} else {
							$('.exactdn-loading-image').hide();
						}
					} else {
						statsContainer.children('.exactdn-stats-modal-charts').html(easyio_vars.invalid_response);
						$('.exactdn-loading-image').hide();
						console.log(response);
					}
				})
				.fail(function() {
					statsContainer.children('.exactdn-stats-modal-charts').html(easyio_vars.invalid_response);
					$('.exactdn-loading-image').hide();
				});
			} else {
				statsContainer = $('#' + statsContainerID);
			}
			statsContainer.show();
			document.body.classList.toggle('exactdn-body-unscroll');
			return false;
		});
	}
	var extraStatsRequests = 0;
	function fetchExtraStats(site_id) {
		var easyio_post_data = {
			action: 'easyio_get_site_stats',
			site_id: site_id,
			require_extra: 1,
			_wpnonce: easyio_vars._wpnonce,
		};
		var statsContainerID = 'exactdn-stats-modal-' + site_id;
		var statsContainer = false;
		var statsExist = document.getElementById(statsContainerID);
		if ( ! statsExist ) {
			console.log('no container for site #' + site_id);
			return;
		}
		statsContainer = $('#' + statsContainerID);
		if ( extraStatsRequests > 11 ) { // Roughly 2 minutes of waiting.
			$('.exactdn-loading-image').hide();
			statsContainer.find('.exactdn-stats-pending').text(easyio_vars.easyio_extra_stats_failed);
			return;
		}
		$.post(ajaxurl, easyio_post_data, function(response) {
			extraStatsRequests++;
			var is_json = true;
			try {
				var easyio_response = $.parseJSON(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				console.log(response);
				setTimeout(fetchExtraStats, 10000, site_id);
				return;
			}
			if (easyio_response.error) {
				console.log(easyio_response.error);
			} else if (easyio_response.html) {
				$('.exactdn-loading-image').hide();
				statsContainer.children('.exactdn-stats-modal-charts').html(easyio_response.html);
				return;
			}
			setTimeout(fetchExtraStats, 10000, site_id);
		});
    }
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
