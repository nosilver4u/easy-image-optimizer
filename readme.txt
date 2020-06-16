=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.0
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 2.2.3
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

= 2.2.3 =
* fixed: lazy load skips img elements with unquoted src attributes
* fixed: sites using S3 fail verification after upgrade

= 2.2.2 =
* fixed: Lazy Load has nested fall-back img elements if an image is found multiple times in a page
* fixed: Easy IO mangles srcset URLs when src URL is relative instead of absolute
* fixed: Easy IO URLs leaking into block editor for new uploads

= 2.2.1 =
* added: EXACTDN_DEFER_JQUERY_SAFE override for when inline scripts depend on jQuery
* changed: Easy IO uses better query-string fall-back for plugins
* changed: Easy IO enforces https if available rather than protocol-relative URLs

= 2.2.0 =
* added: defer jQuery also with EXACTDN_DEFER_JQUERY override
* added: Lazy Load supports VC grid layouts retrieved via AJAX
* fixed: Lazy Load prevents loading of images in oEmbed endpoint
* fixed: jQuery exclusion was preventing deferral of jQuery extensions also
* fixed: Lazy Load parsing Owl Lazy images
* fixed: Easy IO adds srcset/sizes to feeds

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
