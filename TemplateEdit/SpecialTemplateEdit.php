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
		$general_section = $admin_links_tree->getSection( wfMessage( 'adminlinks_general' )->text() );
        	$extensions_row = $general_section->getRow( 'extensions' );
		if ( is_null( $extensions_row ) ) {
			$extensions_row = new ALRow( 'extensions' );
			$general_section->addRow( $extensions_row );
		}
		$extensions_row->addItem( ALItem::newFromSpecialPage( 'TemplateEdit' ) );
		return true;
	}

	function execute( $subPage ) {
		global $wgUser, $wgOut, $wgRequest, $wgLang;

		$this->article=$wgRequest->getText('article');
		$this->articleprefix=$wgRequest->getText('articleprefix');
		$this->articleautomatic=$wgRequest->getText('articleautomatic');
		$this->templatename=$wgRequest->getText('templatename');
		$this->template=$wgRequest->getText('template');
		$this->save=$wgRequest->getText('save');
		$this->user = $wgUser;

		$this->setHeaders();

		//Title valid
		$title=Title::newFromText($this->article);
		if($title!=null) {
			if ( ! $title->userCan( 'edit' ) ) {
				throw new PermissionsError( 'edit' );
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
					$source="{{".$this->templatename."}}";
					if($this->articleautomatic=="1")
						$source.="\n\n(Beschreibungstext fehlt).\n\n{{".$this->templatename." Automatik}}";
					$article->doEdit( $source,wfMessage('templateedit-templatecreated',$this->templatename)->text(), null );
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
			$wgOut->addWikiText(wfMessage('templateedit-createwithtemplate',$this->templatename)->text());
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'id' => 'templateedit', 'action' => $this->getTitle()->getFullUrl(), 'method' => 'post' ) ) .
			html::hidden( 'title', $this->getTitle()->getPrefixedText() ) .
			html::hidden( 'templatename', $this->templatename ) .
			html::hidden( 'articleautomatic', $this->articleautomatic ) .
			Xml::tags( 'p', null , $this->msg('templateedit-showstartingform')->text() ) .
			Xml::input( 'article', 50, $this->articleprefix ) .
			Xml::submitButton(wfMessage('templateedit-continue')->text()) .
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
			Xml::tags( 'p', null , wfMessage('templateedit-showselecttemplateform')->text() )
		);

		for($i=0;$i<count($templates);$i++)
			$wgOut->addHTML( Xml::radio( "template", "$i", $i==0 ,
				Array('style'=>'margin-left:'.$templates[$i]["level"].'em;') )."<b>".$templates[$i]["name"]."</b> (".$templates[$i]["source"]."...)<br />"
			);

		$wgOut->addHTML(
			Xml::submitButton(wfMessage('templateedit-continue')->text()) .
			Xml::closeElement( 'form' )
		);
	}

	function getTemplateDefinitions($template) {
		$this->deleteold=TRUE;
		$title=Title::newFromText(wfMessage('templateedit-templateeditortitle',$template)->text());
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
				$params=explode("!",$explode[1],6);
				if(count($params)<1) $params[]='TEXT';
				if(count($params)<2) $params[]='';
				if(count($params)<3) $params[]='';
				if(count($params)<4) $params[]='';
				if(count($params)<5) $params[]='';
				if(count($params)<6) $params[]='';
				$indexedfields[$explode[0]]=Array(
					'type'        => $params[0],
					'must'        => ($params[1]=='MUST'),
					'description' => $params[2],
					'picklist'    => $params[3],
					'default'	  => $params[4],
					'migration'	  => $params[5],
				);
			}
			return $indexedfields;
		}
		$this->deleteold=FALSE;
		return Array(wfMessage('templateedit-noeditor')->text()=>Array('type'=>'TITLE','must'=>FALSE,'description'=>'','picklist'=>''));
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
			$must='<span style="color:red;">'.wfMessage('templateedit-mustfield')->text().'</span> ';
		$wgOut->addHTML('<tr><td style="border-bottom:1px solid gray;"><b>'.strip_tags($name).':</b><br/>');
		$wgOut->addWikiText($must.$def['description']);
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
		if(($def['type']=='TEXT')||($def['type']=='LINK')||($def['type']=='LINKNONS')||($def['type']=='NUMBER')) {
			$newvalue=$value;
			if($def['type']=='LINK') 
				$newvalue=preg_replace('%\[\[([^:\|\]]*:|)([^\|\]]+)(\|[^\]]*|)\]\]%','$1$2',$value);
			if($def['type']=='LINKNONS') 
				$newvalue=preg_replace('%\[\[([^:\|\]]*:|)([^\|\]]+)(\|[^\]]*|)\]\]%','$2',$value);
			//background-color:#ffff7f;
			$wgOut->addHTML(
				Xml::input( $techname, 250, $newvalue , array( 'style' => 'width: 95%;'.$style ) )
			);
		}
		if($def['type']=='OLD')
			$wgOut->addHTML(
				Xml::checklabel( wfMessage('templateedit-remove')->text() , 'Remove'.$techname , 'Remove'.$techname , $this->deleteold ) .
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
		$wgOut->addWikiText(wfMessage('templateedit-editformintro',$title->getPrefixedText(),$templateparts['template'])->text());
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'id' => 'templateedit', 'action' => $this->getTitle()->getFullUrl(), 'method' => 'post' ) ) .
			Xml::submitButton(wfMessage('templateedit-save')->text()) .
			html::hidden( 'title',  $this->getTitle()->getPrefixedText() ) .
			html::hidden( 'article', $this->article ).
			html::hidden( 'template', $this->template ).
			html::hidden( 'save', "1" ).
			Xml::tags( 'p', null , wfMessage('templateedit-showeditform')->text() ) .
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
				$def['description'].=' '.wfMessage('templateedit-fieldundefined')->text();
				$background='background-color:#ffff7f;';
			}
			if($def['migration']!="") {
				$migs=explode(';',$def['migration']);
				foreach($migs as $mig)
					$value.=' '.$indexedtp[$mig]['value'];
				$value=trim($value);
			}
			$this->addInput($wgOut,$name,$def,$value,$background);
		};
		$def=Array('type'=>'TITLE','must'=>FALSE,'description'=>'','picklist'=>'');
		$this->addInput($wgOut,wfMessage('templateedit-oldfieldstitle')->text(),$def,'','');
		foreach($indexedtp as $name=>$field)
			if(!$field['used']) {
			$def=Array('type'=>'OLD','must'=>FALSE,'description'=>wfMessage('templateedit-oldfield')->text(),'picklist'=>'');
			$this->addInput($wgOut,$name,$def,$field['value'],'');
		};

		$wgOut->addHTML(
			Xml::closeElement( 'table' ) .
			Xml::submitButton(wfMessage('templateedit-save')->text()) .
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
					'type'=>'OLD','must'=>FALSE,'description'=>wfMessage('templateedit-oldfield')->text(),'picklist'=>'',
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
		$article->doEdit( $newsource,wfMessage('templateedit-templateedited',$templateparts['template'])->text(), null );
		$wgOut->addWikiText('[[:'.$title->getPrefixedText().']]');
		$wgOut->addHTML(
			Xml::tags( 'p', null , wfMessage('templateedit-articlesaved')->text() )
		);
	}


}