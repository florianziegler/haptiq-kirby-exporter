<?php
/**
 * Plugin Name: WordPress to Kirby Exporter
 * Plugin URI: https://github.com/florianziegler/haptiq-kirby-exporter
 * Description: Export posts and attachments into folders and text files.
 * Version: 0.0.1
 * Author: Florian Ziegler
 * Author URI: https://florianziegler.com/
 * License: GNU General Public License 2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text-Domain: haptiq-kirby-exporter
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) or die();


/**
 * Add menu item under Tools
 */
function haptiq_kirby_exporter_menu() {
	add_management_page(
		'Kirby Exporter',
		'Kirby Exporter',
		'manage_options',
		'kirby-exporter',
		'haptiq_kirby_exporter_page'
	);
}

add_action( 'admin_menu', 'haptiq_kirby_exporter_menu' );


/**
 * Render Kirby Exporter menu page
 */
function haptiq_kirby_exporter_page() {
?>
	<div class="wrap">
		<h2>Kirby Exporter</h2>
<?php
	if ( haptiq_kirby_exporter_run() ) {
?>
		<div id="message" class="notice notice-success"><p>🎉 Export complete.</p></div>
<?php
	}
?>
	<form action="tools.php?page=kirby-exporter" method="post">
		<p>Running the exporter will create a folder called <code>kirby-export</code> inside your website's root folder, which is <code><?php echo ABSPATH; ?></code>.</p>
		<p>It will create a folder for each post using the following pattern: <code>YYYYMMDD_{post-slug}</code>.</p>
		<p>It will copy all images/files which are attached to a post into the post's folder and for each file add a meta data file (eg. <code>{filename.jpg}.txt</code>) containing alt and caption.</p>
		<p>Finally it will add a file with the pattern <code>YYYYMMDD_{post-slug}.txt</code> containing the post title, publication date, content and tags.</p>
		<p>All WordPress short codes will be removed.</p>
		<p><strong>No other changes will be made!</strong> If you need to replace anything else, eg. HTML tags with markdown, please have a <a href="https://github.com/florianziegler/haptiq-kirby-exporter">look at the code</a> and extend as needed.</p>
		<?php wp_nonce_field( 'run_kirby_exporter', 'kirby_exporter_nonce' ); ?>
		<?php submit_button( 'Run Kirby Export' ); ?>
	</form>
<?php
	
?>
	</div>
<?php
}


/**
 * Run the exporter
 */
function haptiq_kirby_exporter_run() {

	if ( ! isset( $_POST['kirby_exporter_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['kirby_exporter_nonce'], 'run_kirby_exporter' ) ) {
		echo '<div id="message" class="notice notice-success"><p>🚫 You are not allowed to do that.</p></div>';
		return false;
	}

	$args = array(
		'posts_per_page' => -1,
	);

	$posts = get_posts( $args );
	
	$base_path = ABSPATH . '/kirby-export/';

	if ( ! is_dir( $base_path ) ) {
		mkdir( $base_path );
	}

	foreach( $posts as $post ) {
		$dir = $base_path . date( 'Ymd', strtotime( $post->post_date ) ) . '_' . $post->post_name;

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir );
		}

		$filename = $base_path . date( 'Ymd', strtotime( $post->post_date ) ) . '_' . $post->post_name . '/' . date( 'Ymd', strtotime( $post->post_date ) ) . '_' . $post->post_name . '.txt';

		$tags = wp_get_post_tags( $post->ID );

		$collections = [];
		foreach( $tags as $tag ) {
			$collections[] = $tag->name;
		}

		$args = [
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'post_parent' => $post->ID,
			'post_status' => 'inherit',
			'posts_per_page' => -1,
		];

		$images = new WP_Query( $args );

		foreach( $images->posts as $image ) {
			copy( get_attached_file( $image->ID ), $dir . '/' . basename( get_attached_file( $image->ID ) ) );
			$alt = trim( strip_tags( get_post_meta( $image->ID, '_wp_attachment_image_alt', true ) ) );

			$img_txt = 'Alt: ' . $alt . "\n";
			$img_txt .= "----\n";
			$img_txt .= 'Caption: ' . $image->post_excerpt . "\n";
			$img_txt .= "----\n";

			file_put_contents( $dir . '/' . basename( get_attached_file( $image->ID ) ) . '.txt', $img_txt );
		}

		$output = 'Title: ' . $post->post_title . "\n";
		$output .= "----\n";
		$output .= 'Published: ' . $post->post_date . "\n";
		$output .= "----\n";

		$output .= 'Text: ' . strip_shortcodes( $post->post_content ) . "\n";
		$output .= "----\n";
		$output .= 'Tags: ' . implode( ', ', $collections ) . "\n";

		file_put_contents( $filename, $output );
	}

	return true;
}