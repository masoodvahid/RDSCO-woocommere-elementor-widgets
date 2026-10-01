/*!
 * BB Product Search — vanilla JS, no dependencies.
 * Debounced (always waits `delay` ms after the last keystroke), min chars,
 * aborts stale requests, and keeps an in-memory cache of recent terms.
 */
(() => {
	'use strict';

	const config = window.bbpsConfig;
	if (!config || !config.endpoint || !('fetch' in window) || !('AbortController' in window)) {
		return;
	}

	const MIN_CHARS = Number(config.minChars) || 3;
	const DELAY = Number(config.delay) || 500;
	const CACHE_MAX = 50;
	const t = config.i18n || {};
	const cache = new Map(); // Shared by all instances on the page.

	const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();
	const charCount = (value) => Array.from(value).length;
	const isHttpUrl = (value) => /^https?:\/\//i.test(String(value || ''));

	function cacheSet(key, value) {
		if (cache.size >= CACHE_MAX) {
			cache.delete(cache.keys().next().value);
		}
		cache.set(key, value);
	}

	function span(className, text) {
		const el = document.createElement('span');
		el.className = className;
		if (text !== undefined) {
			el.textContent = text;
		}
		return el;
	}

	function init(form) {
		if (form.dataset.bbpsReady) {
			return;
		}
		form.dataset.bbpsReady = '1';

		const input = form.querySelector('.bbps__input');
		const panel = form.querySelector('.bbps__panel');
		const list = form.querySelector('.bbps__list');
		const message = form.querySelector('.bbps__message');
		const status = form.querySelector('.bbps__status');
		if (!input || !panel || !list || !message || !status) {
			return;
		}

		const limit = Number(form.dataset.limit) || 8;
		let timer = 0;
		let controller = null;
		let shownTerm = '';
		let active = -1;

		const options = () => Array.from(list.querySelectorAll('[role="option"]'));

		function open() {
			panel.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		function close() {
			panel.hidden = true;
			input.setAttribute('aria-expanded', 'false');
			setActive(-1);
		}

		function reset() {
			shownTerm = '';
			list.textContent = '';
			message.hidden = true;
			status.textContent = '';
			close();
		}

		function setBusy(busy) {
			form.classList.toggle('is-loading', busy);
			form.setAttribute('aria-busy', busy ? 'true' : 'false');
		}

		function abort() {
			if (controller) {
				controller.abort();
				controller = null;
			}
			setBusy(false);
		}

		function setActive(index) {
			const items = options();
			active = index >= 0 && index < items.length ? index : -1;

			items.forEach((el, i) => {
				const on = i === active;
				el.classList.toggle('is-active', on);
				el.setAttribute('aria-selected', on ? 'true' : 'false');
			});

			if (active >= 0) {
				input.setAttribute('aria-activedescendant', items[active].id);
				items[active].scrollIntoView({ block: 'nearest' });
			} else {
				input.removeAttribute('aria-activedescendant');
			}
		}

		function showMessage(text, term) {
			shownTerm = term;
			list.textContent = '';
			setActive(-1);
			message.textContent = text || '';
			message.hidden = false;
			status.textContent = text || '';
			open();
		}

		function buildOption(index, href, children, extraClass) {
			const li = document.createElement('li');
			li.className = 'bbps__item' + (extraClass ? ' ' + extraClass : '');
			li.id = list.id + '-opt-' + index;
			li.setAttribute('role', 'option');
			li.setAttribute('aria-selected', 'false');

			const a = document.createElement('a');
			a.className = 'bbps__link';
			a.href = href;
			a.tabIndex = -1;
			children.forEach((child) => a.appendChild(child));

			li.appendChild(a);
			return li;
		}

		function productOption(item, index) {
			const children = [];

			if (isHttpUrl(item.image)) {
				const img = document.createElement('img');
				img.className = 'bbps__thumb';
				img.src = item.image;
				img.alt = '';
				img.width = 48;
				img.height = 48;
				img.decoding = 'async';
				img.loading = 'lazy';
				children.push(img);
			}

			const body = span('bbps__body');
			body.appendChild(span('bbps__title', item.title || ''));
			if (item.sku) {
				body.appendChild(span('bbps__meta', (t.sku || 'SKU') + ': ' + item.sku));
			}
			if (item.price) {
				const price = span('bbps__price');
				price.innerHTML = item.price; // Sanitized server-side with wp_kses_post().
				body.appendChild(price);
			}
			children.push(body);

			return buildOption(index, item.url, children);
		}

		function viewAllUrl(term) {
			const url = new URL(form.action, window.location.href);
			url.searchParams.set('s', term);
			url.searchParams.set('post_type', 'product');
			return url.toString();
		}

		function render(items, term) {
			const valid = items.filter((item) => item && isHttpUrl(item.url));

			if (!valid.length) {
				showMessage(t.none, term);
				return;
			}

			shownTerm = term;
			message.hidden = true;
			list.textContent = '';
			active = -1;
			input.removeAttribute('aria-activedescendant');

			const frag = document.createDocumentFragment();
			valid.forEach((item, i) => frag.appendChild(productOption(item, i)));

			// There may be more results than shown: offer the full search page.
			if (valid.length >= limit) {
				frag.appendChild(
					buildOption(valid.length, viewAllUrl(term), [span('bbps__all-label', t.viewAll || '')], 'bbps__all')
				);
			}

			list.appendChild(frag);
			status.textContent = String(t.results || '%d').replace('%d', String(valid.length));
			open();
		}

		async function run(term) {
			if (term === shownTerm && !panel.hidden) {
				return;
			}

			const key = term.toLowerCase() + '|' + limit;
			if (cache.has(key)) {
				abort();
				render(cache.get(key), term);
				return;
			}

			abort();
			const ctrl = new AbortController();
			controller = ctrl;
			setBusy(true);
			status.textContent = t.loading || '';

			try {
				const url = new URL(config.endpoint, window.location.href);
				url.searchParams.set('q', term);
				url.searchParams.set('limit', String(limit));

				const res = await fetch(url.toString(), {
					signal: ctrl.signal,
					credentials: 'omit', // Anonymous request: lighter, and cacheable by browser/CDN.
					headers: { Accept: 'application/json' },
				});
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}

				const data = await res.json();
				const items = data && Array.isArray(data.items) ? data.items : [];
				cacheSet(key, items);

				// Ignore late responses for a term the user has already changed.
				if (normalize(input.value) === term) {
					render(items, term);
				}
			} catch (err) {
				if (err && err.name !== 'AbortError' && normalize(input.value) === term) {
					showMessage(t.error, '');
				}
			} finally {
				if (controller === ctrl) {
					controller = null;
					setBusy(false);
				}
			}
		}

		input.addEventListener('input', () => {
			window.clearTimeout(timer);
			const term = normalize(input.value);

			if (charCount(term) < MIN_CHARS) {
				abort();
				reset();
				return;
			}

			timer = window.setTimeout(() => run(term), DELAY);
		});

		input.addEventListener('focus', () => {
			if (shownTerm && normalize(input.value) === shownTerm) {
				open();
			}
		});

		input.addEventListener('keydown', (e) => {
			const items = options();

			switch (e.key) {
				case 'ArrowDown':
					if (!items.length) {
						return;
					}
					e.preventDefault();
					if (panel.hidden) {
						if (normalize(input.value) !== shownTerm) {
							return;
						}
						open();
					}
					setActive(active < items.length - 1 ? active + 1 : 0);
					break;

				case 'ArrowUp':
					if (!items.length || panel.hidden) {
						return;
					}
					e.preventDefault();
					setActive(active > 0 ? active - 1 : items.length - 1);
					break;

				case 'Enter':
					if (!panel.hidden && active >= 0 && items[active]) {
						const link = items[active].querySelector('a');
						if (link) {
							e.preventDefault();
							window.location.assign(link.href);
						}
					}
					break;

				case 'Escape':
					if (!panel.hidden) {
						e.preventDefault();
						close();
					}
					break;

				case 'Tab':
					close();
					break;
			}
		});

		form.addEventListener('submit', (e) => {
			if (!normalize(input.value)) {
				e.preventDefault();
			}
		});

		document.addEventListener('pointerdown', (e) => {
			if (!panel.hidden && !form.contains(e.target)) {
				close();
			}
		});
	}

	function boot() {
		document.querySelectorAll('form.bbps').forEach(init);
	}

	// Exposed for content injected later (e.g. popups, AJAX-loaded headers).
	window.bbpsInit = boot;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
