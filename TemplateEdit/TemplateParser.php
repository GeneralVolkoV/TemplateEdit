<?php
//The template parser isn't a parser in the computer science way of putting it.
//Anyways it is doing a pretty good job on templates inside templates etc.
//
//Some problems are not solved yet:
//-nowiki, pre, includeonly, noinclude, onlyinclude, html-comments
//-the list of Magic Words is far from complete (see http://www.mediawiki.org/wiki/Help:Magic_words)
//-simple links with a maximum of one "|" work - pictures with more than on don't

class TemplateParser {

	//This function recursively divides the source into elements , which can either be
	//"UNPARSED" source, "IN" brackets "{{" or "OUT" brackets "}}"
	// $article is the source to be divided
	// $elements is the array to which those elements are appended
	protected static function tokenizeTemplates(&$elements,$article) {
		if($article=='') return;
		//find the first "{{" or "}}" if any exist
		$i1=strpos($article,'{{');
		$i2=strpos($article,'}}');
		if(($i1===FALSE)&&($i2===FALSE)) {
			//nothing found - recursion ends.
			$elements[]=Array('type'=>'UNPARSED','source'=>$article);
			return;
		}
		$type='IN';
		$i=$i1;
		$source='{{';
		if($i2<$i1) {
			$type='OUT';
			$i=$i2;
			$source='}}';
		}
		if($i1===FALSE) {
			$i=$i2;
			$type='OUT';
			$source='}}';
		}
		if($i2===FALSE) {
			$i=$i1;
			$type='IN';
			$source='{{';
		}
		//divide the article source and recursively call this function on both parts
		//TODO: shouldn't the second part be sufficient?
		$part1=substr($article,0,$i);
		TemplateParser::tokenizeTemplates($elements,$part1);
		$elements[]=Array('type'=>$type,'source'=>$source);
		$part2=substr($article,$i+2);
		TemplateParser::tokenizeTemplates($elements,$part2);
	}

	//Add a level field to all elements to later find matching closing brackets
	//Since we are only interested in templates, all outside of those get the type "TEXT"
	//and are ignored later on
	// $elements is the array to work on
	protected static function parseTemplateStructure(&$elements) {
	//Rücke den Code gedanklich ein und aus - wenn unterschiedliche Anzahl Error
	//Alles außerhalb wird plaintext
	$level=0;
	for($i=0;$i<count($elements);$i++) {
		if($elements[$i]["type"]=="OUT")
			$level--;
		$elements[$i]["level"]=$level;
		if(($level==0)&&($elements[$i]["type"]=="UNPARSED"))
			$elements[$i]["type"]="TEXT";
		if($elements[$i]["type"]=="IN")
			$level++;
	}
	if($level!=0)
		//TODO: Language dependant
		//TODO: Proper error message inside mediawiki
		die('Number of "{{" and "}}" not equal! '.$level);
	}

	//Get rid of all magic words and all parser functions
	// $elements is the array to work on
	protected static function removeMagicWordsAndParserFunctions(&$elements) {
		//TODO: Get the list of Magic Words from MediaWiki
		$magicwords=Array("FULLPAGENAME","PAGENAME","BASEPAGENAME","ARTICLENAME");
		for($i=0;$i<count($elements);$i++)
			if ($elements[$i]["type"]=="UNPARSED") {
				if(in_array($elements[$i]["source"],$magicwords))
					$elements[$i]["type"]="MAGICWORD";
				if(substr($elements[$i]["source"],0,1)=="#")
					$elements[$i]["type"]="PARSERFUNCTION";
			}
	}

	//In order to use an explode on a template params, the "|" in the links have to be replaced
	//first and back later on. Since we already filtered out "{{" in the first step, we use it
	//for replace.
	// $elements is the array to work on
	protected static function explodeTemplates(&$elements) {
		for($i=0;$i<count($elements);$i++)
			if (($elements[$i]["type"]=="UNPARSED")&&($elements[$i]["level"]>0)) {
				$elements[$i]["type"]="PARSED";
				//Replace "|" in Links by "{{"
				$replaced=preg_replace(
					"%\[\[([^\]\|]*)\|([^\]\|]*)\]\]%",
					'[[$1{{$2]]',
					$elements[$i]["source"]
				);
				//Explode Template by "|"
				$params=explode("|",$replaced);
				$params[0]=preg_replace(
					"%\[\[([^\]\|]*){{([^\]\|]*)\]\]%",
					'[[$1|$2]]',
					$params[0]);
				for($j=1;$j<count($params);$j++) {
					//Replace "{{" in Links by "|"
					$params[$j]=preg_replace(
						"%\[\[([^\]\|]*){{([^\]\|]*)\]\]%",
						'[[$1|$2]]',
						$params[$j]);
					$paramsexplode=explode("=",$params[$j],2);
					if(count($paramsexplode)==2) {
						//Templates with parameter names
						$params[$j]=Array(
							"name" =>trim($paramsexplode[0]),
							"value"=>$paramsexplode[1]
						);
					} else {
						//Templates without parameter names
						$params[$j]=Array(
							"name" =>$j,
							"value"=>$paramsexplode[0]
						);
					}
				}
				if($elements[$i-1]['type']!='OUT') {
					$template=trim(array_shift($params));
					$elements[$i]["template"]=$template;
				}
				$elements[$i]["params"]=$params;
			}
	}

