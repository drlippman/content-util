<!DOCTYPE html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
<title>Quiz Display</title>
</head>
<body>
<?php

if (!isset($_FILES['userfile'])) {
?>
<form enctype="multipart/form-data" action="qti.php" method="POST">
    <!-- MAX_FILE_SIZE must precede the file input field -->
    <input type="hidden" name="MAX_FILE_SIZE" value="30000000" />
    <!-- Name of input element determines name in $_FILES array -->
    Select QTI quiz export: <input name="userfile" type="file" />
    <input type="submit" value="Send File" />
</form>
<?php
} else {
$uploadfile = './uploads/' . basename($_FILES['userfile']['name']);
if (!move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo 'File Upload Error';
	exit;
}
require("phpQuery-onefile.php");

$zip = new ZipArchive;
$zip->open($uploadfile);

$toprocess = array();
for ($i = 0; $i < $zip->numFiles; $i++) {
     $filename = $zip->getNameIndex($i);
     if ($filename == 'imsmanifest.xml') continue;
     if ($filename == 'assessment_meta.xml') continue;
     if (strpos($filename,'.xml')!== false) {
     	     $toprocess[] = $filename;
     }
}

for ($i = 0; $i<count($toprocess); $i++) {
	phpQuery::newDocumentXML($zip->getFromName($toprocess[$i]));
	
	$title = pq("assessment")->attr("title");

	echo '<h2>'.$title.'</h2>';
	
	$items = pq("item");
	foreach ($items as $item) {
		$qtitle = pq($item)->attr("title");
		$qtimeta = pq($item)->find("qtimetadatafield");
		$qtype = '';
		foreach ($qtimeta as $mdf) {
			if (pq($mdf)->children("fieldlabel")->text()=='question_type') {
				$qtype = pq($mdf)->children("fieldentry")->text();
				break;
			}
		}
		if ($qtype != 'multiple_choice_question') {
			echo '<!-- Unsupposed question type '.$qtype.' -->';
			continue;
		}
		echo '<h4>'.$qtitle.'</h4>';
		$qtext = html_entity_decode(pq($item)->find("presentation > material > mattext")->text());
		echo $qtext;
		
		$choices = pq($item)->find("render_choice mattext");
		echo '<ul>';
		foreach ($choices as $choice) {
			echo '<li>'.pq($choice)->text().'</li>';
		}
		echo '</ul>';
	}
}
}
?>
</body>
</html>
