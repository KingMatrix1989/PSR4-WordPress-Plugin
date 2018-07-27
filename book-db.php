<?php
/**
 * Plugin Name: Books WordPress Plugin
 * Description: A Sample WordPress Plugin with autoload and PHP namespace
 * Plugin URI:  https://veronalabs.com
 * Version:     1.0
 * Author:      Karim Mohammadi
 * Author URI:  https://atbox.io/skmohammadi
 * License:     MIT
 * Text Domain: book-db
 * Domain Path: /languages
 */

use Helper\Plugin;
use Actions\SettingsPage;
use Helper\PostType;
use Helper\Taxonomy;

global $books_db_version;
$books_db_version = '1.0';

register_activation_hook( __FILE__, 'activate' );
register_deactivation_hook( __FILE__, 'deactivate' );
register_uninstall_hook( __FILE__, 'uninstall' );

add_action( 'plugins_loaded', 'Books_init' );
add_action( 'save_post_book', 'SaveISBN', 11, 2 );

/**
 * Used for regular plugin work.
 *
 * @wp-hook plugins_loaded
 * @return  void
 */
function Books_init() {
	spl_autoload_register( 'autoload' );

	$plugin                             = new Plugin( __FILE__ );
	$plugin['version']                  = '1.0.0';
	$plugin['slug']                     = 'bookdb';
	$plugin['path']                     = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR;
	$plugin['url']                      = plugin_dir_url( __FILE__ );
//	$plugin['settings_page_properties'] = array(
//		'parent_slug'  => 'options-general.php',
//		'page_title'   => 'BookDB Options',
//		'menu_title'   => 'BookDB',
//		'capability'   => 'manage_options',
//		'menu_slug'    => 'book-db-settings',
//		'option_group' => 'book-db_option_group',
//		'option_name'  => 'book-db_option_name'
//	);
//	$plugin['settings_page']            = 'service_settings'; // call the function

	$names   = [
		'name'     => 'book',
		'singular' => 'Book',
		'plural'   => 'Books',
		'slug'     => 'books'
	];
	$options = [
		'has_archive' => true,
		'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' )
	];
	$books   = new PostType( $names, $options );
	$books->columns()->add( [
		'book_isbn' => __( 'ISBN' ),
	] );
	$books->columns()->order( [
		'book_isbn' => 2
	] );
	$books->taxonomy( 'book_publisher' );
	$books->taxonomy( 'book_author' );
	$books->columns()->populate( 'book_isbn', function ( $column, $post_id ) {
		if ( ! get_post_meta( $post_id, 'book_info_isbn', true ) ) {
			echo '—';
		} else {
			echo get_post_meta( $post_id, 'book_info_isbn', true );
		}
	} );

	$books->metabox( 'Book Info', array( 'ISBN' => 'text' ) );
	$books->register();

	$publisher = new Taxonomy( [
		'name'     => 'book_publisher',
		'singular' => 'Publisher',
		'plural'   => 'Publishers',
		'slug'     => 'book_publisher'
	] );
	$publisher->register();
	$authors = new Taxonomy( [
		'name'     => 'book_author',
		'singular' => 'Author',
		'plural'   => 'Authors',
		'slug'     => 'book_author'
	] );
	$authors->register();
	$plugin->run();

	load_language('books');
}

function saveISBN( $post_id, $post ) {
	if ( isset( $post->post_status ) && 'auto-draft' == $post->post_status ) {
		return;
	}
	// Deny the WordPress autosave function
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	global $wpdb;
	$book_isbn = get_post_meta( $post_id, 'book_info_isbn' )[0];

	$sql = "INSERT INTO {$wpdb->prefix}books_info (post_id,isbn) VALUES (%d,%s) ON DUPLICATE KEY UPDATE isbn = %s";
	$sql = $wpdb->prepare( $sql, $post_id, $book_isbn, $book_isbn );
	$wpdb->query( $sql );
}

function service_settings( $plugin ) {
	static $object;

	if ( null !== $object ) {
		return $object;
	}

	$object = new SettingsPage( $plugin['settings_page_properties'] );

	return $object;
}

/**
 * Loads translation file.
 *
 * Accessible to other classes to load different language files (admin and
 * front-end for example).
 *
 * @wp-hook init
 *
 * @param   string $domain
 *
 * @return  void
 */
function load_language( $domain ) {
	$plugin_path = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR;
	load_plugin_textdomain( $domain, false, $plugin_path . '/languages' );
}

function autoload( $class ) {
	$plugin_path = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR;
	$class       = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
	if ( ! class_exists( $class ) ) {
		$class_full_path = $plugin_path . 'includes/' . $class . '.php';

		if ( file_exists( $class_full_path ) ) {
			require $class_full_path;
		}
	}
}

function debug( $text ) {
	$f = fopen( ABSPATH . '/debug.txt', 'a+' );
	fwrite( $f, print_r( $text, true ) . PHP_EOL );
	fclose( $f );
}

function activate() {

	global $books_db_version;
	if ( get_site_option( 'books_db_version' ) != $books_db_version ) {
		Books_createTables();
	}
	if ( get_option( 'books_plugin_activation' ) ) {
		update_option( 'books_plugin_activation', 'activated' );
	} else {
		add_option( 'books_plugin_activation', 'activated' );
	}
	flush_rewrite_rules();
}

function deactivate() {
	update_option( 'books_plugin_activation', 'deactivated' );
}

function uninstall() {

}

function Books_createTables() {
	global $wpdb;
	global $books_db_version;

	$table_name      = $wpdb->prefix . 'books_info';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			post_id bigint NOT NULL,
			isbn text NOT NULL,
			UNIQUE KEY id (id), 
			UNIQUE KEY `post_id` (`post_id`)
			) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'books_db_version', $books_db_version );
}