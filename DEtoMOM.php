<?php
@set_time_limit(0);
ini_set("max_input_time", "60000");
ini_set("max_execution_time", "60000");
ini_set("memory_limit", "104857600");

require("../validate.php");
require("phpQuery-onefile.php");
require("../filter/math/ASCIIMath2TeX.php");
$AMT = new AMtoTeX;

error_reporting(E_ALL);
$cid = 4138;
$dir = 'DE/introbus';
$source = '12211126';

$cookiestr = 'AWSELB=7915C93112F0CE24AD95DE9656717078272AF43F69BEE14472D81AEE1A6397403ED0236D70736D807B969DB073A65E5D56E1AEE922460F5F86E395B86FFA3120B9C70E2E52; UG=nKvqcUdz; JSESSIONID=93D7E951ACC2B5A1FF85C6155702F688';
$base = 'https://lumen-cbl-prod.difference-engine.com/api/v2/assets/';

function fetchbyget($addr) {
	global $cookiestr;
	$getopts = array(
	  'http'=>array(
	    'method'=>"GET",
	    'header'=>"Accept-language: en\r\n" .
		      "Cookie: $cookiestr\r\n"
	  )
	  );
	$context = stream_context_create($getopts);
	return file_get_contents($addr, false, $context);
}
function processImage($image) {
	global $dir;
	if (substr($image,0,4)=='http') {return;}
	if (substr($image,0,1)!='/') { return; }
	copy('https://lumen-cbl-prod.difference-engine.com'.$image, $dir.'/'.basename($image));
	return ('https://textimgs.s3.amazonaws.com/'.$dir.'/'.basename($image));
}

$mainlist = json_decode(fetchbyget($base.$source.';embed=chapters'));

$query = "SELECT itemorder,blockcnt FROM imas_courses WHERE id='$cid'";
$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
list($items,$blockcnt) = mysql_fetch_row($result);
$MOMitems = unserialize($items);

$n = 0;
foreach ($mainlist->chapters as $chp) {
	$chplist = json_decode(fetchbyget($base.$chp->id.';embed=learningObjectives'));
	foreach ($chplist->learningObjectives as $LO) {	
		$items = json_decode(fetchbyget($base.$LO->id.';embed=sageResources'));
		foreach ($items->sageResources as $item) {
			$n++;
			$title = $item->title;
			echo $title.'<br/>';
			$html = $item->instructions->html;
			$html = preg_replace_callback('|<math[^>]*title="(.*?)".*</math>|',function($matches) {
					global $AMT;
					$str = str_replace(array('–','−','sf','ttattnttd','$'), array('-','-','',' and ','\\$'), $matches[1]);
					return '[latex]'.$AMT->convert($str.' ').'[/latex]';
				}, $html);
			phpQuery::newDocumentHTML('<html><body>'.$html.'</body></html>');
			$imgs = pq("img");
			foreach ($imgs as $img) {
				$newpath = processImage(pq($img)->attr("src"));
				pq($img)->attr("src", $newpath);
			}
			$txt = pq("body")->html();
			$txt = str_replace('\\','\\\\',$txt);
			$txt = addslashes($txt);
			
			$pagetitle = addslashes($title);
			$query = "INSERT INTO imas_linkedtext (courseid,title,summary,text,avail) VALUES ('$cid','$pagetitle','','$txt',2)";
			mysql_query($query) or die("Query failed : $query " . mysql_error());
			$linkid= mysql_insert_id();
			$query = "INSERT INTO imas_items (courseid,itemtype,typeid) VALUES ('$cid','LinkedText',$linkid)";
			mysql_query($query) or die("Query failed : $query " . mysql_error());
			$itemid= mysql_insert_id();
			
			$MOMitems[0]['items'][] = $itemid;	
		}
	}
}

$newitems = addslashes(serialize($MOMitems));
$query = "UPDATE imas_courses SET itemorder='$newitems', blockcnt='$blockcnt' WHERE id='$cid'";
mysql_query($query) or die("Query failed : $query " . mysql_error());

?>

