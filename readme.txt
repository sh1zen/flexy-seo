=== Flexy SEO === 
Contributors: sh1zen 
Tags: SEO, Breadcrumbs, Content analysis, Readability, Schema, Ranking; SERP
Donate link: https://www.paypal.com/donate?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR
Requires at least: 4.6.0
Tested up to: 6.0
Requires PHP: 7.4
Stable tag: 1.5.2
License: GNU v3.0 License
URI: https://github.com/sh1zen/flexy-seo/blob/master/LICENSE

Search Engine (SEO) &amp; Performance Optimization plugin.

== Description ==

Flexy SEO contains most requested features for your website seo, carrying many options to optimize and analyze it.
All customizable in few and easy steps.

**WHY USING Flexy SEO (WPFS)?**
  
* **All In One:** WPFS carries many features to bring you a full control on the SEO.
* **Easy to use:** WPFS was designed to be intuitive, allowing also non experts to be able to make that changes to have a great website. 
* **Performances oriented:** WPFS is built to speed up your site, every single module is optimized to ensure the best performance.
* **Privacy:** WPFS does not collect nor send any data.
* **No subscription email is asked or required.**
* **Free.**

**BENEFITS**

* Improvements in search engine result page rankings. 
* Fast and with a lower memory usage compared to other SEO plugins.

**FEATURES**

* ***Full control over SEO***.
* ***Webmaster tools:*** allow site verification (Google, Bing, Yandex, Baidu).
* ***Social support:*** support open graph by facebook and metacard data for Twitter, furthermore allow site verification and profile connection in schema.org info (Facebook, Linkedin, Twitter, ...).
* ***Adaptive:*** easy adapts to custom post types or custom taxonomies.
* ***Knowledge Graph***.
* ***Standard Breadcrumbs***.
* ***Flexed Breadcrumbs:*** a powerful tool to help you create personalized breadcrumb structure.

**DONATIONS**

This plugin is free and always will be, but if you are feeling generous and want to show your support, you can buy me a
beer or coffee [here](https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR), I will really appreciate it.

== Installation ==

This section describes how to install the plugin. In general, there are 3 ways to install this plugin like any other
WordPress plugin.

**1. VIA WORDPRESS DASHBOARD**
  
* Click on ???Add New??? in the plugins dashboard
* Search for 'WP Optimizer'
* Click ???Install Now??? button
* Activate the plugin from the same page or from the Plugins Dashboard

**2. VIA UPLOADING THE PLUGIN TO WORDPRESS DASHBOARD**
  
* Download the plugin to your computer
  from [https://wordpress.org/plugins/flexy-seo/](https://wordpress.org/plugins/flexy-seo/)
* Click on 'Add New' in the plugins dashboard
* Click on 'Upload Plugin' button
* Select the zip file of the plugin that you have downloaded to your computer before
* Click 'Install Now'
* Activate the plugin from the Plugins Dashboard

**3. VIA FTP**
  
* Download the plugin to your computer
  from [https://wordpress.org/plugins/flexy-seo/](https://wordpress.org/plugins/flexy-seo/)
* Unzip the zip file, which will extract the main directory
* Upload the main directory (included inside the extracted folder) to the /wp-content/plugins/ directory of your website
* Activate the plugin from the Plugins Dashboard

**FOR MULTISITE INSTALLATION**

* Log in to your primary site and go to "My Sites" ?? "Network Admin" ?? "Plugins"
* Install the plugin following one of the above ways
* Network activate the plugin

**INSTALLATION DONE, A NEW LABEL WILL BE DISPLAYED ON YOUR ADMIN MENU**

**Roadmap**

* Add a SEO monitor and SEO meter 
* In page option to override default settings

== Frequently Asked Questions ==

= WHY USING FLEXY-SEO? =

* **Flexed experience:** The plugin has been developed to allow you to build your own breadcrumb and SEO-data structure limiting changes in theme's code.
* **Fully configurable**
* **Fast and low memory usage**

= What to do if I run in some issues after upgrade? =

Deactivate the plugin and reactivate it, if this doesn't work try to uninstall and reinstall it. That should
work! Otherwise, go to the new added module "Setting" and try a reset.

== Changelog ==

= 1.5.2 = 

* fixed some bugs

= 1.5.1 = 

* improved breadcrumbs view
* updated translations
* improved graphic generation
* fixed author archive SEO

= 1.5.0 =

* added theme dev function wpfs_the_title($filtered = true, $trailingBlogName = true)
* added theme dev function wpfs_get_the_description($post = null, $default = '')
* added theme dev function wpfs_get_mainImageURL($post = null, $size = 'large')
* added theme dev function wpfs_get_post_excerpt($post = null, $length = 32, $more = '...')
* improved title generation corner cases
* updated core
* updated user interface
* fixed some bugs in Graph generation process

= 1.4.9 =

* improved schema.org Graph generation
* fixed some bugs
* improved performances

= 1.4.8 = 

* tested up to WordPress 6.0
* fixed some core bugs

= 1.4.7 =

* added wpfs_document_title()
* improved title generation for breadcrumbs

= 1.4.6 =

* fixed reported bugs in wpfs_title filter

= 1.4.5 =

* improved core performances
* improved database and caching performances
* fixed a bug in snippet_image generator

= 1.4.0 =

* added options for classic breadcrumbs
* extended schema.org support
* fixed some security flaws
* improved core performances
* improved SEO
* improved breadcrumbs

= 1.3.4 =

* added support for PHP 8.1 and WordPress 5.9

= 1.3.3 =

* upgraded core dependencies

= 1.3.2 =

* fixed cache bug
* updated translations

= 1.3.0 =

* improved core performances
* improved schema generation
* added snippet in edit-page

= 1.2.3 =

* fixed seo settings saving issue
* updated hash algorithms
* added support for custom page schema

= 1.2.0 =

* updated translations
* added @schema.org support
* added integration with WordPress media uploader
* improved user interface
* rewritten core code to be a shared framework with WP-Optimizer to ensure the best performances if both are installed
* extended WordPress support to version 5.9
* moved minimum WordPress support to version 4.6.0


= 1.1.1 =

* updated translations
* fixed seo settings page
* improved loading time

= 1.1.0 =

* added a filter for pages title "wpfs_title"
* added a filter for replacements rules "wpfs_replacement_{$rule}_{$type}", where $type is one of (search|post_archive|home|post|term|user|date|404)

= 1.0.2 =

* added metabox on edit post/page screen
* improved OpenGraph image properties
* updated translations  
* extended WordPress support to 5.8

= 1.0.1 =

* minor fixes 

= 1.0.0 =

* Initial Release
