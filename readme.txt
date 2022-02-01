=== Easy Image Optimizer ===
Contributors: nosilver4u
Tags: optimize, image, resize, webp, lazy load, convert, compress, scale
Requires at least: 5.7
Tested up to: 5.9
Requires PHP: 7.2
Stable tag: 2.9.1
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

= 2.9.1 =
* added: use decoding=async to prevent images from blocking text render
* fixed: Auto-scale incorrectly handles Divi parallax background images
* fixed: native lazy loading used on inline PNG placeholders

= 2.9.0 =
* changed: Lazy Load no longer excludes first image in a page due to potential CLS issues and auto-scaling suppression
* fixed: AMP detection throws notices in some cases
* fixed: Easy IO misses some image URLs on multi-site when using domain-mapping
* fixed: Lazy Loader incorrectly parses fall-back iframe from Google Tag Manager, triggering 403 errors in some WAF systems
* fixed: Easy IO URL rewriter changing links if they matched a custom upload folder
* fixed: Easy IO incompatible with Toolset Blocks
* fixed: Easy IO incorrectly sizing wide/full width cover blocks
* fixed: SWIS CDN compat called too early in some cases

= 2.8.2 =
* fixed: error when disabling Easy IO
* updated: instructions for disabling Jetpack site accelerator

= 2.8.1 =
* changed: first image in a page is assumed to be "above the fold" and will not be lazy loaded, override with EIO_LAZY_FOLD constant
* fixed: mismatched quotes break HTML markup in some cases
* fixed: Lazy Loader incorrectly parses fall-back iframe from Google Tag Manager, triggering 403 errors in some WAF systems
* fixed: Easy IO could not properly resize PNG-8 images
* fixed: default WebP quality was too high

= 2.8.0 =
* added: EIO_LAZY_FOLD override to configure number of images above-the-fold that will be skipped by Lazy Load
* added: Easy IO URLs for custom (non-WP) srcset markup
* added: Easy IO support for CSS background images with relative URLs
* fixed: Easy IO scaling not working on full-size images without srcset/responsive markup
* fixed: Lazy Load skips images dynamically created by Brizy builder
* fixed: Easy IO conflict on Elementor preview pages
* fixed: EXACTDN_CONTENT_WIDTH not effective at overriding $content_width during image_downsize filter

= 2.7.4 =
* added: Easy IO and Lazy Load support for AJAX responses from FacetWP
* changed: Vimeo videos excluded from iframe lazy load
* changed: use 'bg-image-crop' class on elements with CSS background images that need to be cropped by auto-scaling
* fixed: sub-folder multi-site installs which use separate domains could not activate Easy IO
* fixed: Lazy Load PNG placeholders cannot be cached if the WP_CONTENT_DIR location is read-only (notably on Pantheon servers)
* fixed: is_amp() called too early
* fixed: Fusion Builder (Avada) does not load when Lazy Load or Easy IO options are enabled

= 2.7.3 =
* fixed: local PNG placeholders enabled with Easy IO when placeholder folder is not writable
* fixed: iframe lazy loading breaks Gravity Forms and FacetWP when parsing JSON
* fixed: is_amp() called too early

= 2.7.2 =
* fixed: Lazy Load not automatically creating placeholder folder

= 2.7.1 =
* added: integration with JSON/AJAX respones from Spotlight Social Media Feeds plugin
* fixed: img element search parsing JSON incorrectly

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

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by Shane Bishop of [Exactly WWW](https://ewww.io). Special thanks to my [Lord and Savior](https://www.iamsecond.com/). Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
