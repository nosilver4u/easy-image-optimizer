=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.0
Tested up to: 5.3
Requires PHP: 5.6
Stable tag: 2.0.0
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

Don't panic, just disable the plugin and contact us at https://ewww.io/contact-us/ All changes made by the plugin will automatically be reverted when you disable the plugin.

== Screenshots ==

1. Plugin settings page before activation.
2. Plugin settings when active.

== Changelog ==

* If you would like to help translate this plugin in your language, get started here: https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer/

= 2.0.0 =
* added: Easy IO sets pre-scaled image in data-pin-media for Pinterest
* added: Envira Pro cache cleared when activating Easy IO
* fixed: background image lazy-loading could be interrupted by other plugins copying elements
* fixed: Easy IO misses some images with Envira Gallery Pro layouts
* fixed: missing www in domain prevents rewrites for Easy IO
* fixed: Lazy Load parsing X/Pro theme admin pages

= 1.9.0 =
* added: automatic plan upgrade detection
* changed: plugin can be activated network-wide, though configuration is still per-site
* updated: embedded help beacon code
* updated: lazysizes.js to version 5.2
* fixed: custom domain for Easy IO prevents auto-scaling
* fixed: full-width background images auto-scaled due to scroll bars

= 1.8.0 =
* changed: better compatibility with other implementations of "native lazy load"
* fixed: Easy IO detects wrong domain when using separate domains for site and content

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
