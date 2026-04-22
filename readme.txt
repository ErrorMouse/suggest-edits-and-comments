=== Suggest Edits and Comments ===
Contributors:       nmtnguyen56
Donate link:        https://chout.id.vn/donate
Tags:               suggest edits, comments, inline comments, feedback, inline feedback
Requires at least:  5.2
Tested up to:       6.9
Stable tag:         1.0
Requires PHP:       7.2
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Allows users to highlight text within posts to easily submit comments and suggested edits.

== Description ==

Suggest Edits and Comments provides a professional inline commenting and interactive experience. Readers can highlight any text snippet within a post to trigger a tooltip button, allowing them to leave contextual suggestions, suggest edits, or discuss specific terms accurately.

Key features include:
* **Highlight to Suggest**: Select text to trigger a "Suggest Edit" tooltip.
* **Accurate Text Fragments Positioning**: Automatically generates URLs pointing exactly to the highlighted text. The contextual extraction algorithm ensures that the target location is never missed, even in long articles with duplicate content.
* **Display Control**: Choose exactly where the feature is active by selecting allowed post types (Posts, Pages, Custom Post Types) and defining custom CSS target selectors to match any theme structure perfectly.
* **Flexible Permissions**: Easily exclude specific user roles (or Guests) from highlighting text and submitting suggested edits. 
* **Robust Security & Anti-Spam**: 
    - Built-in Google reCAPTCHA v3 (invisible) integration.
    - 30-second cooldown limit between submissions.
    - Maximum daily suggested edits limit for each account (or IP address for guests).
* **Visual Interface Customization**: Easily change background colors, border colors, hover colors, and button text directly in the Settings page.
* **Optimized User Experience (UX)**: Uses a modern, smooth Toast Message notification.
* **Real-time Widget**: Provides an AJAX-powered Widget to display a list of the latest suggested edits without reloading the page, complete with friendly time-ago formats (e.g., "Just now", "5 mins ago").
* **Compatibility**: Perfectly integrates the quote box display with the default WordPress commenting system and wpDiscuz.
* **Clean Uninstall**: Provides options to securely wipe all data (comments, metadata, settings) when uninstalling the plugin, helping to optimize your Database.

== External Services ==

This plugin utilizes a third-party service, Google reCAPTCHA v3, to protect the suggestion form from spam and bot abuse.

If enabled by the website administrator in the plugin settings, this plugin will load the Google reCAPTCHA JavaScript API (`https://www.google.com/recaptcha/api.js`) and send verification requests to Google (`https://www.google.com/recaptcha/api/siteverify`) whenever a user submits a suggested edit.

* **What data is sent and when:** When a user submits the form, hardware and software information, such as device and application data, IP address, and the results of integrity checks are sent to Google to determine whether the user is a human or a bot.
* **Service Provider:** Google LLC.
* **Google Privacy Policy:** [https://policies.google.com/privacy](https://policies.google.com/privacy)
* **Google Terms of Service:** [https://policies.google.com/terms](https://policies.google.com/terms)

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory of your website.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > Suggest Edits and Comments** in the Admin dashboard to set up permissions, enter your Google reCAPTCHA v3 API Keys, and configure colors.
4. (Optional) Go to Appearance > Widgets and drag & drop the 'Suggest Edits History' widget into your Sidebar or Footer to display the suggestion history.

== Screenshots ==

1. "Suggest Edit" button displayed after highlighting text.
2. Popup for entering suggested edits.
3. User permissions setup.
4. Allowed post types for suggested edits.
5. Usage limits setup.
6. Google reCAPTCHA v3 configuration.
7. Color customization.
8. Uninstall options.
9. Widget setup.
10. Widget display.
11. Referenced text displayed in the comments admin page.

== Frequently Asked Questions ==

= Can I disable reCAPTCHA? =
Yes, you can easily enable or disable Google reCAPTCHA v3 with a simple checkbox in the plugin's Settings page.

= Can I allow guests (not logged in) to submit suggested edits? =
Absolutely. You just need to go to Settings and make sure the "Guest (Not logged in)" role is NOT excluded. The system will use their IP address to calculate anti-spam limits.

= Will my suggested edits be lost when I delete the plugin? =
By default, NO, to ensure your data remains safe. However, if you truly want to wipe everything, go to the plugin's Settings and check the "Delete all comments submitted via this plugin" box under the Uninstall Options section before deleting the plugin.

== Changelog ==

= 1.0 =
* Initial Release.
* Text highlighting and Text Fragments storage feature.
* Anti-Spam system, Rate Limiting, and Google reCAPTCHA v3 integration.
* Custom Admin dashboard for UI colors, excluded user roles, allowed post types, and target selectors.
* Real-time AJAX suggested edits history widget.

== Upgrade Notice ==

= 1.0 =
This is the first version of the plugin. Enjoy it ~

== Support ==

If you have any issues or suggestions, please use the plugin's support forum on WordPress.org or contact the author via their Author URI.

== Donations ==

If you find this plugin useful and would like to support its development, please consider making a [donation](https://chout.id.vn/donate). Thank you!