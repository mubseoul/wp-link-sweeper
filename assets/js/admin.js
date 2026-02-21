/**
 * WP Link Sweeper - Admin JavaScript
 *
 * @package WP_Link_Sweeper
 */

(function($) {
	'use strict';

	var WPLinkSweeper = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.checkScanStatus();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Dashboard - Scan controls
			$('#ls-start-scan').on('click', this.startScan.bind(this));
			$('#ls-stop-scan').on('click', this.stopScan.bind(this));

			// Broken Links - Actions
			$(document).on('click', '.ls-recheck-link', this.recheckLink.bind(this));
			$(document).on('click', '.ls-ignore-link', this.ignoreLink.bind(this));

			// Replace - Preview and Execute
			$('#ls-preview-replacement').on('click', this.previewReplacement.bind(this));
			$('#ls-execute-replacement').on('click', this.executeReplacement.bind(this));
			$('#ls-undo-operation').on('click', this.undoOperation.bind(this));

			// Rules - Management
			$('#ls-add-rule-form').on('submit', this.addRule.bind(this));
			$(document).on('click', '.ls-delete-rule', this.deleteRule.bind(this));
			$(document).on('click', '.ls-toggle-rule', this.toggleRule.bind(this));
			$('#ls-preview-rules').on('click', function() {
				WPLinkSweeper.applyRules(true);
			});
			$('#ls-apply-rules').on('click', function() {
				WPLinkSweeper.applyRules(false);
			});
		},

		/**
		 * Check scan status on page load
		 */
		checkScanStatus: function() {
			if (!$('#ls-scan-progress').length) {
				return;
			}

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_get_scan_status',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success && response.data.is_scanning) {
						WPLinkSweeper.continueScan();
					}
				}
			});
		},

		/**
		 * Start scan
		 */
		startScan: function(e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			$btn.prop('disabled', true).text(wpLinkSweeper.strings.scanStarted);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_start_scan',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success) {
						$('#ls-scan-progress').show();
						WPLinkSweeper.scanPosts(0, response.data.total_posts);
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
						$btn.prop('disabled', false).text(wpLinkSweeper.strings.startScan);
					}
				},
				error: function() {
					alert(wpLinkSweeper.strings.error);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Continue existing scan
		 */
		continueScan: function() {
			$('#ls-scan-progress').show();
			$('#ls-start-scan').hide();
			$('#ls-stop-scan').show();

			// Get current progress
			this.updateProgress();
		},

		/**
		 * Scan posts in batches
		 */
		scanPosts: function(offset, total) {
			$('#ls-progress-text').text(wpLinkSweeper.strings.scanning);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_scan_posts_batch',
					nonce: wpLinkSweeper.nonce,
					offset: offset
				},
				success: function(response) {
					if (response.success) {
						var progress = Math.round((offset / total) * 50); // Posts = 50% of progress
						WPLinkSweeper.updateProgressBar(progress);

						if (response.data.has_more) {
							WPLinkSweeper.scanPosts(response.data.next_offset, total);
						} else {
							// Posts done, start checking URLs
							WPLinkSweeper.checkUrls();
						}
					}
				}
			});
		},

		/**
		 * Check URLs in batches
		 */
		checkUrls: function() {
			$('#ls-progress-text').text(wpLinkSweeper.strings.checking);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_check_urls_batch',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success) {
						var baseProgress = 50; // Posts already done
						var checkProgress = 50; // URLs checking is other 50%
						var progress = baseProgress + (checkProgress * 0.8); // Estimate
						WPLinkSweeper.updateProgressBar(progress);

						if (response.data.has_more) {
							WPLinkSweeper.checkUrls();
						} else {
							// Scan complete
							WPLinkSweeper.scanComplete();
						}
					}
				}
			});
		},

		/**
		 * Scan complete
		 */
		scanComplete: function() {
			this.updateProgressBar(100);
			$('#ls-progress-text').text(wpLinkSweeper.strings.scanComplete);

			setTimeout(function() {
				location.reload();
			}, 2000);
		},

		/**
		 * Update progress bar
		 */
		updateProgressBar: function(percent) {
			$('#ls-progress-fill').css('width', percent + '%').text(percent + '%');
		},

		/**
		 * Update progress from server
		 */
		updateProgress: function() {
			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_get_scan_status',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success && response.data.is_scanning) {
						var progress = response.data.progress;
						var percent = 0;

						if (progress.total_posts > 0) {
							var postsPercent = (progress.processed_posts / progress.total_posts) * 50;
							var urlsPercent = progress.total_urls > 0 ? (progress.processed_urls / progress.total_urls) * 50 : 0;
							percent = Math.round(postsPercent + urlsPercent);
						}

						WPLinkSweeper.updateProgressBar(percent);

						// Continue polling
						setTimeout(function() {
							WPLinkSweeper.updateProgress();
						}, 2000);
					}
				}
			});
		},

		/**
		 * Stop scan
		 */
		stopScan: function(e) {
			e.preventDefault();

			if (!confirm(wpLinkSweeper.strings.confirmStop)) {
				return;
			}

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_stop_scan',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					}
				}
			});
		},

		/**
		 * Recheck single link
		 */
		recheckLink: function(e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var linkId = $btn.data('link-id');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text('...');

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_recheck_link',
					nonce: wpLinkSweeper.nonce,
					link_id: linkId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: function() {
					alert(wpLinkSweeper.strings.error);
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		/**
		 * Ignore link
		 */
		ignoreLink: function(e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var linkId = $btn.data('link-id');

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_ignore_link',
					nonce: wpLinkSweeper.nonce,
					link_id: linkId
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		/**
		 * Preview replacement
		 */
		previewReplacement: function(e) {
			e.preventDefault();

			var formData = this.getReplacementFormData();

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: $.extend({
					action: 'ls_preview_replacement',
					nonce: wpLinkSweeper.nonce
				}, formData),
				success: function(response) {
					if (response.success) {
						var html = '<p><strong>' + response.data.affected_count + ' posts will be affected.</strong></p>';

						if (response.data.sample_diffs.length > 0) {
							html += '<h4>Sample changes:</h4>';
							response.data.sample_diffs.forEach(function(diff) {
								html += '<div style="margin-bottom: 15px; padding: 10px; background: #f6f7f7; border-left: 3px solid #2271b1;">';
								html += '<strong>' + diff.post_title + '</strong><br>';
								html += '<small>Old: ' + diff.old_sample + '</small><br>';
								html += '<small>New: ' + diff.new_sample + '</small>';
								html += '</div>';
							});
						}

						$('#ls-preview-content').html(html);
						$('#ls-preview-results').show();
						$('#ls-execute-replacement').prop('disabled', false);
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
					}
				}
			});
		},

		/**
		 * Execute replacement
		 */
		executeReplacement: function(e) {
			e.preventDefault();

			if (!confirm(wpLinkSweeper.strings.confirmReplace)) {
				return;
			}

			var formData = this.getReplacementFormData();
			var $btn = $(e.currentTarget);
			$btn.prop('disabled', true);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: $.extend({
					action: 'ls_execute_replacement',
					nonce: wpLinkSweeper.nonce
				}, formData),
				success: function(response) {
					if (response.success) {
						var html = '<div class="notice notice-success"><p>' + response.data.message + '</p></div>';
						$('#ls-replacement-content').html(html);
						$('#ls-replacement-results').show();
						$('#ls-replace-form')[0].reset();
						$('#ls-preview-results').hide();
						$btn.prop('disabled', true);

						// Reload after 2 seconds
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
						$btn.prop('disabled', false);
					}
				}
			});
		},

		/**
		 * Get replacement form data
		 */
		getReplacementFormData: function() {
			var postTypes = [];
			$('input[name="post_types[]"]:checked').each(function() {
				postTypes.push($(this).val());
			});

			return {
				find: $('#find-url').val(),
				replace: $('#replace-url').val(),
				match_type: $('#match-type').val(),
				post_types: postTypes
			};
		},

		/**
		 * Undo last operation
		 */
		undoOperation: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to undo the last operation?')) {
				return;
			}

			var $btn = $(e.currentTarget);
			$btn.prop('disabled', true);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_undo_operation',
					nonce: wpLinkSweeper.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
						$btn.prop('disabled', false);
					}
				}
			});
		},

		/**
		 * Add rule
		 */
		addRule: function(e) {
			e.preventDefault();

			var formData = {
				pattern: $('#rule-pattern').val(),
				replacement: $('#rule-replacement').val(),
				match_type: $('#rule-match-type').val()
			};

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: $.extend({
					action: 'ls_add_rule',
					nonce: wpLinkSweeper.nonce
				}, formData),
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
					}
				}
			});
		},

		/**
		 * Delete rule
		 */
		deleteRule: function(e) {
			e.preventDefault();

			if (!confirm(wpLinkSweeper.strings.confirmDelete)) {
				return;
			}

			var ruleId = $(e.currentTarget).data('rule-id');

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_delete_rule',
					nonce: wpLinkSweeper.nonce,
					rule_id: ruleId
				},
				success: function(response) {
					if (response.success) {
						$('tr[data-rule-id="' + ruleId + '"]').fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		/**
		 * Toggle rule
		 */
		toggleRule: function(e) {
			e.preventDefault();

			var ruleId = $(e.currentTarget).data('rule-id');

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_toggle_rule',
					nonce: wpLinkSweeper.nonce,
					rule_id: ruleId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					}
				}
			});
		},

		/**
		 * Apply rules
		 */
		applyRules: function(dryRun) {
			var message = dryRun ? 'Previewing...' : 'Applying rules...';
			var $btn = dryRun ? $('#ls-preview-rules') : $('#ls-apply-rules');

			$btn.prop('disabled', true).text(message);

			$.ajax({
				url: wpLinkSweeper.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ls_apply_rules',
					nonce: wpLinkSweeper.nonce,
					dry_run: dryRun
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						if (!dryRun) {
							location.reload();
						}
					} else {
						alert(response.data.message || wpLinkSweeper.strings.error);
					}
					$btn.prop('disabled', false).text(dryRun ? 'Preview Rule Application' : 'Apply Rules Now');
				}
			});
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		WPLinkSweeper.init();
	});

})(jQuery);
