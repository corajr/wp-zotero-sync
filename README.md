# WP Zotero Sync #
**Contributors:** (this should be a list of wordpress.org userid's)  
**Tags:** academic, publication  
**Requires at least:** 3.0.1  
**Tested up to:** 4.2.2  
**Stable tag:** trunk  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

WP Zotero Sync enables you to synchronize a custom post type, called "Publication," with a Zotero group library.

## Description ##

Zotero is a bibliographic reference manager that is capable of storing
a variety of documents and metadata. This plugin downloads the
contents of a Zotero library and creates a set of posts that
correspond to the records in the library.

Note that this is a one-way synchronization; the Zotero library will
not be affected by changes you make on the WordPress side.

This plugin uses [Co-Authors
Plus](https://wordpress.org/plugins/co-authors-plus/) to handle
documents with multiple authors. Authors who are present on the site
as WordPress users will be set as the authors of their respective
publications, while others will be added as guest authors. (If
Co-Authors Plus is not installed, regular users will be created instead.)

## Installation ##

1. Install the plugin through the admin console (`/wp-admin`) and select "Activate Plugin."
1. Under the "Publications" menu that has appeared, select "Zotero Sync."
1. Enter the details of the library and collection you wish to synchronize. (See below.)
1. Save your settings, then press "Sync Now" to download the documents. 

## Frequently Asked Questions ##

### How do I find the details of my Zotero library? ###

On the [zotero.org](http://www.zotero.org) site, log in and select the
library you wish to sync (a group library or your own).

View the source of that page and search for `libraryID`. The info is
in a JSON object, that looks like this:

```
{"target":"collections", "libraryID":"123456", "groupID":"123456",
"libraryType":"group", "libraryUrlIdentifier":"wordpress_sync_test_data"}
```

The first three settings are taken from this text (Library Type,
Library ID, and "libraryUrlIdentifier" (Library Slug)).

If you wish to download only a particular collection from the library,
the Collection Key is found at the end of that collection's URL. For
example if the collection is at
`https://www.zotero.org/groups/[...]/collectionKey/ABCDEFGH`, then
ABCDEFGH is the collection key.

## Screenshots ##

### 1. The Sync Settings page. ###
![The Sync Settings page.](/assets/screenshot-1.png)

### 2. Publication posts as created by the plugin. ###
![Publication posts as created by the plugin.](/assets/screenshot-2.png)

### 3. A sample publication. ###
![A sample publication.](/assets//screenshot-3.png)


## Changelog ##

### 0.1-alpha ###
* Initial development version.

## Upgrade Notice ##

### 0.1-alpha ###
No updates yet.
