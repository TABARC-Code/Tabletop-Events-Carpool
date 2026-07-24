/**
 * Tabletop Events Calendar — Carpool — single-event widget. Shows
 * live lift offers/requests plus a "post a listing" form. The form is
 * always shown; a past event or bad input gets a clear rejection
 * message from the REST endpoint rather than trying to predict it
 * client-side.
 */
(function () {
	'use strict';

	var REST = ( window.TCAR_CARPOOL && window.TCAR_CARPOOL.restUrl ) || '/wp-json/tcar/v1';
	var EVENT_ID = ( window.TCAR_CARPOOL && window.TCAR_CARPOOL.eventId ) || 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tcar-carpool]' ).forEach( init );
	} );

	function init( root ) {
		root.innerHTML = '<div class="tcar-empty">Loading…</div>';

		fetch( REST + '/event/' + EVENT_ID )
			.then( function ( r ) { return r.json(); } )
			.then( function ( listings ) {
				root.innerHTML = listHtml( listings ) + formHtml();
				bind( root, listings );
			} )
			.catch( function () {
				root.innerHTML = '<div class="tcar-empty">Could not load the carpool board.</div>';
			} );
	}

	function listHtml( listings ) {
		if ( ! Array.isArray( listings ) || ! listings.length ) {
			return '<p class="tcar-empty">No lift-share listings yet — be the first.</p>';
		}
		return '<div class="tcar-list">' + listings.map( card ).join( '' ) + '</div>';
	}

	function card( l ) {
		return (
			'<div class="tcar-card">' +
				'<div class="tcar-card-top">' +
					'<span class="tcar-badge tcar-badge-' + escapeAttr( l.type ) + '">' + ( 'offer' === l.type ? 'Offering a lift' : 'Looking for a lift' ) + '</span>' +
					'<span class="tcar-card-seats">' + l.seats + ( l.seats === 1 ? ' seat' : ' seats' ) + '</span>' +
				'</div>' +
				'<div class="tcar-card-name">' + escapeHtml( l.name ) + '</div>' +
				'<div class="tcar-card-area">' + escapeHtml( l.area ) + '</div>' +
				( l.notes ? '<p class="tcar-card-notes">' + escapeHtml( l.notes ) + '</p>' : '' ) +
				'<button type="button" class="tcar-contact-btn" data-tcar-contact="' + l.id + '">Get in touch</button>' +
				'<div class="tcar-contact-form" data-tcar-contact-form="' + l.id + '" hidden></div>' +
			'</div>'
		);
	}

	function formHtml() {
		return (
			'<h3 class="tcar-form-heading">Post a lift-share listing</h3>' +
			'<form class="tec-sf-form tcar-form" novalidate>' +
				'<div class="tec-sf-field" data-field="type"><label>Type</label><select name="type">' +
					'<option value="offer">Offering a lift</option><option value="request">Looking for a lift</option>' +
				'</select></div>' +
				'<div class="tec-sf-row">' +
					'<div class="tec-sf-field" data-field="name"><label>Your Name</label><input type="text" name="name" required><div class="tec-sf-err">This field is required</div></div>' +
					'<div class="tec-sf-field" data-field="email"><label>Your Email</label><input type="email" name="email" required><div class="tec-sf-err">This field is required</div></div>' +
				'</div>' +
				'<div class="tec-sf-row">' +
					'<div class="tec-sf-field" data-field="area"><label>Departure Area / Town</label><input type="text" name="area" required><div class="tec-sf-err">This field is required</div></div>' +
					'<div class="tec-sf-field" data-field="seats"><label>Seats Offered / Needed</label><input type="number" name="seats" min="1" value="1"></div>' +
				'</div>' +
				'<div class="tec-sf-field" data-field="notes"><label>Notes (route, timing, fuel costs)</label><textarea name="notes" rows="3"></textarea></div>' +
				'<div class="tec-sf-honeypot"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
				'<button type="submit" class="tec-sf-submit">Post Listing</button>' +
				'<div class="tec-sf-error-banner"></div>' +
				'<div class="tec-sf-success">Thanks — we\'ll review your listing shortly. Check your email for a link to edit it or take it down.</div>' +
			'</form>'
		);
	}

	function bind( root, listings ) {
		root.querySelectorAll( '[data-tcar-contact]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				toggleContactForm( root, btn.getAttribute( 'data-tcar-contact' ) );
			} );
		} );

		var form = root.querySelector( '.tcar-form' );
		if ( ! form ) return;
		var errorBanner = root.querySelector( '.tec-sf-error-banner' );
		var success = root.querySelector( '.tec-sf-success' );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			errorBanner.classList.remove( 'visible' );

			var data = {
				event_id: EVENT_ID,
				type: val( form, 'type' ),
				name: val( form, 'name' ),
				email: val( form, 'email' ),
				area: val( form, 'area' ),
				seats: val( form, 'seats' ) || 1,
				notes: val( form, 'notes' ),
				website: val( form, 'website' ),
			};
			form.querySelectorAll( '.tec-sf-field' ).forEach( function ( el ) { el.classList.remove( 'invalid' ); } );
			var valid = true;
			if ( ! data.name ) valid = invalid( form, 'name' );
			if ( ! data.email ) valid = invalid( form, 'email' );
			if ( ! data.area ) valid = invalid( form, 'area' );
			if ( ! valid ) return;

			fetch( REST + '/submit', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( result ) {
					if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Could not post your listing.' );
					form.style.display = 'none';
					success.classList.add( 'visible' );
				} )
				.catch( function ( err ) {
					errorBanner.textContent = err.message;
					errorBanner.classList.add( 'visible' );
				} );
		} );
	}

	function toggleContactForm( root, listingId ) {
		var holder = root.querySelector( '[data-tcar-contact-form="' + listingId + '"]' );
		if ( ! holder ) return;

		if ( ! holder.hidden ) {
			holder.hidden = true;
			return;
		}
		holder.hidden = false;
		if ( holder.dataset.built ) return;
		holder.dataset.built = '1';

		holder.innerHTML =
			'<form class="tec-sf-form tcar-contact-inner" novalidate>' +
				'<div class="tec-sf-field" data-field="name"><label>Your Name</label><input type="text" name="name" required><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-field" data-field="email"><label>Your Email</label><input type="email" name="email" required><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-field" data-field="message"><label>Message</label><textarea name="message" rows="3" required></textarea><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-honeypot"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
				'<button type="submit" class="tec-sf-submit">Send Message</button>' +
				'<div class="tec-sf-error-banner"></div>' +
				'<div class="tec-sf-success">Message sent!</div>' +
			'</form>';

		var form = holder.querySelector( 'form' );
		var errorBanner = holder.querySelector( '.tec-sf-error-banner' );
		var success = holder.querySelector( '.tec-sf-success' );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			errorBanner.classList.remove( 'visible' );

			var name = val( form, 'name' );
			var email = val( form, 'email' );
			var message = val( form, 'message' );
			form.querySelectorAll( '.tec-sf-field' ).forEach( function ( el ) { el.classList.remove( 'invalid' ); } );
			var valid = true;
			if ( ! name ) valid = invalid( form, 'name' );
			if ( ! email ) valid = invalid( form, 'email' );
			if ( ! message ) valid = invalid( form, 'message' );
			if ( ! valid ) return;

			fetch( REST + '/contact/' + listingId, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { name: name, email: email, message: message, website: val( form, 'website' ) } ),
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( result ) {
					if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Could not send your message.' );
					form.style.display = 'none';
					success.classList.add( 'visible' );
				} )
				.catch( function ( err ) {
					errorBanner.textContent = err.message;
					errorBanner.classList.add( 'visible' );
				} );
		} );
	}

	function invalid( form, fieldName ) {
		var el = form.querySelector( '[data-field="' + fieldName + '"]' );
		if ( el ) el.classList.add( 'invalid' );
		return false;
	}
	function val( form, name ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		return input ? input.value.trim() : '';
	}
	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
	function escapeAttr( str ) {
		return String( str == null ? '' : str ).replace( /"/g, '&quot;' );
	}
})();
