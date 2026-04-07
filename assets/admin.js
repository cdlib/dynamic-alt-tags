(function () {
	'use strict';

	function getPayloadMessage(payload, fallbackMessage) {
		if (payload && payload.data && payload.data.message) {
			return String(payload.data.message);
		}

		return fallbackMessage;
	}

	function isProviderWidePayload(payload) {
		return Boolean(payload && payload.data && payload.data.provider_wide);
	}

	function createProviderWideError(message) {
		var error = new Error(message);
		error.aiAltProviderWide = true;
		return error;
	}

	function setAltFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="alt"]',
			'textarea[data-setting="alt"]',
			'[data-setting="alt"] input',
			'[data-setting="alt"] textarea',
			'[data-setting="alt"]',
			'#attachment-details-two-column-alt-text',
			'input#attachment_alt',
			'textarea#attachment_alt',
			'input[name="attachments[' + attachmentId + '][image_alt]"]',
			'textarea[name="attachments[' + attachmentId + '][image_alt]"]',
			'input[name="attachments[' + attachmentId + '][alt]"]',
			'textarea[name="attachments[' + attachmentId + '][alt]"]'
		];

		selectors.forEach(function (selector) {
			var nodes = (scope || document).querySelectorAll(selector);
			nodes.forEach(function (node) {
				if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
					node.value = value;
					node.dispatchEvent(new Event('input', { bubbles: true }));
					node.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	}

	function setTitleFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="title"]',
			'[data-setting="title"] input',
			'[data-setting="title"] textarea',
			'[data-setting="title"]',
			'#attachment-details-two-column-title',
			'input#title',
			'input[name="attachments[' + attachmentId + '][post_title]"]',
			'textarea[name="attachments[' + attachmentId + '][post_title]"]'
		];

		selectors.forEach(function (selector) {
			var nodes = (scope || document).querySelectorAll(selector);
			nodes.forEach(function (node) {
				if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
					node.value = value;
					node.dispatchEvent(new Event('input', { bubbles: true }));
					node.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	}

	function setCaptionFieldValue(scope, value, attachmentId) {
		var selectors = [
			'textarea[data-setting="caption"]',
			'input[data-setting="caption"]',
			'[data-setting="caption"] textarea',
			'[data-setting="caption"] input',
			'[data-setting="caption"]',
			'#attachment-details-two-column-caption',
			'textarea#excerpt',
			'input#excerpt',
			'textarea[name="attachments[' + attachmentId + '][post_excerpt]"]',
			'input[name="attachments[' + attachmentId + '][post_excerpt]"]'
		];

		selectors.forEach(function (selector) {
			var nodes = (scope || document).querySelectorAll(selector);
			nodes.forEach(function (node) {
				if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
					node.value = value;
					node.dispatchEvent(new Event('input', { bubbles: true }));
					node.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	}

	function setDescriptionFieldValue(scope, value, attachmentId) {
		var selectors = [
			'textarea[data-setting="description"]',
			'input[data-setting="description"]',
			'[data-setting="description"] textarea',
			'[data-setting="description"] input',
			'[data-setting="description"]',
			'#attachment-details-two-column-description',
			'textarea#content',
			'input#content',
			'input[name="attachments[' + attachmentId + '][post_content]"]',
			'textarea[name="attachments[' + attachmentId + '][post_content]"]'
		];

		selectors.forEach(function (selector) {
			var nodes = (scope || document).querySelectorAll(selector);
			nodes.forEach(function (node) {
				if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
					node.value = value;
					node.dispatchEvent(new Event('input', { bubbles: true }));
					node.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	}

	function setMediaModelFields(attachmentId, altText, titleText, syncTitle, captionText, syncCaption, descriptionText, syncDescription) {
		if (!window.wp || !window.wp.media) {
			return;
		}

		var numericId = Number(attachmentId);
		if (!numericId) {
			return;
		}

		var model = null;
		var frame = window.wp.media.frame;

		try {
			if (frame && typeof frame.state === 'function') {
				var state = frame.state();
				if (state && typeof state.get === 'function') {
					var selection = state.get('selection');
					if (selection && typeof selection.get === 'function') {
						model = selection.get(numericId) || selection.get(String(numericId));
					}
				}
			}
		} catch (e) {
			model = null;
		}

		if (!model && typeof window.wp.media.attachment === 'function') {
			model = window.wp.media.attachment(numericId);
		}

		if (!model || typeof model.set !== 'function') {
			return;
		}

		var updates = {};
		if (typeof altText === 'string' && altText.trim()) {
			updates.alt = altText;
			updates.image_alt = altText;
		}
		if (syncTitle && typeof titleText === 'string' && titleText.trim()) {
			updates.title = titleText;
		}
		if (syncCaption && typeof captionText === 'string' && captionText.trim()) {
			updates.caption = captionText;
		}
		if (syncDescription && typeof descriptionText === 'string' && descriptionText.trim()) {
			updates.description = descriptionText;
		}
		if (Object.keys(updates).length) {
			model.set(updates);
		}
		if (typeof model.trigger === 'function') {
			if (typeof updates.alt === 'string') {
				model.trigger('change:alt', model, updates.alt);
			}
			if (typeof updates.title === 'string') {
				model.trigger('change:title', model, updates.title);
			}
			if (typeof updates.caption === 'string') {
				model.trigger('change:caption', model, updates.caption);
			}
			if (typeof updates.description === 'string') {
				model.trigger('change:description', model, updates.description);
			}
			model.trigger('change');
		}
	}

	function setActiveSelectionModelFields(altText, titleText, syncTitle, captionText, syncCaption, descriptionText, syncDescription) {
		if (!window.wp || !window.wp.media || !window.wp.media.frame || typeof window.wp.media.frame.state !== 'function') {
			return;
		}

		try {
			var state = window.wp.media.frame.state();
			if (!state || typeof state.get !== 'function') {
				return;
			}
			var selection = state.get('selection');
			if (!selection || typeof selection.first !== 'function') {
				return;
			}
			var model = selection.first();
			if (!model || typeof model.set !== 'function') {
				return;
			}

			if (typeof altText === 'string' && altText.trim()) {
				model.set('alt', altText);
			}
			if (syncTitle && typeof titleText === 'string' && titleText.trim()) {
				model.set('title', titleText);
			}
			if (syncCaption && typeof captionText === 'string' && captionText.trim()) {
				model.set('caption', captionText);
			}
			if (syncDescription && typeof descriptionText === 'string' && descriptionText.trim()) {
				model.set('description', descriptionText);
			}
			if (typeof model.trigger === 'function') {
				model.trigger('change');
			}
		} catch (e) {
			// Ignore media-frame state access errors.
		}
	}

	function applyAltAndMetaAcrossUi(attachmentId, altText, syncTitle, syncCaption, syncDescription, container) {
		var shouldSyncTitle = Boolean(syncTitle);
		var shouldSyncCaption = Boolean(syncCaption);
		var shouldSyncDescription = Boolean(syncDescription);
		var updateOnce = function () {
			try {
				if (container instanceof HTMLElement) {
					setAltFieldValue(container, altText, attachmentId);
					if (shouldSyncTitle) {
						setTitleFieldValue(container, altText, attachmentId);
					}
					if (shouldSyncCaption) {
						setCaptionFieldValue(container, altText, attachmentId);
					}
					if (shouldSyncDescription) {
						setDescriptionFieldValue(container, altText, attachmentId);
					}
				}
				setAltFieldValue(document, altText, attachmentId);
				if (shouldSyncTitle) {
					setTitleFieldValue(document, altText, attachmentId);
				}
				if (shouldSyncCaption) {
					setCaptionFieldValue(document, altText, attachmentId);
				}
				if (shouldSyncDescription) {
					setDescriptionFieldValue(document, altText, attachmentId);
				}
				setMediaModelFields(attachmentId, altText, altText, shouldSyncTitle, altText, shouldSyncCaption, altText, shouldSyncDescription);
				setActiveSelectionModelFields(altText, altText, shouldSyncTitle, altText, shouldSyncCaption, altText, shouldSyncDescription);
			} catch (e) {
				// Never turn a successful server response into a UI error due to local binding issues.
			}
		};

		// Re-apply after short delays because the grid sidebar can re-render asynchronously.
		updateOnce();
		window.setTimeout(updateOnce, 120);
		window.setTimeout(updateOnce, 360);
		window.setTimeout(updateOnce, 800);
		window.setTimeout(updateOnce, 1500);
	}

	function setUploadApplyVisibility(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var applyButton = container ? container.querySelector('.ai-alt-upload-apply') : null;
		if (!(applyButton instanceof HTMLButtonElement) && !(applyButton instanceof HTMLInputElement)) {
			return;
		}

		var isCustom = String(select.value || '') === 'custom';
		applyButton.style.display = isCustom ? 'inline-block' : 'none';
	}

	function setUploadCustomVisibility(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var customWrap = container ? container.querySelector('.ai-alt-upload-custom-wrap') : null;

		if (!(customWrap instanceof HTMLElement)) {
			var customInput = container ? container.querySelector('.ai-alt-upload-custom-alt') : null;
			customWrap = customInput instanceof HTMLElement ? customInput.closest('p') : null;
		}

		if (!(customWrap instanceof HTMLElement)) {
			return;
		}

		var isCustom = String(select.value || '') === 'custom';
		customWrap.style.display = isCustom ? 'block' : 'none';
	}

	function autoSizeSuggestedAltTextareas(scope) {
		var root = (scope instanceof HTMLElement || scope instanceof Document) ? scope : document;
		var nodes = root.querySelectorAll('textarea.ai-alt-row-suggested');
		nodes.forEach(function (node) {
			if (!(node instanceof HTMLTextAreaElement)) {
				return;
			}
			node.style.height = 'auto';
			node.style.height = Math.max(node.scrollHeight, 52) + 'px';
		});
	}

	function sanitizeFocusedQueueIds(value) {
		return String(value || '')
			.split(',')
			.map(function (part) {
				return String(part || '').replace(/[^0-9]/g, '');
			})
			.filter(function (part) {
				return part !== '';
			})
			.join(',');
	}

	function getFocusedQueueStorageKey() {
		return 'ai_alt_focused_queue_ids:' + String(window.location.pathname || '');
	}

	function getStoredFocusedQueueIds() {
		try {
			return sanitizeFocusedQueueIds(window.localStorage.getItem(getFocusedQueueStorageKey()) || '');
		} catch (e) {
			return '';
		}
	}

	function storeFocusedQueueIds(value) {
		var sanitized = sanitizeFocusedQueueIds(value);
		try {
			if (!sanitized) {
				window.localStorage.removeItem(getFocusedQueueStorageKey());
				return;
			}
			window.localStorage.setItem(getFocusedQueueStorageKey(), sanitized);
		} catch (e) {
			// Ignore storage access issues.
		}
	}

	function clearFocusedQueueIds() {
		storeFocusedQueueIds('');
	}

	function syncGenerateQueuedButtonState() {
		var trigger = document.getElementById('ai-alt-generate-all-visible');
		var form = document.querySelector('.ai-alt-queue-form');
		if (!(trigger instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) {
			return;
		}

		var queuedCount = 0;
		var checkboxes = form.querySelectorAll('input.ai-alt-row-checkbox');
		checkboxes.forEach(function (checkbox) {
			if (checkbox instanceof HTMLInputElement && String(checkbox.getAttribute('data-is-queued') || '') === '1') {
				queuedCount += 1;
			}
		});

		trigger.disabled = queuedCount < 1;
	}

	function initFocusedQueuePersistence() {
		var wrap = document.querySelector('.ai-alt-queue-page');
		if (!(wrap instanceof HTMLElement) || !wrap.classList.contains('ai-alt-queue-view-active')) {
			return false;
		}

		var currentIds = sanitizeFocusedQueueIds(wrap.getAttribute('data-focused-queue-ids') || '');
		if (currentIds) {
			storeFocusedQueueIds(currentIds);
			return false;
		}

		var url = new URL(window.location.href);
		var queryIds = sanitizeFocusedQueueIds(url.searchParams.get('queued_ids') || '');
		if (queryIds) {
			storeFocusedQueueIds(queryIds);
			return false;
		}

		clearFocusedQueueIds();
		return false;
	}

	function hideUploadActionHint(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var hintNode = container ? container.querySelector('.ai-alt-upload-action-hint') : null;
		if (!(hintNode instanceof HTMLElement)) {
			return;
		}

		hintNode.style.display = 'none';
	}

	function clearPluginPageNotices() {
		var pluginWraps = document.querySelectorAll('.wrap.ai-alt-wrap');
		if (!pluginWraps.length) {
			return;
		}

		pluginWraps.forEach(function (wrap) {
			if (!(wrap instanceof HTMLElement)) {
				return;
			}

			var notices = wrap.querySelectorAll('.notice, .error, .updated');
			notices.forEach(function (notice) {
				if (notice instanceof HTMLElement) {
					notice.remove();
				}
			});
		});
	}

	function placeRetrieveButtons() {
		var buttons = document.querySelectorAll('.ai-alt-upload-retrieve');
		buttons.forEach(function (button) {
			if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)) {
				return;
			}

			var sourceRow = button.closest('tr, .setting, .compat-field');
			if (!(sourceRow instanceof HTMLElement)) {
				return;
			}

			var wrap = sourceRow.querySelector('.ai-alt-upload-retrieve-wrap');
			if (!(wrap instanceof HTMLElement)) {
				return;
			}

			sourceRow.classList.add('ai-alt-upload-row');
			sourceRow.style.removeProperty('display');
			wrap.style.removeProperty('margin-top');
			wrap.style.removeProperty('margin-left');
			wrap.style.removeProperty('clear');
		});
	}

	function initSettingsTabs() {
		var container = document.getElementById('ai-alt-settings-tabs');
		if (!(container instanceof HTMLElement)) {
			return;
		}

		var tabButtons = container.querySelectorAll('.ai-alt-settings-tab');
		var tabPanels = container.querySelectorAll('.ai-alt-settings-tab-panel');
		if (!tabButtons.length || !tabPanels.length) {
			return;
		}

		container.classList.add('ai-alt-settings-tabs-ready');

		function activateTab(tabKey) {
			tabButtons.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}
				var buttonTab = String(button.getAttribute('data-tab-target') || '');
				var isActive = buttonTab === tabKey;
				button.classList.toggle('nav-tab-active', isActive);
				button.setAttribute('aria-selected', isActive ? 'true' : 'false');
				button.setAttribute('tabindex', isActive ? '0' : '-1');
			});

			tabPanels.forEach(function (panel) {
				if (!(panel instanceof HTMLElement)) {
					return;
				}
				var panelTab = String(panel.getAttribute('data-tab-panel') || '');
				var isActive = panelTab === tabKey;
				panel.hidden = !isActive;
			});

			try {
				window.sessionStorage.setItem('aiAltSettingsTab', tabKey);
			} catch (e) {
				// Ignore sessionStorage availability errors.
			}
		}

		var availableTabs = [];
		tabButtons.forEach(function (button) {
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}
			var key = String(button.getAttribute('data-tab-target') || '');
			if (key) {
				availableTabs.push(key);
			}
			button.addEventListener('click', function () {
				clearPluginPageNotices();
				activateTab(key);
			});
		});

		if (!availableTabs.length) {
			return;
		}

		var initialTab = String(container.getAttribute('data-default-tab') || availableTabs[0]);

		if (availableTabs.indexOf(initialTab) === -1) {
			initialTab = availableTabs[0];
		}

		activateTab(initialTab);
	}

	function initQueueTabNoticeReset() {
		var queueTabs = document.querySelectorAll('.ai-alt-queue-page .ai-alt-queue-tabs .nav-tab');
		if (!queueTabs.length) {
			return;
		}

		queueTabs.forEach(function (tabLink) {
			if (!(tabLink instanceof HTMLAnchorElement)) {
				return;
			}

			tabLink.addEventListener('click', function () {
				clearPluginPageNotices();
			});
		});
	}

	function initSettingsMetricsRefresh() {
		var metricsPanel = document.getElementById('ai-alt-settings-panel-metrics');
		if (!(metricsPanel instanceof HTMLElement)) {
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.settingsMetricsNonce === 'string' ? adminData.settingsMetricsNonce : '';
		if (!ajaxUrl || !nonce || typeof window.fetch !== 'function') {
			return;
		}

		var isRequestInFlight = false;

		function applyMetricFields(fields) {
			if (!fields || typeof fields !== 'object') {
				return;
			}

			Object.keys(fields).forEach(function (fieldId) {
				var node = document.getElementById(fieldId);
				if (!(node instanceof HTMLElement)) {
					return;
				}
				node.textContent = String(fields[fieldId]);
			});
		}

		function refreshMetrics() {
			if (isRequestInFlight || metricsPanel.hidden) {
				return;
			}

			isRequestInFlight = true;
			var body = new URLSearchParams();
			body.append('action', 'ai_alt_settings_metrics_ajax');
			body.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					if (!payload || payload.success !== true || !payload.data || typeof payload.data.fields !== 'object') {
						return;
					}
					applyMetricFields(payload.data.fields);
				})
				.catch(function () {
					return;
				})
				.finally(function () {
					isRequestInFlight = false;
				});
		}

		var metricsTabButton = document.querySelector('.ai-alt-settings-tab[data-tab-target="metrics"]');
		if (metricsTabButton instanceof HTMLButtonElement) {
			metricsTabButton.addEventListener('click', function () {
				window.setTimeout(refreshMetrics, 75);
			});
		}

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				refreshMetrics();
			}
		});

		window.setInterval(refreshMetrics, 15000);
		refreshMetrics();
	}

	function applyUploadAction(trigger, select, customInput, resultNode) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var attachmentId = '';
		if (trigger && trigger.getAttribute) {
			attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
		}
		if (!attachmentId && select && select.getAttribute) {
			attachmentId = String(select.getAttribute('data-attachment-id') || '');
		}
		var nonce = typeof adminData.uploadActionNonce === 'string' ? adminData.uploadActionNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}
		if (!nonce && select && select.getAttribute) {
			nonce = String(select.getAttribute('data-nonce') || '');
		}

		if (!(select instanceof HTMLSelectElement) || !(resultNode instanceof HTMLElement) || !attachmentId || !ajaxUrl || !nonce) {
			return;
		}

		var reviewAction = String(select.value || '');
		if (!reviewAction) {
			resultNode.textContent = i18n.selectUploadAction || 'Please choose an action first.';
			resultNode.classList.add('ai-alt-message-error');
			return;
		}

		var customAlt = '';
		if (customInput instanceof HTMLInputElement || customInput instanceof HTMLTextAreaElement) {
			customAlt = String(customInput.value || '');
		}
		if (reviewAction === 'custom' && !customAlt.trim()) {
			resultNode.textContent = i18n.customAltRequired || 'Enter custom alt text before applying.';
			resultNode.classList.add('ai-alt-message-error');
			return;
		}

		if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
			trigger.disabled = true;
		}
		resultNode.textContent = '';
		resultNode.classList.remove('ai-alt-message-error');
		resultNode.classList.remove('ai-alt-message-success');

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_upload_action_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('attachment_id', attachmentId);
		body.append('review_action', reviewAction);
		body.append('custom_alt', customAlt);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					var errorMessage = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					resultNode.textContent = errorMessage;
					resultNode.classList.add('ai-alt-message-error');
					return;
				}

				var message = payload.data && payload.data.message ? String(payload.data.message) : 'Action applied.';
				resultNode.textContent = message;
				resultNode.classList.add('ai-alt-message-success');

					var shouldUpdateAltField = payload.data && typeof payload.data.alt_text !== 'undefined';
					if (shouldUpdateAltField) {
						var altText = String(payload.data.alt_text);
						var container = select.closest('.attachment-details, .media-sidebar, .compat-item, .setting, tr, table, tbody');
						var shouldSyncTitle = Boolean(adminData && adminData.syncTitleFromAlt);
						var shouldSyncCaption = Boolean(adminData && adminData.syncCaptionFromAlt);
						var shouldSyncDescription = Boolean(adminData && adminData.syncDescriptionFromAlt);
						applyAltAndMetaAcrossUi(attachmentId, altText, shouldSyncTitle, shouldSyncCaption, shouldSyncDescription, container);
					}

					if (customInput instanceof HTMLInputElement || customInput instanceof HTMLTextAreaElement) {
						customInput.value = '';
					}
					setUploadApplyVisibility(select);
					hideUploadActionHint(select);
				})
			.catch(function () {
				if (resultNode.classList.contains('ai-alt-message-success')) {
					return;
				}
				resultNode.textContent = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
				resultNode.classList.add('ai-alt-message-error');
			})
			.finally(function () {
				if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
					trigger.disabled = false;
				}
			});
	}

	function retrieveUploadAltText(trigger, resultNode) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var attachmentId = trigger && trigger.getAttribute ? String(trigger.getAttribute('data-attachment-id') || '') : '';
		var nonce = typeof adminData.uploadActionNonce === 'string' ? adminData.uploadActionNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}

		if (!(resultNode instanceof HTMLElement) || !attachmentId || !ajaxUrl || !nonce) {
			return;
		}

		if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
			trigger.disabled = true;
		}
		resultNode.textContent = '';
		resultNode.classList.remove('ai-alt-message-error');
		resultNode.classList.remove('ai-alt-message-success');

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_upload_action_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('attachment_id', attachmentId);
		body.append('review_action', 'generate');
		body.append('custom_alt', '');

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					var errorMessage = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					resultNode.textContent = errorMessage;
					resultNode.classList.add('ai-alt-message-error');
					return;
				}

				var message = payload.data && payload.data.message ? String(payload.data.message) : 'Action applied.';
				resultNode.textContent = message;
				resultNode.classList.add('ai-alt-message-success');

				if (payload.data && typeof payload.data.alt_text !== 'undefined') {
					var altText = String(payload.data.alt_text || '');
					if (!altText.trim()) {
						return;
					}
					var container = trigger.closest('.attachment-details, .media-sidebar, .compat-item, .setting, tr, table, tbody');
					var shouldSyncTitle = Boolean(adminData && adminData.syncTitleFromAlt);
					var shouldSyncCaption = Boolean(adminData && adminData.syncCaptionFromAlt);
					var shouldSyncDescription = Boolean(adminData && adminData.syncDescriptionFromAlt);
					applyAltAndMetaAcrossUi(attachmentId, altText, shouldSyncTitle, shouldSyncCaption, shouldSyncDescription, container);
				}
			})
			.catch(function () {
				if (resultNode.classList.contains('ai-alt-message-success')) {
					return;
				}
				resultNode.textContent = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
				resultNode.classList.add('ai-alt-message-error');
			})
			.finally(function () {
				if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
					trigger.disabled = false;
				}
			});
	}

	function processQueueRow(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueProcessNonce === 'string' ? adminData.queueProcessNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}
		var rowId = trigger && trigger.getAttribute ? String(trigger.getAttribute('data-row-id') || '') : '';
		if (!ajaxUrl || !nonce || !rowId) {
			return;
		}

		var row = trigger.closest('tr');
		if (!(row instanceof HTMLTableRowElement)) {
			return;
		}

		var progressWrap = row.querySelector('.ai-alt-row-progress-wrap');
		var progressBar = row.querySelector('.ai-alt-row-progress-bar');
		var messageNode = row.querySelector('.ai-alt-row-process-message');
		var statusNode = row.querySelector('.ai-alt-row-status');
		var confidenceNode = row.querySelector('.ai-alt-row-confidence');
		var suggestedInput = row.querySelector('.ai-alt-row-suggested');

		if (!(progressWrap instanceof HTMLDivElement) || !(progressBar instanceof HTMLDivElement) || !(messageNode instanceof HTMLElement)) {
			return;
		}

		function clearRowProcessFeedback() {
			progressWrap.hidden = true;
			messageNode.textContent = '';
			messageNode.classList.remove('ai-alt-message-success');
			messageNode.classList.remove('ai-alt-message-error');
		}

		function scheduleClearRowProcessFeedback() {
			window.setTimeout(function () {
				clearRowProcessFeedback();
			}, 1800);
		}

		trigger.disabled = true;
		progressWrap.hidden = false;
		progressBar.style.width = '0%';
		progressBar.setAttribute('aria-valuenow', '0');
		messageNode.textContent = i18n.rowProcessing || 'Processing image...';
		messageNode.classList.remove('ai-alt-message-success');
		messageNode.classList.remove('ai-alt-message-error');

		var progress = 0;
		var timer = window.setInterval(function () {
			progress = Math.min(progress + 8, 90);
			progressBar.style.width = progress + '%';
			progressBar.setAttribute('aria-valuenow', String(progress));
		}, 160);

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_process_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('row_id', rowId);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				window.clearInterval(timer);
				progressBar.style.width = '100%';
				progressBar.setAttribute('aria-valuenow', '100');

				if (!payload || payload.success !== true) {
					var errorMessage = i18n.rowError || 'Image processing failed. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					messageNode.textContent = errorMessage;
					messageNode.classList.add('ai-alt-message-error');
					scheduleClearRowProcessFeedback();
					return;
				}

				messageNode.textContent = (payload.data && payload.data.message) ? String(payload.data.message) : (i18n.rowSuccess || 'Image successfully processed');
				messageNode.classList.add('ai-alt-message-success');

				if (statusNode instanceof HTMLElement && payload.data && payload.data.status) {
					statusNode.textContent = String(payload.data.status);
				}
				if (confidenceNode instanceof HTMLElement && payload.data && typeof payload.data.confidence !== 'undefined') {
					var conf = Number(payload.data.confidence) || 0;
					confidenceNode.textContent = conf.toFixed(2);
				}
				if ((suggestedInput instanceof HTMLInputElement || suggestedInput instanceof HTMLTextAreaElement) && payload.data && typeof payload.data.suggested_alt !== 'undefined') {
					suggestedInput.value = String(payload.data.suggested_alt || '');
					if (suggestedInput instanceof HTMLTextAreaElement) {
						autoSizeSuggestedAltTextareas(suggestedInput.closest('tr') || row);
					}
				}
				scheduleClearRowProcessFeedback();
			})
			.catch(function () {
				window.clearInterval(timer);
				progressBar.style.width = '100%';
				progressBar.setAttribute('aria-valuenow', '100');
				messageNode.textContent = i18n.rowError || 'Image processing failed. Please try again.';
				messageNode.classList.add('ai-alt-message-error');
				scheduleClearRowProcessFeedback();
			})
			.finally(function () {
				trigger.disabled = false;
			});
	}

	function loadMoreQueueRows(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueLoadMoreNonce === 'string' ? adminData.queueLoadMoreNonce : '';
		if (!ajaxUrl || !nonce || !(trigger instanceof HTMLButtonElement)) {
			return;
		}

		var view = String(trigger.getAttribute('data-view') || 'active');
		var status = String(trigger.getAttribute('data-status') || '');
		var nextPage = Number(trigger.getAttribute('data-next-page') || '1') || 1;
		var perPage = Number(trigger.getAttribute('data-per-page') || '20') || 20;
		var excludeIds = String(trigger.getAttribute('data-exclude-ids') || '');
		var tbody = document.getElementById('ai-alt-queue-tbody');
		if (!(tbody instanceof HTMLTableSectionElement)) {
			return;
		}
		if (view === 'active' && excludeIds) {
			clearFocusedQueueIds();
		}

		trigger.disabled = true;
		var originalLabel = trigger.textContent || '';
		trigger.textContent = i18n.loadingMore || 'Loading more...';

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_load_more_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('view', view);
		body.append('status', status);
		body.append('page', String(nextPage));
		body.append('per_page', String(perPage));
		body.append('exclude_attachment_ids', excludeIds);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true || !payload.data || typeof payload.data.html !== 'string') {
					throw new Error(i18n.loadMoreError || 'Unable to load more items. Please try again.');
				}

				var emptyRow = tbody.querySelector('tr td[colspan]');
				if (emptyRow && emptyRow.parentElement === tbody) {
					tbody.removeChild(emptyRow.parentElement);
				}

				tbody.insertAdjacentHTML('beforeend', payload.data.html);
				autoSizeSuggestedAltTextareas(tbody);
				syncGenerateQueuedButtonState();

				var hasMore = Boolean(payload.data.has_more);
				var newNextPage = Number(payload.data.next_page || (nextPage + 1));
				if (hasMore) {
					trigger.setAttribute('data-next-page', String(newNextPage));
					trigger.disabled = false;
					trigger.textContent = originalLabel;
					return;
				}

				var wrap = trigger.closest('.ai-alt-load-more-wrap');
				if (wrap instanceof HTMLElement) {
					wrap.remove();
				}
			})
			.catch(function () {
				trigger.disabled = false;
				trigger.textContent = originalLabel;
				window.alert(i18n.loadMoreError || 'Unable to load more items. Please try again.');
			});
	}

	function initQueueBrowseTab() {
		var form = document.getElementById('ai-alt-browse-filters');
		var results = document.getElementById('ai-alt-browse-results');
		var summary = document.getElementById('ai-alt-browse-summary');
		var loadMoreButton = document.querySelector('.ai-alt-browse-load-more');
		var loadMoreWrap = document.getElementById('ai-alt-browse-load-more-wrap');
		var bulkToggleButton = document.getElementById('ai-alt-browse-bulk-toggle');
		var bulkActions = document.getElementById('ai-alt-browse-bulk-actions');
		var bulkCancelButton = document.getElementById('ai-alt-browse-bulk-cancel');
		var addSelectedButton = document.getElementById('ai-alt-browse-add-selected');
		if (!(form instanceof HTMLFormElement) || !(results instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueBrowseNonce === 'string' ? adminData.queueBrowseNonce : '';
		var addBrowseNonce = typeof adminData.queueAddBrowseNonce === 'string' ? adminData.queueAddBrowseNonce : '';
		var requestSerial = 0;
		var debounceTimer = 0;
		var bulkMode = false;
		var selectedIds = new Set();
		var lastSelectedAttachmentId = '';
		if (!ajaxUrl || !nonce) {
			return;
		}

		function updateBulkUi() {
			var count = selectedIds.size;
			results.classList.toggle('is-bulk-selecting', bulkMode);
			if (bulkActions instanceof HTMLElement) {
				bulkActions.hidden = !bulkMode;
			}
			if (addSelectedButton instanceof HTMLButtonElement) {
				addSelectedButton.disabled = count < 1;
			}
			if (bulkToggleButton instanceof HTMLButtonElement) {
				bulkToggleButton.hidden = bulkMode;
			}
		}

		function syncCardSelectionStates() {
			var cards = results.querySelectorAll('.ai-alt-browse-card');
			cards.forEach(function (card) {
				if (!(card instanceof HTMLElement)) {
					return;
				}
				var attachmentId = String(card.getAttribute('data-attachment-id') || '');
				var isSelected = selectedIds.has(attachmentId);
				card.classList.toggle('is-selected', isSelected);
			});
		}

		function setBulkMode(enabled) {
			bulkMode = Boolean(enabled);
			if (!bulkMode) {
				selectedIds.clear();
				lastSelectedAttachmentId = '';
			}
			syncCardSelectionStates();
			updateBulkUi();
		}

		function getBrowseCardElements() {
			return Array.prototype.slice.call(results.querySelectorAll('.ai-alt-browse-card')).filter(function (card) {
				return card instanceof HTMLElement;
			});
		}

		function getCardAttachmentId(card) {
			if (!(card instanceof HTMLElement)) {
				return '';
			}
			return String(card.getAttribute('data-attachment-id') || '');
		}

		function selectAttachmentRange(toAttachmentId) {
			var cards = getBrowseCardElements();
			if (!lastSelectedAttachmentId || !toAttachmentId) {
				return false;
			}

			var startIndex = -1;
			var endIndex = -1;
			cards.forEach(function (card, index) {
				var attachmentId = getCardAttachmentId(card);
				if (attachmentId === lastSelectedAttachmentId) {
					startIndex = index;
				}
				if (attachmentId === toAttachmentId) {
					endIndex = index;
				}
			});

			if (startIndex < 0 || endIndex < 0) {
				return false;
			}

			var rangeStart = Math.min(startIndex, endIndex);
			var rangeEnd = Math.max(startIndex, endIndex);
			for (var index = rangeStart; index <= rangeEnd; index += 1) {
				var rangeAttachmentId = getCardAttachmentId(cards[index]);
				if (rangeAttachmentId) {
					selectedIds.add(rangeAttachmentId);
				}
			}

			return true;
		}

		function toggleSelectedAttachment(attachmentId, event) {
			if (!attachmentId) {
				return;
			}

			if (event && event.shiftKey && selectAttachmentRange(attachmentId)) {
				lastSelectedAttachmentId = attachmentId;
			} else {
				if (selectedIds.has(attachmentId)) {
					selectedIds.delete(attachmentId);
				} else {
					selectedIds.add(attachmentId);
				}
				lastSelectedAttachmentId = attachmentId;
			}
			syncCardSelectionStates();
			updateBulkUi();
		}

		function updateSummary(shownCount, totalCount) {
			var searchField = form.querySelector('#ai-alt-browse-search');
			var searchTerm = searchField instanceof HTMLInputElement ? String(searchField.value || '').trim() : '';
			if (searchTerm) {
				if (totalCount > 0) {
					summary.textContent = 'Found ' + totalCount + ' result' + (totalCount === 1 ? '' : 's') + ' for "' + searchTerm + '".';
					return;
				}
				summary.textContent = i18n.browseNoResults || 'No images matched your filters.';
				return;
			}

			summary.textContent = 'Showing ' + shownCount + ' of ' + totalCount + ' media items';
		}

		function addBrowseReturnParam(link) {
			if (!(link instanceof HTMLAnchorElement)) {
				return;
			}

			var href = String(link.getAttribute('href') || '');
			if (!href) {
				return;
			}

			try {
				var url = new URL(href, window.location.origin);
				if (url.searchParams.has('ai_alt_return')) {
					return;
				}
				url.searchParams.set('ai_alt_return', window.location.href);
				link.setAttribute('href', url.toString());
			} catch (e) {
				// Ignore malformed URLs and keep default navigation behavior.
			}
		}

		function runBrowse(page, append) {
			var requestId = ++requestSerial;
			var dateFilter = form.querySelector('#ai-alt-browse-date');
			var altFilterField = form.querySelector('#ai-alt-browse-alt-filter');
			var categoryFilterField = form.querySelector('#ai-alt-browse-category');
			var filebirdFolderField = form.querySelector('#ai-alt-browse-filebird-folder');
			var searchField = form.querySelector('#ai-alt-browse-search');
			var perPage = 24;
			if (loadMoreButton instanceof HTMLButtonElement) {
				perPage = Number(loadMoreButton.getAttribute('data-per-page') || '24') || 24;
			}

			var body = new URLSearchParams();
			body.append('action', 'ai_alt_queue_browse_ajax');
			body.append('_ajax_nonce', nonce);
			body.append('page', String(page));
			body.append('per_page', String(perPage));
			body.append('browse_date', dateFilter instanceof HTMLSelectElement ? String(dateFilter.value || '') : '');
			body.append('browse_alt_filter', altFilterField instanceof HTMLSelectElement ? String(altFilterField.value || 'all') : 'all');
			body.append('browse_category', categoryFilterField instanceof HTMLSelectElement ? String(categoryFilterField.value || '0') : '0');
			body.append('browse_filebird_folder', filebirdFolderField instanceof HTMLSelectElement ? String(filebirdFolderField.value || '0') : '0');
			body.append('browse_search', searchField instanceof HTMLInputElement ? String(searchField.value || '') : '');

			if (!append) {
				selectedIds.clear();
				lastSelectedAttachmentId = '';
				results.innerHTML = '<div class="ai-alt-browse-empty">' + (i18n.browseLoading || 'Loading images...') + '</div>';
			}
			if (loadMoreButton instanceof HTMLButtonElement) {
				loadMoreButton.disabled = true;
			}

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					if (requestId !== requestSerial) {
						return;
					}

					if (!payload || payload.success !== true || !payload.data || typeof payload.data.html !== 'string') {
						throw new Error(i18n.browseError || 'Unable to load browse results. Please try again.');
					}

					if (append) {
						results.insertAdjacentHTML('beforeend', payload.data.html);
					} else {
						results.innerHTML = payload.data.html;
					}

					var cards = results.querySelectorAll('.ai-alt-browse-card').length;
					updateSummary(cards, Number(payload.data.total || 0) || 0);
					syncCardSelectionStates();
					updateBulkUi();

					if (loadMoreButton instanceof HTMLButtonElement && loadMoreWrap instanceof HTMLElement) {
						if (payload.data.has_more) {
							loadMoreButton.disabled = false;
							loadMoreButton.setAttribute('data-next-page', String(payload.data.next_page || (page + 1)));
							loadMoreWrap.style.display = '';
						} else {
							loadMoreWrap.style.display = 'none';
						}
					}
				})
				.catch(function () {
					if (requestId !== requestSerial) {
						return;
					}

					if (!append) {
						results.innerHTML = '<div class="ai-alt-browse-empty">' + (i18n.browseError || 'Unable to load browse results. Please try again.') + '</div>';
					}
					if (loadMoreButton instanceof HTMLButtonElement) {
						loadMoreButton.disabled = false;
					}
					syncCardSelectionStates();
					updateBulkUi();
				});
		}

		function updateBrowseSearchClearVisibility() {
			if (!(browseSearchField instanceof HTMLInputElement)) {
				return;
			}
			var clearButton = form.querySelector('.ai-alt-browse-search-clear');
			if (!(clearButton instanceof HTMLButtonElement)) {
				return;
			}
			clearButton.hidden = String(browseSearchField.value || '').trim() === '';
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			runBrowse(1, false);
		});

		var browseSearchField = form.querySelector('#ai-alt-browse-search');
		if (browseSearchField instanceof HTMLInputElement) {
			browseSearchField.addEventListener('input', function () {
				updateBrowseSearchClearVisibility();
				if (debounceTimer) {
					window.clearTimeout(debounceTimer);
				}
				debounceTimer = window.setTimeout(function () {
					runBrowse(1, false);
				}, 280);
			});

			updateBrowseSearchClearVisibility();

			var browseSearchClear = form.querySelector('.ai-alt-browse-search-clear');
			if (browseSearchClear instanceof HTMLButtonElement) {
				browseSearchClear.addEventListener('click', function () {
					browseSearchField.value = '';
					updateBrowseSearchClearVisibility();
					runBrowse(1, false);
					browseSearchField.focus();
				});
			}
		}

		var browseDateField = form.querySelector('#ai-alt-browse-date');
		if (browseDateField instanceof HTMLSelectElement) {
			browseDateField.addEventListener('change', function () {
				runBrowse(1, false);
			});
		}

		var browseAltFilterField = form.querySelector('#ai-alt-browse-alt-filter');
		if (browseAltFilterField instanceof HTMLSelectElement) {
			browseAltFilterField.addEventListener('change', function () {
				runBrowse(1, false);
			});
		}

		var browseCategoryField = form.querySelector('#ai-alt-browse-category');
		if (browseCategoryField instanceof HTMLSelectElement) {
			browseCategoryField.addEventListener('change', function () {
				runBrowse(1, false);
			});
		}

		var browseFileBirdField = form.querySelector('#ai-alt-browse-filebird-folder');
		if (browseFileBirdField instanceof HTMLSelectElement) {
			browseFileBirdField.addEventListener('change', function () {
				runBrowse(1, false);
			});
		}

		if (loadMoreButton instanceof HTMLButtonElement) {
			loadMoreButton.addEventListener('click', function (event) {
				event.preventDefault();
				var nextPage = Number(loadMoreButton.getAttribute('data-next-page') || '2') || 2;
				runBrowse(nextPage, true);
			});
		}

		if (bulkToggleButton instanceof HTMLButtonElement) {
			bulkToggleButton.addEventListener('click', function () {
				setBulkMode(true);
			});
		}

		if (bulkCancelButton instanceof HTMLButtonElement) {
			bulkCancelButton.addEventListener('click', function () {
				setBulkMode(false);
			});
		}

		if (addSelectedButton instanceof HTMLButtonElement) {
			addSelectedButton.addEventListener('click', function () {
				if (!addBrowseNonce || selectedIds.size < 1) {
					return;
				}

				var ids = Array.from(selectedIds);
				addSelectedButton.disabled = true;

				var body = new URLSearchParams();
				body.append('action', 'ai_alt_queue_add_browse_ajax');
				body.append('_ajax_nonce', addBrowseNonce);
				ids.forEach(function (attachmentId) {
					body.append('attachment_ids[]', attachmentId);
				});

				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (!payload || payload.success !== true || !payload.data || !payload.data.redirect_url) {
							var errorMessage = i18n.queueAddSelectedError || 'Unable to add the selected images to queue.';
							if (payload && payload.data && payload.data.message) {
								errorMessage = String(payload.data.message);
							}
							window.alert(errorMessage);
							addSelectedButton.disabled = false;
							return;
						}

						var redirectUrl = new URL(String(payload.data.redirect_url), window.location.origin);
						var queuedIds = sanitizeFocusedQueueIds(redirectUrl.searchParams.get('queued_ids') || '');
						if (queuedIds) {
							storeFocusedQueueIds(queuedIds);
						}
						window.location.href = redirectUrl.toString();
					})
					.catch(function () {
						window.alert(i18n.queueAddSelectedError || 'Unable to add the selected images to queue.');
						addSelectedButton.disabled = false;
					});
			});
		}

		results.addEventListener('click', function (event) {
			var target = event.target;
			if (!(target instanceof HTMLElement)) {
				return;
			}

			var link = target.closest('a.ai-alt-browse-thumb-link');
			if (!(link instanceof HTMLAnchorElement)) {
				return;
			}

			if (bulkMode) {
				event.preventDefault();
				var card = link.closest('.ai-alt-browse-card');
				if (card instanceof HTMLElement) {
					toggleSelectedAttachment(String(card.getAttribute('data-attachment-id') || ''), event);
				}
				return;
			}

			addBrowseReturnParam(link);
		});

		updateBulkUi();
		syncCardSelectionStates();
	}

		function initMediaGridReturnToBrowse() {
			var currentUrl;
			try {
				currentUrl = new URL(window.location.href);
			} catch (e) {
				return;
			}

			var returnTarget = String(currentUrl.searchParams.get('ai_alt_return') || '');
			if (!returnTarget) {
				return;
			}

			var returnUrl;
			try {
				returnUrl = new URL(returnTarget, window.location.origin);
			} catch (e) {
				return;
			}

			if (returnUrl.origin !== window.location.origin) {
				return;
			}

			var returnHref = returnUrl.toString();
			var bindAttempts = 0;
			var bindTimer = 0;

			function bindCloseHandler() {
				if (!window.wp || !window.wp.media || !window.wp.media.frames) {
					return false;
				}

				var frame = window.wp.media.frames.edit;
				if (!frame || typeof frame.on !== 'function') {
					return false;
				}

				if (frame.aiAltReturnBound) {
					return true;
				}

				frame.aiAltReturnBound = true;
				frame.on('close', function () {
					window.location.href = returnHref;
				});
				return true;
			}

			if (bindCloseHandler()) {
				return;
			}

			bindTimer = window.setInterval(function () {
				bindAttempts++;
				if (bindCloseHandler() || bindAttempts > 60) {
					window.clearInterval(bindTimer);
				}
			}, 150);
		}

	function addNoAltImageToQueue(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueAddNoAltNonce === 'string' ? adminData.queueAddNoAltNonce : '';
		if (!ajaxUrl || !nonce || !(trigger instanceof HTMLButtonElement)) {
			return;
		}

		var attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
		if (!attachmentId) {
			return;
		}

		var row = trigger.closest('tr');
		var messageNode = row ? row.querySelector('.ai-alt-no-alt-message') : null;
		var statusNode = row ? row.querySelector('.ai-alt-no-alt-queue-status') : null;

		trigger.disabled = true;
		if (messageNode instanceof HTMLElement) {
			messageNode.textContent = '';
			messageNode.classList.remove('ai-alt-message-success');
			messageNode.classList.remove('ai-alt-message-error');
		}

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_add_no_alt_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('attachment_id', attachmentId);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					var err = i18n.queueAddError || 'Unable to add image to queue.';
					if (payload && payload.data && payload.data.message) {
						err = String(payload.data.message);
					}
					if (messageNode instanceof HTMLElement) {
						messageNode.textContent = err;
						messageNode.classList.add('ai-alt-message-error');
					}
					trigger.disabled = false;
					return;
				}

				trigger.textContent = i18n.queueAddSuccess || 'Added to queue';
				if (statusNode instanceof HTMLElement) {
					statusNode.textContent = 'queued';
				}
				storeFocusedQueueIds(attachmentId);
				if (messageNode instanceof HTMLElement) {
					messageNode.textContent = i18n.queueAddSuccess || 'Added to queue';
					messageNode.classList.add('ai-alt-message-success');
				}
			})
			.catch(function () {
				if (messageNode instanceof HTMLElement) {
					messageNode.textContent = i18n.queueAddError || 'Unable to add image to queue.';
					messageNode.classList.add('ai-alt-message-error');
				}
				trigger.disabled = false;
			});
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLElement)) {
			return;
		}

		var trigger = target.closest('button, input[type="submit"], input[type="button"]');
		if (!(trigger instanceof HTMLButtonElement) && !(trigger instanceof HTMLInputElement)) {
			return;
		}

		if (trigger.classList.contains('ai-alt-load-more')) {
			event.preventDefault();
			loadMoreQueueRows(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-add-no-alt')) {
			event.preventDefault();
			addNoAltImageToQueue(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-row-process')) {
			event.preventDefault();
			processQueueRow(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-toggle-token')) {
			var inputId = trigger.getAttribute('data-target');
			if (!inputId) {
				return;
			}

			var input = document.getElementById(inputId);
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			var showLabel = trigger.getAttribute('data-show-label') || 'Show';
			var hideLabel = trigger.getAttribute('data-hide-label') || 'Hide';
			var showing = input.type === 'text';

			input.type = showing ? 'password' : 'text';
			trigger.textContent = showing ? showLabel : hideLabel;
			trigger.setAttribute('aria-pressed', showing ? 'false' : 'true');
			return;
		}

			var actionValue = String(trigger.value || '');
			var isReject = actionValue === 'reject' || actionValue.indexOf('reject|') === 0;
			var isSkip = actionValue === 'skip' || actionValue.indexOf('skip|') === 0;
			var adminData = window.aiAltAdmin || {};
			var i18n = adminData.i18n || {};

				if (isReject || isSkip) {
					var message = isSkip
						? (i18n.confirmSkip || 'Skip this image and move it to History?')
						: (i18n.confirmReject || 'Reject this generated alt text?');

				if (!window.confirm(message)) {
					event.preventDefault();
				}
			}

				if (trigger.classList.contains('ai-alt-upload-retrieve')) {
					event.preventDefault();
					var retrieveRow = target.closest('tr, .compat-field, .setting, .attachment-details');
					var retrieveResultNode = retrieveRow ? retrieveRow.querySelector('.ai-alt-upload-action-result') : null;
					if (!(retrieveResultNode instanceof HTMLElement)) {
						retrieveResultNode = document.querySelector('.ai-alt-upload-action-result');
					}
					retrieveUploadAltText(trigger, retrieveResultNode);
					return;
				}

				if (trigger.classList.contains('ai-alt-upload-apply')) {
					event.preventDefault();
					var row = target.closest('tr, .compat-field, .setting, .attachment-details');
					var attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
					var select = row ? row.querySelector('.ai-alt-upload-action') : null;
					var customInput = row ? row.querySelector('.ai-alt-upload-custom-alt') : null;
					var resultNode = row ? row.querySelector('.ai-alt-upload-action-result') : null;
					if (!(select instanceof HTMLSelectElement)) {
						select = document.querySelector('select.ai-alt-upload-action[name="attachments[' + attachmentId + '][ai_alt_action]"]');
				}
					if (!(customInput instanceof HTMLInputElement) && !(customInput instanceof HTMLTextAreaElement)) {
						customInput = document.querySelector('input.ai-alt-upload-custom-alt[name="attachments[' + attachmentId + '][ai_alt_custom_alt]"]');
					}
				if (!(resultNode instanceof HTMLElement)) {
					resultNode = document.querySelector('.ai-alt-upload-action-result');
				}
				applyUploadAction(trigger, select, customInput, resultNode);
			}
		});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (target instanceof HTMLTextAreaElement && target.classList.contains('ai-alt-row-suggested')) {
			autoSizeSuggestedAltTextareas(target.closest('tr') || target);
			return;
		}
		if (target instanceof HTMLInputElement && target.classList.contains('ai-alt-admin-role-lock')) {
			target.checked = true;
			return;
		}
		if (target instanceof HTMLInputElement && target.classList.contains('ai-alt-select-all')) {
			var checked = target.checked;
			var checkboxes = document.querySelectorAll('.ai-alt-row-checkbox');
			checkboxes.forEach(function (checkbox) {
				if (checkbox instanceof HTMLInputElement) {
					checkbox.checked = checked;
				}
			});
			return;
		}

				var isUploadActionSelect = target instanceof HTMLSelectElement && (target.classList.contains('ai-alt-upload-action') || /\[ai_alt_action\]$/.test(String(target.name || '')));
				if (isUploadActionSelect) {
					var actionValue = String(target.value || '');
					setUploadApplyVisibility(target);
					setUploadCustomVisibility(target);
					if (!actionValue) {
						return;
					}
				if (actionValue === 'custom') {
					return;
				}

			var container = target.closest('tr, .compat-field, .setting, .attachment-details');
			var applyButton = container ? container.querySelector('.ai-alt-upload-apply') : null;
			if (applyButton instanceof HTMLButtonElement || applyButton instanceof HTMLInputElement) {
				applyButton.click();
				return;
			}

			var customInput = container ? container.querySelector('.ai-alt-upload-custom-alt') : null;
			var resultNode = container ? container.querySelector('.ai-alt-upload-action-result') : null;
			if (!(customInput instanceof HTMLInputElement) && !(customInput instanceof HTMLTextAreaElement)) {
				customInput = document.querySelector('input.ai-alt-upload-custom-alt[name="attachments[' + String(target.getAttribute('data-attachment-id') || '') + '][ai_alt_custom_alt]"]');
			}
			if (!(resultNode instanceof HTMLElement)) {
				resultNode = document.querySelector('.ai-alt-upload-action-result');
			}
				applyUploadAction(target, target, customInput, resultNode);
			}
		});

		document.addEventListener('DOMContentLoaded', function () {
			placeRetrieveButtons();
			initSettingsTabs();
			initSettingsMetricsRefresh();
			initQueueTabNoticeReset();
			initQueueBrowseTab();
			initMediaGridReturnToBrowse();
			autoSizeSuggestedAltTextareas(document);
			var lockedAdminRoleCheckboxes = document.querySelectorAll('input.ai-alt-admin-role-lock');
			lockedAdminRoleCheckboxes.forEach(function (checkbox) {
				if (checkbox instanceof HTMLInputElement) {
					checkbox.checked = true;
				}
			});

			var selects = document.querySelectorAll('select.ai-alt-upload-action');
			selects.forEach(function (select) {
				if (select instanceof HTMLSelectElement) {
					setUploadApplyVisibility(select);
					setUploadCustomVisibility(select);
				}
			});

		// Make admin notices one-time by removing notice query args after render.
		if (window.history && typeof window.history.replaceState === 'function') {
			var url = new URL(window.location.href);
			var noticeParams = [
				'notice',
				'processed',
				'deleted',
				'enqueued',
				'updated',
				'test_status',
				'test_msg',
				'queue_msg',
				'queue_refresh',
				'process_msg',
				'settings-updated',
				'_wp_http_referer'
			];
			var changed = false;
			noticeParams.forEach(function (key) {
				if (url.searchParams.has(key)) {
					url.searchParams.delete(key);
					changed = true;
				}
			});
			if (changed) {
				window.history.replaceState({}, document.title, url.toString());
			}
		}
	});

	var attachmentObserver = new MutationObserver(function () {
		placeRetrieveButtons();
	});

	attachmentObserver.observe(document.documentElement, {
		childList: true,
		subtree: true
	});

	window.addEventListener('resize', function () {
		placeRetrieveButtons();
	});

	function getQueueProgressNodes() {
		return {
			wrap: document.getElementById('ai-alt-queue-progress-wrap'),
			bar: document.getElementById('ai-alt-queue-progress-bar'),
			message: document.getElementById('ai-alt-queue-progress-message')
		};
	}

	function getQueueBulkAction(form) {
		if (!(form instanceof HTMLFormElement)) {
			return '';
		}
		var topSelect = form.querySelector('#bulk-action-selector-top');
		var bottomSelect = form.querySelector('#bulk-action-selector-bottom');
		var topValue = topSelect instanceof HTMLSelectElement ? String(topSelect.value || '') : '';
		if (topValue && topValue !== '-1') {
			return topValue;
		}
		var bottomValue = bottomSelect instanceof HTMLSelectElement ? String(bottomSelect.value || '') : '';
		return (bottomValue && bottomValue !== '-1') ? bottomValue : '';
	}

	function getSelectedQueueRowIds(form) {
		if (!(form instanceof HTMLFormElement)) {
			return [];
		}
		var ids = [];
		var checkboxes = form.querySelectorAll('input.ai-alt-row-checkbox:checked');
		checkboxes.forEach(function (checkbox) {
			if (checkbox instanceof HTMLInputElement) {
				var value = String(checkbox.value || '').trim();
				if (value) {
					ids.push(value);
				}
			}
		});
		return ids;
	}

	function initGenerateAllVisibleButton() {
		var trigger = document.getElementById('ai-alt-generate-all-visible');
		var form = document.querySelector('.ai-alt-queue-form');
		if (!(trigger instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) {
			return;
		}
		syncGenerateQueuedButtonState();

		trigger.addEventListener('click', function () {
			if (trigger.disabled) {
				return;
			}
			var checkboxes = Array.prototype.slice.call(form.querySelectorAll('input.ai-alt-row-checkbox'));
			var eligible = checkboxes.filter(function (checkbox) {
				return checkbox instanceof HTMLInputElement && String(checkbox.getAttribute('data-is-queued') || '') === '1';
			});
			if (!eligible.length) {
				syncGenerateQueuedButtonState();
				return;
			}

			checkboxes.forEach(function (checkbox) {
				if (checkbox instanceof HTMLInputElement) {
					checkbox.checked = false;
				}
			});
			eligible.forEach(function (checkbox) {
				checkbox.checked = true;
			});

			var selectAll = form.querySelector('.ai-alt-select-all');
			if (selectAll instanceof HTMLInputElement) {
				selectAll.checked = false;
			}

			var topSelect = form.querySelector('#bulk-action-selector-top');
			var bottomSelect = form.querySelector('#bulk-action-selector-bottom');
			if (topSelect instanceof HTMLSelectElement) {
				topSelect.value = 'process';
			}
			if (bottomSelect instanceof HTMLSelectElement) {
				bottomSelect.value = '-1';
			}

			var topApplyButton = form.querySelector('.tablenav.top .button.action');
			if (typeof form.requestSubmit === 'function' && (topApplyButton instanceof HTMLButtonElement || topApplyButton instanceof HTMLInputElement)) {
				form.requestSubmit(topApplyButton);
				return;
			}
			if (topApplyButton instanceof HTMLButtonElement || topApplyButton instanceof HTMLInputElement) {
				topApplyButton.click();
				return;
			}

			form.submit();
		});
	}

	function redirectQueueNotice(notice, params) {
		var url = new URL(window.location.href);
		url.searchParams.set('page', 'ai-alt-text-queue');
		url.searchParams.set('notice', notice);
		if (params) {
			Object.keys(params).forEach(function (key) {
				if (typeof params[key] !== 'undefined' && params[key] !== null) {
					url.searchParams.set(key, String(params[key]));
				}
			});
		}
		window.location.href = url.toString();
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		if (!form.classList.contains('ai-alt-queue-form')) {
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' ? adminData.ajaxUrl : '';

		var queueNodes = getQueueProgressNodes();
		var queueWrap = queueNodes.wrap;
		var queueBar = queueNodes.bar;
		var queueMessage = queueNodes.message;
		if (!(queueWrap instanceof HTMLDivElement) || !(queueBar instanceof HTMLDivElement) || !(queueMessage instanceof HTMLElement)) {
			return;
		}

		function setQueueProgress(percent, text, state) {
			var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
			queueWrap.hidden = false;
			queueBar.style.width = safePercent + '%';
			queueBar.setAttribute('aria-valuenow', String(safePercent));
			queueMessage.textContent = text || '';
			queueMessage.classList.remove('ai-alt-message-error');
			queueMessage.classList.remove('ai-alt-message-success');
			if (state === 'error') {
				queueMessage.classList.add('ai-alt-message-error');
			} else if (state === 'success') {
				queueMessage.classList.add('ai-alt-message-success');
			}
		}

		var bulkAction = getQueueBulkAction(form);
		if (bulkAction !== 'process') {
			return;
		}

		var rowIds = getSelectedQueueRowIds(form);
		if (rowIds.length < 1) {
			return;
		}

		var bulkSelectionCap = 20;
		if (rowIds.length > bulkSelectionCap) {
			event.preventDefault();
			setQueueProgress(100, i18n.bulkSelectionLimit || ('Select no more than ' + bulkSelectionCap + ' items for bulk processing at one time.'), 'error');
			return;
		}

		event.preventDefault();
		var bulkNonce = typeof adminData.queueProcessNonce === 'string' ? adminData.queueProcessNonce : '';
		var submitter = event.submitter;
		var bulkSubmitButton = (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
			? submitter
			: form.querySelector('.tablenav.top .button.action, .tablenav.bottom .button.action');
		if (!(bulkSubmitButton instanceof HTMLButtonElement || bulkSubmitButton instanceof HTMLInputElement) || !ajaxUrl || !bulkNonce) {
			form.submit();
			return;
		}

		bulkSubmitButton.disabled = true;
		var processedCount = 0;
		var failureCount = 0;
		var currentIndex = 0;

		function finishBulkProcess() {
			var percent = 100;
			if (failureCount > 0) {
				var mixedMessage = processedCount > 0
					? 'Processed ' + processedCount + ' images, ' + failureCount + ' failed.'
					: (i18n.rowError || 'Image processing failed. Please try again.');
				setQueueProgress(percent, '', '');
				window.setTimeout(function () {
					redirectQueueNotice('queue_error', { queue_msg: mixedMessage });
				}, 250);
				return;
			}

			var successMessage = (i18n.success || 'Manual processing finished. %d items processed.').replace('%d', String(processedCount));
			setQueueProgress(percent, successMessage, 'success');
			window.setTimeout(function () {
				redirectQueueNotice('queue_batch_done', { processed: processedCount });
			}, 250);
		}

		function processNextBulkRow() {
			if (currentIndex >= rowIds.length) {
				finishBulkProcess();
				return;
			}

			var rowId = rowIds[currentIndex];
			var percent = Math.round((currentIndex / rowIds.length) * 100);
			setQueueProgress(percent, 'Processing images... ' + (currentIndex + 1) + ' of ' + rowIds.length, '');

			var body = new URLSearchParams();
			body.append('action', 'ai_alt_queue_process_ajax');
			body.append('_ajax_nonce', bulkNonce);
			body.append('row_id', rowId);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					if (payload && payload.success === true) {
						processedCount += 1;
					} else {
						if (isProviderWidePayload(payload)) {
							var providerMessage = getPayloadMessage(payload, i18n.providerPaused || i18n.rowError || 'Image processing failed. Please try again.');
							throw createProviderWideError(providerMessage);
						}
						failureCount += 1;
					}
				})
				.catch(function (err) {
					var providerWide = Boolean(err && err.aiAltProviderWide);
					if (providerWide) {
						var message = (err && err.message) ? String(err.message) : (i18n.providerPaused || i18n.rowError || 'Image processing failed. Please try again.');
						setQueueProgress(Math.round((currentIndex / rowIds.length) * 100), message, 'error');
						window.setTimeout(function () {
							redirectQueueNotice('queue_error', { queue_msg: message });
						}, 250);
						return;
					}

					failureCount += 1;
				})
				.finally(function () {
					if (queueMessage.classList.contains('ai-alt-message-error')) {
						return;
					}
					currentIndex += 1;
					processNextBulkRow();
				});
		}

		processNextBulkRow();
	});

	if (!initFocusedQueuePersistence()) {
		initGenerateAllVisibleButton();
		syncGenerateQueuedButtonState();
	}
})();
