=== Better Extended Live Archive ===
Contributors: Charles
Donate link: http://extended-live-archive.googlecode.com
Tags: template tags, archive, post
Requires at least: 2.5
Tested up to: 2.7
Stable tag: 0.20

This plugin extends functionalities of WordPress by many sidebar widgets and template tags. Recent comments, random posts, most commented posts, etc.

== Description ==

The Extended Live Archive plugin is one selfcontained bundle called af-extended-live-archive, which should be put into the plugin folder to yield the following structure:

af-extended-live-archive
©¦  af-extended-live-archive-include.php
©¦  af-extended-live-archive-options.php
©¦  af-extended-live-archive.php
©¦  elalicenses.txt
©¦  readme.txt
©¦  ReadMe_version_0.10beta.html
©¦  treefile
©¦  
©À©¤cache
©¸©¤includes
        af-ela-style.css
        af-ela.php
        af-extended-live-archive.js.php

The plugin provides a single template function called af_ela_super_archive(). It writes some javascript information in the page it's called in and initializes the whole thing. Some parameter can be passed to the template function However, it is STRONGLY recommended that one uses the option panel the plugin is adding to the admin pages to configure ELA.

Doing so, all one has to do to display the Extended Live Archive is to add a <?php af_ela_super_archive(); ?> call wherever one wants the stuff to show up.

Note that if you are using K2, beta one r96 or after, the archives page is already doing that for you.

To install the plugin,

   1. upload the af-extended-archive directory and its content to your wp-content/plugins/ directory.
   2. make sure the cache directory permission are set to 0777 (refer to your webhost knowledge-base if need be)
   3. Then, visit the option->Ext. Live Archive page once to initialize it.


== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the directory of the stable readme.txt, so in this case, `/tags/4.3/screenshot-1.png` (or jpg, jpeg, gif)
2. This is the second screen shot

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`

== Directory Hierarchy ==

There are a lot of sub directories in the root directory of this plugin. The functions
of these directories are:

* "dev" for test files or other files used in the development
* "doc" for documents of this plugin include developer documents and user documents
* "i10n" for multilanguage support
* "inc" all the code of this plugin include php, js and css
* "libs" libraries
* "media" binary files include images, logos, etc.
