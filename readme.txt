=== Uplink Admin Menu Manager ===
Contributors: stphnwlkr
Tags: admin menu, accessibility, menu groups, menu editor, dashboard
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize WordPress admin menus with role-aware profiles and accessible collapsible groups.

== Description ==

Uplink Admin Menu Manager provides a visual editor for organizing the WordPress admin menu. Create a default menu, add role or user profiles, and arrange the navigation around the work each person performs.

The plugin can:

* Reorder, rename, hide, and recolor top-level admin menu items and their expanded submenus.
* Reorder, rename, hide, and move submenu items.
* Place menu items inside accessible collapsible groups.
* Keep one unpinned group open at a time or let users control each group independently.
* Add custom links and non-clickable headings.
* Adjust the desktop admin menu width.
* Hide selected entries from the Plugins screen by role.
* Protect selected user accounts from profile-management actions.
* Export or import the complete configuration or only its visual settings.

Menu visibility changes navigation only. WordPress roles and capabilities remain the authority for access to admin screens and actions.

Special thanks to Tom Homer and the EtchWP community for their invaluable feedback.

== Installation ==

1. Install the plugin ZIP through Plugins > Add Plugin, or upload the `uplink-admin-menu-manager` folder to `/wp-content/plugins/`.
2. Activate Uplink Admin Menu Manager.
3. Open Menu Manager in the WordPress admin menu.
4. Arrange the default profile, then add role or user profiles when needed.
5. Save the profile and test it with an account assigned to that profile.

== Usage ==

= Menu profiles =

The default profile applies to all users. A user profile overrides a role profile, and a role profile overrides the default profile. Deleting an override restores inheritance from the default profile.

= Groups =

Choose Add group to menu, name the group, and move menu items into it by dragging them or using each item's Group selector. Group headings use native buttons and expose their expanded state to assistive technology.

Drag from the handle at the left of an item. An item can be dropped directly on a collapsed group heading, and Menu Manager itself can be placed inside a group while remaining protected from hiding or redirection.

= Visibility and access =

Hiding a menu item removes it from navigation. It does not block its URL. Use WordPress capabilities to control access to admin screens.

= Import and export =

A complete export includes profiles, menu order, groups, appearance settings, and access rules. A look-and-feel export contains presentation settings only. A visual import does not replace menu structure or access rules.

== Frequently Asked Questions ==

= Do hidden menu items block direct access? =

No. Menu visibility is presentation, not authorization. Use WordPress roles and capabilities to control access.

= Are accordion groups accessible? =

Yes. Group headings are native buttons with `aria-expanded`, visible keyboard focus, and reduced-motion support. The group containing the current screen opens automatically unless that user manually collapses it.

= Does the menu editor remember collapsed groups? =

Yes. The editor remembers each group's view state for the current user, profile, and browser. The Group view controls also include an option to always load editor groups collapsed.

= What data does the plugin store? =

The plugin stores menu profiles and site-wide appearance or visibility settings in the WordPress options table. Per-user accordion and editor-view preferences are stored in that user's browser. The plugin does not send configuration or personal data to an external service.

= What happens when I deactivate or delete the plugin? =

WordPress returns to its normal admin menu. Saved configuration remains available if the plugin is installed again. Use Import and export > Clear stored configuration before removal if you want to delete the saved settings.

== Screenshots ==

1. Arrange WordPress menu items and accessible groups in the visual menu editor.
2. Adjust menu dimensions, touch targets, group spacing, and heading typography with a live preview.
3. Set global admin menu and plugin submenu colors with live contrast checks.
4. Define the default colors for open and collapsed group headings and item panels.
5. Choose which roles may edit personal menu profiles and which menu other roles receive.

== Security and permissions ==

Administrators with the `manage_options` capability can change site-wide settings and any unprotected menu profile. Administrators may also allow selected roles to edit only their own personal menu profile. Forms use WordPress nonces, submitted values are validated and sanitized, and rendered values are escaped for their output context. Menu visibility never grants access to an admin screen.

== Third-party libraries ==

SortableJS 1.15.7 is bundled locally for menu reordering. SortableJS is copyright the SortableJS contributors and licensed under the MIT License. Its license is included at `assets/vendor/sortable-LICENSE.txt`.

