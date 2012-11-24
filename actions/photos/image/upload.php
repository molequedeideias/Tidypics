<?php
/**
 * Multi-image uploader action
 *
 * @author Cash Costello
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2
 */

elgg_load_library('tidypics:upload');
$img_river_view = elgg_get_plugin_setting('img_river_view', 'tidypics');

$guid = (int) get_input('guid');
$album = get_entity($guid);
$imgs =  get_input('img_guid');

if($imgs) {

	$uploaded_images = array();
	$not_uploaded = array();
	$error_msgs = array();

	foreach ($imgs as $img) {

		$import = get_entity($img);

		$name = substr($import->getFilename(),5);
		$mime = $import->getMimeType();

		$image = new TidypicsImage();
		$image->container_guid = $album->getGUID();
		$image->setMimeType($mime);
		$image->access_id = $album->access_id;

		$data = array(
			'name' => $import->title,
			'type' => $import->getMimeType(),
			'tmp_name' => $import->getFilenameOnFilestore(),
			'size' => $import->size(),
			'error' => 0
		);
		//system_message($data);
		try {
			$result = $image->save($data);
		} catch (Exception $e) {
			array_push($not_uploaded, $data['name']);
			array_push($error_msgs, $e->getMessage());
		}

		if ($result) {
			array_push($uploaded_images, $image->getGUID());

			if ($img_river_view == "all") {
				add_to_river('river/object/image/create', 'create', $image->getOwnerGUID(), $image->getGUID());
			}
		}

	}


} else {

	if (!$album) {
		register_error(elgg_echo('tidypics:baduploadform'));
		forward(REFERER);
	}

	// post limit exceeded
	if (count($_FILES) == 0) {
		trigger_error('Tidypics warning: user exceeded post limit on image upload', E_USER_WARNING);
		register_error(elgg_echo('tidypics:exceedpostlimit'));
		forward(REFERER);
	}

	// test to make sure at least 1 image was selected by user
	$num_images = 0;
	foreach($_FILES['images']['name'] as $name) {
		if (!empty($name)) {
			$num_images++;
		}
	}
	if ($num_images == 0) {
		// have user try again
		register_error(elgg_echo('tidypics:noimages'));
		forward(REFERER);
	}

	// create the image object for each upload
	$uploaded_images = array();
	$not_uploaded = array();
	$error_msgs = array();
	foreach ($_FILES['images']['name'] as $index => $value) {
		$data = array();
		$text = "($index => $value) <br><br>";
		foreach ($_FILES['images'] as $key => $values) {
			$data[$key] = $values[$index];
			//$text .= "$key => $values[$index] <br>";
				
		}
//		system_message("$text ");
		if (empty($data['name'])) {
			continue;
		}

		$mime = tp_upload_get_mimetype($data['name']);

		$image = new TidypicsImage();
		$image->container_guid = $album->getGUID();
		$image->setMimeType($mime);
		$image->access_id = $album->access_id;

		try {
			$result = $image->save($data);
		} catch (Exception $e) {
			array_push($not_uploaded, $data['name']);
			array_push($error_msgs, $e->getMessage());
		}

		if ($result) {
			array_push($uploaded_images, $image->getGUID());

			if ($img_river_view == "all") {
				add_to_river('river/object/image/create', 'create', $image->getOwnerGUID(), $image->getGUID());
			}
		}

		// importando para files
		$file = new FilePluginFile();
		$file->subtype = "file";
		$prefix = "file/";
		$name = $data['name'];
		$mime = $data['type'];
		$file->title = $name;
		$file->access_id = $album->access_id;
		$file->container_guid = elgg_get_logged_in_user_guid();

		$filestorename = elgg_strtolower(time().$name);

		$file->setFilename("file/".$filestorename);
		$file->setMimeType($mime);
		$file->originalfilename = $name;
		$file->simpletype = get_general_file_type($mime);

		$file->open("write");
		$file->write(file_get_contents($data['tmp_name']));
		$file->close();
		$file->save();
		
		$filestorename = $file->getFilename();
		$filestorename = elgg_substr($filestorename, elgg_strlen($prefix));

		$file->icontime = time();
		
		$thumbnail = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 60, 60, true);
		if ($thumbnail) {
			$thumb = new ElggFile();
			$thumb->setMimeType($mime);
			$thumb->setFilename($prefix."thumb".$filestorename);
			$thumb->open("write");
			$thumb->write($thumbnail);
			$thumb->close();

			$file->thumbnail = $prefix."thumb".$filestorename;
			unset($thumbnail);
		}

		$thumbsmall = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 153, 153, true);
		if ($thumbsmall) {
			$thumb->setFilename($prefix."smallthumb".$filestorename);
			$thumb->open("write");
			$thumb->write($thumbsmall);
			$thumb->close();
			$file->smallthumb = $prefix."smallthumb".$filestorename;
			unset($thumbsmall);
		}

		$thumblarge = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 600, 600, false);
		if ($thumblarge) {
			$thumb->setFilename($prefix."largethumb".$filestorename);
			$thumb->open("write");
			$thumb->write($thumblarge);
			$thumb->close();
			$file->largethumb = $prefix."largethumb".$filestorename;
			unset($thumblarge);
		}
		// fim - importando para files
	}

}

if (count($uploaded_images)) {
	// Create a new batch object to contain these photos
	$batch = new ElggObject();
	$batch->subtype = "tidypics_batch";
	$batch->access_id = $album->access_id;
	$batch->container_guid = $album->getGUID();
	if ($batch->save()) {
		foreach ($uploaded_images as $uploaded_guid) {
			add_entity_relationship($uploaded_guid, "belongs_to_batch", $batch->getGUID());
		}
	}

	$album->prependImageList($uploaded_images);

	// "added images to album" river
	if ($img_river_view == "batch" && $album->new_album == false) {
		add_to_river('river/object/tidypics_batch/create', 'create', $batch->getOwnerGUID(), $batch->getGUID());
	}

	// "created album" river
	if ($album->new_album) {
		$album->new_album = false;
		$album->first_upload = true;

		add_to_river('river/object/album/create', 'create', $album->getOwnerGUID(), $album->getGUID());

		// "created album" notifications
		// we throw the notification manually here so users are not told about the new album until
		// there are at least a few photos in it
		if ($album->shouldNotify()) {
			object_notifications('create', 'object', $album);
			$album->last_notified = time();
		}
	} else {
		// "added image to album" notifications
		if ($album->first_upload) {
			$album->first_upload = false;
		}

		if ($album->shouldNotify()) {
			object_notifications('create', 'object', $album);
			$album->last_notified = time();
		}
	}
}

if (count($not_uploaded) > 0) {
	if (count($uploaded_images) > 0) {
		$error = sprintf(elgg_echo("tidypics:partialuploadfailure"), count($not_uploaded), count($not_uploaded) + count($uploaded_images))  . '<br />';
	} else {
		$error = elgg_echo("tidypics:completeuploadfailure") . '<br />';
	}

	$num_failures = count($not_uploaded);
	for ($i = 0; $i < $num_failures; $i++) {
		$error .= "{$not_uploaded[$i]}: {$error_msgs[$i]} <br />";
	}
	register_error($error);

	if (count($uploaded_images) == 0) {
		//upload failed, so forward to previous page
		forward(REFERER);
	} else {
		// some images did upload so we fall through
	}
} else {
	system_message(elgg_echo('tidypics:upl_success'));
}

forward("photos/edit/$batch->guid");
