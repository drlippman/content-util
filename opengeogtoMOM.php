<?php
@set_time_limit(0);
ini_set("max_input_time", "6000");
ini_set("max_execution_time", "6000");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);

$cid = 4136;

$dir = 'OGdisasters';
$base = 'http://www.opengeography.org/natural-disasters.html';

$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

function processImage($src, $width) {
	global $dir;
	if ($src{0}=='/') {
		$src = 'http://www.opengeography.org'.$src;
	}
	if (strpos($src,'?')!==false) {
		$pt = explode('?',$src);
		$src = $pt[0];
	}
	if (!file_exists($dir.'/'.basename($src))) {
		copy($src, $dir.'/'.basename($src));
	}
	return ('https://textimgs.s3.amazonaws.com/'.$dir.'/'.basename($src));
}


//get links

phpQuery::newDocumentFileHTML($base);
$pages = pq("div.paragraph a");
$urls = array();
foreach ($pages as $page) {
	$url = pq($page)->attr("href");
	if (strpos($url,'html')!==false) {
		$urls[] = $url;
	}
}

foreach ($urls as $url) {
	if ($url{0}=='/') {
		$url = 'http://www.opengeography.org'.$url;
	}
	phpQuery::newDocumentFileHTML($url);
	$title = pq("h2.wsite-content-title:first")->html();
	$mainbody = pq("div#wsite-content");
	$imgs = pq($mainbody)->find("img");
	foreach ($imgs as $img) {
		$src = pq($img)->attr("src");
		$newsrc = processImage($src, 0);
		pq($img)->attr("src",$newsrc);
	}
	$txt = pq($mainbody)->html();
	$txt = addslashes($txt);
	
	$pagetitle = addslashes($title);
	$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$linkid= mysql_insert_id();
	$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
	mysql_query($query) or die("Query failed : $query " . mysql_error());
	$itemid= mysql_insert_id();
	
	$items[0]['items'][] = $itemid;
}

$newitems = addslashes(serialize($items));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());

?>