== Changelog ==

= 1.1.0 =
* Fixed custom menu items missing from the sortable menu editor so they can be reordered and assigned to groups.
* Replaced custom-item capability rules with role visibility, including registered custom roles and an Administrator default for existing items.
* Moved custom-item controls below the main menu and collapsed each item into a compact editor row.
* Replaced the parent menu identifier field with a menu selector.
* Warned before leaving any changed settings screen without saving.

= 1.0.4 =
* Added drag-and-drop and keyboard controls for reordering WordPress and plugin submenu items.
* Preserved the native submenu order unless an administrator explicitly changes it.
* Kept newly registered submenu items in their native positions and reapplied custom orders after late menu changes.

= 1.0.3 =
* Reorganized site-wide Appearance settings into Layout, Group headings, Menu colors, and Group colors tabs.
* Split main-menu and plugin-flyout colors into clearly labeled groups without changing saved values or defaults.
* Added keyboard navigation and tab-specific guidance when a color contrast check prevents saving.

= 1.0.2 =
* Organized group and menu item controls into consistent General and Appearance tabs.
* Grouped appearance controls by menu state and kept contrast results with the colors they validate.
* Added keyboard navigation to the new settings tabs.

= 1.0.1 =
* Fixed the block editor overlapping a custom-width admin menu when Fullscreen mode is off.

= 1.0.0 =
* Updated the bundled SortableJS library to version 1.15.7.
* Fixed group labels overflowing WordPress's automatically folded sidebar.
* Added touch-enabled menu reordering and smoother group-drop behavior.
* Added group heading margin and corner-radius controls with flush, square defaults.
* Made the settings layout respond to narrow split-screen panels and added an easier end-of-list drop target.
* Keep collapsed groups at a stable height while dragging and distinguish group-heading drops from between-group placement.
* Expose closed-group drop areas together while keeping their edges unambiguous for between-group ordering.
* Require a brief intentional hover before a closed group accepts an item, preventing edge flicker and accidental grouping.
* Add optional 44-pixel touch targets with centered group pin controls.

* Initial public release.
* Place Menu Manager at the bottom of WordPress's native admin menu until a saved profile moves it.
* Add a rotated custom drag handle and an icon key beside the menu editor.
* Use a sliders icon for group settings controls.
* Show each plugin's registered image icon in the menu editor instead of a generic gear.
* Use a color-aware list layout icon for the Menu Manager admin entry.
* Make the full saved group card a highlighted drop target while menu items are moving.
* Open a collapsed group's item panel after an item is dropped into it.
* Match fresh menu and submenu editor order to WordPress's rendered numeric positions.
* Use locally bundled SortableJS for smoother bidirectional auto-scrolling and stable dragging between the main menu and groups, including collapsed-group drop targets.
* Add contrast-validated appearance controls for expanded submenus.
* Add global contrast-validated submenu hover and focus colors.
* Adjust group heading typography, spacing, and corner shape.
* Preview Appearance changes in the admin menu before saving.
* Allow the protected Menu Manager entry to be placed inside a group.
* Add searchable Dashicons pickers with live previews beside icon fields.
* Add custom move and circular disclosure icons with state-aware rotation.
* Add profile-based menu and submenu organization.
* Add accessible collapsible menu groups with user-controlled states and pins.
* Add global and per-group appearance controls with contrast validation.
* Add role-level plugin-list visibility and protected-user controls.
* Add complete and look-and-feel-only JSON transfer.

== Upgrade Notice ==

= 1.1.0 =

Custom-item visibility now uses roles instead of capabilities. Existing custom items without a role setting default to Administrator.

= 1.0.4 =

Adds reliable, accessible submenu reordering without changing untouched WordPress or plugin menus.

= 1.0.3 =

Makes site-wide Appearance settings easier to find and explains exactly where a failing contrast check needs attention.

= 1.0.2 =

Makes group and menu item settings easier to scan with keyboard-accessible General and Appearance tabs.

= 1.0.1 =

Keeps the block editor beside custom-width admin menus when Fullscreen mode is off.

= 1.0.0 =

Initial public release.
