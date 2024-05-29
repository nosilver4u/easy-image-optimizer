=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 6.3
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 3.9.1
License: GPLv3

Easily speed up your website to better connect with your visitors. Properly compress and size/scale images. Includes lazy load and WebP auto-convert.

== Description ==

Want to get more visitors and customers? Easy Image Optimizer allows you to quickly and easily speed up your website so that you can connect with more people.
One-click activation enables you to optimize your images by efficiently encoding and properly sizing images. It also includes lazy loading to defer offscreen images and automatic WebP conversion for even more speed.

Easy Image Optimizer will optimize your site automatically by replacing all your image URLs with cloud-based URLs to deliver your content as fast as possible.
Beyond image optimization, it will also speed up the delivery of your CSS, JS (JavaScript), and font resources, by minifying them and delivering them through a speedy CDN (Content Delivery Network).

= Support =

If you need assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/).
The Easy Image Optimizer is developed at https://github.com/nosilver4u/easy-image-optimizer

You may report security issues through our Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/easy-image-optimizer)

= Translations =

If you would like to help translate this plugin (new or existing translations), you can do so here: https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer
To receive updates when new strings are available for translation, you can signup here: https://ewww.io/register/

== Installation ==

1. Install the plugin through the built-in WordPress plugin installer, or upload the "easy-image-optimizer" plugin to your /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Start a free trial subscription for Easy Image Optimizer at https://ewww.io/easy/
1. Link your site at https://ewww.io/manage-sites/
1. Visit the settings page to complete activation.
1. Done!

== Frequently Asked Questions ==

= Does the plugin replace existing images? =

No, all optimization is performed transparently on our network of cloud servers, and your image URLs are automatically updated to point to the optimized images.

= What if something breaks? =

Don't panic, just disable the plugin and [contact us](https://ewww.io/contact-us/). All changes made by the plugin are automatically reverted when you disable the plugin.

== Screenshots ==

1. Plugin settings page before activation.
2. Plugin settings when active.

== Changelog ==

* If you would like to help translate this plugin in your language, get started here: https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer/

= 3.9.1 =
*Release Date - May 29, 2024*

* added: warning when hiding query strings with Hide My WP
* changed: much better resizing for PNG8 and other paletted PNG images
* changed: apply async loading to lazyload JS using WP core functionality

= 3.9.0 =
*Release Date - April 23, 2024*

* added: Easy IO delivery for JS/CSS assets from additional domains
* added: Easy IO support for Divi Pixel image masks
* changed: Lazy Load checks for auto-scale exclusions on ancestors of lazyloaded element
* fixed: Help links broken in Firefox's Strict mode

= 3.8.0 =
*Release Date - April 11, 2024*

* added: Lazy Load can use dominant color placeholders via Easy IO
* added: ability to filter/parse admin-ajax.php requests via eio_filter_admin_ajax_response filter
* changed: improved smoothing of LQIP for Lazy Load when using Easy IO

= 3.7.0 =
*Release Date - March 20, 2024*

* added: support for upcoming Slider Revolution 7 rendering engine
* added: update existing image preload URLs
* added: Lazy Load automatically excludes preloaded images
* fixed: Easy IO skipping Slider Revolution 6 URLs
* fixed: Lazy Load incorrectly auto-scales fixed group background images

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
