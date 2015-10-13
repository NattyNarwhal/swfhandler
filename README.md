## swfhandler

This extension renders Adobe Flash files.

## Installation

Make sure you have swftools and ImageMagick, clone into `$MEDIAWIKI/extensions`
and then add this to `LocalSettings.php`:

```php
// Load swfhandler
require_once("$IP/extensions/swfhandler/swfhandler.php");

// swfrender may require more RAM than MediaWiki gives by default. Increase as needed.
$wgMaxShellMemory = 30000000; // (this number in KB)

// Add SWFs to the upload list
$wgFileExtensions[] = 'swf';
```
This has only been tested on MediaWiki 1.19, 1.23, and 1.25.

## Thanks

* Mark Dayel: making the movhandler extensions this extension is based off of.
* Brian Wolff: for his help on IRC with the \*Handler API
