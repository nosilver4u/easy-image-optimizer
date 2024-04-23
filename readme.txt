=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 6.3
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 3.9.0
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

= 3.6.0 =
*Release Date - February 14, 2024*

* added: Lazy Load for CSS background images in external CSS files or internal CSS sections
* fixed: Easy IO applying resize parameters to existing (re)sizes
* security: improve authentication for some plugin actions

= 3.5.5 =
*Release Date - January 4, 2024*

* fixed: Easy IO incorrectly modifies JS/CSS URLs when using S3 on multisite

= 3.5.4 =
*Release Date - November 28, 2023*

* fixed: Easy IO strips extra sub-folders in non-image URLs
* fixed: PHP notice from VC fix

= 3.5.3 =
*Release Date - November 2, 2023*

* fixed: too much scaling for Visual Composer background images with zoom effect

= 3.5.2 =
*Release Date - September 21, 2023*

* fixed: Lazy Load compatibility with X/Pro themes and Cornerstone builder
* security: sanitize and escape a few remaining strings

= 3.5.1 =
*Release Date - September 5, 2023*

* changed: use updated coding standards
* changed: sanitize/escape remaining strings on settings page
* security: randomize filename of debug log

= 3.5.0 =
*Release Date - July 19, 2023*

* added: Easy IO rewrites poster/thumbnail image URLs for video elements
* changed: Easy IO + Auto Scale checks images on load and resize events to reduce browser upscaling
* changed: prevent Easy IO font substitution when OMGF is active
* fixed: Auto Scale downscales too much for landscape images displayed in portrait containers
* fixed: Easy IO compatibility with Brizy thumbnail generation endpoint

= 3.4.0 =
*Release Date - June 28, 2023*

* added: deliver Google Fonts via Easy IO or Bunny Fonts for improved user privacy
* fixed: incorrect syntax for constants in namespaced code

= 3.3.0 =
*Release Date - May 9, 2023*

* breaking: namespaced and reorganized several classes, third party integrations should check for compatibility
* added: Easy IO support for BuddyBoss images, video, and documents
* added: improved support for Hide My WP Ghost in Lazy Load 
* changed: improved Auto Scaling when using full-width layout in Elementor
* changed: style tag search/regex cleaned up to prevent excess markup
* fixed: content dir functions don't resolve symlinks
* fixed: Easy IO image URLs leaking into image gallery block via post editor
* fixed: clearing debug log does not redirect back to settings page in rare cases

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
