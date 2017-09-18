<?php
/**
 * TemplateEdit.
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

// credits
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Template Edit',
	'version' => '1.0',
	'author' => array( 'VolkoV' ),
	'url' => 'http://www.garetien.de',
	'descriptionmsg'  => 'templateedit-description',
);

$rtgIP = dirname( __FILE__ ) . '/';
$wgHooks['AdminLinks'][] = 'TemplateEdit::addToAdminLinks';

// This extension uses its own permission type, 'replacetext'
$wgSpecialPages['TemplateEdit'] = 'TemplateEdit';
$wgSpecialPageGroups['TemplateEdit'] = 'wiki';
$wgAutoloadClasses['TemplateEditParser'] = $rtgIP . 'TemplateEditParser.php';
$wgAutoloadClasses['TemplateEdit']   = $rtgIP . 'SpecialTemplateEdit.php';

$wgExtensionAliasesFiles['TemplateEdit'] = $rtgIP . 'TemplateEdit.alias.php';
$wgExtensionMessagesFiles['TemplateEdit'] = $rtgIP . 'TemplateEdit.i18n.php';


// Add a shortcut link to the toolbox.
$wgHooks['BaseTemplateToolbox'][] = function( BaseTemplate $skinTemplate, array &$toolbox  ) {
	$title = $skinTemplate->getSkin()->getRelevantTitle();
 	$href = SpecialPage::getTitleFor( 'TemplateEdit' )->getLocalURL(Array("article"=>$title->getPrefixedDBkey()));
    $toolbox['templateedit'] = array(
      'msg' => 'templateedit',
      'href' => $href,
      'id' => 't-templateedit',
      'rel' => 'templateedit'
    );
};


