<?php
@set_time_limit(0);
ini_set("max_input_time", "600");
ini_set("max_execution_time", "600");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);
$cid = 4146;
$folder = "amgovtpol";
$startchp = 5;
$endchp = 21;
//$webroot = 'http://www.savingstudentsmoney.org/FWK/econ/';


$webroot = 'https://textimgs.s3.amazonaws.com/'.$folder.'/';

if (!is_writable(__DIR__)) { die('directory not writable'); }

$curdir = rtrim(dirname(__FILE__), '/\\');
mkdir("$curdir/$folder/images");

// $image is $_FILES[ <image name> ]
// $imageId is the id used in a database or wherever for this image
// $thumbWidth and $thumbHeight are desired dimensions for the thumbnail

$imgcnt = 0;
function processImage($f,$image, $thumbWidth, $thumbHeight )
{
    global $imgcnt,$folder;
    $imgcnt++;
    $curdir = rtrim(dirname(__FILE__), '/\\');
    $galleryPath = "$curdir/$folder/images/";
    
    if (!file_exists($f.$image)) {
    	    return 'false';
    }

    if (strpos($f.$image,'.png')!==false) {
    	    $im = imagecreatefrompng($f.$image);
    } else {
    	    $im = imagecreatefromjpeg($f.$image);
    }
    if ($im===false) {
    	    return 'false';
    }
    $size = getimagesize($f.$image);
    $w = $size[ 0 ];
    $h = $size[ 1 ];
   
    // create thumbnail
    $tw = $thumbWidth;
    $th = $thumbHeight;
    
    if ($w<=500) {
    	    return $image;
    }
    $imname = 'sm_'.str_replace(array('.png','.jpg','.jpeg'),'',basename($image));
   
    if ( $w/$h > $tw/$th )
    { // wider
	$tmph = $h*($tw/$w);
	$imT = imagecreatetruecolor( $tw, $tmph );
	imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $tmph, $w, $h ); // resize to width
    }else
    { // taller
      
	//nocrop version
	$tmpw = $w*($th/$h);
	$imT = imagecreatetruecolor( $tmpw, $th );
	imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tmpw, $th, $w, $h ); // resize to width
    }
   
    // save the image
   imagejpeg( $imT, $galleryPath . $imname . '.jpg', 71 ); 
   return 'images/'.$imname . '.jpg';
}


function fileize($str) {
	global $webroot;
	/*$attr = '<hr />
<div class="smallattr" style="font-size: x-small;">This page is licensed under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0" rel="license">Creative Commons Attribution Non-Commercial Share-Alike License</a> and contains content from a variety of sources published under a variety of open licenses, including:
<ul>
<li>Content created by Anonymous under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0" rel="license">Creative Commons Attribution Non-Commercial Share-Alike License</a></li>
<li>Original content contributed by Lumen Learning</li>
</ul>
<p>If you believe that a portion of this Open Course Framework infringes another\'s copyright, <a href="http://lumenlearning.com/copyright">contact us</a>.</p>
</div>';*/
	$str = preg_replace('/<a\s+class="glossterm">(.*?)<\/a>/sm','<span class="glossterm">$1</span>',$str);
	
	//remove glossdef
	$str = preg_replace('/<span\s+class="glossdef">(.*?)<\/span>/sm','',$str);
	
	$str = preg_replace('/<a\s+class="footnote"[^>]*#(.*?)".*<\/a>(.*?)<\/sup>/sm','<a class="footnote" href="#$1">$2</sup></a>',$str);
	$str = preg_replace('/<a[^>]+name="ftn\.(.*?)".*?<\/a>/sm','<a name="ftn.$1"></a>',$str);
	$str = preg_replace('/<a[^>]*catalog\.flatworldknowledge[^>]*>(.*?)<\/a>/sm',' $1 ',$str);
	$str = preg_replace('/<p[^>]*>/sm','<p>',$str);
	$str = preg_replace_callback('/class="([^"]*)"/sm',function($m) {
					$classes = preg_split('/\s+/',trim($m[1]));
					foreach ($classes as $k=>$v) {
						$classes[$k] = 'im_'.$v;
					}
					return 'class="'.implode(' ',$classes).'"';
				},$str);
	
	return $str;//.$attr;
	
}


$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

