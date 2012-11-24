   <script type="text/javascript"> 
            
            
            function imageSelector(){
               $('img#select').click(function () { 
                  var checkbox= $(this).prev();
                  checkbox.attr('checked',!checkbox.attr('checked')); 
                });
            
            }
   
            /**
             * Callback function for the image container
             */
            var elem_pages = 9;
            function imagePageselectCallback(page_index, jq) {
                 $('div.images').hide();
                 for(var i=0;i < elem_pages ;i++) {
                    $('div.images:eq(' + ((page_index*elem_pages )+ i) + ')').show();
                 }
            }
           
            /** 
             * Initialisation function for pagination
             */
            function initPagination() {          
                // Create pagination for images
                var num_entries = $('div.images').length;
                $("#pagination").pagination(num_entries, {
                    prev_text: 'Anterior',
                    next_text: 'Próximo',
                    callback: imagePageselectCallback,
                    items_per_page:elem_pages 
                });
             }
            
            // When document is ready, initialize pagination
            $(document).ready(function(){      
                initPagination();
                imageSelector();
            });
            
            
            
        </script> 
<?php

   $album = $vars['entity'];
   $access_id = $album->access_id;
   $params = array(
				'metadata_name' => 'simpletype',
				'metadata_value' => 'image',
				'types' => 'object',
				'subtypes' => 'file',
				'owner_guid' => elgg_get_logged_in_user_entity()->guid,
				'limit' => 9999,
				'full_view' => FALSE,
				'view_type_toggle' => TRUE,
			);
	$files = elgg_get_entities_from_metadata($params);
	
	$form .= "<div id=\"pagination\"></div>
	         <br style=\"clear:both;\" />
	         ";
	foreach($files as $file) {
	   if(strlen($file->title) < 20) {
	      $title = $file->title;
	   } else {
	      $title = substr($file->title,0,18)."...";
	   }
      $form .= "<div class=\"images\"><p><input type=\"checkbox\" name=\"img_guid[]\" value=\"{$file->guid}\">{$title}</input>
      <img id=\"select\"src=\"{$vars['url']}mod/file/thumbnail.php?file_guid={$file->guid}&size=medium\"></p></div>";	
	}
	
	if ($album) {
		$form .= '<input type="hidden" name="guid" value="' . $album->guid . '" />';
	}
	if ($access_id) {
		$form .= '<input type="hidden" name="access_id" value="' . $access_id . '" />';
	}

   $form .= "<br style=\"clear: both;\"><div class=\"salvar_fotos\">".elgg_view('input/submit', array(
      'value' => elgg_echo('save')
   ))."</div>";

	$form = elgg_view('input/form', array(
		'body' => $form,
		'action' => "action/photos/image/upload"
	));
  echo $form;
