<?php
/**
 * TemplateEdit.
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

// credits
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Template Edit',
	'version' => '0.7.1',
	'author' => array( 'VolkoV' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:TemplateEdit',
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
$wgHooks['SkinTemplateToolboxEnd'][] = 'editTemplate_AddToolboxLink';

function editTemplate_AddToolboxLink( &$tpl ) {
	global $wgRequest;

	// 1. Determine whether to actually add the link at all.
	// There are certain cases, e.g. in the edit dialog, in a special page,
	// where it's inappropriate for the link to appear.
	// 2. Check the title. Is it a "Special:" page? Don't display the link.
	$action = $wgRequest->getText( 'action', 'view' );
	if ( method_exists( $tpl, 'getSkin' ) ) {
		$title = $tpl->getSkin()->getTitle();
	} else {
		global $wgTitle;
		$title = $wgTitle;
	}

	if( $action != 'view' && $action != 'purge' && !$title->isSpecialPage() ) {
		return true;
	}

	// 3. Add the link!
	$href = SpecialPage::getTitleFor( 'TemplateEdit' )->getLocalURL(Array("article"=>$title->getPrefixedDBkey()));
	echo Html::rawElement( 'li', null, Html::element( 'a', array( 'href' => $href ), 'Vorlagen editieren' ) );

	return true;
}