//3~sFRGaRwnRO2kPq8AeMMW8FIQbqF40n9RjgjQHlXRfK2eJbWStDqu6TGAiROG4fso
$chapters = array();
$sections = array();
$secind = array();
$images = array();
//for ($k=4;$k<=4;$k++) {
for ($k=$startchp;$k<=$endchp;$k++) {

	if ($k<10) {
		$shortsec = 's0'.$k;
		$source = 'section_0'.$k;
	} else {
		$shortsec = 's'.$k;
		$source = 'section_'.$k;
	}

	foreach (glob($folder .'/'.$shortsec.'-*.html') as $filename) {
		if (preg_match('/'.$shortsec.'-[^\d]/',$filename)) {
			echo $filename;
			$c = file_get_contents($filename);
		}
	}
	phpQuery::newDocumentHTML($c);
	
	//strip xref anchors
	$xrefs = pq("a.xref");
	foreach ($xrefs as $xref) {
		pq($xref)->replaceWith(pq($xref)->text());
	}
	
	//remove glossdef's, since don't have JS to display them
	pq("span.glossdef")->remove();
	
	//remove copyrighted images
	$fig = pq("div.figure");
	foreach ($fig as $f) {
		$cr = pq($f)->find(".copyright")->html();
		if (strpos($cr, '©')!==false || strpos($cr, '&copy;')!==false) {
			pq($f)->remove();	
		}
	}
	
	//grab images
	$imgs = pq("img");
	$sl = strlen($source);
	foreach ($imgs as $img) {
		$src = pq($img)->attr("src");
		if (substr($src,0,$sl)==$source) {
			$newpath = processImage('./'.$folder.'/',$src, 500, 700);
			if ($newpath=='false') {
				pq($img)->replaceWith("<span>[Missing Image]</span>");	
			} else {
				pq($img)->attr("src",$webroot.$newpath)->wrap('<a target="_blank" href="'.$webroot.$src.'"/>');
			}
		}
	}
	
	$chp = pq("#book-content .chapter");
	$chptitle = htmlentities(str_replace("\n",' ',strip_tags(pq($chp)->children("h1")->text())), ENT_XML1);
	$chpfolder = array("name"=>$chptitle, "id"=>$blockcnt, "startdate"=>0, "enddate"=>2000000000, "avail"=>2, "SH"=>"HO", "colors"=>"", "public"=>0, "fixedheight"=>0, "grouplimit"=>array());
	$blockcnt++;
	$chpfolder['items'] = array();
	$txt = pq($chp)->html();
	
	//add chapter text
	$txt = fileize('<div class="section">'.$txt.'</div>');
	$txt = addslashes($txt);
	$chptitle = addslashes($chptitle);
	$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$chptitle','','$txt',2)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$linkid= mysql_insert_id();
	$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$itemid= mysql_insert_id();
	$chpfolder['items'][] = $itemid;
	
	$secs = pq("#book-content > .section");
	foreach ($secs as $j=>$asec) {
		$sectitle = htmlentities(str_replace("\n",' ',strip_tags(pq($asec)->children("h2")->text())));
		/*$subs = pq($asec)->children(".section");
		if (count($subs)>0) {
			//$secfolder = array("name"=>$sectitle, "id"=>$blockcnt, "startdate"=>0, "enddate"=>2000000000, "avail"=>2, "SH"=>"HO", "colors"=>"", "public"=>0, "fixedheight"=>0, "grouplimit"=>array());
			//$blockcnt++;
			//$secfolder['items'] = array();
			
			foreach ($subs as $sub) {
				$subtitle = htmlentities(str_replace("\n",' ',strip_tags(pq($sub)->children("h2")->text())));
				$txt = fileize('<div class="section">'.pq($sub)->html().'</div>');
				$txt = addslashes($txt);
				$subtitle = addslashes($subtitle);
				$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$subtitle','','$txt',2)";
				mysql_query($query) or die("Query failed : $query " . mysql_error());
				$linkid= mysql_insert_id();
				$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
				mysql_query($query) or die("Query failed : $query " . mysql_error());
				$itemid= mysql_insert_id();
				$secfolder['items'][] = $itemid;
				pq($sub)->remove();
			}
		}
		*/
		
		//now that subs are removed, grab section text
		$txt = fileize('<div class="section">'.pq($asec)->html().'</div>');
		$txt = addslashes($txt);
		//if (count($subs)>0) {
		//	$sectitle = preg_replace('/^\d+\.\d+\s*/','',trim($sectitle));
		//}
		$sectitle = addslashes($sectitle);
		$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$sectitle','','$txt',2)";
		mysql_query($query) or die("Query failed : $query " . mysql_error());
		$linkid= mysql_insert_id();
		$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
		mysql_query($query) or die("Query failed : $query " . mysql_error());
		$itemid= mysql_insert_id();
		/*if (count($subs)>0) {
			array_unshift($secfolder['items'],$itemid);
			$chpfolder['items'][] = $secfolder;
		} else {*/
			$chpfolder['items'][] = $itemid;
		//}
		
	}
	$items[] = $chpfolder;
}

$newitems = addslashes(serialize($items));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());
?>
Done
