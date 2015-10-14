<?php

if ( !defined( 'MEDIAWIKI' ) ) die();

class TemplateEdit extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'TemplateEdit' , 'edit' );
	}

	static public function addToAdminLinks( &$admin_links_tree ) {
		$general_section = $admin_links_tree->getSection( wfMsg( 'adminlinks_general' ) );
        	$extensions_row = $general_section->getRow( 'extensions' );
		if ( is_null( $extensions_row ) ) {
			$extensions_row = new ALRow( 'extensions' );
			$general_section->addRow( $extensions_row );
		}
		$extensions_row->addItem( ALItem::newFromSpecialPage( 'TemplateEdit' ) );
		return true;
	}

	static public function SkinTemplateNavigationUniversal( $skin, &$content_actions ) {
		$title = $skin->getTitle();
		$href = SpecialPage::getTitleFor( 'TemplateEdit' )->getLocalURL(Array("article"=>$title->getPrefixedDBkey()));
		if( $title->quickUserCan( 'edit' ) && $title->exists() ) {
			$content_actions['views'] = array_slice($content_actions['views'], 0, 2, true) +
			array('templateedit' => array(
				'class' => false,
				'text' => wfMsg('templateedit-tabdescription'),
				'href' => $href,
				'primary' => true,
			)) +
			array_slice($content_actions['views'],2,count($content_actions['views']) - 1, true);
		}
		return true;
	}

	
	function execute( $subPage ) {
		global $wgUser, $wgOut, $wgRequest, $wgLang;

		$this->article=$wgRequest->getText('article');
		$this->articleprefix=$wgRequest->getText('articleprefix');
		$this->templatename=$wgRequest->getText('templatename');
		$this->template=$wgRequest->getText('template');
		$this->save=$wgRequest->getText('save');
		$this->user = $wgUser;

		$this->setHeaders();

		//Title valid
		$title=Title::newFromText($this->article);
		if($title!=null) {
			$wgOut->setPageTitle($title->getBaseText().' - '.wfMsg('templateedit'));
			if ( ! $title->userCan( 'edit' ) ) {
				$wgOut->permissionRequired( 'edit' );
				return;
			}
			$source="";
			if($title!=null) {
				$article=new Article($title);
				if($title->exists()) {
					//Existing article
					$source=$article->getContent();
				} elseif ($this->templatename!='') {
					//New article
					$source='{{'.$this->templatename.'}}';
					$article->doEdit( $source,wfMsg('templateedit-templatecreated',$this->templatename), null );
				}
			};
			//No template selected
			if($this->template=="") {
				$this->showSelectTemplateForm($title,$source);
				return;
			} else {
				if($this->save!="1") {
					//Edit Template
					$this->showEditForm($title,$source);
					return;
				} else {
					//Save Template
					$this->showSavedMessage($title,$source,$article);
					return;
				}
			}
		}

		// if we're still here, show the starting form
		$this->showStartingForm();
	}

	function showStartingForm() {
		global  $wgOut;
		if ($this->templatename!="")
			$wgOut->addWikiText(wfMsg('templateedit-createwithtemplate',$this->templatename));
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'id' => 'templateedit', 'action' => $this->getTitle()->getFullUrl(), 'method' => 'post' ) ) .
			html::hidden( 'title', $this->getTitle()->getPrefixedText() ) .
			html::hidden( 'templatename', $this->templatename ) .
			Xml::tags( 'p', null , wfMsg('templateedit-showstartingform') ) .
			Xml::input( 'article', 50, $this->articleprefix ) .
			Xml::submitButton(wfMsg('templateedit-continue')) .
			Xml::closeElement( 'form' )
		);
	}

	function showSelectTemplateForm($title,$source) {
		global $wgOut;
		if($source=="") die ('Something is wrong!');
		$templates=TemplateEditParser::getTemplates($source);
		$wgOut->addWikiText('[[:'.$title->getPrefixedText().']]');
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'id' => 'templateedit', 'action' => $this->getTitle()->getFullUrl(), 'method' => 'post' ) ) .
			html::hidden( 'title',  $this->getTitle()->getPrefixedText() ) .
			html::hidden( 'article', $this->article ).
			Xml::tags( 'p', null , wfMsg('templateedit-showselecttemplateform') )
		);

		for($i=0;$i<count($templates);$i++)
			$wgOut->addHTML( Xml::radio( "template", "$i", $i==0 ,
				Array('style'=>'margin-left:'.$templates[$i]["level"].'em;') ).$templates[$i]["name"]."<br />"
			);

		$wgOut->addHTML(
			Xml::submitButton(wfMsg('templateedit-continue')) .
			Xml::closeElement( 'form' )
		);
	}

	function getTemplateDefinitions($template) {
		$this->deleteold=TRUE;
		$title=Title::newFromText(wfMsg('templateedit-templateeditortitle',$template));
		if(($title!=null)&&($title->exists())) {
			$article=new Article($title);
			$source=$article->getContent();
			$sourceexplode=explode("|",$source);
			$fields=Array();
			for($i=0;$i<count($sourceexplode);$i++)
				if(trim($sourceexplode[$i])!="")
					$fields[]=trim($sourceexplode[$i]);
			$indexedFields=Array();
			for($i=0;$i<count($fields);$i++) {
				$explode=explode("=",$fields[$i],2);
				if(count($explode)==1) $explode[]='TEXT!!!';
				$params=explode("!",$explode[1],5);
				if(count($params)<1) $params[]='TEXT';
				if(count($params)<2) $params[]='';
				if(count($params)<3) $params[]='';
				if(count($params)<4) $params[]='';
				if(count($params)<5) $params[]='';
				$indexedfields[$explode[0]]=Array(
					'type'        => $params[0],
					'must'        => ($params[1]=='MUST'),
					'description' => $params[2],
					'picklist'    => $params[3],
					'default'	=> $params[4],
				);
			}
			return $indexedfields;
		}
		$this->deleteold=FALSE;
		return Array(wfMsg('templateedit-noeditor')=>Array('type'=>'TITLE','must'=>FALSE,'description'=>'','picklist'=>''));
	}

	function addInput($wgOut,$name,$def,$value,$style) {
		$techname="Field".str_replace(' ','',$name);
		if($def['type']=='TITLE') {
			$wgOut->addHTML(
				'<tr><td colspan="2" class="dunkel" style="border-bottom:1px solid gray;"><h3>'.$name.'</h3></td></tr>'
			);
			return;
		}
		$must='';
		if($def['must'])
			$must='<span style="color:red;">'.wfMsg('templateedit-mustfield').'</span> ';
		$wgOut->addHTML('<tr><td style="border-bottom:1px solid gray;"><b>'.$name.':</b><br/> '.$must);
		$wgOut->addHTML($wgOut->parse($def['description'],false));
		$wgOut->addHTML('</td><td style="border-bottom:1px solid gray;">');
		if($def['type']=='PICK') {
			$wgOut->addHTML(
				Xml::openElement( 'select', array('name'=>$techname,'size'=>'1', 'style' => 'width: 95%;'.$style ) )
			);
			$picks=explode(';',$def['picklist']);
			for($i=0;$i<count($picks);$i++) {
				$selected=null;
				if($picks[$i]==trim($value))
					$selected=Array( 'selected'=>'selected');
				$wgOut->addHTML(
					Xml::tags('option', $selected,$picks[$i])
				);
			}
			$wgOut->addHTML(
				Xml::closeElement( 'select' )
			);
		}
		if($def['type']=='TEXTAREA')
			$wgOut->addHTML(
				Xml::textarea( $techname, $value , 50, 5, array( 'style' => 'width: 95%;'.$style ) )
			);
		if(($def['type']=='TEXT')||($def['type']=='LINK')||($def['type']=='NUMBER'))
			$wgOut->addHTML(
				Xml::input( $techname, 250, $value , array( 'style' => 'width: 95%;'.$style ) )
			);
		if($def['type']=='OLD')
			$wgOut->addHTML(
				Xml::checklabel( wfMsg('templateedit-remove') , 'Remove'.$techname , 'Remove'.$techname , $this->deleteold ) .
				Xml::input( $techname, 250, $value , array( 'style' => 'width: 95%; background-color:#ff7f7f;'.$style ) )
			);
		$wgOut->addHTML('</td></tr>');
	}

	function showEditForm($title,$source) {
		global  $wgOut;
		if($source=="") die ('Something is wrong!');
		$templateparts=TemplateEditParser::getTemplateParts($source,$this->template);
//		print_r($templateparts);
		$definitions=$this->getTemplateDefinitions($templateparts['template']);
		$wgOut->addWikiText(wfMsg('templateedit-editformintro',$title->getPrefixedText(),$templateparts['template']));
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'id' => 'templateedit', 'action' => $this->getTitle()->getFullUrl(), 'method' => 'post' ) ) .
			html::hidden( 'title',  $this->getTitle()->getPrefixedText() ) .
			html::hidden( 'article', $this->article ).
			html::hidden( 'template', $this->template ).
			html::hidden( 'save', "1" ).
			Xml::tags( 'p', null , wfMsg('templateedit-showeditform') ) .
			Xml::openElement('table', array('style' => 'border-collapse:collapse;') )
		);
		$indexedtp=Array();
		for($i=0;$i<count($templateparts['params']);$i++) {
			$indexedtp[$templateparts['params'][$i]['name']]['value']=$templateparts['params'][$i]['value'];
			$indexedtp[$templateparts['params'][$i]['name']]['used']=FALSE;
		}
		foreach($definitions as $name=>$def) {
			if(isset($indexedtp[$name])) {
				$value=$indexedtp[$name]['value'];
				$indexedtp[$name]['used']=TRUE;
				$background='';
			} else {
				$value=$def['default'];
				$def['description'].=' '.wfMsg('templateedit-fieldundefined');
				$background='background-color:#ffff7f;';
			}
			$this->addInput($wgOut,$name,$def,$value,$background);
		};
		$def=Array('type'=>'TITLE','must'=>FALSE,'description'=>'','picklist'=>'');
		$this->addInput($wgOut,wfMsg('templateedit-oldfieldstitle'),$def,'','');
		foreach($indexedtp as $name=>$field)
			if(!$field['used']) {
			$def=Array('type'=>'OLD','must'=>FALSE,'description'=>wfMsg('templateedit-oldfield'),'picklist'=>'');
			$this->addInput($wgOut,$name,$def,$field['value'],'');
		};

		$wgOut->addHTML(
			Xml::closeElement( 'table' ) .
			Xml::submitButton(wfMsg('templateedit-save')) .
			Xml::closeElement( 'form' )
		);
	}

	function showSavedMessage($title,$source,$article) {
		global $wgOut,$wgRequest;
		if($source=="") die ('Something is wrong!');
		$templateparts=TemplateEditParser::getTemplateParts($source,$this->template);
		$definitions=$this->getTemplateDefinitions($templateparts['template']);
		$indexedtp=Array();
		for($i=0;$i<count($templateparts['params']);$i++) {
			$indexedtp[$templateparts['params'][$i]['name']]['value']=$templateparts['params'][$i]['value'];
			$indexedtp[$templateparts['params'][$i]['name']]['used']=FALSE;
		}
		foreach($definitions as $name=>$def) {
			$techname="Field".str_replace(' ','',$name);
			$definitions[$name]['value']=$wgRequest->getText($techname);
			if(isset($indexedtp[$name]))
				$indexedtp[$name]['used']=TRUE;
		}
		foreach($indexedtp as $name=>$field) {
			$techname="Field".str_replace(' ','',$name);
			if((!$field['used'])&&($wgRequest->getText('Remove'.$techname)!='1')) {
				$definitions[$name]=Array(
					'type'=>'OLD','must'=>FALSE,'description'=>wfMsg('templateedit-oldfield'),'picklist'=>'',
					'value'=>$wgRequest->getText($techname)
				);
			}
		}
		$insert=$templateparts['template'];
		$i=1;
		foreach($definitions as $name=>$def) {
			if($def['type']!='TITLE') {
				if($name==$i)
					$insert.='|'.$def['value'];
				else {
					if($i==1)
						$insert.="\n";
					$insert.='|'.$name.'='.$def['value']."\n";
				}
				$i++;
			}
		}

		$newsource=TemplateEditParser::replaceTemplate($source,$this->template,$insert);
		$article->doEdit( $newsource,wfMsg('templateedit-templateedited',$templateparts['template']), null );
		$wgOut->addWikiText('[[:'.$title->getPrefixedText().']]');
		$wgOut->addHTML(
			Xml::tags( 'p', null , wfMsg('templateedit-articlesaved') )
		);
	}


}