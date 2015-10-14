<?php
/**
 * TemplateEdit.
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

// credits
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Template Edit',
	'version' => '0.7.2',
	'author' => array( 'VolkoV' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:TemplateEdit',
	'descriptionmsg'  => 'templateedit-description',
);

$rtgIP = dirname( __FILE__ ) . '/';
$wgHooks['AdminLinks'][] = 'TemplateEdit::addToAdminLinks';
$wgHooks['SkinTemplateNavigation::Universal'][] = 'TemplateEdit::SkinTemplateNavigationUniversal';

$wgSpecialPages['TemplateEdit'] = 'TemplateEdit';
$wgSpecialPageGroups['TemplateEdit'] = 'wiki';

$wgAutoloadClasses['TemplateEditParser']  = $rtgIP . 'TemplateEditParser.php';
$wgAutoloadClasses['TemplateEdit']        = $rtgIP . 'SpecialTemplateEdit.php';
$wgExtensionAliasesFiles['TemplateEdit']  = $rtgIP . 'TemplateEdit.alias.php';
$wgExtensionMessagesFiles['TemplateEdit'] = $rtgIP . 'TemplateEdit.i18n.php';
