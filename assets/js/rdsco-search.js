/*!
 * RDSCO WooCommerce Elementor Widgets — live product search.
 * Vanilla JS, no dependencies. Debounced (always waits `delay` ms after the
 * last keystroke), minimum characters, aborts stale requests, caches recent
 * results in memory, keyboard and screen-reader friendly.
 */
(() => {
	'use strict';

	const config = window.rdscoSearchConfig;
	if (!config || !config.endpoint || !('fetch' in window) || !('AbortController' in window)) {
		return;
	}

	const MIN_CHARS = Number(config.minChars) || 3;
	const DELAY = Number(config.delay) || 500;
	const MAX_LIMIT = 20;
	const CACHE_MAX = 60;
	const t = config.i18n || {};
	const cache = new Map(); // Shared by every widget on the page.

	const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();
	const charCount = (value) => Array.from(value).length;
	const isHttpUrl = (value) => /^https?:\/\//i.test(String(value || ''));

	function parseJSON(value) {
		try {
			return JSON.parse(value || '{}') || {};
		} catch (e) {
			return {};
		}
	}

	function cacheSet(key, value) {
		if (cache.size >= CACHE_MAX) {
			cache.delete(cache.keys().next().value);
		}
		cache.set(key, value);
	}

	function el(tag, className, text) {
		const node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text !== undefined) {
			node.textContent = text;
		}
		return node;
	}

	/* ------------------------------------------------------------------
	 * Search controller (used by both widgets)
	 * ---------------------------------------------------------------- */

	function createSearch(root) {
		if (!root) {
			return null;
		}
		if (root.rdscoSearch) {
			return root.rdscoSearch;
		}

		const form = root.querySelector('.rdsco-search__form');
		const input = root.querySelector('.rdsco-search__input');
		const panel = root.querySelector('.rdsco-search__panel');
		const list = root.querySelector('.rdsco-search__list');
		const message = root.querySelector('.rdsco-search__message');
		const status = root.querySelector('.rdsco-search__status');
		const catField = root.querySelector('.rdsco-search__cat-field');
		if (!form || !input || !panel || !list || !message || !status) {
			return null;
		}

		const opts = parseJSON(root.dataset.rdscoSearch);
		const inline = root.classList.contains('rdsco-search--inline');
		const limit = Math.min(MAX_LIMIT, Math.max(1, parseInt(opts.limit, 10) || 8));
		let scopeIds = Array.isArray(opts.cats) ? opts.cats.map(Number).filter((n) => n > 0) : [];
		let scopeSlugs = Array.isArray(opts.slugs) ? opts.slugs.map(String) : [];

		let timer = 0;
		let controller = null;
		let shownKey = '';
		let active = -1;

		const keyFor = (term) =>
			[term.toLowerCase(), limit, scopeIds.join(','), opts.content ? 1 : 0, opts.group ? 1 : 0].join('|');
		const currentTerm = () => normalize(input.value);
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

		function clear() {
			shownKey = '';
			list.textContent = '';
			message.hidden = true;
			status.textContent = '';
			close();
		}

		function setBusy(busy) {
			root.classList.toggle('is-loading', busy);
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

			items.forEach((node, i) => {
				const on = i === active;
				node.classList.toggle('is-active', on);
				node.setAttribute('aria-selected', on ? 'true' : 'false');
			});

			if (active >= 0) {
				input.setAttribute('aria-activedescendant', items[active].id);
				items[active].scrollIntoView({ block: 'nearest' });
			} else {
				input.removeAttribute('aria-activedescendant');
			}
		}

		function showMessage(text, key) {
			shownKey = key;
			list.textContent = '';
			setActive(-1);
			message.textContent = text || '';
			message.hidden = false;
			status.textContent = text || '';
			open();
		}

		function viewAllUrl(term) {
			const url = new URL(form.getAttribute('action') || '/', window.location.href);
			url.searchParams.set('s', term);
			url.searchParams.set('post_type', 'product');
			if (scopeSlugs.length) {
				url.searchParams.set('product_cat', scopeSlugs.join(','));
			}
			return url.toString();
		}

		function option(index, href, children, extraClass) {
			const li = el('li', 'rdsco-search__item' + (extraClass ? ' ' + extraClass : ''));
			li.id = list.id + '-opt-' + index;
			li.setAttribute('role', 'option');
			li.setAttribute('aria-selected', 'false');

			const a = el('a', 'rdsco-search__link');
			a.href = href;
			a.tabIndex = -1;
			children.forEach((child) => a.appendChild(child));

			li.appendChild(a);
			return li;
		}

		function productOption(item, index) {
			const children = [];

			if (opts.image && isHttpUrl(item.image)) {
				const img = el('img', 'rdsco-search__thumb');
				img.src = item.image;
				img.alt = '';
				img.width = 48;
				img.height = 48;
				img.decoding = 'async';
				img.loading = 'lazy';
				children.push(img);
			}

			const body = el('span', 'rdsco-search__body');
			body.appendChild(el('span', 'rdsco-search__title', item.title || ''));
			if (opts.sku && item.sku) {
				body.appendChild(el('span', 'rdsco-search__meta', (t.sku || 'SKU') + ': ' + item.sku));
			}
			if (opts.price && item.price) {
				const price = el('span', 'rdsco-search__price');
				price.innerHTML = item.price; // Sanitized server-side with wp_kses_post().
				body.appendChild(price);
			}
			children.push(body);

			return option(index, item.url, children);
		}

		function render(items, term, key) {
			const valid = items.filter((item) => item && isHttpUrl(item.url));
			if (!valid.length) {
				showMessage(opts.noResults || t.none, key);
				return;
			}

			shownKey = key;
			message.hidden = true;
			list.textContent = '';
			active = -1;
			input.removeAttribute('aria-activedescendant');

			const frag = document.createDocumentFragment();
			let index = 0;

			if (opts.group) {
				// Keep the server's ranking: groups appear in order of their best result.
				const groups = new Map();
				valid.forEach((item) => {
					const label = item.cat || t.other || '';
					if (!groups.has(label)) {
						groups.set(label, []);
					}
					groups.get(label).push(item);
				});

				let g = 0;
				groups.forEach((groupItems, label) => {
					const group = el('li', 'rdsco-search__group');
					const heading = el('div', 'rdsco-search__group-label', label);
					const inner = el('ul', 'rdsco-search__group-list');
					heading.id = list.id + '-group-' + g++;
					group.setAttribute('role', 'group');
					group.setAttribute('aria-labelledby', heading.id);
					inner.setAttribute('role', 'presentation');
					groupItems.forEach((item) => inner.appendChild(productOption(item, index++)));
					group.append(heading, inner);
					frag.appendChild(group);
				});
			} else {
				valid.forEach((item) => frag.appendChild(productOption(item, index++)));
			}

			if (opts.viewAll && valid.length >= limit) {
				frag.appendChild(
					option(index, viewAllUrl(term), [el('span', 'rdsco-search__all-label', t.viewAll || '')], 'rdsco-search__all')
				);
			}

			list.appendChild(frag);
			status.textContent = String(t.results || '%d').replace('%d', String(valid.length));
			open();
		}

		async function run(term) {
			const key = keyFor(term);
			if (key === shownKey && !panel.hidden) {
				return;
			}

			if (cache.has(key)) {
				abort();
				render(cache.get(key), term, key);
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
				url.searchParams.set('content', opts.content ? '1' : '0');
				url.searchParams.set('group', opts.group ? '1' : '0');
				if (scopeIds.length) {
					url.searchParams.set('cats', scopeIds.join(','));
				}

				const res = await fetch(url.toString(), {
					signal: ctrl.signal,
					credentials: 'omit', // Anonymous: lighter, and cacheable by the browser/CDN.
					headers: { Accept: 'application/json' },
				});
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}

				const data = await res.json();
				const items = data && Array.isArray(data.items) ? data.items : [];
				cacheSet(key, items);

				// Ignore late responses for a term or category the user already changed.
				if (keyFor(currentTerm()) === key) {
					render(items, term, key);
				}
			} catch (err) {
				if (err && err.name !== 'AbortError' && keyFor(currentTerm()) === key) {
					showMessage(t.error, '');
				}
			} finally {
				if (controller === ctrl) {
					controller = null;
					setBusy(false);
				}
			}
		}

		function schedule() {
			window.clearTimeout(timer);
			const term = currentTerm();

			if (charCount(term) < MIN_CHARS) {
				abort();
				clear();
				return;
			}

			timer = window.setTimeout(() => run(term), DELAY);
		}

		input.addEventListener('input', schedule);

		input.addEventListener('focus', () => {
			if (shownKey && keyFor(currentTerm()) === shownKey) {
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
						if (keyFor(currentTerm()) !== shownKey) {
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
					// Inside the popup, Escape closes the dialog itself.
					if (!inline && !panel.hidden) {
						e.preventDefault();
						close();
					}
					break;

				case 'Tab':
					if (!inline) {
						close();
					}
					break;
			}
		});

		form.addEventListener('submit', (e) => {
			if (!currentTerm()) {
				e.preventDefault();
			}
		});

		if (!inline) {
			document.addEventListener('pointerdown', (e) => {
				if (!panel.hidden && !root.contains(e.target)) {
					close();
				}
			});
		}

		const api = {
			focus() {
				input.focus();
			},
			setScope(ids, slugs) {
				scopeIds = ids.map(Number).filter((n) => n > 0);
				scopeSlugs = slugs.map(String).filter(Boolean);

				if (catField) {
					catField.value = scopeSlugs.join(',');
					catField.disabled = !scopeSlugs.length;
				}

				// A category change is not typing: search right away if there is a term.
				window.clearTimeout(timer);
				const term = currentTerm();
				if (charCount(term) >= MIN_CHARS) {
					run(term);
				}
			},
		};

		root.rdscoSearch = api;
		return api;
	}

	/* ------------------------------------------------------------------
	 * Category search popup
	 * ---------------------------------------------------------------- */

	function initPopup(root) {
		if (root.rdscoPopup) {
			return;
		}
		root.rdscoPopup = true;

		const trigger = root.querySelector('.rdsco-csearch__trigger');
		const dialog = root.querySelector('.rdsco-csearch__dialog');
		if (!trigger || !dialog) {
			return;
		}

		const search = createSearch(dialog.querySelector('.rdsco-search'));
		const closeBtn = dialog.querySelector('.rdsco-csearch__close');
		const picker = dialog.querySelector('.rdsco-csearch__cats');
		const html = document.documentElement;
		const native = typeof dialog.showModal === 'function';

		function onClosed() {
			html.classList.remove('rdsco-search-lock');
			trigger.setAttribute('aria-expanded', 'false');
		}

		function openDialog() {
			if (native) {
				if (!dialog.open) {
					dialog.showModal();
				}
			} else {
				dialog.setAttribute('open', '');
			}
			html.classList.add('rdsco-search-lock');
			trigger.setAttribute('aria-expanded', 'true');
			if (search) {
				window.requestAnimationFrame(() => search.focus());
			}
		}

		function closeDialog() {
			if (native) {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
				onClosed();
				trigger.focus();
			}
		}

		trigger.addEventListener('click', openDialog);
		dialog.addEventListener('close', onClosed);
		if (closeBtn) {
			closeBtn.addEventListener('click', closeDialog);
		}

		// Click on the dimmed backdrop (the dialog box itself, outside its content).
		dialog.addEventListener('click', (e) => {
			if (e.target === dialog) {
				closeDialog();
			}
		});

		// Escape always closes. (In a search field with text, browsers would
		// otherwise just clear the field on the first press.)
		dialog.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				e.preventDefault();
				closeDialog();
			}
		});

		if (picker && search) {
			picker.addEventListener('change', (e) => {
				const field = e.target;
				let choice = null;

				if (field instanceof HTMLSelectElement) {
					choice = field.options[field.selectedIndex] || null;
				} else if (field instanceof HTMLInputElement && field.checked) {
					choice = field;
				}
				if (!choice) {
					return;
				}

				const id = parseInt(choice.value, 10) || 0;
				search.setScope(id ? [id] : [], id && choice.dataset.slug ? [choice.dataset.slug] : []);
			});
		}
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */

	function boot(scope) {
		const context = scope && scope.querySelectorAll ? scope : document;
		context.querySelectorAll('.rdsco-csearch').forEach(initPopup);
		context.querySelectorAll('.rdsco-search').forEach(createSearch);
	}

	// Re-init widgets that Elementor re-renders in the editor preview.
	function hookElementor() {
		const add = () => {
			const fe = window.elementorFrontend;
			if (!fe || !fe.hooks) {
				return;
			}
			['rdsco-live-search', 'rdsco-category-search'].forEach((name) => {
				fe.hooks.addAction('frontend/element_ready/' + name + '.default', ($scope) => boot($scope && $scope[0]));
			});
		};

		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			add();
		} else if (window.jQuery) {
			window.jQuery(window).on('elementor/frontend/init', add);
		}
	}

	window.rdscoSearchInit = boot;
	hookElementor();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => boot());
	} else {
		boot();
	}
})();
