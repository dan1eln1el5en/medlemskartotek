/* Member list: per property / per owner / map, search, sort, copy e-mails, Excel download. */
( function () {
	const D = window.amMembers;
	if ( ! D ) { return; }
	const t = D.i18n;

	const $ = ( id ) => document.getElementById( id );
	const table = $( 'am-table' );
	const search = $( 'am-search' );
	const notice = $( 'am-notice' );
	const collator = new Intl.Collator( 'da', { numeric: true, sensitivity: 'base' } );
	const fmt = ( s, ...args ) => {
		let i = 0;
		return s.replace( /%(\d\$)?d/g, ( m, pos ) => args[ pos ? parseInt( pos, 10 ) - 1 : i++ ] );
	};

	/* ---- Link owners and properties ---- */
	const owners = {};
	D.owners.forEach( ( o ) => { o.properties = []; owners[ o.id ] = o; } );
	D.properties.forEach( ( p ) => { if ( owners[ p.owner_id ] ) { owners[ p.owner_id ].properties.push( p ); } } );
	Object.values( owners ).forEach( ( o ) => o.properties.sort( ( a, b ) => collator.compare( a.name, b.name ) ) );

	/* ---- State (view + sort remembered per browser) ---- */
	const store = {
		get( k, d ) { try { return localStorage.getItem( 'amMembers.' + k ) || d; } catch ( e ) { return d; } },
		set( k, v ) { try { localStorage.setItem( 'amMembers.' + k, v ); } catch ( e ) {} },
	};
	let view = D.view || store.get( 'view', 'property' );
	let sortKey = 'property';
	let sortDir = 1;

	/* ---- Row model: { properties: [...], owner: {...}|null } ---- */
	function allRows() {
		if ( view === 'owner' ) {
			return Object.values( owners ).map( ( o ) => ( { properties: o.properties, owner: o } ) );
		}
		return D.properties.map( ( p ) => ( { properties: [ p ], owner: owners[ p.owner_id ] || null } ) );
	}

	const addressOf = ( o ) => [ o.street, [ o.postcode, o.city ].filter( Boolean ).join( ' ' ) ].filter( Boolean ).join( ', ' );

	const columns = [
		{ key: 'property', label: t.property, sort: ( r ) => r.properties.map( ( p ) => p.name ).join( ', ' ) },
		{ key: 'owner', label: t.owner, sort: ( r ) => ( r.owner ? r.owner.name : '' ) },
		{ key: 'email', label: t.email, sort: ( r ) => ( r.owner ? r.owner.email : '' ) },
		{ key: 'phone', label: t.phone, sort: ( r ) => ( r.owner ? r.owner.phone : '' ) },
		{ key: 'address', label: t.address, sort: ( r ) => ( r.owner ? addressOf( r.owner ) : '' ) },
	];
	if ( view === 'owner' ) { sortKey = 'owner'; }

	function searchText( r ) {
		const o = r.owner || {};
		return [
			r.properties.map( ( p ) => p.name ).join( ' ' ),
			o.name, o.co_name, o.email, o.co_email, o.phone, o.co_phone,
			o.phone && o.phone.replace( /\s+/g, '' ), o.co_phone && o.co_phone.replace( /\s+/g, '' ),
			o.street, o.postcode, o.city,
		].filter( Boolean ).join( ' ' ).toLowerCase();
	}

	function visibleRows() {
		const words = search.value.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
		const col = columns.find( ( c ) => c.key === sortKey ) || columns[ 0 ];
		return allRows()
			.filter( ( r ) => { const s = searchText( r ); return words.every( ( w ) => s.includes( w ) ); } )
			.sort( ( a, b ) => {
				const va = col.sort( a ), vb = col.sort( b );
				if ( ! va !== ! vb ) { return va ? -1 : 1; } // empty values last
				return sortDir * collator.compare( va, vb );
			} );
	}

	/* ---- DOM helpers ---- */
	function el( tag, attrs, children ) {
		const n = document.createElement( tag );
		Object.entries( attrs || {} ).forEach( ( [ k, v ] ) => { if ( v !== undefined ) { n[ k ] = v; } } );
		( children || [] ).forEach( ( c ) => { if ( c !== null && c !== '' ) { n.append( c ); } } );
		return n;
	}
	const editLink = ( id, text ) => el( 'a', { href: D.editUrl + id, textContent: text } );
	const missing = ( text ) => el( 'span', { className: 'am-missing', textContent: text } );
	const lines = ( nodes ) => {
		const out = [];
		nodes.filter( Boolean ).forEach( ( n, i ) => { if ( i ) { out.push( el( 'br' ) ); } out.push( n ); } );
		return out;
	};
	const mail = ( e ) => e && el( 'a', { href: 'mailto:' + e, textContent: e } );
	const tel = ( p ) => p && el( 'a', { href: 'tel:' + p.replace( /[^\d+]/g, '' ), textContent: p } );

	function cells( r ) {
		const o = r.owner;
		const props = r.properties.length
			? r.properties.flatMap( ( p, i ) => ( i ? [ ', ', editLink( p.id, p.name ) ] : [ editLink( p.id, p.name ) ] ) )
			: [ missing( t.noProperty ) ];
		if ( ! o ) {
			return [ props, [ missing( t.noOwner ) ], [], [], [] ];
		}
		return [
			props,
			lines( [ editLink( o.id, o.name ), o.co_name && el( 'span', { className: 'am-co', textContent: '& ' + o.co_name } ) ] ),
			lines( [ mail( o.email ), mail( o.co_email ) ] ),
			lines( [ tel( o.phone ), tel( o.co_phone ) ] ),
			[ addressOf( o ) ],
		];
	}

	/* ---- Keep URL and the admin menu (Properties / Owners) in step with the view ---- */
	function syncMenu() {
		const url = new URL( location.href );
		url.searchParams.set( 'view', view );
		url.searchParams.delete( 'trashed' );
		history.replaceState( null, '', url );
		document.querySelectorAll( '#toplevel_page_am_members .wp-submenu li' ).forEach( ( li ) => {
			const a = li.querySelector( 'a' );
			const href = ( a && a.getAttribute( 'href' ) ) || '';
			if ( ! /page=am_members(&|$)/.test( href ) ) { return; }
			const on = ( view === 'owner' ) === /view=owner/.test( href );
			li.classList.toggle( 'current', on );
			a.classList.toggle( 'current', on );
		} );
	}

	/* ---- Render ---- */
	function render() {
		document.querySelectorAll( '.am-view-toggle button' ).forEach( ( b ) => {
			const on = b.dataset.view === view;
			b.classList.toggle( 'am-active', on );
			b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );

		const rows = visibleRows();
		const total = allRows().length;
		$( 'am-count' ).textContent = rows.length === total ? '' : fmt( t.showing, rows.length, total );

		table.hidden = view === 'map';
		mapBox.hidden = view !== 'map';
		if ( view === 'map' ) {
			renderMap( rows );
			return;
		}

		// Put the grouping column first.
		const cols = view === 'owner'
			? [ columns[ 1 ], columns[ 0 ], ...columns.slice( 2 ) ]
			: columns;
		const order = cols.map( ( c ) => columns.indexOf( c ) );

		const head = el( 'tr', {}, cols.map( ( c ) => {
			const btn = el( 'button', { type: 'button', className: 'am-sort', textContent: c.label } );
			if ( c.key === sortKey ) { btn.append( sortDir > 0 ? ' ▲' : ' ▼' ); }
			btn.addEventListener( 'click', () => {
				sortDir = sortKey === c.key ? -sortDir : 1;
				sortKey = c.key;
				render();
			} );
			return el( 'th', { scope: 'col' }, [ btn ] );
		} ) );
		table.tHead.replaceChildren( head );

		table.tBodies[ 0 ].replaceChildren( ...rows.map( ( r ) => {
			const c = cells( r );
			const tr = el( 'tr', {}, order.map( ( i ) => el( 'td', {}, c[ i ] ) ) );
			if ( ! r.owner || ! r.properties.length ) { tr.className = 'am-row-warning'; }
			return tr;
		} ) );
	}

	/* ---- Map view: plot outlines from assets/map-data.json (see tools/build-map.py) ---- */
	const mapBox = $( 'am-map' );
	const SVG_NS = 'http://www.w3.org/2000/svg';
	const norm = ( s ) => String( s ).toUpperCase().replace( /[^A-Z0-9ÆØÅ]/g, '' ); // "SV 7" -> "SV7"
	const propsByCode = {};
	D.properties.forEach( ( p ) => { ( propsByCode[ norm( p.name ) ] = propsByCode[ norm( p.name ) ] || [] ).push( p ); } );
	let map = null;
	let mapLoading = false;
	let tip = null;

	function svgEl( tag, attrs, text ) {
		const n = document.createElementNS( SVG_NS, tag );
		Object.entries( attrs || {} ).forEach( ( [ k, v ] ) => n.setAttribute( k, v ) );
		if ( text ) { n.textContent = text; }
		return n;
	}

	function loadMap() {
		if ( mapLoading ) { return; }
		mapLoading = true;
		mapBox.replaceChildren( el( 'p', { textContent: '…' } ) );
		fetch( D.mapUrl )
			.then( ( r ) => { if ( ! r.ok ) { throw new Error( r.status ); } return r.json(); } )
			.then( ( data ) => { buildMap( data ); render(); } )
			.catch( () => mapBox.replaceChildren( el( 'p', { className: 'am-missing', textContent: t.mapError } ) ) );
	}

	function buildMap( data ) {
		const svg = svgEl( 'svg', { viewBox: `0 0 ${ data.width } ${ data.height }`, class: 'am-map-svg', role: 'img', 'aria-label': t.map } );
		data.context.forEach( ( d ) => svg.append( svgEl( 'path', { d, class: 'am-map-context' } ) ) );
		data.roads.forEach( ( r ) => svg.append( svgEl( 'path', { d: r.d, class: 'am-map-road' } ) ) );

		const plots = data.plots.map( ( pd ) => {
			const plot = {
				data: pd,
				props: pd.codes.flatMap( ( c ) => propsByCode[ norm( c ) ] || [] ),
				path: svgEl( 'path', { d: pd.d, class: 'am-map-plot', tabindex: '0' } ),
			};
			plot.path.addEventListener( 'mouseenter', ( e ) => showTip( plot, e ) );
			plot.path.addEventListener( 'mousemove', ( e ) => moveTip( plot, e ) );
			plot.path.addEventListener( 'mouseleave', hideTip );
			plot.path.addEventListener( 'focus', () => showTip( plot ) );
			plot.path.addEventListener( 'blur', hideTip );
			plot.path.addEventListener( 'click', () => openPlot( plot ) );
			plot.path.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Enter' ) { openPlot( plot ); } } );
			svg.append( plot.path );
			return plot;
		} );
		// Labels on top, never catching the mouse (see CSS).
		plots.forEach( ( p ) => svg.append( svgEl( 'text', { x: p.data.c.x, y: p.data.c.y, class: 'am-map-label' }, p.data.codes.join( '/' ) ) ) );
		data.roads.forEach( ( r ) => svg.append( svgEl( 'text', {
			x: r.label.x, y: r.label.y, class: 'am-map-roadname',
			transform: `rotate(${ r.label.angle } ${ r.label.x } ${ r.label.y })`,
		}, r.name ) ) );

		tip = el( 'div', { className: 'am-map-tip', hidden: true } );
		const key = ( cls, text ) => el( 'span', { className: 'am-map-key is-' + cls }, [ el( 'i' ), text ] );
		mapBox.replaceChildren(
			el( 'p', { className: 'description', textContent: t.mapHint } ),
			el( 'div', { className: 'am-map-frame' }, [ svg, tip ] ),
			el( 'p', { className: 'am-map-legend' }, [ key( 'owned', t.legendOwned ), key( 'no-owner', t.legendNoOwner ), key( 'unknown', t.legendUnknown ) ] ),
			el( 'p', { className: 'am-map-source', textContent: t.mapSource } )
		);
		map = { plots };
	}

	function renderMap( rows ) {
		if ( ! map ) { loadMap(); return; }
		const visible = new Set( rows.flatMap( ( r ) => r.properties.map( ( p ) => p.id ) ) );
		const filtering = search.value.trim() !== '';
		map.plots.forEach( ( plot ) => {
			const state = ! plot.props.length ? 'unknown'
				: plot.props.some( ( p ) => owners[ p.owner_id ] ) ? 'owned' : 'no-owner';
			const hit = plot.props.some( ( p ) => visible.has( p.id ) );
			plot.path.setAttribute( 'class', `am-map-plot is-${ state }${ filtering ? ( hit ? ' is-hit' : ' is-dimmed' ) : '' }` );
		} );
	}

	function showTip( plot, e ) {
		const parts = [];
		plot.data.codes.forEach( ( code, i ) => {
			const props = propsByCode[ norm( code ) ] || [];
			parts.push( el( 'strong', { textContent: code + ' · ' + plot.data.addresses[ i ] } ) );
			if ( ! props.length ) {
				parts.push( el( 'div', { className: 'am-missing', textContent: t.legendUnknown } ) );
			}
			props.forEach( ( p ) => {
				const o = owners[ p.owner_id ];
				if ( ! o ) {
					parts.push( el( 'div', { className: 'am-missing', textContent: t.noOwner } ) );
					return;
				}
				parts.push( el( 'div', { textContent: o.name + ( o.co_name ? ' & ' + o.co_name : '' ) } ) );
				[ o.email, o.co_email, o.phone, o.co_phone ].filter( Boolean )
					.forEach( ( v ) => parts.push( el( 'div', { className: 'am-map-tip-sub', textContent: v } ) ) );
			} );
		} );
		tip.replaceChildren( ...parts );
		tip.hidden = false;
		moveTip( plot, e );
	}

	function moveTip( plot, e ) {
		const frame = tip.parentNode.getBoundingClientRect();
		let x, y;
		if ( e ) {
			x = e.clientX - frame.left;
			y = e.clientY - frame.top;
		} else {
			const b = plot.path.getBoundingClientRect();
			x = b.left + b.width / 2 - frame.left;
			y = b.top + b.height / 2 - frame.top;
		}
		const w = tip.offsetWidth, h = tip.offsetHeight;
		tip.style.left = ( x + 16 + w > frame.width ? Math.max( 0, x - 16 - w ) : x + 16 ) + 'px';
		tip.style.top = Math.min( Math.max( 0, y - h / 2 ), Math.max( 0, frame.height - h ) ) + 'px';
	}

	function hideTip() { if ( tip ) { tip.hidden = true; } }

	/* Click: open the owner (or the property when it has no owner). */
	function openPlot( plot ) {
		const p = plot.props[ 0 ];
		if ( ! p ) { return; }
		const o = owners[ p.owner_id ];
		location.href = D.editUrl + ( o ? o.id : p.id );
	}

	function renderStats() {
		const list = Object.values( owners );
		const noOwner = D.properties.filter( ( p ) => ! owners[ p.owner_id ] ).length;
		const noProp = list.filter( ( o ) => ! o.properties.length ).length;
		const parts = [ fmt( t.stats, D.properties.length, list.length ) ];
		if ( noOwner ) { parts.push( fmt( t.statsNoOwner, noOwner ) ); }
		if ( noProp ) { parts.push( fmt( t.statsNoProperty, noProp ) ); }
		$( 'am-stats' ).textContent = parts.join( ' · ' );
	}

	/* ---- Copy e-mails (owners + co-owners in current list) ---- */
	function currentEmails() {
		const seen = new Set();
		const out = [];
		visibleRows().forEach( ( r ) => {
			if ( ! r.owner ) { return; }
			[ r.owner.email, r.owner.co_email ].forEach( ( e ) => {
				if ( e && ! seen.has( e.toLowerCase() ) ) { seen.add( e.toLowerCase() ); out.push( e ); }
			} );
		} );
		return out;
	}

	function showNotice( text, type, extra ) {
		notice.className = 'am-notice notice inline notice-' + type;
		notice.replaceChildren( el( 'p', { textContent: text } ) );
		if ( extra ) { notice.append( extra ); }
		notice.hidden = false;
	}

	$( 'am-copy-emails' ).addEventListener( 'click', () => {
		const emails = currentEmails();
		if ( ! emails.length ) { showNotice( t.noEmails, 'warning' ); return; }
		const text = emails.join( '; ' );
		const fallback = () => {
			const ta = el( 'textarea', { className: 'am-copy-box', readOnly: true, value: text } );
			showNotice( t.copyFailed, 'warning', ta );
			ta.focus();
			ta.select();
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text )
				.then( () => showNotice( fmt( t.copied, emails.length ), 'success' ) )
				.catch( fallback );
		} else {
			fallback();
		}
	} );

	/* ---- Excel download: semicolon CSV + BOM (Danish Excel opens it directly) ---- */
	$( 'am-download-csv' ).addEventListener( 'click', () => {
		const header = [ t.property, t.owner, t.email, t.phone, t.coOwner, t.coEmail, t.coPhone, t.street, t.postcode, t.city ];
		const data = visibleRows().map( ( r ) => {
			const o = r.owner || {};
			return [
				r.properties.map( ( p ) => p.name ).join( ', ' ),
				o.name, o.email, o.phone, o.co_name, o.co_email, o.co_phone, o.street, o.postcode, o.city,
			];
		} );
		const csv = [ header, ...data ]
			.map( ( row ) => row.map( ( v ) => '"' + String( v || '' ).replace( /"/g, '""' ) + '"' ).join( ';' ) )
			.join( '\r\n' );
		const blob = new Blob( [ '﻿' + csv ], { type: 'text/csv;charset=utf-8' } );
		const a = el( 'a', {
			href: URL.createObjectURL( blob ),
			download: t.fileName + '-' + new Date().toISOString().slice( 0, 10 ) + '.csv',
		} );
		document.body.append( a );
		a.click();
		a.remove();
		setTimeout( () => URL.revokeObjectURL( a.href ), 1000 );
	} );

	/* ---- Wiring ---- */
	document.querySelectorAll( '.am-view-toggle button' ).forEach( ( b ) => {
		b.addEventListener( 'click', () => {
			view = b.dataset.view;
			store.set( 'view', view );
			sortKey = view === 'owner' ? 'owner' : 'property';
			sortDir = 1;
			syncMenu();
			render();
		} );
	} );
	search.addEventListener( 'input', () => { notice.hidden = true; render(); } );

	renderStats();
	syncMenu();
	render();
	search.focus();
}() );
