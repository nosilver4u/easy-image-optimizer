=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.7
Tested up to: 5.8
Requires PHP: 7.2
Stable tag: 2.7.0
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

= 2.7.0 =
* added: disable "deep" integration with image_downsize filter via EIO_DISABLE_DEEP_INTEGRATION override
* changed: PNG placeholders are now inlined for less HTTP requests and better auto-scaling
* fixed: LQIP query strings not allowing resize operations
* fixed: Lazy Load throws error when ewww_webp_supported not defined in edge cases.
* fixed: Lazy Load regression prevents above-the-fold CSS background images from loading
* fixed: WebP source images ignored by URL rewriter
* fixed: Lazy Load scripts loading for page builders when they shouldn't be
* fixed: Easy IO does not rewrite image (href) links if image_downsize integration has rewritten the img tag

= 2.6.0 =
* added: enable -sharp_yuv option for WebP conversion with the EIO_WEBP_SHARP_YUV override
* added: Lazy Load for iframes, add 'iframe' in exclusions to disable
* added: preserve metadata and apply lossless compression to linked versions of images via Easy IO with EIO_PRESERVE_LINKED_IMAGES constant
* added: Easy IO rewrites URLs in existing picture elements
* changed: native lazy loading is now enabled for right-sized PNG placeholders, override with EIO_DISABLE_NATIVE_LAZY constant
* changed: move Easy IO check-in to wp_cron
* fixed: Add Missing Dimensions overwrites smaller width/height attribute if only one is set
* fixed: replacing an existing attribute (like width) with a numeric value is broken

= 2.5.5 =
* fixed: Include All Resources compat with Oxygen Builder and Beaver Builder
* fixed: regex to detect SVG images in use elements caused excessive backtracking
* fixed: adding invalid zoom parameter of 1920 to srcset URL

= 2.5.4 =
* fixed: Lazy Load fails to auto-scale with img-crop class for Easy IO
* fixed: Lazy Load throws JS error in SCRIPT_DEBUG mode
* fixed: warnings due to empty needle in strpos() function call

= 2.5.3 =
* added: use 'img-crop' id/class, or data-img-crop attribute to force cropping with Easy IO + Lazy Load.
* changed: lazysizes script moved to footer of page
* changed: use core wp_getimagesize() for proper error handling

= 2.5.2 =
* fixed: lazy load threshold missing from minified code

= 2.5.1 =
* change: added setting to enable adding of missing width/height dimensions
* fixed: warning from plugins using core wp_lazy_load filter without second parameter/argument

= 2.5.0 =
* added: ability to use SVG placeholders for more efficient lazy load via EASYIO_USE_SIIP override
* added: Easy IO and Lazy Load add missing width and height to image elements
* added: Lazy Load - right-sized placeholders can be generated for full-sized images
* added: configure Lazy Load pre-load threshold via EIO_LL_THRESHOLD constant
* changed: Lazy Load for external (non-inline) CSS images must be configured for specific elements
* changed: Easy IO's Include All Resources unlocked for all plans
* changed: native lazy loading is now disabled when using Easy IO lazy load, override with EIO_ENABLE_NATIVE_LAZY constant
* changed: Lazy Load pre-load threshold increased from 500px to 1000px
* changed: Lazy Load picture elements use right-sized img placeholder instead of 1x1 inline GIF
* fixed: native iframe lazy load disabled in WP 5.7+
* fixed: removing metadata clobbers APNG animations
* fixed: some JSON elements still being altered by Lazy Load
* fixed: Easy IO throws warnings when WP content is not in a sub-directory

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