	//Return an array of indexes in the elements, which mark the beginning of a template
	// $elements is the array to get the information from
	protected static function getTemplateList($elements) {
		$templateix=Array();
		for($i=0;$i<(count($elements)-1);$i++)
			if(($elements[$i]["type"]=="IN")&&($elements[$i+1]["type"]=="PARSED"))
				$templateix[]=$i+1;
		return $templateix;
	}

	//Return the index of the end of the template
	// $elements is the array to get the information from
	// $index is the index of the template begin
	protected static function getTemplateEnd($elements,$index) {
		$closing=-1;
		$level=$elements[$index]['level']-1;
		$i=$index;
		while(($i<count($elements))&&($closing==-1)) {
			if(($elements[$i]['type']=='OUT')&&($elements[$i]['level']==$level))
				$closing=$i;
			$i++;
		};
		return $closing;
	}

	//Return the full template with all parameters including sub-templates.
	// $elements is the array to get the information from
	// $index is the index of the template begin
	protected static function getFullTemplate($elements,$index) {
		$closing=TemplateParser::getTemplateEnd($elements,$index);
		if($closing==-1) return;

		$result=$elements[$index]['params'];
		$append='';
		$i=$index+1;
		$baselevel=$elements[$index]['level'];
		while($i!=$closing) {
			if(($elements[$i]['level']==$baselevel)&&($elements[$i]['type']=='PARSED')) {
				if(is_array($elements[$i]['params'][0]))
					$append.=$elements[$i]['params'][0]['value'];
				else
					$append.=$elements[$i]['params'][0];
				$result[count($result)-1]['value'].=$append;
				for($j=1;$j<count($elements[$i]['params']);$j++)
					$result[]=$elements[$i]['params'][$j];
				$append='';
			} else {
				$append.=$elements[$i]['source'];
			}
			$i++;
		}
		if($append!='')
			$result[count($result)-1]['value'].=$append;

		return Array('template'=>$elements[$index]['template'],'params'=>$result);
	}

	//Return the full template with all parameters including sub-templates.
	// $elements is the array to get the information from
	// $index is the index of the template begin
	protected static function getSource($elements,$begin,$end) {
		$result="";
		for($i=$begin;$i<$end;$i++)
			$result.=$elements[$i]['source'];
		return($result);
	}

	//the public function to get the list of templates for the article's source
	public static function getTemplates($source) {
		$elements=Array();
		TemplateParser::tokenizeTemplates($elements,$source);
		TemplateParser::parseTemplateStructure($elements);
		TemplateParser::removeMagicWordsAndParserFunctions($elements);
		TemplateParser::explodeTemplates($elements);
		$templatelist=TemplateParser::getTemplateList($elements);
		$result=Array();
		for($i=0;$i<count($templatelist);$i++)
			$result[]=Array(
				"level"=>$elements[$templatelist[$i]]["level"],
				"name"=>$elements[$templatelist[$i]]["template"]
			);
		return $result;
	}

	//the public funtion to get a template with all params depending on soucr and index
	public static function getTemplateParts($source,$index) {
		$elements=Array();
		TemplateParser::tokenizeTemplates($elements,$source);
		TemplateParser::parseTemplateStructure($elements);
		TemplateParser::removeMagicWordsAndParserFunctions($elements);
		TemplateParser::explodeTemplates($elements);
		$templatelist=TemplateParser::getTemplateList($elements);
		$result=TemplateParser::getFullTemplate($elements,$templatelist[$index]);
		return $result;
	}

	//the public function to replace one template defined by index in the source
	public static function replaceTemplate($source,$index,$replace) {
		$elements=Array();
		TemplateParser::tokenizeTemplates($elements,$source);
		TemplateParser::parseTemplateStructure($elements);
		TemplateParser::removeMagicWordsAndParserFunctions($elements);
		TemplateParser::explodeTemplates($elements);
		$templatelist=TemplateParser::getTemplateList($elements);
		$closing=TemplateParser::getTemplateEnd($elements,$templatelist[$index]);
		$result=TemplateParser::getSource($elements,0,$templatelist[$index]);
		$result.=$replace;
		$result.=TemplateParser::getSource($elements,$closing,count($elements));
		return $result;
	}

}