=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.0
Tested up to: 5.3
Requires PHP: 5.6
Stable tag: 1.8.0
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

= 1.9.0 =
* added: automatic plan upgrade detection
* updated: embedded help beacon code

= 1.8.0 =
* changed: better compatibility with other implementations of "native lazy load"
* fixed: Easy IO detects wrong domain when using separate domains for site and content

= 1.7.0 =
* added: support for background images on link (a) elements
* added: exclude images from Easy IO in settings
* added: exclude images from Lazy Load by string or class name
* added: prevent auto-scaling with skip-autoscale
* added: exclusions can be defined as overrides (single value as string, multiple values as an array)
* fixed: errors due to duplicate ssl= arguments in URLs

= 1.6.0 =
* added: support background images on a/link elements
* fixed: partial wp-content URLs being rewritten for some plugins
* fixed: inconsistent settings page links
* updated: new version of lazysizes JS

= 1.5.0 =
* added: disable native lazy-load attributes with EASYIO_DISABLE_NATIVE_LAZY
* added: ability to choose LQIP or blank placeholders for lazy load
* added: better compatibility with Divi filterable grid images and parallax backgrounds
* changed: default to blank placeholders with Easy IO
* fixed: Divi builder will not load with Easy IO and Include All Resources active
* fixed: image cover block with fixed width scaled too much
* fixed: low-quality placeholders sometimes had larger dimensions than necessary
* fixed: Slider Revolution dummy.png not properly handled by Easy IO

= 1.4.0 =
* changed: turn off WebP conversion when premium optimization is disabled
* fixed: incorrect debug function in ExactDN class
* fixed: wrong translation slug for some strings

= 1.3.0 =
* added: GCS sub-folder rewriting with ExactDN for cleaner URLs

= 1.2.0 =
* changed: better URL detection with WP Offload Media activated
* changed: lazy placeholders limited to 1920px wide

= 1.1.0 =
* added: use native loading="lazy" for even better performance
* updated: lazysizes core library
* fixed: ExactDN incorrectly scales Elementor background images rather than cropping
* fixed: ExactDN cannot work with Divi/Elementor background images due to use of external CSS files
* fixed: Lazy Load auto-scaling breaks if background image is enclosed in encoded quotes

= 1.0.0 =
* First release

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
