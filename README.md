# Uplink Admin Menu Manager

Uplink Admin Menu Manager is a clean-room WordPress plugin for shaping the admin menu around the work each role or user performs.

The `1.0.2` release organizes group and menu item controls into consistent, keyboard-accessible General and Appearance tabs. The plugin also includes touch-enabled handle-based menu and submenu ordering, labels, visibility, icons, adjustable admin-menu width, group-heading spacing and corner controls, global, per-group, and expanded-submenu color controls, optional borders, custom links, protected user profiles, complete or look-and-feel-only JSON transfer, remembered editor group views, and clearly nested accessible groups.

Deactivating or deleting the plugin leaves WordPress data and saved Menu Manager configuration intact. Administrators can clear the stored configuration explicitly from the Import and export screen.

Special thanks to Tom Homer and the EtchWP community for their invaluable feedback.

## Profile priority

1. A user profile, when one exists.
2. The first matching role profile.
3. The default profile.

Deleting a role or user override restores inheritance from the default profile.

## Security model

Administrators with `manage_options` can edit site-wide settings and unprotected profiles. Administrators may also allow selected roles to edit only their own personal menu profile. All writes use WordPress nonces and server-side validation. Menu visibility never grants access to an admin page. Custom items require an explicit WordPress capability, and external new-tab links receive `noopener noreferrer`.

## Accessibility

Accordion headings use native buttons. They expose expanded state, work from the keyboard, keep the current section open, show visible focus, and respect reduced-motion preferences. The menu editor includes move buttons as a keyboard alternative to dragging.

## Third-party libraries

SortableJS 1.15.7 is bundled locally for menu reordering. It is licensed under the MIT License; the complete license is included at `assets/vendor/sortable-LICENSE.txt`.
