/**
 * Asraa Broker Feed — Premium Property Carousel
 *
 * Features:
 *   - Auto-slide every 3 seconds
 *   - Infinite loop (clone-based, seamless wrap-around)
 *   - Pause on hover / focus
 *   - Touch / swipe support
 *   - Dot indicators
 *   - Prev / Next arrow buttons
 *   - Keyboard navigation (ArrowLeft / ArrowRight)
 *   - Responsive: 3 cards desktop, 2 tablet, 1 mobile
 *   - Equal-height cards (handled by flex stretch)
 *
 * No external dependencies — plain ES5 for broad browser support.
 *
 * @package Asraa_CRM
 * @since   5.2.0
 */

/* global document, window */

( function () {
	'use strict';

	var AUTOPLAY_MS   = 3000;
	var TRANSITION_MS = 500;
	var SWIPE_THRESH  = 40;    // px minimum horizontal swipe distance

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'asraa-feed-carousel' );
		if ( ! wrap ) { return; }

		var track     = wrap.querySelector( '.asraa-feed-track' );
		var prevBtn   = wrap.querySelector( '.asraa-feed-prev' );
		var nextBtn   = wrap.querySelector( '.asraa-feed-next' );
		var dotsWrap  = wrap.querySelector( '.asraa-feed-dots' );

		// Capture original cards before any clones are added.
		var origCards = Array.prototype.slice.call( track.children );
		var total     = origCards.length;

		if ( total < 1 ) { return; }

		var spv          = getSPV();      // slides per view
		var current      = 0;             // logical index 0 … total-1
		var transitioning = false;
		var autoTimer    = null;

		/* ─── Responsive helper ─────────────────────────────────────── */

		function getSPV() {
			if ( window.innerWidth >= 1024 ) { return 3; }
			if ( window.innerWidth >= 768  ) { return 2; }
			return 1;
		}

		/* ─── Card size calculation ─────────────────────────────────── */

		function getGap() {
			var g = parseFloat( window.getComputedStyle( track ).columnGap );
			return isNaN( g ) ? 28 : g;
		}

		function getCardWidth() {
			var wrapWidth = track.parentElement.offsetWidth;
			return ( wrapWidth - ( spv - 1 ) * getGap() ) / spv;
		}

		function getSlideStep() {
			return getCardWidth() + getGap();
		}

		/* ─── Clone management ──────────────────────────────────────── */

		function removeClones() {
			Array.prototype.slice.call( track.children ).forEach( function ( c ) {
				if ( c.dataset && c.dataset.clone === '1' ) {
					track.removeChild( c );
				}
			} );
		}

		function buildClones() {
			removeClones();

			// Prepend clones of the last `total` originals (keeps infinite scroll seamless).
			var before = origCards.slice().reverse().map( function ( c ) {
				var cl         = c.cloneNode( true );
				cl.dataset.clone = '1';
				cl.setAttribute( 'aria-hidden', 'true' );
				return cl;
			} );
			before.forEach( function ( cl ) {
				track.insertBefore( cl, track.firstChild );
			} );

			// Append clones of the first `total` originals.
			origCards.forEach( function ( c ) {
				var cl         = c.cloneNode( true );
				cl.dataset.clone = '1';
				cl.setAttribute( 'aria-hidden', 'true' );
				track.appendChild( cl );
			} );
		}

		/* ─── Offset / translation ──────────────────────────────────── */

		// Track layout: [total prepend-clones] [total originals] [total append-clones]
		// trackPos of the first original card = total (index in track children).

		function trackPos() {
			// `total` clones precede the originals.
			return total + current;
		}

		function applyOffset( animate ) {
			var offset = trackPos() * getSlideStep();
			track.style.transition = animate
				? ( 'transform ' + TRANSITION_MS + 'ms cubic-bezier(0.25,0.46,0.45,0.94)' )
				: 'none';
			track.style.transform = 'translateX(-' + offset + 'px)';
		}

		/* ─── Card sizing ───────────────────────────────────────────── */

		function sizeCards() {
			var cw = getCardWidth();
			Array.prototype.slice.call( track.children ).forEach( function ( c ) {
				c.style.flexBasis = cw + 'px';
				c.style.width     = '';
				c.style.minWidth  = '';
				c.style.maxWidth  = '';
			} );
		}

		/* ─── Navigation ────────────────────────────────────────────── */

		function goTo( idx, animate ) {
			current = ( ( idx % total ) + total ) % total;
			applyOffset( animate !== false );
			updateDots();
		}

		function next() {
			if ( transitioning ) { return; }
			var newIdx = current + 1;
			if ( newIdx >= total ) {
				// Slide to the append-clone zone, then silently snap back to 0.
				transitioning = true;
				current = newIdx;          // intentionally out-of-range
				applyOffset( true );
				setTimeout( function () {
					goTo( 0, false );
					transitioning = false;
				}, TRANSITION_MS + 20 );
			} else {
				goTo( newIdx, true );
			}
		}

		function prev() {
			if ( transitioning ) { return; }
			var newIdx = current - 1;
			if ( newIdx < 0 ) {
				// Slide into prepend-clone zone, then silently snap to last real card.
				transitioning = true;
				current = newIdx;          // intentionally out-of-range
				applyOffset( true );
				setTimeout( function () {
					goTo( total - 1, false );
					transitioning = false;
				}, TRANSITION_MS + 20 );
			} else {
				goTo( newIdx, true );
			}
		}

		/* ─── Dot indicators ────────────────────────────────────────── */

		function buildDots() {
			if ( ! dotsWrap ) { return; }
			dotsWrap.innerHTML = '';
			for ( var i = 0; i < total; i++ ) {
				var dot = document.createElement( 'button' );
				dot.type      = 'button';
				dot.className = 'asraa-feed-dot';
				dot.setAttribute( 'role', 'tab' );
				dot.setAttribute( 'aria-label', 'Slide ' + ( i + 1 ) + ' of ' + total );
				dot.setAttribute( 'data-idx', String( i ) );
				dotsWrap.appendChild( dot );
			}
			dotsWrap.addEventListener( 'click', function ( e ) {
				var dot = e.target;
				while ( dot && ! dot.classList.contains( 'asraa-feed-dot' ) ) {
					dot = dot.parentElement;
				}
				if ( dot && dot.dataset.idx !== undefined ) {
					stopAutoplay();
					goTo( parseInt( dot.dataset.idx, 10 ), true );
					startAutoplay();
				}
			} );
			updateDots();
		}

		function updateDots() {
			if ( ! dotsWrap ) { return; }
			var active = ( ( current % total ) + total ) % total;
			Array.prototype.slice.call( dotsWrap.children ).forEach( function ( d, i ) {
				var isActive = ( i === active );
				d.classList.toggle( 'asraa-feed-dot--active', isActive );
				d.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
		}

		/* ─── Autoplay ──────────────────────────────────────────────── */

		function startAutoplay() {
			stopAutoplay();
			autoTimer = setInterval( next, AUTOPLAY_MS );
		}

		function stopAutoplay() {
			clearInterval( autoTimer );
			autoTimer = null;
		}

		/* ─── Touch / swipe ─────────────────────────────────────────── */

		var touchX    = 0;
		var touchY    = 0;
		var swipeActive = false;

		wrap.addEventListener( 'touchstart', function ( e ) {
			touchX      = e.touches[0].clientX;
			touchY      = e.touches[0].clientY;
			swipeActive = false;
		}, { passive: true } );

		wrap.addEventListener( 'touchmove', function ( e ) {
			var dx = e.touches[0].clientX - touchX;
			var dy = e.touches[0].clientY - touchY;
			if ( Math.abs( dx ) > Math.abs( dy ) && Math.abs( dx ) > 8 ) {
				swipeActive = true;
				e.preventDefault(); // prevent page scroll during horizontal swipe
			}
		}, { passive: false } );

		wrap.addEventListener( 'touchend', function ( e ) {
			if ( ! swipeActive ) { return; }
			var deltaX = e.changedTouches[0].clientX - touchX;
			if ( Math.abs( deltaX ) >= SWIPE_THRESH ) {
				stopAutoplay();
				if ( deltaX < 0 ) { next(); } else { prev(); }
				startAutoplay();
			}
			swipeActive = false;
		} );

		/* ─── Pause on hover / focus ────────────────────────────────── */

		wrap.addEventListener( 'mouseenter', stopAutoplay );
		wrap.addEventListener( 'mouseleave', startAutoplay );
		wrap.addEventListener( 'focusin',    stopAutoplay );
		wrap.addEventListener( 'focusout',   startAutoplay );

		/* ─── Pause when off-screen or tab hidden (perf / battery) ───── */

		var inViewport = true;
		var tabVisible = ( typeof document.hidden === 'boolean' ) ? ! document.hidden : true;

		function syncAutoplay() {
			if ( inViewport && tabVisible ) {
				startAutoplay();
			} else {
				stopAutoplay();
			}
		}

		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					inViewport = entry.isIntersecting;
				} );
				syncAutoplay();
			}, { threshold: 0.15 } );
			observer.observe( wrap );
		}

		document.addEventListener( 'visibilitychange', function () {
			tabVisible = ! document.hidden;
			syncAutoplay();
		} );

		/* ─── Arrow buttons ─────────────────────────────────────────── */

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				stopAutoplay(); prev(); startAutoplay();
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				stopAutoplay(); next(); startAutoplay();
			} );
		}

		/* ─── Keyboard ──────────────────────────────────────────────── */

		wrap.addEventListener( 'keydown', function ( e ) {
			// Only handle arrow keys when the carousel wrapper itself has focus,
			// not when focus is on an interactive element inside a card.
			if ( e.target !== wrap ) { return; }
			if ( e.key === 'ArrowLeft'  ) { stopAutoplay(); prev(); startAutoplay(); e.preventDefault(); }
			if ( e.key === 'ArrowRight' ) { stopAutoplay(); next(); startAutoplay(); e.preventDefault(); }
		} );

		/* ─── Resize ────────────────────────────────────────────────── */

		var resizeTimer = null;
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () {
				var newSPV = getSPV();
				if ( newSPV !== spv ) {
					spv = newSPV;
					buildClones();
					sizeCards();
				} else {
					sizeCards();
				}
				applyOffset( false );
			}, 150 );
		} );

		/* ─── Init ──────────────────────────────────────────────────── */

		buildClones();
		sizeCards();
		applyOffset( false );
		buildDots();
		startAutoplay();
	} );
} )();
