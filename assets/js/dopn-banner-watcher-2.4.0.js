/**
 * Catches promo banners that a plugin inserts into the page with JavaScript
 * after it has already loaded, instead of printing them through a WordPress
 * action hook.
 *
 * The PHP-side collector (class-dopn-notice-collector.php) works by asking
 * WordPress which callback is registered on a notice hook and where that
 * callback's code lives -- reliable, but only possible for markup that is
 * part of the server response. Some banners are built client-side: the
 * plugin enqueues a script, that script decides on its own (sometimes with
 * an AJAX round trip) whether to show anything, and if so it constructs the
 * banner element and inserts it into the page well after `admin_notices` and
 * `in_admin_header` have already fired and, on a slower connection, after
 * this very script has already run its first pass. There is no hook for the
 * PHP side to intercept, because nothing on the server ever prints this
 * markup -- so this file exists to catch it on the client instead.
 *
 * Deliberately conservative: rather than guessing at "banner-like" markup by
 * class name or text content (which risks moving something that only looks
 * like a promo), this only ever acts on the explicit selector list handed
 * down from PHP via `dopnBannerWatcher.selectors`, built from the
 * `dopn_js_late_banner_selectors` filter. An empty list makes this script a
 * no-op.
 *
 * That filter can be added to by any other plugin, so each selector it
 * contributes is validated before use (see validSelectors() below). Selectors
 * are normally joined into one comma-separated string for a single
 * querySelectorAll() call; without validation, one syntactically invalid
 * entry from a badly-behaved filter callback would make the whole joined
 * string throw on every DOM mutation, silently breaking detection of every
 * other selector in the list too, not just the bad one.
 *
 * @since 2.2.0
 */
( function () {
	'use strict';

	var config = window.dopnBannerWatcher;

	if ( ! config || ! config.groupingEnabled ) {
		return;
	}

	/**
	 * Filters a selector list down to the ones the browser actually accepts.
	 *
	 * Tests each selector on its own with a throwaway querySelectorAll()
	 * call, so a single invalid entry is dropped instead of breaking the
	 * combined selector string every other entry relies on.
	 *
	 * @param {Array} candidates Selector strings from dopnBannerWatcher.selectors.
	 * @return {Array} Selector strings the browser accepts.
	 */
	function validSelectors( candidates ) {
		var valid = [];

		for ( var i = 0; i < candidates.length; i++ ) {
			try {
				document.querySelectorAll( candidates[ i ] );
				valid.push( candidates[ i ] );
			} catch ( e ) {
				// Invalid selector contributed via dopn_js_late_banner_selectors
				// -- skip it rather than let it take the rest of the list down.
			}
		}

		return valid;
	}

	var selectors = validSelectors( config.selectors || [] );

	if ( ! selectors.length ) {
		return;
	}

	var selector = selectors.join( ',' );
	var root = document.getElementById( 'wpbody-content' );

	if ( ! root ) {
		return;
	}

	var panelList = null;

	/**
	 * Finds (or builds) the panel this script relocates banners into.
	 *
	 * Reuses the PHP-rendered panel when one already exists on the page --
	 * a request that also had a hook-based notice to group -- so a banner
	 * caught here lands in the same panel rather than a second one. Builds
	 * a matching panel from scratch when this is the only third-party
	 * content on the screen.
	 *
	 * @return {Element} The `.dopn-notices__list` element to append into.
	 */
	function getPanelList() {
		if ( panelList && document.body.contains( panelList ) ) {
			return panelList;
		}

		var existing = document.querySelector( '.dopn-notices__list' );

		if ( existing ) {
			panelList = existing;

			return panelList;
		}

		var wrap = document.createElement( 'div' );
		wrap.className = 'dopn-notices';
		wrap.setAttribute( 'data-dopn-count', '0' );

		var details = document.createElement( 'details' );
		details.className = 'dopn-notices__panel';

		var summary = document.createElement( 'summary' );
		summary.className = 'dopn-notices__summary';
		summary.innerHTML =
			'<span class="dopn-notices__label"></span>' +
			'<span class="dopn-notices__count" aria-hidden="true">0</span>' +
			'<span class="screen-reader-text dopn-notices__count-text"></span>';
		summary.querySelector( '.dopn-notices__label' ).textContent = config.strings.label;

		var list = document.createElement( 'div' );
		list.className = 'dopn-notices__list';

		details.appendChild( summary );
		details.appendChild( list );
		wrap.appendChild( details );

		// Same place the PHP panel appears: printed on all_admin_notices,
		// which lands at the top of #wpbody-content, above .wrap.
		root.insertBefore( wrap, root.firstChild );

		panelList = list;

		return panelList;
	}

	/**
	 * Refreshes the visible and screen-reader counts on the panel.
	 *
	 * @return {void}
	 */
	function updateCount() {
		var wrap = document.querySelector( '.dopn-notices' );

		if ( ! wrap ) {
			return;
		}

		// Server callbacks can emit STYLE and SCRIPT siblings beside a notice.
		// Count the callback entries reported by PHP plus banners moved here,
		// instead of treating every child element as another notice.
		var count = ( parseInt( wrap.getAttribute( 'data-dopn-count' ), 10 ) || 0 ) + 1;
		wrap.setAttribute( 'data-dopn-count', String( count ) );
		var countEl = wrap.querySelector( '.dopn-notices__count' );
		var countText = wrap.querySelector( '.dopn-notices__count-text' );

		if ( countEl ) {
			countEl.textContent = String( count );
		}

		if ( countText ) {
			var template = 1 === count ? config.strings.singular : config.strings.plural;

			countText.textContent = template.replace( '%s', String( count ) );
		}
	}

	/**
	 * Moves one matched element into the panel.
	 *
	 * Uses appendChild rather than cloning, so any click handlers the
	 * banner's own script attached (a dismiss button, for example) keep
	 * working after the move.
	 *
	 * @param {Element} el Matched element to relocate.
	 * @return {void}
	 */
	function collapse( el ) {
		if ( ! el || ( el.closest && el.closest( '.dopn-notices' ) ) ) {
			return;
		}

		el.classList.add( 'below-h2', 'dopn-notices__item' );

		getPanelList().appendChild( el );
		updateCount();
	}

	/**
	 * Checks one added node (and its descendants) against the selector list.
	 *
	 * @param {Node} node Node passed to the MutationObserver callback.
	 * @return {void}
	 */
	function scan( node ) {
		if ( ! node || 1 !== node.nodeType ) {
			return;
		}

		if ( node.matches && node.matches( selector ) ) {
			collapse( node );

			return;
		}

		if ( node.querySelectorAll ) {
			var matches = node.querySelectorAll( selector );

			for ( var i = 0; i < matches.length; i++ ) {
				collapse( matches[ i ] );
			}
		}
	}

	// Covers a banner script that already ran before this one attached its
	// observer (e.g. loaded earlier in the queue with no async gap).
	scan( root );

	var observer = new MutationObserver( function ( mutations ) {
		for ( var i = 0; i < mutations.length; i++ ) {
			var added = mutations[ i ].addedNodes;

			for ( var j = 0; j < added.length; j++ ) {
				scan( added[ j ] );
			}
		}
	} );

	observer.observe( root, { childList: true, subtree: true } );

	// A banner built client-side appears within a second or two of the page
	// settling, never minutes later, so the observer is torn down instead of
	// running for as long as the admin keeps the tab open.
	window.setTimeout( function () {
		observer.disconnect();
	}, 10000 );
} )();
