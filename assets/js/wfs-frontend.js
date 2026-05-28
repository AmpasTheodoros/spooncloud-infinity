/**
 * Weather Food Suggestion — frontend
 */
(function () {
	'use strict';

	if (typeof wfsData === 'undefined') {
		return;
	}

	var i18n = wfsData.i18n || {};

	/**
	 * Set element text content safely.
	 *
	 * @param {Element|null} el
	 * @param {string} text
	 */
	function setText(el, text) {
		if (el) {
			el.textContent = text || '';
		}
	}

	/**
	 * Clear and populate a list element.
	 *
	 * @param {HTMLUListElement|null} list
	 * @param {string[]} items
	 * @param {string} emptyMsg
	 */
	function fillList(list, items, emptyMsg) {
		if (!list) {
			return;
		}
		list.innerHTML = '';
		if (!items || !items.length) {
			var li = document.createElement('li');
			li.textContent = emptyMsg || '';
			list.appendChild(li);
			return;
		}
		items.forEach(function (item) {
			var li = document.createElement('li');
			li.textContent = item;
			list.appendChild(li);
		});
	}

	/**
	 * Show error message in widget.
	 *
	 * @param {HTMLElement} wrap
	 * @param {string} message
	 */
	function showError(wrap, message) {
		var errorEl = wrap.querySelector('.wfs-error');
		if (errorEl) {
			errorEl.textContent = message;
			errorEl.hidden = false;
		}
		wrap.classList.add('wfs-has-error');
	}

	/**
	 * Hide error message.
	 *
	 * @param {HTMLElement} wrap
	 */
	function hideError(wrap) {
		var errorEl = wrap.querySelector('.wfs-error');
		if (errorEl) {
			errorEl.hidden = true;
			errorEl.textContent = '';
		}
		wrap.classList.remove('wfs-has-error');
	}

	/**
	 * Set loading state.
	 *
	 * @param {HTMLElement} wrap
	 * @param {boolean} loading
	 */
	function setLoading(wrap, loading) {
		var submit = wrap.querySelector('.wfs-submit');
		wrap.classList.toggle('wfs-is-loading', loading);
		if (submit) {
			submit.disabled = loading;
			submit.setAttribute('aria-busy', loading ? 'true' : 'false');
		}
	}

	/**
	 * Parse fridge textarea into array.
	 *
	 * @param {string} text
	 * @returns {string[]}
	 */
	function parseFridgeText(text) {
		if (!text) {
			return [];
		}
		return text
			.split(/[\n,;]+/)
			.map(function (s) {
				return s
					.trim()
					.toLowerCase()
					.replace(/[^\p{L}\p{N}\s\-]/gu, '')
					.replace(/\s+/g, ' ');
			})
			.filter(function (s) {
				return s.length >= 2 && s.length <= 64;
			})
			.filter(function (s, i, arr) {
				return arr.indexOf(s) === i;
			})
			.slice(0, 20);
	}

	/**
	 * Render API result into DOM.
	 *
	 * @param {HTMLElement} wrap
	 * @param {object} data
	 */
	function renderResult(wrap, data) {
		var result = wrap.querySelector('.wfs-result');
		if (!result) {
			return;
		}

		var suggestion = data.suggestion || {};
		var weather = data.weather || {};

		setText(result.querySelector('.wfs-result-title'), suggestion.title);
		setText(
			result.querySelector('.wfs-weather-summary'),
			(i18n.weatherHeading || 'Current weather') + ': ' + (weather.summary || '')
		);
		setText(result.querySelector('.wfs-reason'), suggestion.reason);

		var used = suggestion.ingredients_used || [];
		fillList(
			result.querySelector('.wfs-used-list'),
			used,
			i18n.noUsedIngredients || 'No direct matches from your list.'
		);

		var missing = suggestion.ingredients_missing || [];
		var missingBlock = result.querySelector('.wfs-missing-block');
		if (missingBlock) {
			missingBlock.hidden = !missing.length;
		}
		fillList(result.querySelector('.wfs-missing-list'), missing, '');

		var stepsList = result.querySelector('.wfs-steps-list');
		if (stepsList) {
			stepsList.innerHTML = '';
			(suggestion.steps || []).forEach(function (step) {
				var li = document.createElement('li');
				li.textContent = step;
				stepsList.appendChild(li);
			});
		}

		result.hidden = false;
		result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	/**
	 * POST suggestion request.
	 *
	 * @param {object} payload
	 * @returns {Promise<object>}
	 */
	function fetchSuggestion(payload) {
		return fetch(wfsData.restUrl + 'suggest', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wfsData.nonce,
			},
			body: JSON.stringify(payload),
			credentials: 'same-origin',
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					var msg =
						(body && body.message) ||
						i18n.errorGeneric ||
						'Something went wrong.';
					throw new Error(msg);
				}
				return body;
			});
		});
	}

	/**
	 * Initialize one widget instance.
	 *
	 * @param {HTMLElement} wrap
	 */
	function initInstance(wrap) {
		if (wrap.dataset.wfsInitialized) {
			return;
		}
		wrap.dataset.wfsInitialized = '1';

		var form = wrap.querySelector('.wfs-form');
		var cityInput = wrap.querySelector('[name="city"]');
		var fridgeInput = wrap.querySelector('[name="fridge_text"]');
		var dietarySelect = wrap.querySelector('[name="dietary"]');
		var mealSelect = wrap.querySelector('[name="meal_type"]');
		var geoBtn = wrap.querySelector('.wfs-geolocate');

		var coords = { lat: null, lon: null };

		if (geoBtn) {
			geoBtn.addEventListener('click', function () {
				hideError(wrap);
				if (!navigator.geolocation) {
					showError(
						wrap,
						i18n.locationUnavailable ||
							'Geolocation is not supported by your browser.'
					);
					return;
				}

				geoBtn.disabled = true;
				navigator.geolocation.getCurrentPosition(
					function (pos) {
						coords.lat = pos.coords.latitude;
						coords.lon = pos.coords.longitude;
						if (cityInput) {
							cityInput.value = '';
							cityInput.placeholder =
								'Using your current location…';
						}
						geoBtn.disabled = false;
					},
					function (err) {
						geoBtn.disabled = false;
						var msg = i18n.locationUnavailable;
						if (err.code === 1) {
							msg = i18n.locationDenied;
						} else if (err.code === 3) {
							msg = i18n.locationTimeout;
						}
						showError(wrap, msg);
					},
					{ enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 }
				);
			});
		}

		if (cityInput) {
			cityInput.addEventListener('input', function () {
				coords.lat = null;
				coords.lon = null;
				cityInput.placeholder = cityInput.getAttribute('data-placeholder') || '';
			});
			if (!cityInput.getAttribute('data-placeholder')) {
				cityInput.setAttribute('data-placeholder', cityInput.placeholder || '');
			}
		}

		if (!form) {
			return;
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			hideError(wrap);

			var city = cityInput ? cityInput.value.trim() : '';
			var hasCoords =
				typeof coords.lat === 'number' && typeof coords.lon === 'number';

			if (!city && !hasCoords) {
				showError(wrap, i18n.cityRequired || 'Please enter a city or use location.');
				return;
			}

			var payload = {
				fridge_text: fridgeInput ? fridgeInput.value : '',
				fridge_items: parseFridgeText(fridgeInput ? fridgeInput.value : ''),
				dietary: dietarySelect ? dietarySelect.value : 'none',
				meal_type: mealSelect ? mealSelect.value : '',
			};

			if (hasCoords && !city) {
				payload.latitude = coords.lat;
				payload.longitude = coords.lon;
			} else {
				payload.city = city;
			}

			setLoading(wrap, true);

			fetchSuggestion(payload)
				.then(function (data) {
					renderResult(wrap, data);
				})
				.catch(function (err) {
					showError(wrap, err.message || i18n.errorGeneric);
				})
				.finally(function () {
					setLoading(wrap, false);
				});
		});
	}

	function init() {
		document.querySelectorAll('[data-wfs-instance]').forEach(initInstance);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
