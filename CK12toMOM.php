<?php
@set_time_limit(0);
ini_set("max_input_time", "6000");
ini_set("max_execution_time", "6000");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);

$cid = 4147;

$dir = 'CK12earthsci';
$base = 'http://www.ck12.org/api/flx/get/info/revisions/';
$baseid = 2972779;

$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

function processImage($src, $width) {
	global $dir;
	if (substr($src,0,2)=='//') {
		$src = 'http:' . $src;
	} else if ($src{0}=='/') {
		$src = 'http://www.ck12.org'.$src;
	}
	if (!file_exists($dir.'/'.basename($src))) {
		copy($src, $dir.'/'.basename($src));
	}
	return ('https://textimgs.s3.amazonaws.com/'.$dir.'/'.basename($src));
}

function processhtml($html) {
	phpQuery::newDocumentHTML($html);
	$imgs = pq("body")->find("img");
	foreach ($imgs as $img) {
		$src = pq($img)->attr("src");
		$width = pq($img)->attr("width");
		$newsrc = processImage($src, $width);
		pq($img)->attr("src",$newsrc);
		pq($img)->removeAttr("width");
		pq($img)->removeAttr("height");
	}
	$as = pq("body")->find("a");
	foreach ($as as $a) {
		if (substr(pq($a)->attr("href"),0,4)!='http' && substr(pq($a)->attr("href"),0,1)!='#') {
			pq($a)->replaceWith(pq($a)->contents());		
		}
	}
	$iframes = pq("iframe");
	foreach ($iframes as $iframe) {
		$src = pq($iframe)->attr("src");
		if (preg_match('|/flx/show/video.*?(youtu.*)$|',$src,$matches)) {
			$parts = explode('%', $matches[1]);
			pq($iframe)->replaceWith('<p>https://www.'.$parts[0].'</p>');
		}
	}
	
	$txt = pq("body")->html();
	return $txt;
}


$book = json_decode(file_get_contents($base.$baseid.'?format=json'));
$chapters = array();
foreach ($book->response->artifacts[0]->revisions[0]->children as $child) {
	$chapters[] = $child->artifactRevisionID;
}

foreach($chapters as $chp) {
	echo "Processing chapter $chp<br/>";
	$chpcon = json_decode(file_get_contents($base.$chp.'?format=json'));
	$chpdet = $chpcon->response->artifacts[0];
	$chptitle = $chpdet->title;
	$chptxt = processhtml($chpdet->xhtml_prime);
	
	$pagetitle = addslashes($chptitle);
	$txt = addslashes($chptxt);
	$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$linkid= mysql_insert_id();
	$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$itemid= mysql_insert_id();
	
	$items[0]['items'][] = $itemid;
	
	$secs = array();
	foreach ($chpdet->revisions[0]->children as $child) {
		$secs[] = $child->artifactRevisionID;
	}
	foreach ($secs as $sec) {
		echo "Processing section $sec<br/>";
		$seccon = json_decode(file_get_contents($base.$sec.'?format=json'));
		$secdet = $seccon->response->artifacts[0];
		$sectitle = $secdet->title;
		$sectxt = processhtml($secdet->xhtml_prime);
		$pagetitle = addslashes($sectitle);
		$txt = addslashes($sectxt);
		$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
		mysql_query($query) or die("Query failed : $query " . mysql_error());
		$linkid= mysql_insert_id();
		$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
		mysql_query($query) or die("Query failed : $query " . mysql_error());
		$itemid= mysql_insert_id();
		
		$items[0]['items'][] = $itemid;
	}
}

$newitems = addslashes(serialize($items));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());

?>
