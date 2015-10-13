<?php

// Based on Mark Dayel's movhandler extension. License is under what he chooses to license it under as a dervivative work.
// Thanks to Brian Wolff for all his help on IRC with all the nasty poorly documented bits.

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['swfhandler'] = $dir . 'swfhandler_body.php';
$wgExtensionMessagesFiles['swfhandler'] = $dir . 'swfhandler.i18n.php';

$wgExtensionCredits['media'][] = array(
	'path' => __FILE__,
	'name' => 'swfhandler',
	'author' => 'Calvin Buckley', 
	'url' => 'http://wopr.imaginarycode.com/', 
	'description' => 'Create thumbnails for Adobe SWF files',
	'descriptionmsg' => 'swfhandler-desc',
);

/*
 *  Requires swftools to be installed
 */

$wgMediaHandlers['application/x-shockwave-flash'] = 'swfhandler';
$wgMediaHandlers['application/vnd.adobe.flash-movie'] = 'swfhandler';
