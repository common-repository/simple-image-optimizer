<?php 

/**
 * @package Simple Image Optimizer
 * @version 1.0
 */
/*
Plugin Name: Simple Image Optimizer
Plugin URI: https://wordpress.org/plugins/simple-image-optimizer
Description: Free plugin for image optimization. Set the quality of every image, you can go back if you think the quality set is too much. Also, the original images are not erased.
Author: Elías Margolis
Version: 1.0
Author URI: https://www.linkedin.com/in/el%C3%ADas-margolis-2268048b/
License: GPLv2 or later
Text Domain: simple-image-optimizer
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function simple_io_custom_upload_mimes( $existing_mimes ) {
	// add webm to the list of mime types
	$existing_mimes['jpg|jpeg|jpe'] = 'image/jpeg';
	$existing_mimes['png'] = 'image/png';
	// return the array back to the function with our added mime type
	return $existing_mimes;
}
add_filter( 'mime_types', 'simple_io_custom_upload_mimes' );



add_action( 'admin_menu', 'simple_io_menu' );

function simple_io_menu() {
	add_options_page( 'Simple Image Optimizer Options', 'Simple Image Optimizer', 'manage_options', 'simple_io_admin_menu', 'simple_io_options' );
}

function simple_io_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}



	  // variables for the field and option names 
	$quality_percentaje = 'quality_percentaje';
	$hidden_field_name = 'mt_submit_hidden';

	$quality_percentaje_value = get_option( $quality_percentaje );

	

	if( is_numeric($_POST[ $quality_percentaje ]) && isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' &&  check_admin_referer( 'change_quality', 'simple_io_nonce' ) && current_user_can('manage_options') ) {
       
		$quality_value_safe = sanitize_text_field($_POST[ $quality_percentaje ]);
        

        // Read their posted value
		$quality_percentaje_value = $quality_value_safe;


		if( simple_io_update_quality_every_image($quality_percentaje_value) ){
        // Save the posted value in the database
			update_option( $quality_percentaje, $quality_percentaje_value );
		}


        // Put a "settings saved" message on the screen
		?>
			<div class="updated"><p><strong><?php _e('images optimized.', 'menu-test' ); ?></strong></p></div>
		<?php

	}else{
		if(!is_numeric( $_POST[ $quality_percentaje ])  && isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ){
		?>
			<div class="error"><p><strong><?php _e('Quality must be a number!', 'error-number' ); ?></strong></p></div>
		<?php
		}
		if (empty($quality_percentaje_value)) {
			$quality_percentaje_value = 100;
		}

	}

	?>
	<div class="wrap">
		<h1>Simple Image Optimizer</h1>
	</div>
	<form name="form1" method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
		<?php wp_nonce_field( 'change_quality', 'simple_io_nonce'); ?>
		<p><?php _e("Quality Percentage (Default: 100):", 'menu-test' ); ?> 
			<input min="1" max="100" type="number" name="quality_percentaje" value="<?php echo esc_attr( $quality_percentaje_value ); ?>">
		</p><hr />

		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Optimize Images') ?>" />
		</p>

	</form>
</div>

<?php 

}

function simple_io_update_quality_every_image($quality_percentaje_value)
{
	$args = array(
		'post_type' => 'attachment',
		'post_mime_type' => 'image',
		'orderby' => 'post_date',
		'order' => 'desc',
		'posts_per_page' => '30',
		'post_status'    => 'inherit'
	);

	$loop = new WP_Query( $args );

	while ( $loop->have_posts() ) { 

		$loop->the_post();
		//echo "<img src='" . $image[0] . "'>";
		$quality_value = get_post_meta( get_the_ID(),  'simple_io_image_optimized_quality', true );


		if (empty($quality_value)) {
			simple_io_set_quality_image(get_the_ID(), $quality_percentaje_value); 
			add_post_meta( get_the_ID(), 'simple_io_image_optimized_quality', $quality_percentaje_value, true );
		}else if ($quality_value != $quality_percentaje_value) {
			simple_io_set_quality_image(get_the_ID(), $quality_percentaje_value); 
			update_post_meta ( get_the_ID(), 'simple_io_image_optimized_quality', $quality_percentaje_value );
		}else{
			// This quality has already been set.
		}
	}
	return true;
}

function simple_io_set_quality_image($post_id, $quality){
	$fullsize_path = get_attached_file( $post_id );
	$ext = pathinfo(basename($fullsize_path), PATHINFO_EXTENSION);
	

	$original_path = get_post_meta( $post_id,  'simple_io_original_image_path', true );

	if (empty($original_path)) {
		$fullsize_path = str_replace('\\', '/', $fullsize_path);
		add_post_meta( $post_id, 'simple_io_original_image_path', $fullsize_path, true );
	}else{
		if (file_exists($original_path)) {
			$fullsize_path = $original_path;
		}
	}

	$new_fullsize_path =str_replace('.' . $ext, '_simple_io_quality_' . $quality . '.' . $ext, $fullsize_path);

	if (!copy($fullsize_path, $new_fullsize_path)) {
		echo "failed to copy";
	}

	
	if ($quality == 100) {
		//Do nothing
	}else{
		$image_resource = simple_io_imageCreateFromAny($new_fullsize_path);
		$valid = imagejpeg($image_resource, $new_fullsize_path, $quality);
	}
	update_attached_file($post_id, $new_fullsize_path);
	return $valid;
}
	

function simple_io_imageCreateFromAny($filepath) { 
    $type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize() 
    $allowedTypes = array( 
        1,  // [] gif 
        2,  // [] jpg 
        3,  // [] png 
        6   // [] bmp 
      ); 
    if (!in_array($type, $allowedTypes)) { 
    	return false; 
    } 
    switch ($type) { 
    	case 1 : 
    	$im = imageCreateFromGif($filepath); 
    	break; 
    	case 2 : 
    	$im = imageCreateFromJpeg($filepath); 
    	break; 
    	case 3 : 
    	$im = imageCreateFromPng($filepath); 
    	break; 
    	case 6 : 
    	$im = imageCreateFromBmp($filepath); 
    	break; 
    }    
    return $im;  
  }



