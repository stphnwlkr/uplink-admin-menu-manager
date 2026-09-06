( function () {
	'use strict';

	var config = window.uammRuntime || {};
	var groupDefaults = config.groupDefaults || {};
	var menu = document.getElementById( 'adminmenu' );

	if ( ! menu ) {
		document.body.classList.remove( 'uamm-menu-pending' );
		return;
	}

	var menuPositionFrame = 0;

	function refreshAdminMenuPosition() {
		if ( menuPositionFrame ) {
			window.cancelAnimationFrame( menuPositionFrame );
		}
		menuPositionFrame = window.requestAnimationFrame( function () {
			menuPositionFrame = 0;
			if ( window.jQuery ) {
				window.jQuery( document ).trigger( 'wp-pin-menu' );
			} else {
				window.dispatchEvent( new Event( 'resize' ) );
			}
		} );
	}

	function format( template, value ) {
		return String( template || '%s' ).replace( '%s', value );
	}

	function applyCustomLinks() {
		Object.keys( config.customLinks || {} ).forEach( function ( slug ) {
			var rule = config.customLinks[ slug ];
			var item = findBySlug( slug );
			var link = item ? item.querySelector( ':scope > a' ) : null;
			if ( ! link ) {
				return;
			}
			if ( rule.mode === 'none' ) {
				link.removeAttribute( 'href' );
				link.setAttribute( 'aria-disabled', 'true' );
				link.addEventListener( 'click', function ( event ) { event.preventDefault(); } );
				return;
			}
			if ( rule.url ) {
				link.href = rule.url;
			}
			if ( rule.mode === 'new' ) {
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
			}
		} );
	}

	function applySubmenuIndicators() {
		menu.querySelectorAll( ':scope > li.menu-top.wp-has-submenu > a.menu-top' ).forEach( function ( link ) {
			var item = link.parentElement;
			var submenu = item ? item.querySelector( ':scope > .wp-submenu' ) : null;
			if ( ! submenu || ! submenu.querySelector( 'a' ) || link.querySelector( ':scope > .uamm-submenu-indicator' ) ) {
				return;
			}
			var indicator = document.createElement( 'span' );
			indicator.className = 'uamm-submenu-indicator';
			indicator.setAttribute( 'aria-hidden', 'true' );
			link.appendChild( indicator );
		} );
	}

	function applyTouchSubmenuNavigation() {
		if ( ! document.body.classList.contains( 'uamm-touch-optimized' ) ) {
			return;
		}

		var armedItem = null;
		var lastTouchAt = 0;

		function rememberTouch( event ) {
			if ( ! event.pointerType || event.pointerType === 'touch' ) {
				lastTouchAt = Date.now();
			}
		}

		function closeOtherFlyouts( keep ) {
			menu.querySelectorAll( ':scope > li.menu-top.opensub' ).forEach( function ( item ) {
				if ( item !== keep ) {
					item.classList.remove( 'opensub' );
					var link = item.querySelector( ':scope > a.menu-top' );
					if ( link ) { link.setAttribute( 'aria-expanded', 'false' ); }
				}
			} );
		}

		menu.querySelectorAll( ':scope > li.menu-top.wp-has-submenu > a.menu-top' ).forEach( function ( link ) {
			var item = link.parentElement;
			var expanded = item && item.classList.contains( 'wp-has-current-submenu' ) &&
				! document.body.classList.contains( 'folded' ) && ! window.matchMedia( '(max-width: 960px)' ).matches;
			link.setAttribute( 'aria-haspopup', 'true' );
			link.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );

		if ( window.PointerEvent ) {
			menu.addEventListener( 'pointerdown', rememberTouch, true );
		} else {
			menu.addEventListener( 'touchstart', rememberTouch, { capture: true, passive: true } );
		}

		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( '#adminmenu > li.menu-top.wp-has-submenu > a.menu-top' ) : null;
			var isRecentTouch = Date.now() - lastTouchAt < 900;
			if ( ! link || ! isRecentTouch ) {
				if ( ! event.target.closest || ! event.target.closest( '#adminmenu' ) ) {
					closeOtherFlyouts( null );
					armedItem = null;
				}
				return;
			}

			var item = link.parentElement;
			var submenu = item ? item.querySelector( ':scope > .wp-submenu' ) : null;
			var submenuAlreadyVisible = item && item.classList.contains( 'wp-has-current-submenu' ) &&
				! document.body.classList.contains( 'folded' ) && ! window.matchMedia( '(max-width: 960px)' ).matches;
			if ( ! item || ! submenu || ! submenu.querySelector( 'a' ) || submenuAlreadyVisible ) {
				armedItem = null;
				return;
			}

			if ( armedItem === item ) {
				armedItem = null;
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			closeOtherFlyouts( item );
			item.classList.add( 'opensub' );
			link.setAttribute( 'aria-expanded', 'true' );
			armedItem = item;
		}, true );
	}

	function applyMenuIconColors() {
		menu.querySelectorAll( ':scope > li.menu-top > a.menu-top .wp-menu-image' ).forEach( function ( image ) {
			var pseudoStyle = window.getComputedStyle( image, '::before' );
			var pseudoMask = pseudoStyle.maskImage || pseudoStyle.webkitMaskImage || 'none';
			var iconImage = image.querySelector( 'img' );
			var mask = '';

			if ( pseudoMask !== 'none' ) {
				return;
			}

			if ( iconImage ) {
				var source = iconImage.currentSrc || iconImage.getAttribute( 'src' ) || '';
				if ( source ) {
					mask = 'url(' + JSON.stringify( source ) + ')';
				}
			} else {
				var backgroundImage = window.getComputedStyle( image ).backgroundImage;
				if ( backgroundImage && backgroundImage !== 'none' ) {
					mask = backgroundImage;
				}
			}

			if ( ! mask ) {
				return;
			}

			image.classList.add( 'uamm-menu-image-mask' );
			image.style.setProperty( '--uamm-menu-icon-mask', mask );
		} );
	}

	function findBySlug( slug ) {
		var stableClass = ( config.itemClasses || {} )[ slug ];
		var stableItem = stableClass ? menu.querySelector( ':scope > li.' + stableClass ) : null;
		if ( stableItem ) {
			return stableItem;
		}

		var candidates = menu.querySelectorAll( ':scope > li.uamm-item' );
		for ( var i = 0; i < candidates.length; i++ ) {
			var link = candidates[ i ].querySelector( ':scope > a' );
			if ( ! link ) {
				continue;
			}
			var href = link.getAttribute( 'href' ) || '';
			if ( href === slug || href.indexOf( 'page=' + slug ) !== -1 || decodeURIComponent( href ).indexOf( slug ) !== -1 ) {
				return candidates[ i ];
			}
		}
		return null;
	}

	function linkMatchesSlug( link, slug ) {
		var href = link ? link.getAttribute( 'href' ) || '' : '';
		return href === slug || href.indexOf( 'page=' + slug ) !== -1 || decodeURIComponent( href ).indexOf( slug ) !== -1;
	}

	function applySubmenuOrder() {
		Object.keys( config.submenus || {} ).forEach( function ( parentSlug ) {
			var parentItem = findBySlug( parentSlug );
			var submenu = parentItem ? parentItem.querySelector( ':scope > ul.wp-submenu, :scope > div.wp-submenu > ul.wp-submenu' ) : null;
			if ( ! submenu ) {
				return;
			}

			var orderedItems = [];
			( config.submenus[ parentSlug ] || [] ).forEach( function ( savedKey ) {
				var childSlug = String( savedKey ).indexOf( '::' ) === -1 ? String( savedKey ) : String( savedKey ).split( '::' ).slice( 1 ).join( '::' );
				var children = submenu.querySelectorAll( ':scope > li' );
				for ( var i = 0; i < children.length; i++ ) {
					var link = children[ i ].querySelector( ':scope > a' );
					if ( linkMatchesSlug( link, childSlug ) ) {
						orderedItems.push( children[ i ] );
						break;
					}
				}
			} );
			orderedItems.forEach( function ( item ) { submenu.appendChild( item ); } );

			var parentLink = parentItem.querySelector( ':scope > a.menu-top' );
			var canonicalChild = Array.prototype.some.call( submenu.querySelectorAll( ':scope > li > a' ), function ( link ) {
				return ( link.getAttribute( 'href' ) || '' ) === parentSlug;
			} );
			var firstItem = submenu.querySelector( ':scope > li:not(.wp-submenu-head)' );
			var firstLink = firstItem ? firstItem.querySelector( ':scope > a' ) : null;
			if ( parentLink && firstLink && ! canonicalChild ) {
				parentLink.href = firstLink.href;
			}
		} );
	}

	function applyGroups() {
		var controllers = [];

		function activeOutsideGroups() {
			return Array.prototype.some.call( menu.querySelectorAll( ':scope > li' ), function ( item ) {
				return ! item.classList.contains( 'uamm-group-heading' ) &&
					! item.classList.contains( 'uamm-group-member' ) &&
					( item.classList.contains( 'current' ) || item.classList.contains( 'wp-has-current-submenu' ) );
			} );
		}

		function persist( controller ) {
			try { window.localStorage.setItem( controller.storageKey, controller.collapsed ? '1' : '0' ); } catch ( error ) { /* Storage is optional. */ }
		}

		function persistPin( controller ) {
			try { window.localStorage.setItem( controller.pinStorageKey, controller.pinned ? '1' : '0' ); } catch ( error ) { /* Storage is optional. */ }
		}

		( config.groups || [] ).forEach( function ( group ) {
			var members = ( group.items || [] ).map( findBySlug ).filter( Boolean );
			if ( ! members.length ) {
				return;
			}

			var wrapper = document.createElement( 'li' );
			var button = document.createElement( 'button' );
			var icon = document.createElement( 'span' );
			var label = document.createElement( 'span' );
			var pinButton = document.createElement( 'button' );
			var pinIcon = document.createElement( 'span' );
			var chevron = document.createElement( 'span' );
			var storageKey = 'uamm-group-' + group.id;
			var pinStorageKey = 'uamm-group-pin-' + ( config.userId || 0 ) + '-' + group.id;
			var activeCollapseStorageKey = 'uamm-group-active-collapse-' + ( config.userId || 0 ) + '-' + group.id;
			var saved = null;
			var savedPin = null;
			var savedActiveCollapse = null;
			try { saved = window.localStorage.getItem( storageKey ); } catch ( error ) { saved = null; }
			try { savedPin = window.localStorage.getItem( pinStorageKey ); } catch ( error ) { savedPin = null; }
			try { savedActiveCollapse = window.localStorage.getItem( activeCollapseStorageKey ); } catch ( error ) { savedActiveCollapse = null; }
			var pinned = savedPin === null ? Boolean( group.pinned ) : savedPin === '1';
			var activeCollapse = savedActiveCollapse === '1';
			var collapsed = pinned ? false : saved === null ? Boolean( group.collapsed ) : saved === '1';

			wrapper.className = 'uamm-group-heading';
			wrapper.setAttribute( 'data-group-id', group.id );
			wrapper.classList.toggle( 'is-pinned', pinned );
			wrapper.classList.toggle( 'has-border', Boolean( group.show_heading_border ) );
			wrapper.style.setProperty( '--uamm-group-open-bg', group.background || groupDefaults.background || '#0071cc' );
			wrapper.style.setProperty( '--uamm-group-open-text', group.text_color || groupDefaults.text_color || '#ffffff' );
			wrapper.style.setProperty( '--uamm-group-heading-border', group.border_color || groupDefaults.border_color || '#ffffff' );
			wrapper.style.setProperty( '--uamm-group-heading-hover-bg', group.hover_background || groupDefaults.hover_background || '#0a66b2' );
			wrapper.style.setProperty( '--uamm-group-heading-hover-text', group.hover_text_color || groupDefaults.hover_text_color || '#ffffff' );
			wrapper.style.setProperty( '--uamm-group-collapsed-bg', group.collapsed_background || groupDefaults.collapsed_background || '#1d2327' );
			wrapper.style.setProperty( '--uamm-group-collapsed-text', group.collapsed_text_color || groupDefaults.collapsed_text_color || '#f0f0f1' );
			wrapper.style.setProperty( '--uamm-group-collapsed-hover-bg', group.collapsed_hover_background || groupDefaults.collapsed_hover_background || '#2c3338' );
			wrapper.style.setProperty( '--uamm-group-collapsed-hover-text', group.collapsed_hover_text_color || groupDefaults.collapsed_hover_text_color || '#ffffff' );
			button.type = 'button';
			button.className = 'uamm-group-toggle';
			icon.className = 'uamm-group-icon dashicons ' + ( group.icon || 'dashicons-category' );
			icon.setAttribute( 'aria-hidden', 'true' );
			label.className = 'uamm-group-label';
			label.textContent = group.label;
			pinButton.type = 'button';
			pinButton.className = 'uamm-group-pin-toggle';
			pinIcon.className = 'uamm-group-pin-icon';
			pinIcon.setAttribute( 'aria-hidden', 'true' );
			pinButton.appendChild( pinIcon );
			chevron.className = 'uamm-group-chevron dashicons dashicons-arrow-down-alt2';
			chevron.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( icon );
			button.appendChild( label );
			button.appendChild( chevron );
			wrapper.appendChild( button );
			wrapper.appendChild( pinButton );
			members[ 0 ].parentNode.insertBefore( wrapper, members[ 0 ] );

			function activeInside() {
				return members.some( function ( item ) { return item.classList.contains( 'current' ) || item.classList.contains( 'wp-has-current-submenu' ); } );
			}

			function setState( isCollapsed, force ) {
				var hasActiveItem = activeInside();
				collapsed = pinned ? false : hasActiveItem && ! force && ! activeCollapse ? false : isCollapsed;
				button.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				button.setAttribute( 'aria-label', format( pinned ? config.i18n.pinned : collapsed ? config.i18n.expand : config.i18n.collapse, group.label ) );
				button.setAttribute( 'aria-disabled', pinned ? 'true' : 'false' );
				wrapper.classList.toggle( 'is-collapsed', collapsed );
				members.forEach( function ( item, index ) {
					item.classList.add( 'uamm-group-member' );
					item.classList.toggle( 'has-border', Boolean( group.show_panel_border ) );
					item.style.setProperty( '--uamm-group-panel-border', group.panel_border_color || groupDefaults.panel_border_color || '#72aee6' );
					item.style.setProperty( '--uamm-group-panel-bg', group.panel_background || groupDefaults.panel_background || '#1d2327' );
					item.style.setProperty( '--uamm-group-panel-text', group.panel_text_color || groupDefaults.panel_text_color || '#f0f0f1' );
					item.style.setProperty( '--uamm-group-panel-hover-bg', group.panel_hover_background || groupDefaults.panel_hover_background || '#2c3338' );
					item.style.setProperty( '--uamm-group-panel-hover-text', group.panel_hover_text_color || groupDefaults.panel_hover_text_color || '#ffffff' );
					item.classList.toggle( 'uamm-group-member--first', 0 === index );
					item.classList.toggle( 'uamm-group-member--last', members.length - 1 === index );
					item.classList.toggle( 'uamm-group-member--hidden', collapsed );
				} );
				refreshAdminMenuPosition();
			}

			function persistActiveCollapse() {
				try { window.localStorage.setItem( activeCollapseStorageKey, activeCollapse ? '1' : '0' ); } catch ( error ) { /* Storage is optional. */ }
			}

			function updatePinState() {
				wrapper.classList.toggle( 'is-pinned', pinned );
				pinButton.setAttribute( 'aria-pressed', pinned ? 'true' : 'false' );
				pinButton.setAttribute( 'aria-label', format( pinned ? config.i18n.unpin : config.i18n.pin, group.label ) );
			}

			var controller = {
				activeInside: activeInside,
				get collapsed() { return collapsed; },
				get pinned() { return pinned; },
				pinStorageKey: pinStorageKey,
				setState: setState,
				storageKey: storageKey
			};
			controllers.push( controller );

			button.addEventListener( 'click', function () {
				if ( pinned ) { return; }
				if ( collapsed && config.singleOpen ) {
					controllers.forEach( function ( other ) {
						if ( other !== controller && ! other.pinned ) {
							other.setState( true, true );
							persist( other );
						}
					} );
				}
				activeCollapse = ! collapsed && activeInside();
				persistActiveCollapse();
				setState( ! collapsed, true );
				persist( controller );
			} );
			pinButton.addEventListener( 'click', function () {
				pinned = ! pinned;
				updatePinState();
				if ( pinned ) {
					activeCollapse = false;
					persistActiveCollapse();
					setState( false, true );
					persist( controller );
				} else {
					if ( config.singleOpen && ! collapsed ) {
						controllers.forEach( function ( other ) {
							if ( other !== controller && ! other.pinned ) {
								other.setState( true, true );
								persist( other );
							}
						} );
					}
					setState( collapsed, true );
				}
				persistPin( controller );
			} );
			updatePinState();
			setState( collapsed );
		} );

		if ( activeOutsideGroups() ) {
			controllers.forEach( function ( controller ) {
				if ( ! controller.pinned ) {
					controller.setState( true, true );
					persist( controller );
				}
			} );
		} else if ( config.singleOpen ) {
			var keeper = controllers.find( function ( controller ) { return ! controller.pinned && controller.activeInside() && ! controller.collapsed; } ) || controllers.find( function ( controller ) { return ! controller.pinned && ! controller.collapsed; } );
			controllers.forEach( function ( controller ) {
				if ( controller !== keeper && ! controller.pinned ) {
					controller.setState( true, true );
					persist( controller );
				}
			} );
		}
	}

	try {
		applySubmenuOrder();
		applyCustomLinks();
		applySubmenuIndicators();
		applyGroups();
		applyMenuIconColors();
		applyTouchSubmenuNavigation();
	} finally {
		document.body.classList.remove( 'uamm-menu-pending' );
	}
}() );
