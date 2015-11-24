=== Paid Memberships Pro - Member RSS Add On ===
Contributors: strangerstudios
Tags: rss, feed, blubrry, powerpress, podcasts, podcasting, paid memberships pro, secure, protect, lock
Requires at least: 3.5
Tested up to: 3.9.1
Stable tag: .1

Create Member-Specific RSS Feeds for Paid Memberships Pro

== Description ==

RSS feeds, including those created by plugins like Blubrry PowerPress,
are filtered to hide member content. Enclosures, like mp3 URLs, are hidden as well.

All members are given a "memberkey" that can be attached to the end of a feed URL
that will allow access to the full member RSS feed.

E.g. of a feed URL with a member key:
http://www.yoursite.com/feed/podcast/?memberkey=58efacdc83d88e3edf8e31f4b4f5806e

There are no protections currently to keep users from sharing their feed URLs,
but future improvements could include monitoring use of the memberkeys and
deactivating ones that show signs of abuse.

== Installation ==

1. Upload the `pmpro-member-rss` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-member-rss/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==

= .2. =
* Now removing enclosures from member posts.
* Filtering the RSS content filter to show a link to the post.

= .1 =
* Initial release.