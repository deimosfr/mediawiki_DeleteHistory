<?php
// Prerequisite :
// - php5-cli

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "extensions/DeleteHistory/DeleteHistory.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
    'path' => __FILE__,                                                                     // File name for the extension itself, required to getting the revision number from SVN - string, adding in 1.15
    'name' => "DeleteHistory",                                                              // Name of extension - string
    'description' => 'deletehistory-desc',                                                  // Description of what the extension does - string
    'descriptionmsg' => "deletehistory-desc",                                               // Same as above but name of a message, for i18n - string, added in 1.12.0
    'version' => '0.2',                                                                     // Version number of extension - number or string
    'author' => "Pierre Mavro",                                                             // The extension author's name - string
    'url' => "http://www.mediawiki.org/wiki/Extension:SpecialDeleteHistory",                // URL of extension (usually instructions) - string
);

$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['DeleteHistory'] = $dir . 'DeleteHistory_body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['DeleteHistory'] = $dir . 'DeleteHistory.i18n.php';
$wgExtensionAliasesFiles['DeleteHistory'] = $dir . 'DeleteHistory.alias.php';
$wgSpecialPages['DeleteHistory'] = 'DeleteHistory'; # Let MediaWiki know about your new special page.
