=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.8
Tested up to: 6.1
Requires PHP: 7.2
Stable tag: 3.2.0
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

= 3.2.0 =
* added: exclude by page for Easy IO and Lazy Load
* changed: include upstream lazysizes unveilhooks for use by developers, props @saas786
* fixed: better compatibility with S3 Uploads when using autoload
* fixed: Easy IO srcset URL construction not accounting for object versioning with S3 (or other cloud storage)

= 3.1.3 =
* added: image watermarking, configure at https://ewww.io/manage-sites/
* fixed: prevent WP core from generating duplicate WebP images

= 3.1.2 =
* fixed: trailing space on image URL handled incorrectly
* fixed: lazy load sometimes incorrectly scales images in AffiliateWP portal

= 3.1.1 =
* fixed: Lazy Load not using EWWWIO_CONTENT_DIR
* fixed: Lazy Load Auto-scale adds query parameters to SVG images
* fixed: Lazy Load prevents image loading in GiveWP iframe
* fixed: Auto Scale crops too much for object-* images in Oxygen

= 3.1.0 =
* added: AVIF support via Easy IO, enable on site management at ewww.io
* added: ability for Easy IO to get full-size path when using offloaded media
* fixed: front-end HTML parsers running within Bricks editor
* fixed: Easy IO not finding scaled full-size for dynamic size generation
* fixed: cover images not cropped properly by Easy IO
* fixed: Easy IO URLs leaking into post editor with WP 6.0

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
