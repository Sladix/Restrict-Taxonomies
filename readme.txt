=== Restrict Taxonomies ===
Contributors: sladix, mmuro
Tags: restrict, admin, administration, cms, categories, category, taxonomies, taxonomy
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=sladix%40gmail%2ecom&lc=FR&item_name=Sladix&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Requires at least: 3.1
Tested up to: 4.9
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin restrict the taxonomies terms that users can view, add, and edit in the admin panel.

== Description ==

Based on the Restrict Categories plugin, this plugin allows you to restrict the taxonomies terms that users or roles can use when they publish content. It also provide front end restriction for non logged users.

== Installation ==

1. Upload the restrict-taxonomies folder to to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the plugin in Settings > Restrict Taxonomies
1. Choose the taxonomies *AND* the corresponding post types
1. Activate (or don't, as you wish) the front end rescrictions

== Frequently Asked Questions ==

*Does this plugin also works for page taxonomies ?*
Yes, the page post type will always be handled, therefore, it doesn't appear in the post types and taxonomies settings

== Screenshots ==

1. The settings are located in Settings > Restrict Taxonomies
2. First choose the post types and the taxonomies to handle
3. Select the categories allowed for a role or a user (note that user options override role options)
4. Should the plugin restrict taxonomies in the front end ?

== ToDo List ==
1. Prevent the user from accessing the single view of a CPT that is in a disallowed taxonomy

== Changelog ==

= 1.3.1 =
* Refactored the user tab in order to handle large amount of users

= 1.3.0 =
* Fixed warnings on the plugin activation thanks to Gavin
* Fixed bug when only a empty taxonomy is allowed

= 1.2.9 =
* Added support for the latest vession of Geodirectory
* Fixed a bug that occurs when multiples CPT and taxonomies are on a same page
* Code clean up

= 1.2.8 =
* Added Dokan Dashboard support
* Changed front hook to pre_get_posts instead of init

= 1.2.7 =
* Fixed warnings for frontend options

= 1.2.6 =
* Fixed warnings for frontend options

= 1.2.5 =
* Fixed warnings reported by @swinggraphics

= 1.2 =
* Added page taxonomies support

= 1.1 =
* Added front end restrictions and settings

= 1.0 =
* Initial release
