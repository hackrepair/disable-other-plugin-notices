/**
 * jsdom-based tests for assets/js/dopn-banner-watcher-2.4.1.js.
 *
 * Mirrors the PHP test suite's approach: build the exact DOM shapes the
 * script has to deal with in real WordPress admin pages, run the real
 * script file unmodified, and assert on the resulting DOM. No mocking of
 * the script's own internals -- only the browser primitives it depends on
 * (JSDOM's MutationObserver + DOM) are provided.
 *
 * Usage: node test-banner-watcher.js
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { JSDOM } = require( 'jsdom' );

const SCRIPT_PATH = process.argv[ 2 ] ||
	path.join( __dirname, '..', '..', 'assets', 'js', 'dopn-banner-watcher-2.4.1.js' );

const scriptSource = fs.readFileSync( SCRIPT_PATH, 'utf8' );

let passed = 0;
let failed = 0;

function assert( condition, message ) {
	if ( condition ) {
		passed++;
		console.log( '  ok - ' + message );
	} else {
		failed++;
		console.log( '  FAIL - ' + message );
	}
}

/**
 * Builds a fresh jsdom document with a config object, loads the real
 * script into it, and hands back { window, document }.
 *
 * @param {Object} config Value to set as window.dopnBannerWatcher.
 * @param {string} bodyHtml Initial #wpbody-content markup.
 * @return {Object} { window, document }
 */
function makeEnv( config, bodyHtml ) {
	const dom = new JSDOM(
		'<!doctype html><html><body><div id="wpbody-content">' + bodyHtml + '</div></body></html>',
		{ runScripts: 'outside-only', pretendToBeVisual: true }
	);

	dom.window.dopnBannerWatcher = config;
	dom.window.eval( scriptSource );

	return { window: dom.window, document: dom.window.document };
}

function baseConfig( overrides ) {
	return Object.assign(
		{
			groupingEnabled: true,
			selectors: [ '#e-conversion-banner' ],
			strings: {
				label: 'Other plugin notices',
				singular: '%s notice from another plugin or theme',
				plural: '%s notices from other plugins and themes',
			},
		},
		overrides || {}
	);
}

