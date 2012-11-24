<?php
/**
 * Elgg single upload action for flash/ajax uploaders
 */

elgg_load_library('tidypics:upload');

$album_guid = (int) get_input('album_guid');
$file_var_name = get_input('file_var_name', 'Image');
$batch = get_input('batch');

$album = get_entity($album_guid);
if (!$album) {
	echo elgg_echo('tidypics:baduploadform');
	exit;
}

// probably POST limit exceeded
if (empty($_FILES)) {
	trigger_error('Tidypics warning: user exceeded post limit on image upload', E_USER_WARNING);
	register_error(elgg_echo('tidypics:exceedpostlimit'));
	exit;
}

$file = $_FILES[$file_var_name];

$mime = tp_upload_get_mimetype($file['name']);
if ($mime == 'unknown') {
	echo 'Not an image';
	exit;
}

// we have to override the mime type because uploadify sends everything as application/octet-string
$file['type'] = $mime;

$image = new TidypicsImage();
$image->container_guid = $album->getGUID();
$image->setMimeType($mime);
$image->access_id = $album->access_id;
$image->batch = $batch;

try {
	$image->save($file);
	$album->prependImageList(array($image->guid));

	if (elgg_get_plugin_setting('img_river_view', 'tidypics') === "all") {
		add_to_river('river/object/image/create', 'create', $image->getOwnerGUID(), $image->getGUID());
	}

	echo elgg_echo('success');
} catch (Exception $e) {
	// remove the bits that were saved
	$image->delete();
	echo $e->getMessage();
}

// importando para files
$image = new FilePluginFile();
$image->subtype = "file";
$prefix = "file/";
$name = $file['name'];
$mime = $file['type'];
$image->title = $name;
$image->access_id = $album->access_id;
$image->container_guid = elgg_get_logged_in_user_guid();

$filestorename = elgg_strtolower(time().$name);

$image->setFilename("file/".$filestorename);
$image->setMimeType($mime);
$image->originalfilename = $name;
$image->simpletype = get_general_file_type($mime);

$image->open("write");
$image->write(file_get_contents($file['tmp_name']));
$image->close();
$image->save();

$filestorename = $image->getFilename();
$filestorename = elgg_substr($filestorename, elgg_strlen($prefix));

$image->icontime = time();

$thumbnail = get_resized_image_from_existing_file($image->getFilenameOnFilestore(), 60, 60, true);
if ($thumbnail) {
	$thumb = new ElggFile();
	$thumb->setMimeType($mime);
	$thumb->setFilename($prefix."thumb".$filestorename);
	$thumb->open("write");
	$thumb->write($thumbnail);
	$thumb->close();

	$image->thumbnail = $prefix."thumb".$filestorename;
	unset($thumbnail);
}

$thumbsmall = get_resized_image_from_existing_file($image->getFilenameOnFilestore(), 153, 153, true);
if ($thumbsmall) {
	$thumb->setFilename($prefix."smallthumb".$filestorename);
	$thumb->open("write");
	$thumb->write($thumbsmall);
	$thumb->close();
	$image->smallthumb = $prefix."smallthumb".$filestorename;
	unset($thumbsmall);
}

$thumblarge = get_resized_image_from_existing_file($image->getFilenameOnFilestore(), 600, 600, false);
if ($thumblarge) {
	$thumb->setFilename($prefix."largethumb".$filestorename);
	$thumb->open("write");
	$thumb->write($thumblarge);
	$thumb->close();
	$image->largethumb = $prefix."largethumb".$filestorename;
	unset($thumblarge);
}
// fim - importando para files


exit;