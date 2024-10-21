<?php
/**
 * Plugin Name: WordPress to Kirby Exporter
 * Plugin URI: https://github.com/florianziegler/haptiq-kirby-exporter
 * Description: Export posts and attachments into folders and text files.
 * Version: 2.0.0
 * Author: Florian Ziegler
 * Author URI: https://florianziegler.com/
 * License: GNU General Public License 2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text-Domain: haptiq-kirby-exporter
 * Domain Path: /languages
 */
namespace Haptiq_Kirby_Exporter;

defined( 'ABSPATH' ) or die();


/**
 * Add menu item under Tools
 */
function kirby_exporter_menu() {
	add_management_page(
		__( 'Export to Kirby', 'haptiq-kirby-exporter' ),
		__( 'Export to Kirby', 'haptiq-kirby-exporter' ),
		'manage_options',
		'kirby-exporter',
		__NAMESPACE__ . '\kirby_exporter_page'
	);
}

add_action( 'admin_menu', __NAMESPACE__ . '\kirby_exporter_menu' );


/**
 * Render Kirby Exporter menu page
 */
function kirby_exporter_page() {
?>
	<div class="wrap">
		<h2><?php _e( 'Export to Kirby', 'haptiq-kirby-exporter' ); ?></h2>
<?php
	if ( kirby_exporter_run() ) {
?>
		<div id="message" class="notice notice-success"><p>🎉 <?php _e( 'Export complete.', 'haptiq-kirby-exporter' ); ?></p></div>
<?php
	}
?>
		<form action="tools.php?page=kirby-exporter" method="post">
			<p><?php echo sprintf( __( 'Please check the %sdocumentation%s before running the exporter.', 'haptiq-kirby-exporter' ), '<a href="https://github.com/florianziegler/haptiq-kirby-exporter">', '</a>' ); ?></p>
			<?php wp_nonce_field( 'run_kirby_exporter', 'kirby_exporter_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="site_url"><?php _e( 'Site URL', 'haptiq-kirby-exporter' ); ?></label></th>
						<td><input name="site_url" type="text" id="site_url" value="<?php echo get_site_url(); ?>/" class="regular-text"><p class="description" id="site_url-description"><?php _e( 'The domain of your Kirby site.', 'haptiq-kirby-exporter' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="blog_base"><?php _e( 'Blog Base', 'haptiq-kirby-exporter' ); ?></label></th>
						<td><input name="blog_base" type="text" id="blog_base" value="journal" class="regular-text">
						<p class="description" id="blog_base-description"><?php _e( 'URL part, where your blog posts will live in your Kirby site.', 'haptiq-kirby-exporter' ); ?></p></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Run Kirby Export', 'haptiq-kirby-exporter' ) ); ?>
		</form>
	</div>
<?php
}


/**
 * Run the exporter
 */
function kirby_exporter_run() {
	// Check nonce
	if ( ! isset( $_POST['kirby_exporter_nonce'] ) || ! wp_verify_nonce( $_POST['kirby_exporter_nonce'], 'run_kirby_exporter' ) ) {
		echo '<div id="message" class="notice notice-error"><p>🚫 ' . __( 'You are not allowed to do that.', 'haptiq-kirby-exporter' ) . '</p></div>';
		return false;
	}

	// Set base export path
	$base_path = ABSPATH . 'kirby-content/';
	if ( ! is_dir( $base_path ) ) {
		mkdir( $base_path );
	}

	// Set path for blog posts
	$blog_base = sanitize_file_name( stripslashes( $_POST['blog_base'] ) );
	$blog_path = $base_path . $blog_base . '/';
	if ( ! is_dir( $blog_path ) ) {
		mkdir( $blog_path );
	}

	// Set draft folder for blog posts
	$draft_path = $blog_path . '_drafts/';
	if ( ! is_dir( $draft_path ) ) {
		mkdir( $draft_path );
	}

	// Get all posts
	$args = array(
		'posts_per_page' => -1,
		'post_status' => 'any'
	);
	$posts = get_posts( $args );

	// Iterate through posts and export them
	foreach( $posts as $post ) {
		export_post( $post, $blog_path, $draft_path, $blog_base );
	}

	// Get all pages
	$args = array(
		'posts_per_page' => -1,
		'post_status' => 'any',
		'post_type' => 'page',
		'post_parent' => 0,
		'orderby' => 'menu_order',
	);
	$pages = get_posts( $args );

	// Iterate through pages and export them
	foreach( $pages as $page ) {
		export_page( $page->ID, $base_path );
	}

	return true;
}


/**
 * Export a post.
 *
 * @param object $post The post object
 * @param string $base_path The posts folder
 * @param string $draft_path The posts draft folder
 * @param string $blog_base The path to blog posts
 */
function export_post( $post, $base_path, $draft_path, $blog_base ) {
	// Set the post's path
	$post_path_base = date( 'Ymd', strtotime( $post->post_date ) ) . '_' . $post->post_name;
	$post_path = $base_path . $post_path_base;
	if ( in_array( $post->post_status, [ 'draft', 'private' ] ) ) {
		$post_path = $draft_path . $post_path_base;
	}
	if ( ! is_dir( $post_path ) ) {
		mkdir( $post_path );
	}

	// Selecting different Kirby templates, depending on post format:
	// - `journal` for blog articles
	// - `status` for shorter posts without a headline
	$filename = 'journal';
	if ( get_post_format( $post->ID ) == 'status' ) {
		$filename = 'status';
	}

	// Set the filename
	$filename = $post_path . '/' . $filename . '.txt';

	// Set the post's URL
	$site_url = esc_url( $_POST['site_url'] );
	$post_url = $site_url . $blog_base . '/' . $post->post_name . '/';

	// Get categories
	$wp_categories = wp_get_object_terms( $post->ID,  'category' );
	$categories = [];
	foreach( $wp_categories as $category ) {
		$categories[] = $category->name;
	}

	// Get tags
	$wp_tags = wp_get_post_tags( $post->ID );
	$tags = [];
	foreach( $wp_tags as $tag ) {
		$tags[] = $tag->name;
	}

	// Mastodon link
	$mastoddon = get_post_meta( $post->ID, '_share_on_mastodon_url', true );

	// Get the post content and do some cleanup
	$content = strip_shortcodes( $post->post_content );
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	$content = handle_images( $content, $post_path, $post_url );
	$content = clean_markup( $content );

	// Prepare Kirby text file
	$output = 'Title: ' . $post->post_title . "\n";
	$output .= "----\n";
	$output .= 'Published: ' . $post->post_date . "\n";
	$output .= "----\n";
	$output .= 'Text: ' . $content . "\n";
	$output .= "----\n";
	$output .= 'Categories: ' . implode( ', ', $categories ) . "\n";
	$output .= "----\n";
	$output .= 'Tags: ' . implode( ', ', $tags ) . "\n";
	if ( ! empty( $mastoddon ) ) {
		$output .= "----\n";
		$output .= 'Mastodon: ' . $mastoddon . "\n";
	}

	// Save text file
	file_put_contents( $filename, $output );
}


/**
 * Export a page.
 *
 * @param int $page_id The page's post ID
 * @param string $base_path The export base path
 */
function export_page( $page_id, $base_path ) {
	// Get page post object
	$page = get_post( $page_id, OBJECT );

	$title = $page->post_title;
	$published = $page->post_date;
	$menu_order = $page->menu_order; 

	// Set the page's path
	if ( ! is_dir( $base_path . $menu_order . '_' . $page->post_name ) ) {
		mkdir( $base_path . $menu_order . '_' . $page->post_name );
	}

	// Create file name
	$page_path = $base_path . $menu_order . '_' . $page->post_name . '/'; 
	$filename = $page_path . 'default.txt';

	// Handle child page
	$parent_slugs = '';
	if ( ! empty( $page->post_parent ) ) {
		$full_page_path = get_page_uri( $page_id );
		$parent_slugs = str_replace( $page->post_name, '', $full_page_path );
	}
	$site_url = esc_url( $_POST['site_url'] );
	$page_url = $site_url . $parent_slugs . $page->post_name . '/';

	// Get the page content and do some cleanup
	$content = strip_shortcodes( $page->post_content );
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	$content = handle_images( $content, $page_path, $page_url );
	$content = clean_markup( $content );

	// Build kirby txt file
	$output = 'Title: ' . $title . "\n";
	$output .= "----\n";
	$output .= 'Published: ' . $published . "\n";
	$output .= "----\n";
	$output .= 'Text: ' . $content . "\n";

	// Get custom place post meta
	$place = get_post_meta( $page->ID, 'place', true );
	if ( ! empty( $place ) ) {
		$output .= "----\n";
		$output .= 'Type: ' . $place . "\n";
	}

	// Get custom time post meta
	$time = get_post_meta( $page->ID, 'time', true );
	if ( ! empty( $time ) ) {
		$output .= "----\n";
		$output .= 'Timeframe: ' . $time . "\n";
	}

	// Save the template file
	file_put_contents( $filename, $output );

	// Check if there are child pages, if so: Export them inside the current folder
	$children = get_children( [ 'post_parent' => $page_id, 'post_type' => 'page' ] , OBJECT );
	if ( ! empty( $children ) ) {
		foreach( $children as $child ) {
			export_page( $child->ID, $page_path );
		}
	}
}


/**
 * Fix src attribute and copy images into the parent folder.
 *
 * @param string $content The post content
 * @param string $path The post's path
 * @param string $post_url The post's new URL
 */
function handle_images( $content, $path, $post_url ) {
	// Reset URI attributes, so "http://" won't be added to src attribute automatically
	add_filter( 'wp_kses_uri_attributes', function() {
		return [];
	});

	$tags = new \WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag( 'img' ) ) {
		$tags->remove_attribute( 'decoding' );
		$tags->remove_attribute( 'srcset' );
		$tags->remove_attribute( 'sizes' );
		$tags->remove_attribute( 'data-wp-init' );
		$tags->remove_attribute( 'data-wp-on-async--click' );
		$tags->remove_attribute( 'data-wp-on-async--load' );
		$tags->remove_attribute( 'data-wp-on-async-window--resize' );
		$tags->remove_attribute( 'data-id' );
		$tags->remove_attribute( 'class' );

		// Get the src attribute
		$src = $tags->get_attribute( 'src' );

		// Add full URL, if relative
		if ( str_starts_with( $src, '/wp-content/uploads/' ) ) {
			$src = get_site_url() . $src;
		}

		// Replace local links
		if ( str_contains( $src, preg_replace( '#^https?://#i', '', get_site_url() ) ) ) {
			// Fix possible thumbnail size: Remove size suffix from file name
			$src = preg_replace( '/[_-]\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src );

			// Try to export the image, if it is local
			if ( ! empty( $src ) ) {
				$attachment_id = attachment_url_to_postid( $src );
				if ( $attachment_id ) {
					if ( ! empty( $attachment_id ) ) {
						export_attachment( $attachment_id, $path );
					}
				}
			}
	
			// Set the src attribute, so it works in Kirby
			$src = parse_url( $src, PHP_URL_PATH );
			$src = $post_url . basename( $src );
	
			$tags->set_attribute( 'src', $src );
		}
	}

	return $tags->get_updated_html();
}


/**
 * Export an attachment.
 *
 * @param int $attachment_id The attachment post ID
 * @param string $path The parent post's path
 */
function export_attachment( $attachment_id, $path ) {
	$file = get_attached_file( $attachment_id );
	$target = $path . '/' . basename( get_attached_file( $attachment_id ) );

	// Do not copy, if the file already exists
	if ( file_exists( $target ) ) {
		return;
	}

	// Copy the file
	copy( $file, $target );

	// Prepare alt and caption
	$alt = trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
	$img_txt = 'Alt: ' . $alt . "\n";
	$img_txt .= "----\n";
	$img_txt .= 'Caption: ' . get_the_excerpt( $attachment_id ) . "\n";
	$img_txt .= "----\n";

	file_put_contents( $path . '/' . basename( get_attached_file( $attachment_id ) ) . '.txt', $img_txt );
}


/**
 * Do some cleanup.
 *
 * @param string $content The post content
 * @return string The cleaned content
 */
function clean_markup( $content ) {
	$tags = new \WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag( 'figure' ) ) {
		$tags->remove_attribute( 'class' );
		$tags->remove_attribute( 'data-wp-context' );
		$tags->remove_attribute( 'data-wp-interactive' );
		$tags->remove_attribute( 'data-wp-init' );
		$tags->remove_attribute( 'data-wp-on-async--click' );
	}

	$content = $tags->get_updated_html();
	$tags = new \WP_HTML_Tag_Processor( $content );

	while ( $tags->next_tag( 'hr' ) ) {
		$tags->remove_attribute( 'class' );
	}

	$content = $tags->get_updated_html();
	$tags = new \WP_HTML_Tag_Processor( $content );

	while ( $tags->next_tag( 'ul' ) ) {
		$tags->remove_attribute( 'class' );
	}

	$content = $tags->get_updated_html();
	$tags = new \WP_HTML_Tag_Processor( $content );

	while ( $tags->next_tag( 'h3' ) ) {
		$tags->remove_attribute( 'class' );
	}

	return $tags->get_updated_html();
}