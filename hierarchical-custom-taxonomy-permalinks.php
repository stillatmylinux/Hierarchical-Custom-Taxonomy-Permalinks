<?php
/*
Plugin Name: Hierarchical Custom Taxonomy Permalinks
Description: Creates a custom post type and custom taxonomy that allows the taxonomy to be hierarchical.
			 Creates the permalink stucture for the parent and child taxonomies.  This will give you the
			 the URL like domain.com/books/non-fiction/biography/george-washington/ or domain.com/custom_post_type_slug/parent_taxonomy/child_taxonomy/custom_post_type/.
			 In this example if you would like the URL domain.com/books/, create a page named books.  Create a template page-books.php with your own WP_Query.
Author: Matt Thiessen <matt@thiessen.us>
Version: 1.0.1
Author URI: http://matt.thiessen.us/
Plugin URI: http://matt.thiessen.us/wordpress/hierarchical-custom-taxonomy-permalinks/

*/

// To debug: use  HierarchicalCustomTaxonomyPermalinks::dev4press_debug_page_request() in header.php to display page request info.

/*  Copyright 2012  Matt Thiessen  (email : matt@thiessen.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class HierarchicalCustomTaxonomyPermalinks {

	private $post_type_slug;
	private $post_type_label = array();
	private $taxonomy_slug;
	private $taxonomy_label = array();
	private $permastruct;
	private $permalink_rule_option_name;
	private $rewrite_rule_key;
	private $default_permalink;
	private $default_child_term_permalink;

	function HierarchicalCustomTaxonomyPermalinks() {

		// post_type
		$this->post_type_slug = 'books';
		// post type label
		$this->post_type_label['plural'] ='Books';
		$this->post_type_label['singular'] = 'Book';

		// taxonomy
		$this->taxonomy_slug = 'book-taxonomy';
		// taxonomy label
		$this->taxonomy_label['plural'] = 'Categories';
		$this->taxonomy_label['singular'] = 'Category';

		// The format of the permastruct is not important,
		// just needs to be unique using %someword%,
		// but we take the liberty of setting it for you.
		// It's used to str_replace %someword% with the actual
		// non-fiction/biography/ or parent_taxonomy/child_taxonomy taxonomy structure
		// in the post_type_link filter
		$this->permastruct = '%' . $this->post_type_slug . '_' . $this->taxonomy_slug . '%';

		// when a taxonomy does not have a parent taxonomy use a default placeholder
		$this->default_child_term_permalink = 'library'; // domain.com/books/{library}/post-slug (instead of 'uncategorized')

		// the name of the option used to know if the permalinks rules have been created
		$this->permalink_rule_option_name = $this->post_type_slug . '-' . $this->taxonomy_slug . '_permalink_rules';

		add_action( 'init', array( &$this, 'create_posttype' ) );
		add_action( 'init', array( &$this, 'register_taxonomy' ) );

		add_filter('post_type', array( &$this, 'custom_permalinks' ), 1, 3);
		add_filter('post_type_link', array( &$this, 'custom_permalinks' ), 1, 3);

		// we only need to setup the custom permalink rules once
		// so save them in option
		$custom_permalink_rules_exist = get_option( $this->permalink_rule_option_name );

		if( $custom_permalink_rules_exist != "2") {
			add_action( 'init', 'flush_rewrite_rules');
			add_action( 'generate_rewrite_rules', array( &$this, 'add_rewrite_rules' ) );

			update_option( $this->permalink_rule_option_name, '2');
		}

		$this->verify_rewrite_rule_exists();

		register_activation_hook( __FILE__, array(  &$this, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array(  &$this, 'deactivate_plugin' ) );
	}

	function create_posttype() {

		$label_sg = $this->post_type_label['singular'];
		$label_pl = $this->post_type_label['plural'];

		$labels = array (
			'name' => $label_pl,
			'singular_name' => $label_sg,
			'menu_name' => $label_pl,
			'add_new' => "Add $label_sg",
			'add_new_item' => "Add New $label_sg",
			'edit' => 'Edit',
			'edit_item' => "Edit $label_sg",
			'new_item' => "New $label_sg",
			'view' => "View $label_sg",
			'view_item' => "View $label_sg",
			'search_items' => "Search $label_pl",
			'not_found' => "No $label_pl Found",
			'not_found_in_trash' => "No $label_pl Found in Trash",
			'parent' => "Parent $label_sg"
		);

		$args = array(
			'label' => $label_pl,
			'labels' => $labels,
			'description' => '',
			'public' => true,
			'show_ui' => true,
			'has_archive' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'query_var' => true,
			'supports' => array('title','editor','excerpt','trackbacks','custom-fields','comments','revisions','thumbnail','author','page-attributes'),
			'taxonomies' => array($this->taxonomy_slug),
			'rewrite' => array(
				'slug' => $this->post_type_slug . '/' . $this->permastruct,
				'with_front' => true,
				'hierarchical' => true
			)
		);

		register_post_type( $this->post_type_slug, $args );
	}

	function register_taxonomy() {

		$label_sg = $this->taxonomy_label['singular'];
		$label_pl = $this->taxonomy_label['plural'];

		$labels = array(
			'name' => $label_pl,
			'singular_name' => $label_sg,
			'search_items' => "Search $label_pl",
			'popular_items' => "Popular $label_pl",
			'all_items' => "All $label_pl",
			'parent_item' => "Parent $label_sg",
			'parent_item_colon' => "Parent $label_sg:",
			'edit_item' => "Edit $label_sg",
			'update_item' => "Update $label_sg",
			'add_new_item' => "Add New $label_sg",
			'new_item_name' => "New $label_sg",
			'separate_items_with_commas' => "Separate $label_pl with commas",
			'add_or_remove_items' => "Add or remove $label_pl",
			'choose_from_most_used' => "Choose from the most used $label_pl",
			'menu_name' => $label_pl,
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'show_tagcloud' => true,
			'hierarchical' => true,
			'query_var' => true,
			'rewrite' => array(
					'slug' => $this->post_type_slug,
					'with_front' => false,
					'hierarchical' 	=> true,
				)

		);

		register_taxonomy( $this->taxonomy_slug, array($this->post_type_slug), $args );
	}

	function custom_permalinks( $post_link, $id = 0, $leavename = FALSE ) {

		// only modify link if it matches our permastruct
		if ( strpos($this->permastruct, $post_link) < 0 ) {
			return $post_link;
		}

		// only modify link if it is for our custom post_type
		$post = get_post($id);
		if ( !is_object($post) || $post->post_type != $this->post_type_slug ) {
			return $post_link;
		}

		// get the taxonomies associate with this custom post type
		$terms = wp_get_object_terms($post->ID, $this->taxonomy_slug);

		if ( !$terms ) {
			return str_replace( $this->permastruct, '', $post_link );
		}

		// customize the permalinks
		// start dancing, because the music starts here

		// if only one taxonomy
		if(count($terms) == 1) {
			// if only one taxonomy check if there is a parent taxonomy
			// domain.com/books/biography/george-washington/
			// turns into domain.com/books/non-fiction/biography/george-washington/
			if( $terms[0]->parent != "0" ) {
				$parent_term = get_term( $terms[0]->parent, $this->taxonomy_slug );

				$tax_perm = $parent_term->slug.'/'.$terms[0]->slug;
			}
			else {
				// only a parent taxonomy exists
				// so create a sudo parent and use the tax as the child
				//$tax_perm = 'uncategorized/' . $terms[0]->slug;

				// instead of 'uncategorized'
				$tax_perm = $terms[0]->slug . '/' . $this->default_child_term_permalink;
			}
		}
		else {
			// sort the $terms array by term_id;
			// this assumes that the parent taxonomy has
			// a lower term_id than its child
			usort( $terms, array( $this, 'term_id_sort' ) );

			if(!empty($terms[1]->slug)) {
				$tax_perm = $terms[0]->slug.'/'.$terms[1]->slug;
			}
		}

		return str_replace($this->permastruct, $tax_perm, $post_link);
	}

	function term_id_sort( $a, $b ) {
		if(  $a->term_id ==  $b->term_id ){ return 0 ; }
		return ($a->term_id < $b->term_id) ? -1 : 1;
	}

	/**
	 * Flushing the wp_rewrite rules is an expensive proceedure so check
	 * if the rules exist
	 *
	 * @global object $wp_rewrite
	 */
	function verify_rewrite_rule_exists() {
		global $wp_rewrite;

		if( empty($this->rewrite_rule_key) ||
			!array_key_exists($this->rewrite_rule_key, $wp_rewrite->rules ) ) {
			add_action( 'generate_rewrite_rules', array( &$this, 'add_rewrite_rules' ) );
		}
	}

	function add_rewrite_rules( $wp_rewrite ) {
		global $wp_rewrite;

		$this->rewrite_rule_key = "^{$this->post_type_slug}/([^/]*)/([^/]*)/([^/]*)$";

		// match a custom post_type with a parent and child taxonomy
		$new_rules = array( "^{$this->post_type_slug}/([^/]*)/([^/]*)/([^/]*)$" => "index.php?{$this->post_type_slug}=" . $wp_rewrite->preg_index(3) );

		// match a custom post_type with only one taxonomy; however, will break the archive page for two taxonomies
		//$new_rules = array( "^{$this->post_type_slug}/([^/]*)/([^/]*)$" => "index.php?{$this->post_type_slug}=" . $wp_rewrite->preg_index(2) );

		// Add the new rewrite rule into the top of the global rules array
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

		//flush_rewrite_rules();

		return $wp_rewrite->rules;
	}

	function activate_plugin() {
		flush_rewrite_rules();
		delete_option( $this->permalink_rule_option_name );
	}

	function deactivate_plugin() {
		// remove the permalink rules
		flush_rewrite_rules();
		delete_option( $this->permalink_rule_option_name );
	}

	/**
	 * Checks to see if the request URI contains a custom permalink with both the
	 * parent and child taxonomy books/biography/ or books/biography/george-washington/
	 *
	 * @return (bool) true|false
	 */
	public static function is_parent_child_taxonomy_request() {

		global $wp;

		// TODO: update this for a variable length

		// look for parent_taxonomy/child_taxonomy request
		// look for a slash after the 6th character
		// i.e. $wp->request = 'books/biograpy/george-washington'
		if(!empty($wp->request) && strpos($wp->request, '/', 6) !== false) {
			return true;
		}
		else {
			return false;
		}
	}

	public static function dev4press_debug_page_request() {
		global $wp, $template, $wp_rewrite;

		if (isset($_GET['debug'])) {
			echo '<pre>';
			echo '!-- Request: ';
			echo empty($wp->request) ? "None" : esc_html($wp->request);
			echo ' -->'.PHP_EOL;
			echo '!-- Matched Rewrite Rule: ';
			echo empty($wp->matched_rule) ? None : esc_html($wp->matched_rule);
			echo ' -->'.PHP_EOL;
			echo '!-- Matched Rewrite Query: ';
			echo empty($wp->matched_query) ? "None" : esc_html($wp->matched_query);
			echo ' -->'.PHP_EOL;
			echo '!-- Loaded Template: ';
			echo basename($template);
			echo (isset($_GET['debug'])) ? '</pre>' : ' -->';
			echo PHP_EOL;
		}
		if (isset($_GET['wprules'])) {
			echo '<pre>';
			print_r($wp_rewrite->rules);
			echo '</pre>';
		}
	}
}

$HierarchicalCustomTaxonomyPermalinks = new HierarchicalCustomTaxonomyPermalinks();

?>