function wait( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

async function testNoOpWhenGroupingDisabled() {
	console.log( 'Test: no-op when grouping is disabled for the user' );

	const { document, window } = makeEnv(
		baseConfig( { groupingEnabled: false } ),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	document.querySelector( '.wrap' ).appendChild( banner );

	await wait( 20 );

	assert( ! document.querySelector( '.dopn-notices' ), 'no panel is created' );
	assert( document.getElementById( 'e-conversion-banner' ).parentElement.className === 'wrap', 'banner is left where it was' );

	window.close();
}

async function testNoOpWithEmptySelectors() {
	console.log( 'Test: no-op when the selector list is empty' );

	const { document, window } = makeEnv(
		baseConfig( { selectors: [] } ),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	document.querySelector( '.wrap' ).appendChild( banner );

	await wait( 20 );

	assert( ! document.querySelector( '.dopn-notices' ), 'no panel is created' );

	window.close();
}

async function testBuildsPanelForLateInsertedBanner() {
	console.log( 'Test: relocates a banner inserted after the script runs (the real-world case)' );

	const { document, window } = makeEnv(
		baseConfig(),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	// Simulate a plugin's own script building and inserting its banner a
	// tick after page load -- e-conversion-banner.min.js's actual behavior.
	await wait( 5 );

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	banner.innerHTML = '<h2>Go Pro, Go Limitless</h2>';
	document.querySelector( '.wrap' ).appendChild( banner );

	await wait( 20 );

	const panel = document.querySelector( '.dopn-notices' );
	assert( !! panel, 'a panel was created' );

	const relocated = document.getElementById( 'e-conversion-banner' );
	assert( !! relocated.closest( '.dopn-notices__list' ), 'the banner now lives inside the panel list' );
	assert( relocated.classList.contains( 'below-h2' ), 'below-h2 is added so core cannot pull it back out' );
	assert( relocated.classList.contains( 'dopn-notices__item' ), 'dopn-notices__item is added for spacing' );

	const count = panel.querySelector( '.dopn-notices__count' );
	assert( '1' === count.textContent, 'visible count reads 1' );

	const srText = panel.querySelector( '.dopn-notices__count-text' );
	assert( srText.textContent === '1 notice from another plugin or theme', 'screen-reader text uses the singular string' );

	window.close();
}

async function testReusesExistingPhpRenderedPanel() {
	console.log( 'Test: reuses a panel the PHP collector already printed, instead of building a second one' );

	const { document, window } = makeEnv(
		baseConfig(),
		'<div class="dopn-notices" data-dopn-count="1"><details class="dopn-notices__panel" open>' +
			'<summary class="dopn-notices__summary">' +
				'<span class="dopn-notices__label">Other plugin notices</span>' +
				'<span class="dopn-notices__count" aria-hidden="true">1</span>' +
				'<span class="screen-reader-text dopn-notices__count-text">1 notice from another plugin or theme</span>' +
			'</summary>' +
			'<div class="dopn-notices__list"><style>.notice { color: blue; }</style><div class="notice below-h2"><p>Some other plugin notice</p></div><script>window.noticeTest = true;</script></div>' +
		'</details></div>' +
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	document.querySelector( '.wrap' ).appendChild( banner );

	await wait( 20 );

	const panels = document.querySelectorAll( '.dopn-notices' );
	assert( 1 === panels.length, 'still exactly one panel on the page' );

	const list = document.querySelector( '.dopn-notices__list' );
	assert( 4 === list.children.length, 'the panel retains the original markup and relocated banner' );
	assert( 2 === list.querySelectorAll( '.notice, #e-conversion-banner' ).length, 'style and script siblings are not counted as notices' );

	const count = document.querySelector( '.dopn-notices__count' );
	assert( '2' === count.textContent, 'count updates to 2' );

	const srText = document.querySelector( '.dopn-notices__count-text' );
	assert( srText.textContent === '2 notices from other plugins and themes', 'screen-reader text switches to the plural string' );

	window.close();
}

async function testIgnoresNonMatchingInsertions() {
	console.log( 'Test: leaves unrelated late-inserted elements alone' );

	const { document, window } = makeEnv(
		baseConfig(),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	await wait( 5 );

	const unrelated = document.createElement( 'div' );
	unrelated.id = 'some-other-widget';
	unrelated.className = 'notice notice-success';
	unrelated.textContent = 'Settings saved.';
	document.querySelector( '.wrap' ).appendChild( unrelated );

	await wait( 20 );

	assert( ! document.querySelector( '.dopn-notices' ), 'no panel is created for a non-matching element' );
	assert( document.getElementById( 'some-other-widget' ).parentElement.className === 'wrap', 'the unrelated element is left in place' );

	window.close();
}

async function testIgnoresInvalidSelectorWithoutBreakingValidOnes() {
	console.log( 'Test: a syntactically invalid selector from the filter does not break matching for the rest of the list' );

	const { document, window } = makeEnv(
		baseConfig( { selectors: [ '#e-conversion-banner', ':::not-a-real-selector(((', '#also-should-still-work' ] } ),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	await wait( 5 );

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	document.querySelector( '.wrap' ).appendChild( banner );

	const other = document.createElement( 'div' );
	other.id = 'also-should-still-work';
	document.querySelector( '.wrap' ).appendChild( other );

	await wait( 20 );

	const relocated1 = document.getElementById( 'e-conversion-banner' );
	const relocated2 = document.getElementById( 'also-should-still-work' );

	assert( !! relocated1.closest( '.dopn-notices__list' ), 'the valid selector before the bad one still matches' );
	assert( !! relocated2.closest( '.dopn-notices__list' ), 'the valid selector after the bad one still matches' );

	window.close();
}

async function testDoesNotReRelocateAlreadyCollapsedBanner() {
	console.log( 'Test: does not loop or re-append once a banner is already inside the panel' );

	const { document, window } = makeEnv(
		baseConfig(),
		'<div class="wrap"><h1>Plugins</h1></div>'
	);

	await wait( 5 );

	const banner = document.createElement( 'div' );
	banner.id = 'e-conversion-banner';
	document.querySelector( '.wrap' ).appendChild( banner );

	// Give the observer more than one cycle to prove it settles rather than
	// oscillating (each relocation is itself a mutation the observer sees).
	await wait( 60 );

	const list = document.querySelectorAll( '.dopn-notices__list > *' );
	assert( 1 === list.length, 'exactly one copy of the banner ends up in the list (no duplication)' );

	window.close();
}

( async function run() {
	console.log( 'Script under test: ' + SCRIPT_PATH );
	console.log();

	await testNoOpWhenGroupingDisabled();
	await testNoOpWithEmptySelectors();
	await testBuildsPanelForLateInsertedBanner();
	await testReusesExistingPhpRenderedPanel();
	await testIgnoresNonMatchingInsertions();
	await testIgnoresInvalidSelectorWithoutBreakingValidOnes();
	await testDoesNotReRelocateAlreadyCollapsedBanner();

	console.log();
	console.log( passed + ' passed, ' + failed + ' failed' );

	process.exit( failed > 0 ? 1 : 0 );
} )();
