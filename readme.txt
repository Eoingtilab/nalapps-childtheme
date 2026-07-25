=== NalApps Child Theme ===
Contributors: nalapps
Tags: child theme, theme, developer tools, lightweight
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and activate a child theme for the currently active WordPress theme with one click.

== Description ==

NalApps Child Theme is a tiny, settings-free utility for creating a child theme from the currently active parent theme.

Features:

* One-click child theme creation
* Automatic child theme activation after confirmation
* Redirects to the creator screen after plugin activation
* Parent screenshot copy when available
* Avoids duplicate child stylesheet loading
* Clear file-permission error messages
* No settings
* No ads
* No tracking
* No external API calls

After activation, review the active theme and click **Create and Activate Child Theme**.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New.
2. Activate **NalApps Child Theme**.
3. Review the active parent theme on the creator screen.
4. Click **Create and Activate Child Theme**.

== Frequently Asked Questions ==

= Does activation immediately create a child theme? =

No. Activation opens the creator screen. The child theme is created only after an administrator clicks the creation button.

= Does it work with any theme? =

It is designed to work with standard WordPress themes that support child themes.

= Does it change the parent theme? =

No. It creates a new child theme directory and leaves the parent theme unchanged.

= What happens if a child theme is already active? =

The plugin displays a message and does not create another theme.

= Does it collect data? =

No. It does not collect or transmit any data.

== Changelog ==

= 1.0.2 =

* Redirected administrators to the creator screen after plugin activation.
* Clarified that activation alone does not create a child theme.
* Added writable-directory validation and complete-write verification.
* Added the generated child theme directory name to the success message.
* Improved theme cache refresh and custom theme-root compatibility.

= 1.0.1 =

* Prevented duplicate stylesheet loading on themes such as GeneratePress.
* Improved compatibility with custom theme roots.
* Added a direct action link on the Plugins screen.
* Added the NalApps product update URI.

= 1.0.0 =

* Initial public release.
* Added one-click child theme creation and activation.
