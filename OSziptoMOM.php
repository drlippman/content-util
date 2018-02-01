<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
</head>
<body>
<?php
@set_time_limit(0);
ini_set("max_input_time", "9000");
ini_set("max_execution_time", "9000");
require("../init_without_validate.php");
require("phpQuery-onefile.php");
require("unicodetotex.php");

error_reporting(E_ALL);

$cid = 13342;
//make sure course has one folder in it
$dir = 'OSbioconc';

$meta['book'] = 'Biology';
$meta['author'] = 'OpenStax';
$meta['org'] = 'OpenStax';
$meta['license'] = 'CC-BY';
$meta['licenseurl'] = 'http://creativecommons.org/licenses/by/4.0/';
$bookname = 'OpenStax Biology';

//read in collection file to get module order
//phpQuery::newDocumentFileXML($dir.'/collection.xml');
$c = file_get_contents($dir.'/collection.xml');
$c = preg_replace('/<\w+:/','<',$c);
$c = preg_replace('/<\/\w+:/','</',$c);
phpQuery::newDocumentXML($c);

//downsize image quality of jpgs
function processImage($image, $width) {
	global $dir;
	if (strpos($image,'.jpg')!==false) {
		$im = imagecreatefromjpeg($dir.'/'.$image);
		if (trim($width)==='' || $width===0) {	
			imagejpeg($im, $dir.'/'.$image, 90);
		} else {
			$w = imagesx($im);
			$h = imagesy($im);
			
			if ($w<=2*$width) {
			    imagejpeg($im, $dir.'/'.$image, 90);
			    return;
			}
			$tw = 2*$width;
			$th = $h/$w*$tw;
			
			$imT = imagecreatetruecolor( $tw, $th );
			imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $th, $w, $h ); // resize to width
			imagejpeg($imT, $dir.'/'.$image, 90);
		}
	} else if (strpos($image,'.png')!==false) {
		$im = imagecreatefrompng($dir.'/'.$image);
		if (trim($width)==='' || $width===0) {	
			imagepng($im, $dir.'/'.$image, 9);
		} else {
			$w = imagesx($im);
			$h = imagesy($im);
			
			if ($w<=2*$width) {
			    imagepng($im, $dir.'/'.$image, 9);
			    return;
			}
			$tw = 2*$width;
			$th = $h/$w*$tw;
			
			$imT = imagecreatetruecolor( $tw, $th );
			imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $th, $w, $h ); // resize to width
			imagepng($imT, $dir.'/'.$image, 9);
		}
	} else {
		return;
	}
}

//this one is for converting jpg to png
function processImagePng($image, $width) {
	global $dir;
	
	$out = $dir.'/imgs/'.str_replace('.jpg','.png',basename($image));
	
	
	if (strpos($image,'.jpg')===false) {
		copy($dir.'/'.$image, $out);
		return;
	} 
		
	$im = imagecreatefromjpeg($dir.'/'.$image);
	
	
	if (trim($width)==='' || $width===0) {	
		imagepng($im, $out, 9);
	} else {
		$w = imagesx($im);
		$h = imagesy($im);
		
		if ($w<=2*$width) {
		    imagepng($im, $out, 9);
		    return;
		}
		$tw = 2*$width;
		$th = $h/$w*$tw;
		
		$imT = imagecreatetruecolor( $tw, $th );
		imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $th, $w, $h ); // resize to width
		imagepng($imT, $out, 9);
	}
}

$mods = pq("module");
$modlist = array();
foreach ($mods as $mod) {
	$name = pq($mod)->parent()->siblings("title")->text();
	if ($name=='') { $name = "none";}
	if (isset($modlist[$name])) {
		$modlist[$name][] = pq($mod)->attr("document");
	} else {
		$modlist[$name]= array(pq($mod)->attr("document"));
	}
}

//$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
//$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
$stm = $DBH->prepare("SELECT itemorder,blockcnt FROM imas_courses WHERE id=?");
$stm->execute(array($cid));
list($items,$blockcnt) = $stm->fetch(PDO::FETCH_NUM); //mysql_fetch_row($result);
$items = unserialize($items);

