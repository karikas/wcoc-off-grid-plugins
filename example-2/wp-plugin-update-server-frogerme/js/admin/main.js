/* global Wppus */
jQuery(document).ready(function($) {
	$('.clean-trigger').on('click', function(e) {
		e.preventDefault();

		var button = $(this),
			type   = button.data('type'),
			data   = {
				type :   type,
				nonce :  $('#wppus_plugin_options_handler_nonce').val(),
				action : 'wppus_force_clean'
			};

		button.attr('disabled', 'disabled');

		$.ajax({
			url: Wppus.ajax_url,
			data: data,
			type: 'POST',
			success: function(response) {

				if (!response.success) {
					var message = '';

					/* jshint ignore:start */
					$.each(response.data, function(idx, value) {
						message += value.message + "\n";
					});
					/* jshint ignore:end */

					window.alert(message);
				}

				button.removeAttr('disabled');
			},
			error: function (jqXHR, textStatus) {
				Wppus.debug && window.console.log(textStatus);
			}
		});
		
	});

	$('.manual-download-slug-trigger').on('click', function(e) {
		e.preventDefault();

		var button = $(this),
			data   = {
				type : $('#wppus_manual_download_type').val(),
				slug :   $('#wppus_manual_download_slug').val(),
				nonce :  $('#wppus_plugin_options_handler_nonce').val(),
				action : 'wppus_prime_package_from_remote'
			};

		button.attr('disabled', 'disabled');

		$.ajax({
			url: Wppus.ajax_url,
			data: data,
			type: 'POST',
			success: function(response) {

				if (!response.success) {
					var message = '';

					/* jshint ignore:start */
					$.each(response.data, function(idx, value) {
						message += value.message + "\n";
					});
					/* jshint ignore:end */

					button.removeAttr('disabled');
					window.alert(message);
				} else {
					window.location.reload(true); 
				}
			},
			error: function (jqXHR, textStatus) {
				Wppus.debug && window.console.log(textStatus);
			}
		});
		
	});

	$('.manual-package-upload-trigger').on('click', function(e) {
		e.preventDefault();

		var button = $(this),
			data   = new FormData(),
			valid  = true,
			file   = $('#wppus_manual_package_upload')[0].files[0],
			regex  = /^([a-zA-Z0-9\-\_]*)\.zip$/gm;

		button.attr('disabled', 'disabled');

		if (typeof file !== 'undefined' &&
			typeof file.type !== 'undefined' &&
			typeof file.size !== 'undefined' &&
			typeof file.name !==  'undefined'
		) {

			if ('application/zip' !== file.type) {
				window.alert(Wppus.invalidFileFormat);
				button.removeAttr('disabled');

				valid = false;
			}

			if (0 === file.size) {
				window.alert(Wppus.invalidFileSize);
				button.removeAttr('disabled');

				valid = false;
			}

			if (!regex.test(file.name)) {
				window.alert(Wppus.invalidFileName);
				button.removeAttr('disabled');

				valid = false;
			}
			
		} else {
			window.alert(Wppus.invalidFile);
			button.removeAttr('disabled');

			valid = false;
		}

		if (valid) {
			data.append('action','wppus_manual_package_upload');
			data.append('package', file);
			data.append('nonce', $('#wppus_plugin_options_handler_nonce').val());

			$.ajax({
				url: Wppus.ajax_url,
				data: data,
				type: 'POST',
				cache: false,
        		contentType: false,
        		processData: false,
				success: function(response) {

					if (!response.success) {
						var message = '';

						/* jshint ignore:start */
						$.each(response.data, function(idx, value) {
							message += value.message + "\n";
						});
						/* jshint ignore:end */

						button.removeAttr('disabled');
						window.alert(message);
					} else {
						window.location.reload(true); 
					}
				},
				error: function (jqXHR, textStatus) {
					Wppus.debug && window.console.log(textStatus);
				}
			});
		}
	});

});