<?php
//David Lippman 2014 for Lumen Learning
//Cleans up OpenStax EPUBs before import into Candela.

// GPL License

@set_time_limit(0);
ini_set("max_input_time", "600");
ini_set("max_execution_time", "600");
ini_set('memory_limit', '800M');

require("phpQuery-onefile.php");

$zipfile = "cheminter.zip";

$zip = new ZipArchive;

$tmpcnt = 0;
function processImage($image, $width)
{
	global $tmpcnt, $contentdir;
	$tmpcnt++;
    if (trim($width)==='' || $width===0) { return;}
    global $zip;
    $filename = $contentdir.'/'.urldecode($image);
    $im = imagecreatefromstring($zip->getFromName($filename));
    if ($im===false) {
    	    echo "error loading $image<br/>";
    	    return;
    }
    /*(if (strpos($image,'.png')!==false) {
    	    $im = imagecreatefrompng($f.$image);
    } else {
    	    $im = imagecreatefromjpeg($f.$image);
    }
    $size = getimagesize($f.$image);
    $w = $size[ 0 ];
    $h = $size[ 1 ];
    */
    $w = imagesx($im);
    $h = imagesy($im);
    
    if ($w<=2*$width) {
    	    imagedestroy($im);
    	    return;
    }
    
    $tw = 2*$width;
    $th = $h/$w*$tw;
   
    $imT = imagecreatetruecolor( $tw, $th );
    imagecopyresampled( $imT, $im, 0, 0, 0, 0, $tw, $th, $w, $h ); // resize to width
   
    // save the image
    
    if (strpos($image,'.png')!==false) {
    	    ob_start();
    	    imagejpeg( $imT, null, 9 );
    	    $contents = ob_get_clean();
    	    $zip->addFromString($filename, $contents);
    } else {
    	    ob_start();
    	    imagejpeg( $imT, null, 90 );
    	    $contents = ob_get_clean();
    	    $zip->addFromString($filename, $contents);
    }
    imagedestroy($im);
    imagedestroy($imT);
}

$zip->open($zipfile);
$toprocess = array();
for( $i = 0; $i < $zip->numFiles; $i++ ){ 
    $stat = $zip->statIndex( $i );
    if (preg_match('/\.html/',basename($stat['name']))) {
    	    $toprocess[] = $stat['name'];
    }
}
$zip->close();
$n = 0;
$contentdir = '';
foreach ($toprocess as $file) {
	$zip->open($zipfile);
	$thisfile = basename($file);
	
	$contentdir = dirname($file);
	
	$html = $zip->getFromName($file);
	
	//clear any HTML comments
	$html = preg_replace('|<!--(.*?)-->|s','',$html);
	
	//remove empty spans.  Doing it this way so I can replace it with
	//blank space
	$html = preg_replace('/<span[^>]*>(&nbsp;|\s)*<\/span>/s',' ',$html);
	
	//replace empty target with _blank
	$html = str_replace('target=""','target="_blank"',$html);
	
	echo "loading $thisfile<br/>";
	phpQuery::newDocumentHTML($html);

	$as = pq("a");
	foreach ($as as $a) {
		$href = pq($a)->attr("href");
		if ($href != null) {
			if ($href{0}=='#') {
				continue;
			} else if (strpos($href,"$thisfile#")===0) {
				//strip off file name if pointing to self
				pq($a)->attr("href", str_replace($thisfile,'',$href));
			} else if (strlen($href)>3 && substr($href,0,4)=='http') {
				continue;	
			} else {
				//remove link
				if (pq($a)->contents()=='*') {
					pq($a)->remove();
				} else {
					pq($a)->replaceWith(pq($a)->contents());
				}
			}
		}
	}
	
	$imgs = pq("img");
	foreach ($imgs as $img) {
		processImage(pq($img)->attr("src"), pq($img)->attr("width"));
	}
	
	//remove .cnx-eoc which houses exercises, because we're dealing with those separately
	//pq(".cnx-eoc")->remove();
	
	$out = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml">';
	$out .= str_replace("\n", ' ',pq("html")->html());
	$out .= '</html>';
	$zip->addFromString($file, $out);
	echo "Edited $file<br/>";
	$zip->close();
	$n++;
}

echo $n;
?>
