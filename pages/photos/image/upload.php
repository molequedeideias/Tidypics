<?php
/**
 * Upload images
 *
 * @author Cash Costello
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2
 */

gatekeeper();

$album_guid = (int) get_input('guid');
if (!$album_guid) {
	// @todo
	forward();
}

$album = get_entity($album_guid);
if (!$album) {
	// @todo
	// throw warning and forward to previous page
	forward(REFERER);
}

if (!$album->getContainerEntity()->canWriteToContainer()) {
	// @todo have to be able to edit album to upload photos
	forward(REFERER);
}

// set page owner based on container (user or group)
elgg_set_page_owner_guid($album->getContainerGUID());
$owner = elgg_get_page_owner_entity();

$title = elgg_echo('album:addpix');

// set up breadcrumbs
elgg_push_breadcrumb(elgg_echo('photos'), "photos/all");
elgg_push_breadcrumb($owner->name, "photos/owner/$owner->username");
elgg_push_breadcrumb($album->getTitle(), $album->getURL());
elgg_push_breadcrumb(elgg_echo('album:addpix'));

$uploader = get_input('uploader');
if ($uploader == 'basic') {
	$content = elgg_view('forms/photos/basic_upload', array('entity' => $album));
	$basic_selected = "class=\"elgg-state-selected\"";
} elseif ($uploader == 'import') {
	elgg_load_js('jquery.pagination');
	elgg_load_css('pagination');
	$content = elgg_view('forms/photos/import_upload', array('entity' => $album));
	$import_selected = "class=\"elgg-state-selected\"";
} else {
	elgg_load_js('swfobject');
	elgg_load_js('jquery.uploadify-tp');
	elgg_load_js('tidypics:uploading');
	$content = elgg_view('forms/photos/ajax_upload', array('entity' => $album));	
	$ajax_selected = "class=\"elgg-state-selected\"";
}

$url = elgg_get_site_url();
$ajax = elgg_echo('tidypics:ajax');
$basic = elgg_echo('tidypics:basic');
$import = elgg_echo('tidypics:import');

$menu = <<<__MENU
<ul class="elgg-menu elgg-menu-filter elgg-menu-hz elgg-menu-filter-default">
 <li $ajax_selected><a href="{$url}photos/upload/$album_guid/">$ajax</a></li>
 <li $basic_selected><a href="{$url}photos/upload/$album_guid/basic">$basic</a></li>
 <li $import_selected><a href="{$url}photos/upload/$album_guid/import">$import</a></li>
</ul>
__MENU;

	
	

$body = elgg_view_layout('content', array(
	'content' => $menu.$content,
	'title' => $title,
	'filter' => '',
	'sidebar' => elgg_view('photos/sidebar', array('page' => 'upload')),
));

echo elgg_view_page($title, $body);
