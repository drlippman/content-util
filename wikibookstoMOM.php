<?php
@set_time_limit(0);
ini_set("max_input_time", "6000");
ini_set("max_execution_time", "6000");
require("../validate.php");
require("phpQuery-onefile.php");

error_reporting(E_ALL);

$cid = 4144;

$dir = 'WBcomp';
$base = 

$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$items = unserialize($items);

function processImage($src, $width) {
	global $dir;
	if (substr($src,0,2)=='//') {
		$src = 'http:' . $src;
	} 
	if (!file_exists($dir.'/'.basename($src))) {
		copy($src, $dir.'/'.basename($src));
	}
	return ('https://textimgs.s3.amazonaws.com/'.$dir.'/'.basename($src));
}


//get links

phpQuery::newDocumentFileHTML('http://en.wikibooks.org/wiki/Rhetoric_and_Composition');
$pages = pq("div#mw-content-text li a");
$urls = array();
foreach ($pages as $page) {
	$urls[] = pq($page)->attr("href");
}

foreach ($urls as $url) {
	if ($url{0}=='/') {
		$url = 'http://en.wikibooks.org'.$url;
	}
	phpQuery::newDocumentFileHTML($url);
	$title = pq("h1.firstHeading")->html();
	pq(".mw-editsection")->remove();
	pq(".navbox,.vertical-navbox,.catlinks,.metadata")->remove();
	pq("#See_also")->parent()->nextAll("ul:first")->remove();
	pq("#See_also")->parent()->remove();
	pq("noscript")->remove();
	pq("#toc")->parents("table")->remove();
	pq("#toc")->remove();
	pq("#bottom-navigation")->remove();
	$mainbody = pq("#mw-content-text");
	$imgs = pq($mainbody)->find("img");
	foreach ($imgs as $img) {
		$src = pq($img)->attr("src");
		$width = pq($img)->attr("width");
		$newsrc = processImage($src, $width);
		pq($img)->attr("src",$newsrc);
		pq($img)->removeAttr("width");
		pq($img)->removeAttr("height");
	}
	$as = pq($mainbody)->find("a");
	foreach ($as as $a) {
		if (substr(pq($a)->attr("href"),0,4)!='http' && substr(pq($a)->attr("href"),0,1)!='#') {
			pq($a)->replaceWith(pq($a)->contents());		
		}
	}
	
	$txt = pq($mainbody)->html();
	$txt = preg_replace('/<!--.*?-->/sm','',$txt);
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