//process each module
foreach ($modlist as $chpname=>$mods) {
	$chpfolder = array("name"=>$chpname, "id"=>$blockcnt, "startdate"=>0, "enddate"=>2000000000, "avail"=>2, "SH"=>"HO", "colors"=>"", "public"=>0, "fixedheight"=>0, "grouplimit"=>array());
	$blockcnt++;
	$chpfolder['items'] = array();
	foreach ($mods as $mod) {
	
		$c = file_get_contents($dir.'/'.$mod.'/index.cnxml.html');
		$c = str_replace(array('&#8220;','&#8221;'),'"', $c);
		phpQuery::newDocumentHTML($c);
		$pagetitle = trim(pq("title")->html());
		
		pq('div[data-type=document-title]')->remove();
		pq('div[data-type=abstract]')->addClass("textbox")->addClass("learning-objectives")->prepend("<h3>Learning Objectives</h3>");
		pq('div.exercise')->addClass("textbox");
		
		//rewrite image urls
		$imgs = pq("img");
		foreach ($imgs as $img) {
			$src = pq($img)->attr("src");
			$width = pq($img)->attr("width");
			$src = 'https://textimgs.s3.amazonaws.com/'.$dir.'/'.$mod.'/'.basename($src);
			processImage($mod.'/'.basename($src), $width);
			pq($img)->attr("src",$src);
			
			//the following was handy in one of the books where .jpg's were 
			//much bigger than .png would be because of the content type.
			//processImagePng($mod.'/'.basename($src), $width);
			//$src = 'https://textimgs.s3.amazonaws.com/'.$dir.'/'.str_replace('.jpg','.png',basename($src));
			//pq($img)->attr("src",$src)->attr("data-media-type","image/png");
		}
	
		//strip internal links
		$as = pq("a");
		foreach ($as as $a) {
			if (substr(pq($a)->attr("href"),0,4)!='http' && substr(pq($a)->attr("href"),0,1)!='#') {
				pq($a)->replaceWith(pq($a)->contents());		
			}
		}
		
		//grab and process review questions
/*	
		$revq = pq("section.review-questions");
		if (count($revq)>0) {
			$assessinfo = makeQTI($pagetitle, $revq);
			//pq($revq)->html('<h1>Review Questions</h1>'.$assessinfo);
		} else {
			$revq = pq("section.multiple-choice");
			if (count($revq)>0) {
				$assessinfo = makeQTI($pagetitle, $revq);
				//pq($revq)->html('<h1>Review Questions</h1>'.$assessinfo);
			} else { 
				$revq = pq("section.section-quiz");
				if (count($revq)>0) {
					$assessinfo = makeQTI($pagetitle, $revq);
					//pq($revq)->html('<h1>Section Quiz</h1>'.$assessinfo);
				}	
			}
		}
*/		
		$txt = pq("body")->html();
		
		
		echo "Processing $pagetitle.  Initial length ".strlen($txt);
		if (strpos($txt,'<math')!==false) {
			//** TO DO
			//add stuff to 
			//  -convert weird whitespace to simple whitespace
			//  -change 2x-3 to 2x -3
			//  -change < to &lt; inside mathex
			//  -move end . and , outside latex tags
			//convert mathml to latex
			$txt = preg_replace('/<mspace\s+width="[\d\.]+em"\/>/','',$txt);
			$txt = preg_replace('/<mspace\s+width="[\d\.]+em"><\/mspace>/','',$txt);
			$txt = preg_replace('/<annotation-xml.*?<\/annotation-xml>/','',$txt);
			$txt = preg_replace('/<\/?semantics>/','',$txt);
			$url = 'http://54.191.55.159/mmltex/conv.php';
			$data = array('html'=>$txt);
			$options = array(
			    'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded; charset=utf-8\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data),
			    ),
			);
			$context  = stream_context_create($options);
			$txt = file_get_contents($url, false, $context);
			$txt = preg_replace_callback('/\[latex\].*?\[\/latex\]/s', function($matches) {
					$out = preg_replace('/\s+/u', ' ', $matches[0]);
					$out = str_replace('[latex]$','[latex]',$out);
					$out = str_replace('$[/latex]','[/latex]',$out);
					$out = str_replace(array('∑','−','→','⇋','⇌','≤','≥','±','×','°','·'), array('\Sigma ','-','\rightarrow ','\leftrightharpoons ','\rightleftharpoons ','\leq ', '\geq ', '\pm ', '\times ', '^\circ ', '\cdot '), $out);
					$out = str_replace(array('\text{Δ}','Δ','σ','’','\x27f6','λ','∞','Φ','θ','⟶'), array('\Delta ','\Delta ','\sigma ',"'",'\longrightarrow ','\lambda ','\infty ','\phi ', '\theta ','\longrightarrow '), $out);
					$out = str_replace(array('־','‐','‑','‒','–','—','―','−'), '-', $out);
					$out = preg_replace('/(\w)\-(\d)/', '$1 - $2', $out);
					$out = unicodetotex($out);
					$out = preg_replace('/mathrm{(sin|cos|tan|sec|csc|cot)}/','$1 ',$out);
					$out = str_replace('.[/latex]', '[/latex].', $out);
					$out = str_replace(',[/latex]', '[/latex],', $out);
					$out = str_replace(';[/latex]', '[/latex];', $out);
					$out = str_replace('\text{;}[/latex]', '[/latex];', $out);
					$out = str_replace('\text{,}[/latex]', '[/latex],', $out);
					$out = str_replace('<', '&lt;', $out);
					$out = str_replace('>', '&gt;', $out);
					return $out;
				}, $txt);
			
		}
		echo ". Post conv ".strlen($txt).'<br/>';

		//$txt = addslashes($txt);
		
		//$pagetitle = addslashes($pagetitle);
		//$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
		//mysql_query($query) or die("Query failed : $query " . mysql_error());
		//$linkid= mysql_insert_id();
		$stm = $DBH->prepare("INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES (?,?,'',?,2)");
		$stm->execute(array($cid,$pagetitle,$txt));
		$linkid = $DBH->lastInsertId();
		
		//$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
		//mysql_query($query) or die("Query failed : $query " . mysql_error());
		//$itemid= mysql_insert_id();
		$stm = $DBH->prepare("INSERT INTO imas_items (courseid,itemtype,typeid) VALUES (?,'LinkedText',?)");
		$stm->execute(array($cid,$linkid));
		$itemid=$DBH->lastInsertId();
		
		$chpfolder['items'][] = $itemid;
	}
	$items[] = $chpfolder;
	
}

