( function () {
	'use strict';

	var strings = ( window.uammAdmin || {} ).i18n || {};
	var groupStyle = ( window.uammAdmin || {} ).groupStyle || {};
	var adminMenuFallbacks = {};
	var groupViewState = { alwaysCollapsed: false, groups: {} };
	var appearanceFormDirty = false;
	var activeAdminTab = new URL( window.location.href ).searchParams.get( 'tab' ) || 'menus';

	function initializeAdminTabs() {
		var tabs = Array.from( document.querySelectorAll( '[data-uamm-tab]' ) );
		var panels = Array.from( document.querySelectorAll( '[data-uamm-panel]' ) );
		if ( ! tabs.length || ! panels.length ) { return; }
		var tabList = tabs[ 0 ].closest( '[role="tablist"]' );
		if ( tabList ) {
			tabList.addEventListener( 'pointermove', function () { tabList.classList.remove( 'is-keyboard-nav' ); } );
		}

		function showTab( key, updateHistory, moveFocus ) {
			var nextTab = tabs.find( function ( item ) { return item.getAttribute( 'data-uamm-tab' ) === key; } );
			var nextPanel = panels.find( function ( item ) { return item.getAttribute( 'data-uamm-panel' ) === key; } );
			if ( ! nextTab || ! nextPanel ) { return false; }
			if ( appearanceFormDirty && 'appearance' === activeAdminTab && 'appearance' !== key && ! window.confirm( strings.unsavedAppearance || 'You have unsaved Appearance changes. Leave this section and discard the preview?' ) ) { return false; }
			tabs.forEach( function ( item ) {
				var selected = item === nextTab;
				item.classList.toggle( 'is-active', selected );
				item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				item.setAttribute( 'tabindex', selected ? '0' : '-1' );
			} );
			panels.forEach( function ( item ) { item.hidden = item !== nextPanel; } );
			activeAdminTab = key;
			if ( updateHistory ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'tab', key );
				url.hash = '';
				window.history.pushState( { uammTab: key }, '', url );
			}
			if ( moveFocus ) { nextTab.focus(); }
			return true;
		}

		tabs.forEach( function ( item, index ) {
			item.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				showTab( item.getAttribute( 'data-uamm-tab' ), true, false );
			} );
			item.addEventListener( 'keydown', function ( event ) {
				if ( tabList && [ ' ', 'Spacebar', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) {
					tabList.classList.add( 'is-keyboard-nav' );
				}
				if ( ' ' === event.key || 'Spacebar' === event.key ) {
					event.preventDefault();
					showTab( item.getAttribute( 'data-uamm-tab' ), true, true );
					return;
				}
				if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) { return; }
				event.preventDefault();
				var targetIndex = index;
				if ( 'ArrowRight' === event.key ) { targetIndex = ( index + 1 ) % tabs.length; }
				if ( 'ArrowLeft' === event.key ) { targetIndex = ( index - 1 + tabs.length ) % tabs.length; }
				if ( 'Home' === event.key ) { targetIndex = 0; }
				if ( 'End' === event.key ) { targetIndex = tabs.length - 1; }
				showTab( tabs[ targetIndex ].getAttribute( 'data-uamm-tab' ), true, true );
			} );
		} );
		window.addEventListener( 'popstate', function () {
			var requested = new URL( window.location.href ).searchParams.get( 'tab' ) || 'menus';
			if ( ! showTab( requested, false, false ) ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'tab', activeAdminTab );
				window.history.pushState( { uammTab: activeAdminTab }, '', url );
			}
		} );
	}

	function initializeAppearanceTabs() {
		var tabs = Array.from( document.querySelectorAll( '.uamm-appearance-tab[role="tab"]' ) );
		if ( ! tabs.length ) { return; }

		function activate( tab, moveFocus ) {
			if ( ! tab ) { return; }
			tabs.forEach( function ( candidate ) {
				var selected = candidate === tab;
				candidate.classList.toggle( 'is-active', selected );
				candidate.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				candidate.tabIndex = selected ? 0 : -1;
				var panel = document.getElementById( candidate.getAttribute( 'aria-controls' ) );
				if ( panel ) { panel.hidden = ! selected; }
			} );
			if ( moveFocus ) { tab.focus(); }
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () { activate( tab, false ); } );
			tab.addEventListener( 'keydown', function ( event ) {
				if ( ! [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End' ].includes( event.key ) ) { return; }
				event.preventDefault();
				var targetIndex = index;
				if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) { targetIndex = ( index + 1 ) % tabs.length; }
				if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) { targetIndex = ( index - 1 + tabs.length ) % tabs.length; }
				if ( 'Home' === event.key ) { targetIndex = 0; }
				if ( 'End' === event.key ) { targetIndex = tabs.length - 1; }
				activate( tabs[ targetIndex ], true );
			} );
		} );

		var requestedPanel = window.location.hash ? document.getElementById( window.location.hash.slice( 1 ) ) : null;
		var requestedTab = requestedPanel ? tabs.find( function ( tab ) { return tab.getAttribute( 'aria-controls' ) === requestedPanel.id; } ) : null;
		activate( requestedTab || tabs.find( function ( tab ) { return 'true' === tab.getAttribute( 'aria-selected' ); } ) || tabs[ 0 ], false );
		window.uammActivateAppearancePanel = function ( panel ) {
			var tab = panel ? tabs.find( function ( candidate ) { return candidate.getAttribute( 'aria-controls' ) === panel.id; } ) : null;
			if ( tab ) { activate( tab, false ); }
		};
	}

	function initializeRoleAccessControls() {
		document.querySelectorAll( '[data-uamm-role-access-row]' ).forEach( function ( row ) {
			var access = row.querySelector( '[data-uamm-role-access]' );
			var mode = row.querySelector( '[data-uamm-role-menu-mode]' );
			if ( ! access || ! mode ) { return; }
			function updateModeState() {
				mode.disabled = access.checked;
				row.classList.toggle( 'has-editor-access', access.checked );
			}
			access.addEventListener( 'change', updateModeState );
			updateModeState();
		} );
	}

	function uid( prefix ) {
		return prefix + '-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 7 );
	}

	function input( label, field, type, value ) {
		var wrapper = document.createElement( 'label' );
		var control = document.createElement( 'input' );
		wrapper.appendChild( document.createTextNode( label ) );
		control.type = type || 'text';
		control.setAttribute( field.indexOf( 'group' ) === 0 ? 'data-group-field' : 'data-custom-field', field.replace( /^(group|custom)-/, '' ) );
		control.value = value || '';
		wrapper.appendChild( control );
		return wrapper;
	}

	function dashiconInput( label, field, value ) {
		var wrapper = document.createElement( 'div' );
		var fieldLabel = document.createElement( 'label' );
		var labelText = document.createElement( 'span' );
		var control = document.createElement( 'span' );
		var preview = document.createElement( 'span' );
		var text = document.createElement( 'input' );
		var button = document.createElement( 'button' );
		var buttonIcon = document.createElement( 'span' );
		wrapper.classList.add( 'uamm-icon-field' );
		labelText.textContent = label;
		control.className = 'uamm-icon-control';
		preview.className = 'uamm-icon-field__preview dashicons ' + ( /^dashicons-[a-z0-9-]+$/i.test( value ) ? value : 'dashicons-admin-generic' );
		preview.setAttribute( 'aria-hidden', 'true' );
		text.type = 'text';
		text.value = value || '';
		text.setAttribute( field.indexOf( 'group' ) === 0 ? 'data-group-field' : 'data-custom-field', field.replace( /^(group|custom)-/, '' ) );
		button.type = 'button';
		button.className = 'button uamm-open-dashicon-picker';
		button.setAttribute( 'aria-label', strings.chooseIcon || 'Choose Dashicon' );
		buttonIcon.className = 'dashicons dashicons-search';
		buttonIcon.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( buttonIcon );
		control.appendChild( preview );
		control.appendChild( text );
		control.appendChild( button );
		fieldLabel.appendChild( labelText );
		fieldLabel.appendChild( control );
		wrapper.appendChild( fieldLabel );
		return wrapper;
	}

	var dashiconPicker = null;
	var dashiconTarget = null;
	var dashiconOpener = null;
	var dashiconNames = null;

	function discoverDashicons() {
		if ( dashiconNames ) { return dashiconNames; }
		var names = new Set();
		function inspectRules( rules ) {
			Array.prototype.forEach.call( rules || [], function ( rule ) {
				if ( rule.cssRules ) { inspectRules( rule.cssRules ); }
				String( rule.selectorText || '' ).replace( /\.dashicons-([a-z0-9-]+)(?=[:\s,.{])/gi, function ( match, name ) {
					if ( 'before' !== name ) { names.add( 'dashicons-' + name ); }
					return match;
				} );
			} );
		}
		Array.prototype.forEach.call( document.styleSheets, function ( sheet ) {
			try { inspectRules( sheet.cssRules ); } catch ( error ) { /* Ignore stylesheets that the browser will not expose. */ }
		} );
		if ( ! names.size ) {
			[ 'admin-appearance', 'admin-comments', 'admin-customizer', 'admin-generic', 'admin-home', 'admin-links', 'admin-media', 'admin-page', 'admin-plugins', 'admin-post', 'admin-settings', 'admin-site', 'admin-tools', 'admin-users', 'analytics', 'archive', 'awards', 'book', 'calendar-alt', 'category', 'chart-area', 'chart-bar', 'chart-line', 'chart-pie', 'clipboard', 'cloud', 'database', 'desktop', 'edit', 'email', 'feedback', 'flag', 'format-gallery', 'format-image', 'format-video', 'forms', 'groups', 'hammer', 'heart', 'images-alt2', 'layout', 'location', 'media-document', 'menu', 'megaphone', 'networking', 'open-folder', 'performance', 'portfolio', 'products', 'schedule', 'search', 'shield', 'store', 'tag', 'tickets', 'universal-access', 'welcome-widgets-menus', 'wordpress' ].forEach( function ( name ) { names.add( 'dashicons-' + name ); } );
		}
		dashiconNames = Array.from( names ).sort();
		return dashiconNames;
	}

	function readableDashiconName( value ) {
		return value.replace( /^dashicons-/, '' ).replace( /-/g, ' ' );
	}

	function updateIconPreview( target, iconClass ) {
		var row = target.closest( '.uamm-menu-group, .uamm-menu-item' );
		var preview = row ? row.querySelector( target.matches( '[data-group-field="icon"]' ) ? '.uamm-group-row-icon' : '.uamm-menu-item__icon' ) : null;
		var inlinePreview = target.closest( '.uamm-icon-field' ).querySelector( '.uamm-icon-field__preview' );
		var isDashicon = /^dashicons-[a-z0-9-]+$/i.test( iconClass );
		var isImage = /^https?:\/\/[^\s]+$/i.test( iconClass ) || /^data:image\/svg\+xml;base64,[A-Za-z0-9+/=]+$/.test( iconClass );
		[ preview, inlinePreview ].forEach( function ( icon ) {
			if ( ! icon ) { return; }
			Array.from( icon.classList ).forEach( function ( className ) {
				if ( className.indexOf( 'dashicons-' ) === 0 ) { icon.classList.remove( className ); }
			} );
			icon.classList.remove( 'dashicons', 'uamm-menu-icon-image' );
			icon.replaceChildren();
			if ( isDashicon ) {
				icon.classList.add( 'dashicons', iconClass );
			} else if ( isImage ) {
				var image = document.createElement( 'img' );
				image.alt = '';
				image.src = iconClass;
				icon.classList.add( 'uamm-menu-icon-image' );
				icon.appendChild( image );
			} else {
				icon.classList.add( 'dashicons', 'dashicons-admin-generic' );
			}
		} );
	}

	function closeDashiconPicker() {
		if ( ! dashiconPicker || dashiconPicker.hidden ) { return; }
		dashiconPicker.hidden = true;
		document.body.classList.remove( 'uamm-modal-open' );
		dashiconTarget = null;
		if ( dashiconOpener && dashiconOpener.isConnected ) { dashiconOpener.focus(); }
		dashiconOpener = null;
	}

	function renderDashiconChoices( query ) {
		var grid = dashiconPicker.querySelector( '[data-dashicon-grid]' );
		var status = dashiconPicker.querySelector( '[data-dashicon-status]' );
		var term = String( query || '' ).trim().toLowerCase();
		var choices = discoverDashicons().filter( function ( name ) { return ! term || name.indexOf( term ) !== -1; } );
		grid.replaceChildren();
		choices.forEach( function ( name ) {
			var button = document.createElement( 'button' );
			var icon = document.createElement( 'span' );
			var text = document.createElement( 'span' );
			button.type = 'button';
			button.className = 'uamm-dashicon-choice';
			button.setAttribute( 'data-dashicon', name );
			button.setAttribute( 'aria-label', ( strings.useIcon || 'Use %s' ).replace( '%s', readableDashiconName( name ) ) );
			button.setAttribute( 'aria-pressed', dashiconTarget && dashiconTarget.value.trim() === name ? 'true' : 'false' );
			icon.className = 'dashicons ' + name;
			icon.setAttribute( 'aria-hidden', 'true' );
			text.textContent = readableDashiconName( name );
			button.appendChild( icon );
			button.appendChild( text );
			grid.appendChild( button );
		} );
		status.textContent = ( strings.iconCount || '%d icons' ).replace( '%d', choices.length );
	}

	function createDashiconPicker() {
		if ( dashiconPicker ) { return dashiconPicker; }
		dashiconPicker = document.createElement( 'div' );
		dashiconPicker.className = 'uamm-dashicon-modal';
		dashiconPicker.hidden = true;
		dashiconPicker.innerHTML = '<div class="uamm-dashicon-modal__backdrop" data-dashicon-close></div><section class="uamm-dashicon-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="uamm-dashicon-title"><header><div><h2 id="uamm-dashicon-title"></h2><p></p></div><button type="button" class="uamm-dashicon-modal__close" data-dashicon-close><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"></span></button></header><div class="uamm-dashicon-modal__search"><label for="uamm-dashicon-search"></label><input id="uamm-dashicon-search" type="search" autocomplete="off"><span data-dashicon-status aria-live="polite"></span></div><div class="uamm-dashicon-grid" data-dashicon-grid></div><footer><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer"></a><button type="button" class="button" data-dashicon-close></button></footer></section>';
		dashiconPicker.querySelector( 'h2' ).textContent = strings.iconPickerTitle || 'Choose a Dashicon';
		dashiconPicker.querySelector( 'header p' ).textContent = strings.iconPickerHelp || 'Choose an icon from this WordPress installation.';
		dashiconPicker.querySelector( 'label' ).textContent = strings.searchIcons || 'Search icons';
		dashiconPicker.querySelector( 'input[type="search"]' ).placeholder = strings.searchIconsPlaceholder || 'Search by name';
		dashiconPicker.querySelector( '.screen-reader-text' ).textContent = strings.closeIconPicker || 'Close icon picker';
		dashiconPicker.querySelector( 'footer a' ).textContent = strings.dashiconsReference || 'Dashicons reference';
		dashiconPicker.querySelector( 'footer button' ).textContent = strings.close || 'Close';
		document.body.appendChild( dashiconPicker );
		return dashiconPicker;
	}

	function openDashiconPicker( opener ) {
		var field = opener.closest( '.uamm-icon-field' );
		var target = field ? field.querySelector( 'input[type="text"]' ) : null;
		var modal = createDashiconPicker();
		if ( ! target ) { return; }
		dashiconTarget = target;
		dashiconOpener = opener;
		modal.hidden = false;
		document.body.classList.add( 'uamm-modal-open' );
		var search = modal.querySelector( 'input[type="search"]' );
		search.value = '';
		renderDashiconChoices( '' );
		window.requestAnimationFrame( function () { search.focus(); } );
	}

	function removeButton() {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button-link-delete uamm-remove';
		button.textContent = strings.remove || 'Remove';
		return button;
	}

	function initializeAutoDismissNotices() {
		document.querySelectorAll( '[data-uamm-auto-dismiss]' ).forEach( function ( notice ) {
			var delay = parseInt( notice.getAttribute( 'data-uamm-auto-dismiss' ), 10 );
			if ( ! Number.isFinite( delay ) || delay < 1 ) { return; }
			window.setTimeout( function () {
				if ( ! notice.isConnected ) { return; }
				var dismiss = notice.querySelector( '.notice-dismiss' );
				if ( dismiss ) { dismiss.click(); } else { notice.remove(); }
			}, delay );
		} );
	}

	function groupColorInput( label, field, value, fallback ) {
		var wrapper = document.createElement( 'label' );
		var labelText = document.createElement( 'span' );
		var controls = document.createElement( 'span' );
		var swatch = document.createElement( 'span' );
		var picker = document.createElement( 'input' );
		var text = document.createElement( 'input' );
		wrapper.className = 'uamm-group-color-field';
		labelText.textContent = label;
		wrapper.appendChild( labelText );
		controls.className = 'uamm-inline-color';
		swatch.className = 'uamm-color-swatch';
		picker.type = 'color';
		picker.value = value || fallback;
		picker.setAttribute( 'data-group-color-picker', field );
		swatch.style.setProperty( '--uamm-swatch-color', picker.value );
		text.type = 'text';
		text.value = value || '';
		text.placeholder = fallback;
		text.maxLength = 7;
		text.pattern = '#[0-9A-Fa-f]{6}';
		text.spellcheck = false;
		text.setAttribute( 'data-group-field', field );
		swatch.appendChild( picker );
		controls.appendChild( swatch );
		controls.appendChild( text );
		wrapper.appendChild( controls );
		return wrapper;
	}

	function groupSettingsSection( label, description ) {
		var section = document.createElement( 'fieldset' );
		var legend = document.createElement( 'legend' );
		var help = document.createElement( 'p' );
		var fields = document.createElement( 'div' );
		section.className = 'uamm-group-settings-section';
		legend.textContent = label;
		help.textContent = description;
		fields.className = 'uamm-field-grid';
		section.appendChild( legend );
		section.appendChild( help );
		section.appendChild( fields );
		return { section: section, fields: fields };
	}

	function groupCheckboxInput( label, field, description ) {
		var wrapper = document.createElement( 'label' );
		var checkbox = document.createElement( 'input' );
		var copy = document.createElement( 'span' );
		var help = document.createElement( 'small' );
		wrapper.className = 'uamm-check';
		checkbox.type = 'checkbox';
		checkbox.setAttribute( 'data-group-field', field );
		copy.appendChild( document.createTextNode( label ) );
		help.textContent = description;
		copy.appendChild( help );
		wrapper.appendChild( checkbox );
		wrapper.appendChild( copy );
		return wrapper;
	}

	function activateGroupSettingsTab( tab, moveFocus ) {
		var tablist = tab.closest( '.uamm-group-settings-tabs' );
		var details = tab.closest( '.uamm-menu-group__details, .uamm-menu-item__details' );
		if ( ! tablist || ! details ) { return; }
		tablist.querySelectorAll( '[role="tab"]' ).forEach( function ( candidate ) {
			var selected = candidate === tab;
			candidate.classList.toggle( 'is-active', selected );
			candidate.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			candidate.tabIndex = selected ? 0 : -1;
		} );
		details.querySelectorAll( ':scope > .uamm-group-settings-panel' ).forEach( function ( panel ) {
			panel.hidden = panel.id !== tab.getAttribute( 'aria-controls' );
		} );
		if ( moveFocus ) { tab.focus(); }
	}

	function hexRgb( value ) {
		var hex = String( value || '' ).trim().replace( '#', '' );
		if ( /^[0-9a-f]{3}$/i.test( hex ) ) { hex = hex.replace( /./g, function ( part ) { return part + part; } ); }
		if ( ! /^[0-9a-f]{6}$/i.test( hex ) ) { return null; }
		return [ parseInt( hex.slice( 0, 2 ), 16 ), parseInt( hex.slice( 2, 4 ), 16 ), parseInt( hex.slice( 4, 6 ), 16 ) ];
	}

	function luminance( rgb ) {
		var channels = rgb.map( function ( value ) {
			value /= 255;
			return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];
	}

	function contrastRatio( first, second ) {
		var firstRgb = hexRgb( first );
		var secondRgb = hexRgb( second );
		if ( ! firstRgb || ! secondRgb ) { return 0; }
		var firstLum = luminance( firstRgb );
		var secondLum = luminance( secondRgb );
		return ( Math.max( firstLum, secondLum ) + 0.05 ) / ( Math.min( firstLum, secondLum ) + 0.05 );
	}

	function cssColorToHex( value, fallback ) {
		if ( /^#[0-9a-f]{6}$/i.test( String( value || '' ).trim() ) ) { return value.trim(); }
		var channels = String( value || '' ).match( /\d+(?:\.\d+)?/g );
		if ( ! channels || channels.length < 3 ) { return fallback; }
		return '#' + channels.slice( 0, 3 ).map( function ( channel ) {
			return Math.max( 0, Math.min( 255, Math.round( Number( channel ) ) ) ).toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	}

	function readAdminMenuFallbacks() {
		var menu = document.getElementById( 'adminmenu' );
		var normalLink = menu ? menu.querySelector( 'li.menu-top:not(.current):not(.wp-has-current-submenu) > a.menu-top' ) : null;
		var currentLink = menu ? menu.querySelector( 'li.current > a.menu-top, li.wp-has-current-submenu > a.wp-has-current-submenu' ) : null;
		var normalIcon = normalLink ? normalLink.querySelector( '.wp-menu-image' ) : null;
		var currentIcon = currentLink ? currentLink.querySelector( '.wp-menu-image' ) : null;
		return {
			background: cssColorToHex( menu ? getComputedStyle( menu ).backgroundColor : '', '#1d2327' ),
			text: cssColorToHex( normalLink ? getComputedStyle( normalLink ).color : '', '#f0f0f1' ),
			icon: cssColorToHex( normalIcon ? getComputedStyle( normalIcon, '::before' ).color : '', '#a7aaad' ),
			highlight: cssColorToHex( currentLink ? getComputedStyle( currentLink ).backgroundColor : '', '#2271b1' ),
			highlightText: cssColorToHex( currentLink ? getComputedStyle( currentLink ).color : '', '#ffffff' ),
			highlightIcon: cssColorToHex( currentIcon ? getComputedStyle( currentIcon, '::before' ).color : '', '#ffffff' ),
			submenu_background: '#333333',
			submenu_text: '#f0f0f1',
			submenu_hover_background: '#2c3338',
			submenu_hover_text: '#ffffff'
		};
	}

	function initializeAdminMenuColors() {
		var card = document.querySelector( '[data-admin-menu-colors]' );
		if ( ! card ) { return; }
		adminMenuFallbacks = readAdminMenuFallbacks();
		card.querySelectorAll( '[data-appearance-field]' ).forEach( function ( input ) {
			var field = input.getAttribute( 'data-appearance-field' );
			var fallback = adminMenuFallbacks[ field ];
			var picker = card.querySelector( '[data-appearance-color-picker="' + field + '"]' );
			if ( fallback ) { input.placeholder = fallback; }
			if ( picker && ! input.value.trim() && fallback ) { picker.value = fallback; updateColorSwatch( picker ); }
		} );
	}

	function updateRangeProgress( range ) {
		var min = parseFloat( range.min );
		var max = parseFloat( range.max );
		var value = parseFloat( range.value );
		var progress = max > min ? ( ( value - min ) / ( max - min ) ) * 100 : 0;
		range.style.setProperty( '--uamm-range-progress', Math.max( 0, Math.min( 100, progress ) ) + '%' );
	}

	function initializeMenuWidth() {
		var range = document.querySelector( '[data-menu-width-range]' );
		var number = document.querySelector( '[data-menu-width-number]' );
		if ( ! range || ! number ) { return; }
		function sync( source, target ) {
			var width = Math.max( 140, Math.min( 320, parseInt( source.value, 10 ) || 160 ) );
			target.value = String( width );
			document.documentElement.style.setProperty( '--uamm-admin-menu-width', width + 'px' );
		}
		range.addEventListener( 'input', function () { sync( range, number ); } );
		number.addEventListener( 'input', function () { sync( number, range ); } );
	}

	function appearanceFieldValue( form, selector, fallback ) {
		var field = form.querySelector( selector );
		if ( field && 'radio' === field.type ) {
			field = Array.from( form.elements ).find( function ( control ) {
				return control.name === field.name && control.checked;
			} ) || field;
		}
		return field && field.value.trim() ? field.value.trim() : field && field.placeholder ? field.placeholder : fallback;
	}

	function previewAppearance( form ) {
		var root = document.documentElement;
		var colorVariables = {
			background: '--uamm-bg',
			text: '--uamm-text',
			icon: '--uamm-icon',
			highlight: '--uamm-highlight',
			divider_color: '--uamm-menu-divider',
			submenu_background: '--uamm-submenu-bg',
			submenu_text: '--uamm-submenu-text',
			submenu_hover_background: '--uamm-submenu-hover-bg',
			submenu_hover_text: '--uamm-submenu-hover-text'
		};
		Object.keys( colorVariables ).forEach( function ( field ) {
			var color = appearanceFieldValue( form, '[data-appearance-field="' + field + '"]', '' );
			if ( /^#[0-9a-f]{6}$/i.test( color ) ) { root.style.setProperty( colorVariables[ field ], color ); }
		} );
		var width = Math.max( 140, Math.min( 320, parseInt( appearanceFieldValue( form, '[name="menu_width"]', '275' ), 10 ) || 275 ) );
		var submenuEdgeDistanceValue = parseInt( appearanceFieldValue( form, '[name="submenu_edge_distance"]', '0' ), 10 );
		var submenuEdgeDistance = Math.max( 0, Math.min( 24, Number.isNaN( submenuEdgeDistanceValue ) ? 0 : submenuEdgeDistanceValue ) );
		var touch = Boolean( form.querySelector( '[name="touch_optimized_menu"][type="checkbox"]:checked' ) );
		var dividerOpacityValue = parseInt( appearanceFieldValue( form, '[name="divider_opacity"]', '13' ), 10 );
		var dividerOpacity = Math.max( 0, Math.min( 100, Number.isNaN( dividerOpacityValue ) ? 13 : dividerOpacityValue ) );
		var submenuIndicators = Boolean( form.querySelector( '[name="submenu_indicators"][type="checkbox"]:checked' ) );
		root.style.setProperty( '--uamm-admin-menu-width', width + 'px' );
		root.style.setProperty( '--uamm-submenu-edge-distance', submenuEdgeDistance + 'px' );
		root.style.setProperty( '--uamm-menu-divider-opacity', dividerOpacity + '%' );
		document.body.classList.toggle( 'uamm-submenu-indicators', submenuIndicators );
		root.style.setProperty( '--uamm-menu-item-min-height', touch ? '44px' : '34px' );
		root.style.setProperty( '--uamm-menu-item-padding-block', touch ? '13px' : '8px' );
		root.style.setProperty( '--uamm-menu-icon-padding-top', touch ? '12px' : '7px' );
		root.style.setProperty( '--uamm-submenu-item-padding-block', touch ? '12px' : '5px' );
		root.style.setProperty( '--uamm-submenu-item-line-height', touch ? '20px' : '24px' );
		root.style.setProperty( '--uamm-group-pin-size', touch ? '34px' : '26px' );
		root.style.setProperty( '--uamm-group-pin-top', touch ? '5px' : '4px' );
		root.style.setProperty( '--uamm-group-label-pin-space', touch ? '36px' : '28px' );
		root.style.setProperty( '--uamm-group-margin-inline', appearanceFieldValue( form, '[name="group_margin_inline"]', '5' ) + 'px' );
		root.style.setProperty( '--uamm-group-margin-block', appearanceFieldValue( form, '[name="group_margin_block"]', '4' ) + 'px' );
		root.style.setProperty( '--uamm-group-border-radius', appearanceFieldValue( form, '[name="group_border_radius"]', '5' ) + 'px' );
		root.style.setProperty( '--uamm-group-heading-font-size', appearanceFieldValue( form, '[name="group_heading_font_size"]', '11' ) + 'px' );
		root.style.setProperty( '--uamm-group-heading-font-weight', appearanceFieldValue( form, '[name="group_heading_font_weight"]', '700' ) );
		root.style.setProperty( '--uamm-group-heading-letter-spacing', appearanceFieldValue( form, '[name="group_heading_letter_spacing"]', '0.4' ) + 'px' );
		root.style.setProperty( '--uamm-group-heading-text-transform', appearanceFieldValue( form, '[name="group_heading_text_transform"]', 'uppercase' ) );

		var groupVariableMap = {
			background: '--uamm-group-open-bg',
			text_color: '--uamm-group-open-text',
			border_color: '--uamm-group-heading-border',
			hover_background: '--uamm-group-heading-hover-bg',
			hover_text_color: '--uamm-group-heading-hover-text',
			collapsed_background: '--uamm-group-collapsed-bg',
			collapsed_text_color: '--uamm-group-collapsed-text',
			collapsed_hover_background: '--uamm-group-collapsed-hover-bg',
			collapsed_hover_text_color: '--uamm-group-collapsed-hover-text'
		};
		var panelVariableMap = {
			panel_border_color: '--uamm-group-panel-border',
			panel_background: '--uamm-group-panel-bg',
			panel_text_color: '--uamm-group-panel-text',
			panel_hover_background: '--uamm-group-panel-hover-bg',
			panel_hover_text_color: '--uamm-group-panel-hover-text'
		};
		var runtimeGroups = ( window.uammRuntime || {} ).groups || [];
		document.querySelectorAll( '#adminmenu .uamm-group-heading' ).forEach( function ( heading ) {
			var configuredGroup = runtimeGroups.find( function ( group ) { return group.id === heading.getAttribute( 'data-group-id' ); } ) || {};
			Object.keys( groupVariableMap ).forEach( function ( field ) {
				if ( ! configuredGroup[ field ] ) { heading.style.setProperty( groupVariableMap[ field ], appearanceFieldValue( form, '[data-global-group-style] [data-group-field="' + field + '"]', groupStyle[ field ] || '' ) ); }
			} );
			var member = heading.nextElementSibling;
			while ( member && member.classList.contains( 'uamm-group-member' ) ) {
				Object.keys( panelVariableMap ).forEach( function ( field ) {
					if ( ! configuredGroup[ field ] ) { member.style.setProperty( panelVariableMap[ field ], appearanceFieldValue( form, '[data-global-group-style] [data-group-field="' + field + '"]', groupStyle[ field ] || '' ) ); }
				} );
				member = member.nextElementSibling;
			}
		} );
	}

	function initializeAppearancePreview() {
		var form = document.querySelector( '[data-global-group-style-form]' );
		if ( ! form ) { return; }
		previewAppearance( form );
		[ 'input', 'change' ].forEach( function ( eventName ) {
			form.addEventListener( eventName, function ( event ) {
				if ( ! event.target.matches( 'input, select' ) || 'hidden' === event.target.type ) { return; }
				appearanceFormDirty = true;
				previewAppearance( form );
			} );
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! appearanceFormDirty ) { return; }
			event.preventDefault();
			event.returnValue = '';
		} );
	}

	function adminMenuColorValue( card, field, highlighted ) {
		var input = card.querySelector( '[data-appearance-field="' + field + '"]' );
		var custom = input ? input.value.trim() : '';
		if ( custom ) { return custom; }
		if ( highlighted && 'text' === field ) { return adminMenuFallbacks.highlightText; }
		if ( highlighted && 'icon' === field ) { return adminMenuFallbacks.highlightIcon; }
		return adminMenuFallbacks[ field ] || '';
	}

	function createContrastResult( label, ratio, threshold, status, badgeText ) {
		var result = document.createElement( 'div' );
		var labelNode = document.createElement( 'span' );
		var ratioNode = document.createElement( 'span' );
		var meter = document.createElement( 'span' );
		var fill = document.createElement( 'span' );
		var badge = document.createElement( 'span' );
		result.className = 'uamm-contrast-result ' + status;
		labelNode.className = 'uamm-contrast-result__label';
		ratioNode.className = 'uamm-contrast-result__ratio';
		meter.className = 'uamm-contrast-result__meter';
		badge.className = 'uamm-contrast-result__badge';
		labelNode.textContent = label;
		ratioNode.textContent = ratio.toFixed( 2 ) + ':1';
		fill.style.width = Math.min( 100, Math.max( 0, ratio / Math.max( threshold, 7 ) * 100 ) ) + '%';
		badge.textContent = badgeText;
		meter.appendChild( fill );
		result.append( labelNode, ratioNode, meter, badge );
		result.setAttribute( 'aria-label', label + ': ' + ratio.toFixed( 2 ) + ' to 1, ' + badgeText );
		return result;
	}

	function appendContrastHeading( output ) {
		var heading = document.createElement( 'h3' );
		heading.className = 'uamm-contrast-status__title';
		heading.textContent = strings.colorContrast || 'Color contrast';
		output.appendChild( heading );
	}

	function validateAdminMenuContrast( card ) {
		var output = card ? card.querySelector( '[data-admin-menu-contrast]' ) : null;
		if ( ! output ) { return true; }
		var checks = [
			[ 'Normal text', 'text', 'background', 4.5, false ],
			[ 'Normal icons', 'icon', 'background', 3, false ],
			[ 'Highlighted text', 'text', 'highlight', 4.5, true ],
			[ 'Highlighted icons', 'icon', 'highlight', 3, true ],
			[ 'Plugin submenu text', 'submenu_text', 'submenu_background', 4.5, false ],
			[ 'Submenu hover and focus text', 'submenu_hover_text', 'submenu_hover_background', 4.5, false ]
		];
		var valid = true;
		output.replaceChildren();
		appendContrastHeading( output );
		card.querySelectorAll( '[data-appearance-field][aria-invalid]' ).forEach( function ( input ) { input.removeAttribute( 'aria-invalid' ); } );
		checks.forEach( function ( check ) {
			var ratio = contrastRatio( adminMenuColorValue( card, check[ 1 ], check[ 4 ] ), adminMenuColorValue( card, check[ 2 ], check[ 4 ] ) );
			var passes = ratio >= check[ 3 ];
			var result = createContrastResult( check[ 0 ], ratio, check[ 3 ], passes ? 'is-pass' : 'is-fail', passes ? 'Pass' : 'Needs ' + check[ 3 ] + ':1' );
			output.appendChild( result );
			if ( ! passes ) {
				valid = false;
				[ check[ 1 ], check[ 2 ] ].forEach( function ( field ) {
					var input = card.querySelector( '[data-appearance-field="' + field + '"]' );
					if ( input ) { input.setAttribute( 'aria-invalid', 'true' ); }
				} );
			}
		} );
		return valid;
	}

	function groupColorValue( row, field ) {
		var input = row.querySelector( '[data-group-field="' + field + '"]' );
		return input ? input.value.trim() || input.placeholder : '';
	}

	function validateGroupContrast( row ) {
		var output = row.querySelector( '[data-group-contrast]' );
		if ( ! output ) { return true; }
		var checks = [
			[ 'Heading text', 'text_color', 'background', 4.5, true ],
			[ 'Heading hover text', 'hover_text_color', 'hover_background', 4.5, true ],
			[ 'Collapsed heading text', 'collapsed_text_color', 'collapsed_background', 4.5, true ],
			[ 'Collapsed heading hover text', 'collapsed_hover_text_color', 'collapsed_hover_background', 4.5, true ],
			[ 'Heading border', 'border_color', 'background', 3, false, 'show_heading_border' ],
			[ 'Item text', 'panel_text_color', 'panel_background', 4.5, true ],
			[ 'Item hover text', 'panel_hover_text_color', 'panel_hover_background', 4.5, true ],
			[ 'Item border', 'panel_border_color', 'panel_background', 3, false, 'show_panel_border' ]
		];
		var valid = true;
		output.replaceChildren();
		appendContrastHeading( output );
		row.querySelectorAll( '[data-group-field][aria-invalid]' ).forEach( function ( input ) { input.removeAttribute( 'aria-invalid' ); } );
		checks.forEach( function ( check ) {
			var enabledInput = check[ 5 ] ? row.querySelector( '[data-group-field="' + check[ 5 ] + '"]' ) : null;
			if ( enabledInput && ! enabledInput.checked ) {
				var disabledResult = createContrastResult( check[ 0 ], 0, check[ 3 ], 'is-neutral', 'Off' );
				output.appendChild( disabledResult );
				return;
			}
			var ratio = contrastRatio( groupColorValue( row, check[ 1 ] ), groupColorValue( row, check[ 2 ] ) );
			var passes = ratio >= check[ 3 ];
			var result = createContrastResult( check[ 0 ], ratio, check[ 3 ], passes ? 'is-pass' : check[ 4 ] ? 'is-fail' : 'is-warning', passes ? 'Pass' : check[ 4 ] ? 'Needs ' + check[ 3 ] + ':1' : 'Review' );
			output.appendChild( result );
			if ( ! passes && check[ 4 ] ) {
				valid = false;
				[ check[ 1 ], check[ 2 ] ].forEach( function ( field ) {
					var input = row.querySelector( '[data-group-field="' + field + '"]' );
					if ( input ) { input.setAttribute( 'aria-invalid', 'true' ); }
				} );
			}
		} );
		return valid;
	}

	function itemColorValue( row, field ) {
		var input = row.querySelector( '[data-field="' + field + '"]' );
		return input ? input.value.trim() || input.placeholder : '';
	}

	function validateItemContrast( row ) {
		var output = row ? row.querySelector( '[data-item-contrast]' ) : null;
		if ( ! output ) { return true; }
		var checks = [ [ 'Top-level item text', 'text_color', 'background' ] ];
		if ( row.querySelector( '[data-field="submenu_background"]' ) ) {
			checks.push( [ 'Submenu text', 'submenu_text_color', 'submenu_background' ] );
			checks.push( [ 'Submenu hover text', 'submenu_hover_text_color', 'submenu_hover_background' ] );
		}
		var valid = true;
		output.replaceChildren();
		appendContrastHeading( output );
		row.querySelectorAll( '[data-item-appearance] [data-field][aria-invalid]' ).forEach( function ( input ) { input.removeAttribute( 'aria-invalid' ); } );
		checks.forEach( function ( check ) {
			var ratio = contrastRatio( itemColorValue( row, check[ 1 ] ), itemColorValue( row, check[ 2 ] ) );
			var passes = ratio >= 4.5;
			var result = createContrastResult( check[ 0 ], ratio, 4.5, passes ? 'is-pass' : 'is-fail', passes ? 'Pass' : 'Needs 4.5:1' );
			output.appendChild( result );
			if ( ! passes ) {
				valid = false;
				[ check[ 1 ], check[ 2 ] ].forEach( function ( field ) {
					var input = row.querySelector( '[data-field="' + field + '"]' );
					if ( input ) { input.setAttribute( 'aria-invalid', 'true' ); }
				} );
			}
		} );
		return valid;
	}

	function updateMenuSaveState() {
		var form = document.getElementById( 'uamm-menu-form' );
		var output = document.getElementById( 'uamm-save-contrast-state' );
		if ( ! form || ! output ) { return; }
		var invalidCount = 0;
		form.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( function ( row ) { if ( ! validateGroupContrast( row ) ) { invalidCount++; } } );
		form.querySelectorAll( '#uamm-menu-list .uamm-menu-item' ).forEach( function ( row ) { if ( ! validateItemContrast( row ) ) { invalidCount++; } } );
		var button = form.querySelector( 'button[type="submit"]' );
		if ( button ) { button.disabled = invalidCount > 0; }
		output.className = 'uamm-save-state ' + ( invalidCount ? 'is-blocked' : 'is-ready' );
		output.textContent = invalidCount ? ( strings.menuContrastCount || 'Cannot save: fix %d contrast check(s).' ).replace( '%d', invalidCount ) : strings.contrastReady || 'Contrast checks pass.';
	}

	function updateGlobalSaveState() {
		var form = document.querySelector( '[data-global-group-style-form]' );
		var style = form ? form.querySelector( '[data-global-group-style]' ) : null;
		var menuColors = form ? form.querySelector( '[data-admin-menu-colors]' ) : null;
		var output = document.getElementById( 'uamm-global-save-contrast-state' );
		if ( ! form || ! style || ! menuColors || ! output ) { return; }
		var groupValid = validateGroupContrast( style );
		var menuValid = validateAdminMenuContrast( menuColors );
		var valid = groupValid && menuValid;
		var button = form.querySelector( 'button[type="submit"]' );
		if ( button ) { button.disabled = ! valid; }
		output.className = 'uamm-save-state ' + ( valid ? 'is-ready' : 'is-blocked' );
		if ( valid ) {
			output.textContent = strings.contrastReady || 'Contrast checks pass.';
		} else if ( ! menuValid && ! groupValid ) {
			output.textContent = strings.allColorsContrastBlocked || 'Cannot save: fix contrast in Menu colors and Group colors.';
		} else if ( ! menuValid ) {
			output.textContent = strings.menuColorsContrastBlocked || 'Cannot save: fix contrast in Menu colors.';
		} else {
			output.textContent = strings.groupColorsContrastBlocked || 'Cannot save: fix contrast in Group colors.';
		}
	}

	function addGroup() {
		var list = document.getElementById( 'uamm-menu-list' );
		var row = document.createElement( 'li' );
		var id = uid( 'group' );
		var summary = document.createElement( 'div' );
		var drag = document.createElement( 'button' );
		var icon = document.createElement( 'span' );
		var title = document.createElement( 'strong' );
		var count = document.createElement( 'span' );
		var actions = document.createElement( 'span' );
		var moveUp = document.createElement( 'button' );
		var moveDown = document.createElement( 'button' );
		var collapse = document.createElement( 'button' );
		var disclosure = document.createElement( 'button' );
		var details = document.createElement( 'div' );
		var fields = document.createElement( 'div' );
		var members = document.createElement( 'ul' );
		if ( ! list ) { return; }
		row.className = 'uamm-menu-group';
		row.setAttribute( 'data-group-id', id );
		row.setAttribute( 'data-group-unsaved', 'true' );
		summary.className = 'uamm-menu-group__summary';
		drag.type = 'button';
		drag.className = 'uamm-drag';
		drag.setAttribute( 'aria-label', 'Drag group to reorder' );
		drag.innerHTML = '<span class="uamm-control-icon uamm-icon-drag" aria-hidden="true"></span>';
		icon.className = 'uamm-group-row-icon dashicons dashicons-category';
		icon.setAttribute( 'aria-hidden', 'true' );
		title.setAttribute( 'data-group-summary-label', '' );
		title.textContent = strings.newGroup || 'New group';
		count.className = 'uamm-group-count';
		count.textContent = '0 items';
		actions.className = 'uamm-row-actions';
		moveUp.type = 'button';
		moveUp.className = 'uamm-move-up';
		moveUp.setAttribute( 'aria-label', 'Move group up' );
		moveUp.innerHTML = '<span class="uamm-control-icon uamm-icon-move-up" aria-hidden="true"></span>';
		moveDown.type = 'button';
		moveDown.className = 'uamm-move-down';
		moveDown.setAttribute( 'aria-label', 'Move group down' );
		moveDown.innerHTML = '<span class="uamm-control-icon uamm-icon-move-down" aria-hidden="true"></span>';
		collapse.type = 'button';
		collapse.className = 'uamm-group-collapse';
		collapse.setAttribute( 'aria-expanded', 'true' );
		collapse.setAttribute( 'aria-controls', 'uamm-group-items-' + id );
		collapse.setAttribute( 'aria-label', ( strings.collapseGroup || 'Collapse %s group' ).replace( '%s', strings.newGroup || 'New group' ) );
		collapse.innerHTML = '<span class="uamm-control-icon uamm-icon-chevron" aria-hidden="true"></span>';
		disclosure.type = 'button';
		disclosure.className = 'uamm-group-disclosure';
		disclosure.setAttribute( 'aria-expanded', 'true' );
		disclosure.setAttribute( 'aria-label', 'Edit group settings' );
		disclosure.innerHTML = '<span class="uamm-control-icon uamm-icon-settings" aria-hidden="true"></span>';
		actions.appendChild( moveUp );
		actions.appendChild( moveDown );
		actions.appendChild( collapse );
		actions.appendChild( disclosure );
		summary.appendChild( drag );
		summary.appendChild( icon );
		summary.appendChild( title );
		summary.appendChild( count );
		summary.appendChild( actions );
		details.className = 'uamm-menu-group__details';
		var tablist = document.createElement( 'div' );
		var generalTab = document.createElement( 'button' );
		var appearanceTab = document.createElement( 'button' );
		var generalPanel = document.createElement( 'div' );
		var appearancePanel = document.createElement( 'div' );
		var generalId = 'uamm-group-items-' + id + '-general';
		var appearanceId = 'uamm-group-items-' + id + '-appearance';
		tablist.className = 'uamm-group-settings-tabs';
		tablist.setAttribute( 'role', 'tablist' );
		tablist.setAttribute( 'aria-label', 'Group settings' );
		generalTab.id = generalId + '-tab';
		generalTab.className = 'uamm-group-settings-tab is-active';
		generalTab.type = 'button';
		generalTab.setAttribute( 'role', 'tab' );
		generalTab.setAttribute( 'aria-selected', 'true' );
		generalTab.setAttribute( 'aria-controls', generalId );
		generalTab.textContent = 'General';
		appearanceTab.id = appearanceId + '-tab';
		appearanceTab.className = 'uamm-group-settings-tab';
		appearanceTab.type = 'button';
		appearanceTab.tabIndex = -1;
		appearanceTab.setAttribute( 'role', 'tab' );
		appearanceTab.setAttribute( 'aria-selected', 'false' );
		appearanceTab.setAttribute( 'aria-controls', appearanceId );
		appearanceTab.textContent = 'Appearance';
		tablist.appendChild( generalTab );
		tablist.appendChild( appearanceTab );
		details.appendChild( tablist );
		generalPanel.id = generalId;
		generalPanel.className = 'uamm-group-settings-panel';
		generalPanel.setAttribute( 'role', 'tabpanel' );
		generalPanel.setAttribute( 'aria-labelledby', generalTab.id );
		var detailsSection = groupSettingsSection( 'Group details', 'Set the label and icon shown in the admin menu.' );
		detailsSection.fields.appendChild( input( 'Group label', 'group-label', 'text', strings.newGroup || 'New group' ) );
		detailsSection.fields.appendChild( dashiconInput( 'Dashicon', 'group-icon', 'dashicons-category' ) );
		generalPanel.appendChild( detailsSection.section );
		var behaviorSection = groupSettingsSection( 'Group behavior', 'Choose the initial state for users who have not set their own preference.' );
		var check = groupCheckboxInput( 'Start collapsed', 'collapsed', 'Users can still open the group from the admin menu.' );
		var pinned = groupCheckboxInput( 'Pin this group open by default', 'pinned', 'Users can change the pin from the admin menu.' );
		behaviorSection.fields.className = 'uamm-group-state-options';
		behaviorSection.fields.appendChild( check );
		behaviorSection.fields.appendChild( pinned );
		generalPanel.appendChild( behaviorSection.section );
		details.appendChild( generalPanel );
		appearancePanel.id = appearanceId;
		appearancePanel.className = 'uamm-group-settings-panel';
		appearancePanel.hidden = true;
		appearancePanel.setAttribute( 'role', 'tabpanel' );
		appearancePanel.setAttribute( 'aria-labelledby', appearanceTab.id );
		fields.className = 'uamm-group-settings-sections';
		var openSection = groupSettingsSection( 'Open heading', 'Colors used while the group is expanded.' );
		openSection.fields.appendChild( groupColorInput( 'Background', 'background', '', groupStyle.background || '#053a8a' ) );
		openSection.fields.appendChild( groupColorInput( 'Text and icon', 'text_color', '', groupStyle.text_color || '#ffffff' ) );
		openSection.fields.appendChild( groupColorInput( 'Hover background', 'hover_background', '', groupStyle.hover_background || '#014cbc' ) );
		openSection.fields.appendChild( groupColorInput( 'Hover text and icon', 'hover_text_color', '', groupStyle.hover_text_color || '#ffffff' ) );
		openSection.fields.appendChild( groupColorInput( 'Border', 'border_color', '', groupStyle.border_color || '#ffffff' ) );
		var headingBorderCheck = groupCheckboxInput( 'Show heading border', 'show_heading_border', 'Uses the border color shown beside this option.' );
		headingBorderCheck.classList.add( 'uamm-group-border-toggle' );
		openSection.fields.appendChild( headingBorderCheck );
		fields.appendChild( openSection.section );
		var collapsedSection = groupSettingsSection( 'Collapsed heading', 'Colors used while the group is closed.' );
		collapsedSection.fields.appendChild( groupColorInput( 'Background', 'collapsed_background', '', groupStyle.collapsed_background || '#0056d6' ) );
		collapsedSection.fields.appendChild( groupColorInput( 'Text and icon', 'collapsed_text_color', '', groupStyle.collapsed_text_color || '#f0f0f1' ) );
		collapsedSection.fields.appendChild( groupColorInput( 'Hover background', 'collapsed_hover_background', '', groupStyle.collapsed_hover_background || '#2c3338' ) );
		collapsedSection.fields.appendChild( groupColorInput( 'Hover text and icon', 'collapsed_hover_text_color', '', groupStyle.collapsed_hover_text_color || '#ffffff' ) );
		fields.appendChild( collapsedSection.section );
		var panelSection = groupSettingsSection( 'Item panel', 'Colors used by top-level menu items inside this group.' );
		panelSection.fields.appendChild( groupColorInput( 'Background', 'panel_background', '', groupStyle.panel_background || '#474747' ) );
		panelSection.fields.appendChild( groupColorInput( 'Text and icon', 'panel_text_color', '', groupStyle.panel_text_color || '#f0f0f1' ) );
		panelSection.fields.appendChild( groupColorInput( 'Hover and active background', 'panel_hover_background', '', groupStyle.panel_hover_background || '#2c3338' ) );
		panelSection.fields.appendChild( groupColorInput( 'Hover and active text and icon', 'panel_hover_text_color', '', groupStyle.panel_hover_text_color || '#ffffff' ) );
		panelSection.fields.appendChild( groupColorInput( 'Border', 'panel_border_color', '', groupStyle.panel_border_color || '#72aee6' ) );
		var panelBorderCheck = groupCheckboxInput( 'Show item panel border', 'show_panel_border', 'Uses the border color shown beside this option.' );
		panelBorderCheck.classList.add( 'uamm-group-border-toggle' );
		panelSection.fields.appendChild( panelBorderCheck );
		fields.appendChild( panelSection.section );
		appearancePanel.appendChild( fields );
		var contrast = document.createElement( 'div' );
		contrast.className = 'uamm-contrast-status';
		contrast.setAttribute( 'data-group-contrast', '' );
		contrast.setAttribute( 'aria-live', 'polite' );
		appearancePanel.appendChild( contrast );
		details.appendChild( appearancePanel );
		var remove = removeButton();
		remove.classList.remove( 'uamm-remove' );
		remove.classList.add( 'uamm-remove-group' );
		remove.textContent = 'Remove group and keep its menu items';
		details.appendChild( remove );
		members.className = 'uamm-group-items';
		members.id = 'uamm-group-items-' + id;
		members.setAttribute( 'aria-label', 'Menu items in ' + ( strings.newGroup || 'New group' ) );
		row.appendChild( summary );
		row.appendChild( details );
		row.appendChild( members );
		list.appendChild( row );
		refreshGroupSelects();
		syncGroupAssignments();
		updateMenuSaveState();
		row.querySelector( '[data-group-field="label"]' ).focus();
	}

	function addCustom() {
		var list = document.getElementById( 'uamm-custom-list' );
		var row = document.createElement( 'div' );
		if ( ! list ) { return; }
		row.className = 'uamm-repeater';
		row.setAttribute( 'data-custom-id', uid( 'item' ) );
		row.appendChild( input( 'Label', 'custom-title', 'text', strings.newItem || 'New menu item' ) );
		row.appendChild( input( 'URL', 'custom-url', 'url', '' ) );
		row.appendChild( input( 'Parent menu slug', 'custom-parent', 'text', '' ) );
		row.appendChild( input( 'Capability', 'custom-capability', 'text', 'read' ) );
		row.appendChild( dashiconInput( 'Dashicon', 'custom-icon', 'dashicons-admin-links' ) );
		var mode = document.createElement( 'label' );
		var select = document.createElement( 'select' );
		mode.appendChild( document.createTextNode( 'Open in' ) );
		select.setAttribute( 'data-custom-field', 'open_mode' );
		[ [ 'same', 'Same window' ], [ 'new', 'New tab' ], [ 'iframe', 'Admin frame' ], [ 'none', 'Non-clickable' ] ].forEach( function ( choice ) {
			var option = document.createElement( 'option' ); option.value = choice[ 0 ]; option.textContent = choice[ 1 ]; select.appendChild( option );
		} );
		mode.appendChild( select );
		row.appendChild( mode );
		row.appendChild( removeButton() );
		list.appendChild( row );
	}

	function refreshGroupSelects() {
		var groups = Array.prototype.map.call( document.querySelectorAll( '#uamm-menu-list > [data-group-id]' ), function ( row ) {
			var label = row.querySelector( '[data-group-field="label"]' );
			return { id: row.getAttribute( 'data-group-id' ), label: label ? label.value : '', unsaved: row.getAttribute( 'data-group-unsaved' ) === 'true' };
		} ).filter( function ( group ) { return ! group.unsaved; } );
		document.querySelectorAll( '[data-field="group"]' ).forEach( function ( select ) {
			var current = select.value;
			while ( select.options.length > 1 ) { select.remove( 1 ); }
			groups.forEach( function ( group ) {
				if ( ! group.label ) { return; }
				var option = document.createElement( 'option' ); option.value = group.id; option.textContent = group.label; select.appendChild( option );
			} );
			select.value = current;
		} );
	}

	function updateGroupDropStates() {
		document.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( function ( group ) {
			var list = group.querySelector( ':scope > .uamm-group-items' );
			var itemCount = list ? list.querySelectorAll( ':scope > .uamm-menu-item' ).length : 0;
			var unsaved = group.getAttribute( 'data-group-unsaved' ) === 'true';
			var message = list ? list.querySelector( ':scope > .uamm-group-empty-message' ) : null;
			if ( ! list ) { return; }
			list.classList.toggle( 'is-empty', itemCount === 0 );
			list.classList.toggle( 'is-awaiting-save', unsaved );
			if ( itemCount > 0 ) {
				if ( message ) { message.remove(); }
				return;
			}
			if ( ! message ) {
				message = document.createElement( 'li' );
				message.className = 'uamm-group-empty-message';
				message.setAttribute( 'role', 'note' );
				list.appendChild( message );
			}
			message.classList.toggle( 'is-awaiting-save', unsaved );
			message.textContent = unsaved ? strings.saveGroupForDrop || "Save the menu profile to activate this group's drop area." : strings.dragItemsHere || 'Drag menu items here';
		} );
	}

	function syncGroupAssignments() {
		document.querySelectorAll( '.uamm-menu-item' ).forEach( function ( row ) {
			var group = row.parentElement && row.parentElement.classList.contains( 'uamm-group-items' ) ? row.parentElement.closest( '[data-group-id]' ) : null;
			var select = row.querySelector( '[data-field="group"]' );
			if ( select ) { select.value = group ? group.getAttribute( 'data-group-id' ) : ''; }
		} );
		document.querySelectorAll( '#uamm-menu-list > [data-group-id]' ).forEach( function ( group ) {
			var count = group.querySelectorAll( ':scope > .uamm-group-items > .uamm-menu-item' ).length;
			var output = group.querySelector( ':scope > .uamm-menu-group__summary .uamm-group-count' );
			if ( output ) { output.textContent = count + ( count === 1 ? ' item' : ' items' ); }
		} );
		updateGroupDropStates();
	}

	function moveItemToGroup( select ) {
		var row = select.closest( '.uamm-menu-item' );
		var list = document.getElementById( 'uamm-menu-list' );
		var group = select.value ? document.querySelector( '#uamm-menu-list > [data-group-id="' + CSS.escape( select.value ) + '"]' ) : null;
		if ( ! row || ! list ) { return; }
		if ( group ) {
			group.querySelector( ':scope > .uamm-group-items' ).appendChild( row );
		} else {
			list.appendChild( row );
		}
		syncGroupAssignments();
	}

	function value( row, selector ) {
		var field = row.querySelector( selector );
		if ( ! field ) { return ''; }
		return field.type === 'checkbox' ? field.checked : field.value.trim();
	}

	function serializeMenu() {
		var profile = { items: {}, order: [], submenus: {}, groups: [], custom_items: [], single_open: Boolean( value( document, '[data-profile-field="single_open"]' ) ) };
		document.querySelectorAll( '#uamm-menu-list .uamm-menu-item' ).forEach( function ( row ) {
			var slug = row.getAttribute( 'data-slug' );
			var safetyItem = row.getAttribute( 'data-safety-item' ) === '1';
			profile.order.push( slug );
			profile.items[ slug ] = {
				title: value( row, '[data-field="title"]' ),
				icon: value( row, '[data-field="icon"]' ),
				css_class: value( row, '[data-field="css_class"]' ),
				url: safetyItem ? '' : value( row, '[data-field="url"]' ),
				open_mode: safetyItem ? 'same' : value( row, '[data-field="open_mode"]' ),
				background: value( row, '[data-field="background"]' ),
				text_color: value( row, '[data-field="text_color"]' ),
				submenu_background: value( row, '[data-field="submenu_background"]' ),
				submenu_text_color: value( row, '[data-field="submenu_text_color"]' ),
				submenu_hover_background: value( row, '[data-field="submenu_hover_background"]' ),
				submenu_hover_text_color: value( row, '[data-field="submenu_hover_text_color"]' ),
				hidden: safetyItem ? false : Boolean( value( row, '[data-field="hidden"]' ) )
			};
			row.querySelectorAll( '[data-submenu-key]' ).forEach( function ( subrow ) {
				var key = subrow.getAttribute( 'data-submenu-key' );
				profile.items[ key ] = { title: value( subrow, '[data-subfield="title"]' ), parent: value( subrow, '[data-subfield="parent"]' ), hidden: Boolean( value( subrow, '[data-subfield="hidden"]' ) ) };
			} );
		} );
		document.querySelectorAll( '#uamm-menu-list [data-submenu-parent]' ).forEach( function ( list ) {
			var parent = list.getAttribute( 'data-submenu-parent' );
			var currentOrder = Array.from( list.querySelectorAll( ':scope > [data-submenu-key]' ) ).map( function ( row ) { return row.getAttribute( 'data-submenu-key' ); } );
			var originalOrder = [];
			try { originalOrder = JSON.parse( list.getAttribute( 'data-submenu-original-order' ) || '[]' ); } catch ( error ) { originalOrder = []; }
			if ( parent && JSON.stringify( currentOrder ) !== JSON.stringify( originalOrder ) ) {
				profile.submenus[ parent ] = currentOrder;
			}
		} );
		document.querySelectorAll( '#uamm-menu-list > [data-group-id]' ).forEach( function ( row ) {
			var id = row.getAttribute( 'data-group-id' );
			var label = value( row, '[data-group-field="label"]' );
			if ( ! label ) { return; }
			var items = Array.prototype.map.call( row.querySelectorAll( ':scope > .uamm-group-items > .uamm-menu-item' ), function ( item ) { return item.getAttribute( 'data-slug' ); } );
			var pinned = Boolean( value( row, '[data-group-field="pinned"]' ) );
			profile.groups.push( { id: id, label: label, icon: value( row, '[data-group-field="icon"]' ), collapsed: ! pinned && Boolean( value( row, '[data-group-field="collapsed"]' ) ), pinned: pinned, background: value( row, '[data-group-field="background"]' ), text_color: value( row, '[data-group-field="text_color"]' ), border_color: value( row, '[data-group-field="border_color"]' ), show_heading_border: Boolean( value( row, '[data-group-field="show_heading_border"]' ) ), hover_background: value( row, '[data-group-field="hover_background"]' ), hover_text_color: value( row, '[data-group-field="hover_text_color"]' ), collapsed_background: value( row, '[data-group-field="collapsed_background"]' ), collapsed_text_color: value( row, '[data-group-field="collapsed_text_color"]' ), collapsed_hover_background: value( row, '[data-group-field="collapsed_hover_background"]' ), collapsed_hover_text_color: value( row, '[data-group-field="collapsed_hover_text_color"]' ), panel_background: value( row, '[data-group-field="panel_background"]' ), panel_text_color: value( row, '[data-group-field="panel_text_color"]' ), panel_border_color: value( row, '[data-group-field="panel_border_color"]' ), show_panel_border: Boolean( value( row, '[data-group-field="show_panel_border"]' ) ), panel_hover_background: value( row, '[data-group-field="panel_hover_background"]' ), panel_hover_text_color: value( row, '[data-group-field="panel_hover_text_color"]' ), items: items } );
		} );
		document.querySelectorAll( '[data-custom-id]' ).forEach( function ( row ) {
			var custom = { id: row.getAttribute( 'data-custom-id' ) };
			row.querySelectorAll( '[data-custom-field]' ).forEach( function ( field ) { custom[ field.getAttribute( 'data-custom-field' ) ] = field.type === 'checkbox' ? field.checked : field.value.trim(); } );
			if ( custom.title ) { profile.custom_items.push( custom ); }
		} );
		document.getElementById( 'uamm-profile-json' ).value = JSON.stringify( profile );
	}

	function groupLabel( row ) {
		var label = row ? row.querySelector( '[data-group-summary-label]' ) : null;
		return label ? label.textContent.trim() : strings.newGroup || 'New group';
	}

	function closeMenuItemDetails( row ) {
		var disclosure = row ? row.querySelector( ':scope > .uamm-menu-item__summary .uamm-disclosure' ) : null;
		var details = row ? row.querySelector( ':scope > .uamm-menu-item__details' ) : null;
		if ( ! row || ! disclosure || ! details ) { return; }
		disclosure.setAttribute( 'aria-expanded', 'false' );
		details.hidden = true;
		row.classList.remove( 'is-open' );
	}

	function groupViewStorageKey() {
		var config = window.uammAdmin || {};
		return 'uamm-editor-group-view-' + String( config.userId || 0 ) + '-' + String( config.profileKey || 'default' );
	}

	function readGroupViewState() {
		try {
			var saved = JSON.parse( window.localStorage.getItem( groupViewStorageKey() ) || '{}' );
			return {
				alwaysCollapsed: Boolean( saved.alwaysCollapsed ),
				groups: saved.groups && typeof saved.groups === 'object' ? saved.groups : {}
			};
		} catch ( error ) {
			return { alwaysCollapsed: false, groups: {} };
		}
	}

	function saveGroupViewState() {
		try {
			window.localStorage.setItem( groupViewStorageKey(), JSON.stringify( groupViewState ) );
		} catch ( error ) {
			// The editor remains usable when browser storage is unavailable.
		}
	}

	function rememberGroupView( row, collapsed ) {
		var id = row ? row.getAttribute( 'data-group-id' ) : '';
		if ( ! id ) { return; }
		groupViewState.groups[ id ] = Boolean( collapsed );
		saveGroupViewState();
	}

	function setGroupCollapsed( row, collapsed, remember ) {
		var members = row ? row.querySelector( ':scope > .uamm-group-items' ) : null;
		var details = row ? row.querySelector( ':scope > .uamm-menu-group__details' ) : null;
		var toggle = row ? row.querySelector( '.uamm-group-collapse' ) : null;
		var disclosure = row ? row.querySelector( '.uamm-group-disclosure' ) : null;
		if ( ! row || ! members || ! toggle ) { return; }
		row.classList.toggle( 'is-collapsed', collapsed );
		members.hidden = collapsed;
		toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		toggle.setAttribute( 'aria-label', ( collapsed ? strings.expandGroup || 'Expand %s group' : strings.collapseGroup || 'Collapse %s group' ).replace( '%s', groupLabel( row ) ) );
		if ( collapsed && details ) {
			details.hidden = true;
			if ( disclosure ) { disclosure.setAttribute( 'aria-expanded', 'false' ); }
		}
		if ( remember !== false ) { rememberGroupView( row, collapsed ); }
	}

	function setAllGroupsCollapsed( collapsed ) {
		document.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( function ( row ) {
			setGroupCollapsed( row, collapsed, false );
			var id = row.getAttribute( 'data-group-id' );
			if ( id ) { groupViewState.groups[ id ] = Boolean( collapsed ); }
		} );
		saveGroupViewState();
	}

	function initializeGroupView() {
		var always = document.getElementById( 'uamm-always-collapse-groups' );
		groupViewState = readGroupViewState();
		if ( always ) {
			always.checked = groupViewState.alwaysCollapsed;
			always.addEventListener( 'change', function () {
				groupViewState.alwaysCollapsed = always.checked;
				if ( always.checked ) { setAllGroupsCollapsed( true ); } else { saveGroupViewState(); }
			} );
		}
		document.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( function ( row ) {
			var id = row.getAttribute( 'data-group-id' );
			var collapsed = groupViewState.alwaysCollapsed || Boolean( id && Object.prototype.hasOwnProperty.call( groupViewState.groups, id ) && groupViewState.groups[ id ] );
			setGroupCollapsed( row, collapsed, false );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var iconPickerButton = event.target.closest( '.uamm-open-dashicon-picker' );
		var iconChoice = event.target.closest( '[data-dashicon]' );
		var iconPickerClose = event.target.closest( '[data-dashicon-close]' );
		var groupSettingsTab = event.target.closest( '.uamm-group-settings-tab' );
		var disclosure = event.target.closest( '.uamm-disclosure' );
		var groupDisclosure = event.target.closest( '.uamm-group-disclosure' );
		var groupCollapse = event.target.closest( '.uamm-group-collapse' );
		var moveUp = event.target.closest( '.uamm-move-up' );
		var moveDown = event.target.closest( '.uamm-move-down' );
		var submenuMoveUp = event.target.closest( '.uamm-submenu-move-up' );
		var submenuMoveDown = event.target.closest( '.uamm-submenu-move-down' );
		var remove = event.target.closest( '.uamm-remove' );
		var removeGroup = event.target.closest( '.uamm-remove-group' );
		if ( iconPickerButton ) { openDashiconPicker( iconPickerButton ); return; }
		if ( iconChoice && dashiconTarget ) {
			dashiconTarget.value = iconChoice.getAttribute( 'data-dashicon' );
			dashiconTarget.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			updateIconPreview( dashiconTarget, dashiconTarget.value );
			closeDashiconPicker();
			return;
		}
		if ( iconPickerClose ) { closeDashiconPicker(); return; }
		if ( groupSettingsTab ) { activateGroupSettingsTab( groupSettingsTab, false ); return; }
		if ( disclosure ) {
			var row = disclosure.closest( '.uamm-menu-item' ); var details = row.querySelector( '.uamm-menu-item__details' ); var expanded = disclosure.getAttribute( 'aria-expanded' ) === 'true'; disclosure.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' ); details.hidden = expanded; row.classList.toggle( 'is-open', ! expanded );
		}
		if ( groupDisclosure ) { var groupRow = groupDisclosure.closest( '.uamm-menu-group' ); if ( groupRow.classList.contains( 'is-collapsed' ) ) { setGroupCollapsed( groupRow, false ); } var groupDetails = groupRow.querySelector( '.uamm-menu-group__details' ); var groupExpanded = groupDisclosure.getAttribute( 'aria-expanded' ) === 'true'; groupDisclosure.setAttribute( 'aria-expanded', groupExpanded ? 'false' : 'true' ); groupDetails.hidden = groupExpanded; }
		if ( groupCollapse ) { var collapseRow = groupCollapse.closest( '.uamm-menu-group' ); setGroupCollapsed( collapseRow, groupCollapse.getAttribute( 'aria-expanded' ) === 'true' ); }
		if ( moveUp ) { var upRow = moveUp.closest( '.uamm-menu-item, .uamm-menu-group' ); if ( upRow.previousElementSibling ) { upRow.parentNode.insertBefore( upRow, upRow.previousElementSibling ); moveUp.focus(); syncGroupAssignments(); } }
		if ( moveDown ) { var downRow = moveDown.closest( '.uamm-menu-item, .uamm-menu-group' ); if ( downRow.nextElementSibling ) { downRow.parentNode.insertBefore( downRow.nextElementSibling, downRow ); moveDown.focus(); syncGroupAssignments(); } }
		if ( submenuMoveUp ) { var submenuUpRow = submenuMoveUp.closest( '.uamm-submenu-row' ); if ( submenuUpRow.previousElementSibling ) { submenuUpRow.parentNode.insertBefore( submenuUpRow, submenuUpRow.previousElementSibling ); submenuMoveUp.focus(); updateMenuSaveState(); } }
		if ( submenuMoveDown ) { var submenuDownRow = submenuMoveDown.closest( '.uamm-submenu-row' ); if ( submenuDownRow.nextElementSibling ) { submenuDownRow.parentNode.insertBefore( submenuDownRow.nextElementSibling, submenuDownRow ); submenuMoveDown.focus(); updateMenuSaveState(); } }
		if ( remove ) { remove.closest( '.uamm-repeater' ).remove(); refreshGroupSelects(); }
		if ( removeGroup ) { var removedGroup = removeGroup.closest( '.uamm-menu-group' ); var removedGroupId = removedGroup.getAttribute( 'data-group-id' ); var root = document.getElementById( 'uamm-menu-list' ); removedGroup.querySelectorAll( ':scope > .uamm-group-items > .uamm-menu-item' ).forEach( function ( item ) { root.insertBefore( item, removedGroup ); } ); removedGroup.remove(); if ( removedGroupId ) { delete groupViewState.groups[ removedGroupId ]; saveGroupViewState(); } refreshGroupSelects(); syncGroupAssignments(); updateMenuSaveState(); }
	} );
	document.addEventListener( 'keydown', function ( event ) {
		var tab = event.target.closest( '.uamm-group-settings-tab' );
		if ( ! tab || ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) { return; }
		var tabs = Array.from( tab.closest( '.uamm-group-settings-tabs' ).querySelectorAll( '[role="tab"]' ) );
		var index = tabs.indexOf( tab );
		var targetIndex = index;
		event.preventDefault();
		if ( 'ArrowRight' === event.key ) { targetIndex = ( index + 1 ) % tabs.length; }
		if ( 'ArrowLeft' === event.key ) { targetIndex = ( index - 1 + tabs.length ) % tabs.length; }
		if ( 'Home' === event.key ) { targetIndex = 0; }
		if ( 'End' === event.key ) { targetIndex = tabs.length - 1; }
		activateGroupSettingsTab( tabs[ targetIndex ], true );
	} );

	document.addEventListener( 'input', function ( event ) {
		if ( dashiconPicker && event.target.matches( '#uamm-dashicon-search' ) ) { renderDashiconChoices( event.target.value ); }
		if ( event.target.matches( '.uamm-icon-field input[type="text"]' ) ) { updateIconPreview( event.target, event.target.value.trim() ); }
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( ! dashiconPicker || dashiconPicker.hidden ) { return; }
		if ( 'Escape' === event.key ) { event.preventDefault(); closeDashiconPicker(); return; }
		if ( 'Tab' !== event.key ) { return; }
		var focusable = Array.from( dashiconPicker.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled])' ) ).filter( function ( control ) { return control.offsetParent !== null; } );
		if ( ! focusable.length ) { return; }
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) { event.preventDefault(); last.focus(); }
		if ( ! event.shiftKey && document.activeElement === last ) { event.preventDefault(); first.focus(); }
	} );

	function initializeMenuSortables() {
		var Sortable = window.Sortable;
		var root = document.getElementById( 'uamm-menu-list' );
		if ( ! Sortable || ! root ) { return; }

		var savedGroupLists = Array.from( root.querySelectorAll( ':scope > .uamm-menu-group:not([data-group-unsaved="true"]) > .uamm-group-items' ) );
		var savedGroups = Array.from( root.querySelectorAll( ':scope > .uamm-menu-group:not([data-group-unsaved="true"])' ) );
		var lastSortPointer = { x: 0, y: 0 };
		var activeGroupDropList = null;
		var pendingGroupDropList = null;
		var groupActivationTimer = null;
		root.classList.add( 'uamm-menu-sortable' );
		savedGroupLists.forEach( function ( list ) { list.classList.add( 'uamm-menu-sortable' ); } );

		function exposeDropTargets( item ) {
			document.body.classList.add( 'uamm-is-sorting' );
			root.setAttribute( 'data-end-drop-label', strings.dropAtEnd || 'Drop after the last group or menu item' );
			if ( item.classList.contains( 'uamm-menu-group' ) ) { return; }
			savedGroupLists.filter( function ( list ) {
				return list.hidden && list.closest( '.uamm-menu-group' ).classList.contains( 'is-collapsed' );
			} ).forEach( function ( list ) {
				var group = list.closest( '.uamm-menu-group' );
				list.hidden = false;
				list.classList.add( 'uamm-collapsed-drop-zone' );
				list.setAttribute( 'data-drop-label', ( strings.dropIntoGroup || 'Drop into %s' ).replace( '%s', groupLabel( group ) ) );
			} );
		}

		function restoreDropTargets() {
			document.body.classList.remove( 'uamm-is-sorting' );
			root.removeAttribute( 'data-end-drop-label' );
			savedGroups.forEach( function ( group ) {
				group.classList.remove( 'is-item-drop-target' );
				group.removeAttribute( 'data-drop-label' );
			} );
			root.querySelectorAll( '.uamm-collapsed-drop-zone' ).forEach( function ( list ) {
				list.classList.remove( 'uamm-collapsed-drop-zone', 'is-sort-over' );
				list.removeAttribute( 'data-drop-label' );
				list.hidden = true;
			} );
			if ( groupActivationTimer ) { window.clearTimeout( groupActivationTimer ); }
			groupActivationTimer = null;
			pendingGroupDropList = null;
			activeGroupDropList = null;
		}

		function groupDropListAtPointer( related ) {
			var target = document.elementFromPoint( lastSortPointer.x, lastSortPointer.y );
			var relatedGroup = related && related.closest ? related.closest( '.uamm-menu-group:not([data-group-unsaved="true"])' ) : null;
			var group = relatedGroup || ( target ? target.closest( '.uamm-menu-group:not([data-group-unsaved="true"])' ) : null );
			if ( ! group || ! savedGroups.includes( group ) ) { return null; }

			var list = group.querySelector( ':scope > .uamm-group-items' );
			if ( ! group.classList.contains( 'is-collapsed' ) ) {
				return target.closest( '.uamm-group-items' ) === list ? list : null;
			}

			var box = list.getBoundingClientRect();
			var edgeBuffer = 8;
			if ( ! box || lastSortPointer.x < box.left + edgeBuffer || lastSortPointer.x > box.right - edgeBuffer || lastSortPointer.y < box.top + edgeBuffer || lastSortPointer.y > box.bottom - edgeBuffer ) { return null; }
			return ( target && target.closest( '.uamm-group-items' ) === list ) || ( related && related.closest && related.closest( '.uamm-group-items' ) === list ) ? list : null;
		}

		function updateGroupDropTarget( item, related ) {
			var list = item.classList.contains( 'uamm-menu-item' ) ? groupDropListAtPointer( related ) : null;

			function clearActiveTarget() {
				savedGroups.forEach( function ( group ) { group.classList.remove( 'is-item-drop-target' ); } );
				activeGroupDropList = null;
			}

			if ( list !== pendingGroupDropList ) {
				if ( groupActivationTimer ) { window.clearTimeout( groupActivationTimer ); }
				groupActivationTimer = null;
				pendingGroupDropList = list;
				clearActiveTarget();
				if ( list ) {
					var group = list.closest( '.uamm-menu-group' );
					if ( ! group.classList.contains( 'is-collapsed' ) ) {
						activeGroupDropList = list;
						group.classList.add( 'is-item-drop-target' );
					} else {
						groupActivationTimer = window.setTimeout( function () {
							if ( pendingGroupDropList !== list ) { return; }
							activeGroupDropList = list;
							group.classList.add( 'is-item-drop-target' );
						}, 180 );
					}
				}
			}

			return { active: activeGroupDropList === list ? list : null, hovered: list };
		}

		function rememberSortPointer( event ) {
			var pointerEvent = event.originalEvent || event;
			var clientX = Number( pointerEvent.clientX );
			var clientY = Number( pointerEvent.clientY );
			if ( ! Number.isFinite( clientX ) && Number.isFinite( Number( pointerEvent.pageX ) ) ) {
				clientX = Number( pointerEvent.pageX ) - window.scrollX;
			}
			if ( ! Number.isFinite( clientY ) && Number.isFinite( Number( pointerEvent.pageY ) ) ) {
				clientY = Number( pointerEvent.pageY ) - window.scrollY;
			}
			if ( Number.isFinite( clientX ) && Number.isFinite( clientY ) ) {
				lastSortPointer = { x: clientX, y: clientY };
			}
		}

		function placeMenuItemAtPointer( item, list ) {
			var nextItem = Array.from( list.querySelectorAll( ':scope > .uamm-menu-item' ) ).find( function ( candidate ) {
				var box = candidate.getBoundingClientRect();
				return candidate !== item && lastSortPointer.y < box.top + box.height / 2;
			} );
			var emptyMessage = list.querySelector( ':scope > .uamm-group-empty-message' );
			list.insertBefore( item, nextItem || emptyMessage || null );
		}

		function placeTopLevelItemAtPointer( item ) {
			var nextItem = Array.from( root.querySelectorAll( ':scope > .uamm-menu-item, :scope > .uamm-menu-group' ) ).find( function ( candidate ) {
				var box = candidate.getBoundingClientRect();
				return candidate !== item && lastSortPointer.y < box.top + box.height / 2;
			} );
			root.insertBefore( item, nextItem || null );
		}

		function sortableOptions( isRootList ) {
			return {
				animation: 150,
				bubbleScroll: true,
				chosenClass: 'uamm-drag-chosen',
				direction: 'vertical',
				dragClass: 'uamm-drag-active',
				draggable: isRootList ? '.uamm-menu-item, .uamm-menu-group' : '.uamm-menu-item',
				fallbackClass: 'uamm-drag-helper',
				fallbackOnBody: true,
				fallbackTolerance: 5,
				forceAutoScrollFallback: true,
				forceFallback: true,
				ghostClass: 'uamm-drag-placeholder',
				group: {
					name: 'uamm-menu-order',
					pull: true,
					put: isRootList ? true : function ( to, from, item ) {
						return ! item.classList.contains( 'uamm-menu-group' );
					}
				},
				handle: '.uamm-drag',
				invertSwap: false,
				scroll: true,
				scrollSensitivity: 100,
				scrollSpeed: 12,
				swapThreshold: 0.62,
				touchStartThreshold: 4,
				onStart: function ( event ) {
					rememberSortPointer( event );
					pendingGroupDropList = null;
					activeGroupDropList = null;
					event.item.classList.add( 'is-dragging' );
					exposeDropTargets( event.item );
					updateGroupDropTarget( event.item );
				},
				onMove: function ( event, originalEvent ) {
					rememberSortPointer( originalEvent || event );
					var groupDropTarget = updateGroupDropTarget( event.dragged, event.related );
					if ( event.to.classList.contains( 'uamm-group-items' ) && event.dragged.classList.contains( 'uamm-menu-group' ) ) { return false; }
					if ( event.to.classList.contains( 'uamm-group-items' ) && event.to !== groupDropTarget.active ) { return false; }
					if ( groupDropTarget.hovered && ! groupDropTarget.active ) { return false; }
					if ( groupDropTarget.active && event.to !== groupDropTarget.active ) { return false; }
					return true;
				},
				onEnd: function ( event ) {
					rememberSortPointer( event );
					var item = event.item;
					var currentGroupDropList = item.classList.contains( 'uamm-menu-item' ) ? groupDropListAtPointer() : null;
					var groupDropList = currentGroupDropList && currentGroupDropList === activeGroupDropList ? currentGroupDropList : null;
					if ( ! groupDropList && item.parentElement && item.parentElement.classList.contains( 'uamm-group-items' ) ) {
						placeTopLevelItemAtPointer( item );
					}
					var droppedGroup = groupDropList ? groupDropList.closest( '.uamm-menu-group' ) : null;
					var openDroppedGroup = Boolean( droppedGroup && droppedGroup.classList.contains( 'is-collapsed' ) );
					if ( groupDropList && item.parentElement !== groupDropList ) {
						placeMenuItemAtPointer( item, groupDropList );
					}
					item.classList.remove( 'is-dragging' );
					closeMenuItemDetails( item );
					restoreDropTargets();
					if ( openDroppedGroup ) {
						setGroupCollapsed( droppedGroup, false );
					}
					syncGroupAssignments();
					updateMenuSaveState();
				}
			};
		}

		Sortable.create( root, sortableOptions( true ) );
		savedGroupLists.forEach( function ( list ) { Sortable.create( list, sortableOptions( false ) ); } );
		root.querySelectorAll( '[data-submenu-parent]' ).forEach( function ( list ) {
			Sortable.create( list, {
				animation: 150,
				direction: 'vertical',
				draggable: '.uamm-submenu-row',
				fallbackClass: 'uamm-drag-helper',
				fallbackOnBody: true,
				fallbackTolerance: 5,
				forceFallback: true,
				ghostClass: 'uamm-drag-placeholder',
				handle: '.uamm-submenu-drag',
				scroll: true,
				scrollSensitivity: 100,
				scrollSpeed: 12,
				touchStartThreshold: 4,
				onEnd: updateMenuSaveState
			} );
		} );
	}

	var menuForm = document.getElementById( 'uamm-menu-form' ); if ( menuForm ) { menuForm.addEventListener( 'submit', function ( event ) {
		var invalidRow = null;
		var summary = document.getElementById( 'uamm-contrast-summary' );
		document.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( function ( row ) { if ( ! validateGroupContrast( row ) && ! invalidRow ) { invalidRow = row; } } );
		document.querySelectorAll( '#uamm-menu-list .uamm-menu-item' ).forEach( function ( row ) { if ( ! validateItemContrast( row ) && ! invalidRow ) { invalidRow = row; } } );
		if ( invalidRow ) {
			event.preventDefault();
			if ( summary ) { summary.hidden = false; summary.textContent = strings.contrastBlocked || 'Color changes were not saved. Fix the failing text contrast checks below.'; }
			var parentGroup = invalidRow.closest( '.uamm-menu-group' );
			if ( invalidRow.classList.contains( 'uamm-menu-item' ) && parentGroup && parentGroup.classList.contains( 'is-collapsed' ) ) { setGroupCollapsed( parentGroup, false ); }
			var disclosure = invalidRow.querySelector( invalidRow.classList.contains( 'uamm-menu-group' ) ? '.uamm-group-disclosure' : '.uamm-disclosure' );
			var details = invalidRow.querySelector( invalidRow.classList.contains( 'uamm-menu-group' ) ? '.uamm-menu-group__details' : '.uamm-menu-item__details' );
			if ( disclosure ) { disclosure.setAttribute( 'aria-expanded', 'true' ); }
			if ( details ) { details.hidden = false; }
			var appearanceTab = invalidRow.querySelector( '.uamm-group-settings-tab[aria-controls$="-appearance"]' );
			if ( appearanceTab ) { activateGroupSettingsTab( appearanceTab, false ); }
			var invalidInput = invalidRow.querySelector( '[aria-invalid="true"]' );
			if ( invalidInput ) { invalidInput.focus(); }
			return;
		}
		if ( summary ) { summary.hidden = true; summary.textContent = ''; }
		serializeMenu();
	} ); }
	var addGroupButton = document.getElementById( 'uamm-add-group' ); if ( addGroupButton ) { addGroupButton.addEventListener( 'click', addGroup ); }
	var addCustomButton = document.getElementById( 'uamm-add-custom' ); if ( addCustomButton ) { addCustomButton.addEventListener( 'click', addCustom ); }
	var collapseAllButton = document.getElementById( 'uamm-collapse-all-groups' ); if ( collapseAllButton ) { collapseAllButton.addEventListener( 'click', function () { setAllGroupsCollapsed( true ); } ); }
	var expandAllButton = document.getElementById( 'uamm-expand-all-groups' ); if ( expandAllButton ) { expandAllButton.addEventListener( 'click', function () { setAllGroupsCollapsed( false ); } ); }
	document.querySelectorAll( '[data-group-field="label"]' ).forEach( function ( field ) { field.addEventListener( 'input', refreshGroupSelects ); } );
	document.addEventListener( 'input', function ( event ) { if ( event.target.matches( '[data-group-field="label"]' ) ) { refreshGroupSelects(); } } );
	document.addEventListener( 'change', function ( event ) { if ( event.target.matches( '[data-field="group"]' ) ) { moveItemToGroup( event.target ); } } );
	document.addEventListener( 'change', function ( event ) {
		if ( ! event.target.matches( '[data-group-field="pinned"]' ) ) { return; }
		var row = event.target.closest( '.uamm-menu-group' );
		var collapsed = row ? row.querySelector( '[data-group-field="collapsed"]' ) : null;
		if ( collapsed ) {
			collapsed.disabled = event.target.checked;
			if ( event.target.checked ) { collapsed.checked = false; }
		}
	} );
	document.addEventListener( 'input', function ( event ) { if ( event.target.matches( '[data-group-field="label"]' ) ) { var group = event.target.closest( '[data-group-id]' ); var summary = group.querySelector( '[data-group-summary-label]' ); summary.textContent = event.target.value || strings.newGroup || 'New group'; group.querySelector( '.uamm-group-items' ).setAttribute( 'aria-label', 'Menu items in ' + summary.textContent ); var collapseToggle = group.querySelector( '.uamm-group-collapse' ); if ( collapseToggle ) { collapseToggle.setAttribute( 'aria-label', ( collapseToggle.getAttribute( 'aria-expanded' ) === 'true' ? strings.collapseGroup || 'Collapse %s group' : strings.expandGroup || 'Expand %s group' ).replace( '%s', summary.textContent ) ); } } } );
	document.addEventListener( 'input', function ( event ) {
		var row = event.target.closest( '.uamm-menu-group, [data-global-group-style]' );
		if ( event.target.matches( '[data-group-color-picker]' ) && row ) {
			var field = event.target.getAttribute( 'data-group-color-picker' );
			var text = row.querySelector( '[data-group-field="' + field + '"]' );
			if ( text ) { text.value = event.target.value; }
		}
		if ( event.target.matches( '.uamm-group-color-field [data-group-field]' ) && row ) {
			var picker = row.querySelector( '[data-group-color-picker="' + event.target.getAttribute( 'data-group-field' ) + '"]' );
			if ( picker && /^#[0-9a-f]{6}$/i.test( event.target.value.trim() ) ) { picker.value = event.target.value.trim(); updateColorSwatch( picker ); }
		}
		if ( row && ( event.target.matches( '[data-group-color-picker]' ) || event.target.matches( '.uamm-group-color-field [data-group-field]' ) || event.target.matches( '[data-group-field="show_heading_border"], [data-group-field="show_panel_border"]' ) ) ) {
			validateGroupContrast( row );
			if ( row.matches( '[data-global-group-style]' ) ) { updateGlobalSaveState(); } else { updateMenuSaveState(); }
		}
	} );
	document.addEventListener( 'input', function ( event ) {
		var row = event.target.closest( '.uamm-menu-item' );
		if ( ! row ) { return; }
		if ( event.target.matches( '[data-item-color-picker]' ) ) {
			var field = event.target.getAttribute( 'data-item-color-picker' );
			var text = row.querySelector( '[data-field="' + field + '"]' );
			if ( text ) { text.value = event.target.value; }
		}
		if ( event.target.matches( '.uamm-item-color-field [data-field]' ) ) {
			var picker = row.querySelector( '[data-item-color-picker="' + event.target.getAttribute( 'data-field' ) + '"]' );
			if ( picker && /^#[0-9a-f]{6}$/i.test( event.target.value.trim() ) ) { picker.value = event.target.value.trim(); updateColorSwatch( picker ); }
			if ( picker && ! event.target.value.trim() && event.target.placeholder ) { picker.value = event.target.placeholder; updateColorSwatch( picker ); }
		}
		if ( event.target.matches( '[data-item-color-picker], .uamm-item-color-field [data-field]' ) ) {
			validateItemContrast( row );
			updateMenuSaveState();
		}
	} );
	document.querySelectorAll( '[data-uamm-reset]' ).forEach( function ( form ) { form.addEventListener( 'submit', function ( event ) {
		var message = form.matches( '[data-uamm-appearance-reset]' ) ? strings.confirmAppearanceReset || 'Reset all Appearance settings to the plugin defaults?' : strings.confirmReset || 'Delete this profile?';
		if ( ! window.confirm( message ) ) { event.preventDefault(); return; }
		if ( form.matches( '[data-uamm-appearance-reset]' ) ) { appearanceFormDirty = false; }
	} ); } );
	var globalGroupStyleForm = document.querySelector( '[data-global-group-style-form]' ); if ( globalGroupStyleForm ) { globalGroupStyleForm.addEventListener( 'submit', function ( event ) {
		var style = globalGroupStyleForm.querySelector( '[data-global-group-style]' );
		var menuColors = globalGroupStyleForm.querySelector( '[data-admin-menu-colors]' );
		var summary = document.getElementById( 'uamm-global-contrast-summary' );
		var groupValid = style ? validateGroupContrast( style ) : true;
		var menuValid = menuColors ? validateAdminMenuContrast( menuColors ) : true;
		if ( ! groupValid || ! menuValid ) {
			event.preventDefault();
			var invalidSection = ( menuValid ? style : menuColors ).closest( '.uamm-inspector-section' );
			var invalidInput = invalidSection ? invalidSection.querySelector( '[aria-invalid="true"]' ) : null;
			if ( summary ) { summary.hidden = false; summary.textContent = strings.contrastBlocked || 'Color changes were not saved. Fix the failing contrast checks below.'; }
			if ( invalidSection && window.uammActivateAppearancePanel ) { window.uammActivateAppearancePanel( invalidSection ); }
			if ( invalidSection ) { invalidSection.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			if ( invalidInput ) { invalidInput.focus( { preventScroll: true } ); } else if ( summary ) { summary.focus( { preventScroll: true } ); }
			return;
		}
		if ( summary ) { summary.hidden = true; summary.textContent = ''; }
		appearanceFormDirty = false;
	} ); }
	document.querySelectorAll( '[data-uamm-clear]' ).forEach( function ( form ) { form.addEventListener( 'submit', function ( event ) { if ( ! window.confirm( strings.confirmClear || 'Clear every saved Menu Manager setting?' ) ) { event.preventDefault(); } } ); } );
	document.querySelectorAll( '.uamm-color-grid label' ).forEach( function ( label ) { var picker = label.querySelector( 'input[type="color"]' ); var text = label.querySelector( 'input[type="text"]' ); if ( picker && text ) { picker.addEventListener( 'input', function () { text.value = picker.value; updateGlobalSaveState(); } ); text.addEventListener( 'input', function () { if ( /^#[0-9a-f]{6}$/i.test( text.value ) ) { picker.value = text.value; updateColorSwatch( picker ); } else if ( ! text.value.trim() && text.placeholder ) { picker.value = text.placeholder; updateColorSwatch( picker ); } updateGlobalSaveState(); } ); } } );
	initializeAutoDismissNotices();
	function updateColorSwatch( picker ) {
		var swatch = picker.closest( '.uamm-color-swatch' );
		if ( swatch ) { swatch.style.setProperty( '--uamm-swatch-color', picker.value ); }
	}
	document.querySelectorAll( '.uamm-admin input[type="color"]' ).forEach( updateColorSwatch );
	document.addEventListener( 'input', function ( event ) {
		if ( event.target.matches( '.uamm-admin input[type="color"]' ) ) { updateColorSwatch( event.target ); }
	} );
	initializeAdminTabs();
	initializeAppearanceTabs();
	initializeRoleAccessControls();
	initializeAdminMenuColors();
	initializeMenuWidth();
	document.querySelectorAll( '[data-uamm-range]' ).forEach( function ( range ) {
		var field = range.getAttribute( 'data-uamm-range' );
		var number = document.querySelector( '[data-uamm-number="' + field + '"]' );
		if ( ! number ) { return; }
		range.addEventListener( 'input', function () { number.value = range.value; number.dispatchEvent( new Event( 'input', { bubbles: true } ) ); } );
		number.addEventListener( 'input', function () { range.value = number.value; updateRangeProgress( range ); } );
	} );
	document.querySelectorAll( '.uamm-admin input[type="range"]' ).forEach( function ( range ) {
		updateRangeProgress( range );
		range.addEventListener( 'input', function () { updateRangeProgress( range ); } );
	} );
	initializeAppearancePreview();
	initializeGroupView();
	initializeMenuSortables();
	document.querySelectorAll( '#uamm-menu-list [data-group-field="pinned"]' ).forEach( function ( pinned ) {
		var row = pinned.closest( '.uamm-menu-group' );
		var collapsed = row ? row.querySelector( '[data-group-field="collapsed"]' ) : null;
		if ( collapsed ) { collapsed.disabled = pinned.checked; }
	} );
	document.querySelectorAll( '#uamm-menu-list > .uamm-menu-group' ).forEach( validateGroupContrast );
	document.querySelectorAll( '#uamm-menu-list .uamm-menu-item' ).forEach( validateItemContrast );
	document.querySelectorAll( '[data-global-group-style]' ).forEach( validateGroupContrast );
	document.querySelectorAll( '[data-admin-menu-colors]' ).forEach( validateAdminMenuContrast );
	updateMenuSaveState();
	updateGlobalSaveState();
	syncGroupAssignments();
}() );