//$newitems = addslashes(serialize($items));
//$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
//mysql_query($query) or die("Query failed : $query " . mysql_error());
$newitems = serialize($items);
$stm = $DBH->prepare("UPDATE imas_courses SET itemorder=?, blockcnt=? WHERE id=?");
$stm->execute(array($newitems,$blockcnt,$cid));

$n = 0;
function makeQTI($pagetitle, $revq) {
	global $meta,$n,$bookname,$dir,$assessuniq;
	$lets = array('A','B','C','D','E','F','G');
	$meta['chapter'] = $pagetitle;
	$returntext = '';
	$out = startqti($pagetitle);
	
	//remove "Answer" text from solutions
	pq($revq)->find("div[data-type=solution] div[data-type=title]")->remove();
	
	$qs = pq($revq)->find("div[data-type=exercise]");
	foreach ($qs as $k=>$q) {
		$n++;
		$prob = pq($q)->find("div[data-type=problem]");
		$prompt = '';
		$lis = pq($prob)->find("p > span[data-type=list]")->find("span[data-type=item]");
		if (count($lis)>0) {
			$solntext = array();
			foreach ($lis as $li) {
				$solntext[] = pq($li)->html();
			}
			//$lis = pq($prob)->children("ol")->remove();
			$lis = pq($prob)->find("p > span[data-type=list]")->remove();
			$prompt = pq($prob)->html();
			$soln = trim(pq($q)->find("div[data-type=solution]")->text());;
			//get solution letter index
			$corrects = array(array_search($soln, $lets));
		}
		$lis = pq($prob)->children("ol")->find("li");
		if (count($lis)>0) {
			$solntext = array();
			foreach ($lis as $li) {
				$solntext[] = pq($li)->html();
			}
			$lis = pq($prob)->children("ol")->remove();
			$prompt = pq($prob)->html();
			$soln = trim(pq($q)->find("div[data-type=solution]")->text());;
			//get solution letter index
			$corrects = array(array_search($soln, $lets));
		}
		if ($prompt != '') {
		
		$out .= '<item ident="'.$assessuniq.'q'.$n.'" title="Question #'.($k+1).'">
		<itemmetadata>
		  <qtimetadata>
		    <qtimetadatafield>
		      <fieldlabel>question_type</fieldlabel>
		      <fieldentry>'.((count($corrects)>1)?'multiple_answers_question':'multiple_choice_question').'</fieldentry>
		    </qtimetadatafield>
		    <qtimetadatafield>
		      <fieldlabel>points_possible</fieldlabel>
		      <fieldentry>1</fieldentry>
		    </qtimetadatafield>
		   ';
		    $out.= '</qtimetadata>
		</itemmetadata>
		<presentation>
		  <material>
		    <mattext texttype="text/html">'.htmlentities(trim($prompt),ENT_XML1).'</mattext>
		  </material>
		  <response_lid ident="response1" rcardinality="Single">
		    <render_choice>';
		    foreach ($solntext as $k=>$it) {
			    $out .= '<response_label ident="'.$assessuniq.'q'.$n.'o'.$k.'">
			<material>
			  <mattext texttype="text/html">'.htmlentities(trim($it),ENT_XML1).'</mattext>
			</material>
		      </response_label>';
		   }
		   $out .= '
		    </render_choice>
		  </response_lid>
		</presentation>
		<resprocessing>
		  <outcomes>
		    <decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/>
		  </outcomes>
		  <respcondition continue="No">
		    <conditionvar>
		    ';
		    if (count($corrects)==1) {
			    $out .= '<varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$corrects[0].'</varequal>';
		    } else {
			    $out .= '<and>';
			    foreach ($solntext as $k=>$it) {
				    if (in_array($k,$corrects)) {
					    $out .= '<varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$k.'</varequal>';
				    } else {
					    $out .= '<not><varequal respident="response1">'.$assessuniq.'q'.$n.'o'.$k.'</varequal></not>';
				    }
			    }
			    $out .= '</and>';
		    }
		    
		    
		     $out .= '</conditionvar>
		    <setvar action="Set" varname="SCORE">100</setvar>
		  </respcondition>
		</resprocessing>
	      </item>';
		} else {
			$returntext .= '<div data-type="exercise">'.pq($q)->html().'</div>';
		}
		
	}
	
	$out .= endqti();
	$cleantitle = preg_replace('/\W/','_',$pagetitle);
	file_put_contents($dir.'/OEA/'.$cleantitle.".xml", $out);
	echo "$cleantitle.xml<br/>";
	return "<p>$cleantitle.xml</p>".$returntext;
}

function startqti($assessv='') {
	global $meta,$assessn,$dir,$assessuniq,$bookname;
	if ($assessv=='') {
		$assessv = $assessn;
		$assessn++;
	}
	$assessuniq = $dir.'-20150516-'.preg_replace('/\W/','-',$assessv);
	
	$c = '<?xml version="1.0" encoding="UTF-8"?>
<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsglobal.org/xsd/ims_qtiasiv1p2 http://www.imsglobal.org/xsd/ims_qtiasiv1p2p1.xsd">
  <assessment ident="'.$assessuniq.'" title="'.$bookname.': '.$assessv.'">
    <qtimetadata>
      <qtimetadatafield>
        <fieldlabel>cc_maxattempts</fieldlabel>
        <fieldentry>1</fieldentry>
      </qtimetadatafield>';
    if (isset($meta['book'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_publication</fieldlabel>
        <fieldentry>'.$meta['book'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['org'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_organization</fieldlabel>
        <fieldentry>'.$meta['org'].'</fieldentry>
      </qtimetadatafield>';
     } 
     if (isset($meta['author'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_author</fieldlabel>
        <fieldentry>'.$meta['author'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['chapter'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_chapter</fieldlabel>
        <fieldentry>'.$meta['chapter'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['chpn'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_chapter_number</fieldlabel>
        <fieldentry>'.$meta['chpn'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['license'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_license</fieldlabel>
        <fieldentry>'.$meta['license'].'</fieldentry>
      </qtimetadatafield>';
     }
     if (isset($meta['licenseurl'])) {
    	    $c .= ' <qtimetadatafield>
        <fieldlabel>qmd_license_id</fieldlabel>
        <fieldentry>'.$meta['licenseurl'].'</fieldentry>
      </qtimetadatafield>';
     }
     
    $c .=' </qtimetadata>
    <section ident="root_section">';
    return $c;
}

function endqti() {
	$c =  '</section>
  </assessment>
</questestinterop>';
	return $c;
}


?>
