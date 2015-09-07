<?php
/*
Plugin Name: WP Zotero Sync
Version: 0.1.6
Description: Syncs Zotero items into a custom "Publications" post type.
Author: Cora Johnson-Roberson
Author URI: http://www.corajr.com
Plugin URI: http://github.com/corajr/wp-zotero-sync
Text Domain: wp-zotero-sync
Domain Path: /languages
*/

require_once( dirname(__FILE__) . '/libZoteroSingle.php' );
require_once( dirname(__FILE__) . '/options.php' );

class WP_Zotero_Sync_Plugin {
	private static $instance = false;

	private $option_handler = null;
	private $libraries = array();

	private $api_fields = array(
		'publicationTitle' => 'wpcf-journal',
		'bookTitle' => 'wpcf-journal', // the name for the overall collection
		'publisher' => 'wpcf-publisher',
	);

	private $valid_author_types = array(
		'author',
		'contributor',
		'guest',
		'reviewedAuthor',
	);

	private $categories = array();

	/**
	 * This is our constructor
	 *
	 * @return void
	 */
	private function __construct() {
		$this->option_handler = new WPZoteroSyncOptionHandler( $this );
		add_action( 'init', array( $this, 'ensure_publication_post_type' ) );
		add_action( 'admin_menu', array( $this->option_handler, 'add_submenu' ) );
		add_action( 'admin_init', array( $this->option_handler, 'register_settings' ) );
	}

	public static function get_instance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	public function ensure_publication_post_type() {
		if ( !post_type_exists( 'publication' ) ) {
			register_post_type(
				'publication',
				array(
					'labels' => array(
						'name' => __( 'Publications' ),
						'singular_name' => __( 'Publication' )
					),
					'public' => true,
					'has_archive' => true,
				)
			);
		}
	}

	public function set_categories( $wp_categories = null ) {
		if (empty($wp_categories)) {
			$wp_categories = get_categories( array( 'hide_empty' => false));
		}
		foreach ($wp_categories as $category) {
			$this->categories[$category->name] = intval($category->cat_ID);
		}
	}

	public function get_server_connection($config) {
		$lib_key = $config['library_type'] . $config['library_id'] . $config['collection_key'];

		if (isset($this->libraries[''])) {
			$library = $this->libraries[$lib_key];
		} else {
			$library = new \Zotero\Library(
				$config['library_type'],
				$config['library_id'],
				$config['library_slug']
			);

			$this->libraries[$lib_key] = $library;
		}
		return $library;
	}

	public function get_items($config, $total_item_limit = -1, $recursive = true) {
		$library = $this->get_server_connection($config);

		$per_request_limit = 100;

		$params = array(
			'order' => 'title',
			'limit' => $per_request_limit,
			'collectionKey' => $config['collection_key'],
			'content' => 'json,bib',
		);

		$items = array();

		$this->fetch_items_into(
			$items,
			$library,
			array_merge($params, array('collectionKey' => $config['collection_key'])),
			$total_item_limit
		);

		if ($recursive) {
			$sub_collections = $this->get_sub_collections($library, $config['collection_key']);
			foreach ($sub_collections as $sub_collection) {
				$this->fetch_items_into(
					$items,
					$library,
					array_merge($params, array('collectionKey' => $sub_collection)),
					$total_item_limit
				);
			}
		}

		return $items;
	}

	public function get_sub_collections(&$library, $collection_key) {
		$collection_keys = array();
		$collections = $library->fetchCollections(
			array(
				'collectionKey' => $collection_key,
				'content' =>'json',
			)
		);
		if (count($collections)){
			foreach ($collections as $collection) {
				$key = $collection->collectionKey;
				if ($key != $collection_key) {
					$collection_keys[] = $key;
				}
			}
		}
		return $collection_keys;
	}

	public function fetch_items_into(&$items, &$library, $params, $total_item_limit = -1) {
		$more_items = true;
		$already_fetched = count($items);
		$fetched_items_count = 0;
		$offset = 0;

		while (($already_fetched + $fetched_items_count < $total_item_limit || $total_item_limit == -1)
			   && $more_items) {
			$fetched_items = $library->fetchItemsTop(
				array_merge($params, array('start'=>$offset))
			);
			$items = array_merge($items, $fetched_items);
			$fetched_items_count += count($fetched_items);
			$offset = $fetched_items_count;

			$links = $library->getLastResponse()->linkHeaders();
			if(!isset($links['next'])){
				$more_items = false;
			}
		}
	}

	public function find_creator($creator) {
		$args = array(
			'meta_query' => array(
				'relation' => 'AND',
			),
		);

		if (isset($creator['firstName'])) {
			$args['meta_query'][] = array(
				'key'	  => 'first_name',
				'value'	  => explode(' ', $creator['firstName'])[0],
				'compare' => 'LIKE'
			);
		}

		if (isset($creator['lastName'])) {
			$args['meta_query'][] = array(
				'key'	  => 'last_name',
				'value'	  => $creator['lastName'],
				'compare' => 'LIKE'
			);
		}

		if (isset($creator['name'])) {
			$args['meta_query'][] = array(
				'key'	  => 'display_name',
				'value'	  => $creator['name'],
				'compare' => 'LIKE'
			);
		}

		$authors = get_users( $args );
		return reset( $authors );
	}

	public function add_guest_author( $creator ) {
		global $coauthors_plus;

		$user_id = null;

		if (isset($creator['name'])) {
			$display_name = $creator['name'];
			$creator['firstName'] = '';
			$creator['lastName'] = $creator['name'];
		} else if (isset($creator['firstName'])) {
			$display_name = $creator['firstName'] . ' ' . $creator['lastName'];
		}

		$user_login = sanitize_title($display_name);
		$args = array(
			'display_name' => $display_name,
			'user_login' => $user_login,
			'first_name' => $creator['firstName'],
			'last_name' => $creator['lastName'],
		);

		if (!empty( $coauthors_plus )) {
			$user_id = $coauthors_plus->guest_authors->create( $args );
			return $user_login;
		} else {
			$args['user_pass'] = wp_generate_password();
			$user_id = wp_insert_user( $args );
			$users = get_users( array( 'include' => array($user_id) ) );
			$user = reset($users);
			return $user->user_nicename;
		}
	}

	public function get_or_create_wp_author($creator) {
		$author_nicename = null;

		$author = $this->find_creator($creator);
		if (!empty($author)) { // use existing author
			$author_nicename = $author->user_nicename;
		} else { // create a guest author
			$author_nicename = $this->add_guest_author( $creator );
		}
		return $author_nicename;
	}

	public function get_wp_authors_for($item) {
		$authors = array();

		$valid_authors = array_filter($item->creators, function ($c) {
			return in_array($c['creatorType'], $this->valid_author_types, true);
		});

		if ($valid_authors) {
			foreach ($valid_authors as $creator) {
				$authors[] = $this->get_or_create_wp_author($creator);
			}
		} else {
			$editors = array_filter($item->creators, function ($c) {
				return $c['creatorType'] == 'editor';
			});

			if ($editors) {
				foreach ($editors as $creator) {
					$authors[] = $this->get_or_create_wp_author($creator);
				}
			}
		}

		return $authors;
	}

	public function get_categories_for( $item ) {
		$categories = array();
		foreach ($item->get('tags') as $tag_obj) {
			$cat = $tag_obj['tag'];
			if ( isset( $this->categories[$cat] ) ) {
				$categories[] = $this->categories[$cat];
			}
		}
		return $categories;
	}

	public function commaify( $list ) {
		switch (count( $list ) ) {
		case 0:
			return $list;
			break;
		case 1:
			return $list[0];
			break;
		case 2:
			return implode(" and ", $list);
			break;
		default:
			$last = 'and ' . end( $list );
			array_splice( $list, -1, 1, $last );
			return implode(', ', $list);
		}
	}

	function convert_date($date) {
		$dt = false;
		if (strlen($date) == 4) {
			$dt = new DateTime();
			$dt->setDate(intval($date), 1, 1);
			$dt->setTime(0, 0);
		} else {
			$time = strtotime($date);
			if ($time) {
				$dt = new DateTime();
				$dt->setTimestamp($time);
			}
		}
		return $dt ? $dt->getTimestamp() : false;
	}

	public function get_editors_for( $item ) {
		$editors = array();
		foreach ($item->creators as $creator) {
			if ($creator['creatorType'] == 'editor') {
				if (isset($creator['lastName'])) {
					$editors[] = $creator['firstName'] . ' ' . $creator['lastName'];
				} else if (isset($creator['name'])) {
					$editors[] = $creator['name'];
				}
			}
		}
		if ( count( $editors ) > 0 ) {
			return $this->commaify( $editors );
		} else {
			return false;
		}
	}

	public function reformat_citation( $citation ) {
		return preg_replace("/>[^.<&]+\. /", ">", $citation);
	}

	public function convert_to_posts($items) {
		$posts = array();
		foreach ($items as $item) {
			$meta = $item->apiObj['meta'];
			$date = isset($meta['parsedDate']) ? $meta['parsedDate'] : false;
			$year = $date ? substr($date, 0, 4) : null;
			$timestamp = $date ? $this->convert_date($date) : null;
			$post = array(
				'title' => $item->title,
				'authors' => $this->get_wp_authors_for($item),
				'dateUpdated' => $item->dateModified,
				'categories' => $this->get_categories_for( $item ),
				'meta' => array(
					'wpcf-publication-year' => $year,
					'wpcf-publication-date' => $timestamp,
					'wpcf-zotero-key' => $item->itemKey,
					'wpcf-citation' => $this->reformat_citation( $item->bibContent ),
				),
			);

			$editors = $this->get_editors_for( $item );
			if ($editors) {
				$post['meta']['wpcf-editors'] = $editors;
			}

			$api_obj = $item->apiObj;
			foreach ($this->api_fields as $field=>$custom) {
				if (isset($api_obj[$field])) {
					$post['meta'][$custom] = $api_obj[$field];
				} else if (isset($api_obj['meta'][$field])) {
					$post['meta'][$custom] = $api_obj['meta'][$field];
				} else if (isset($api_obj['data'][$field])) {
					$post['meta'][$custom] = $api_obj['data'][$field];
				}
			}

			if ( isset( $api_obj['data']['abstractNote'] ) ) {
				$post['abstract'] = $api_obj['data']['abstractNote'];
			}

			$posts[] = $post;
		}
		return $posts;
	}

	private function get_by_zotero_key($zotero_key) {
		$args = array(
			'post_type' => 'publication',
			'meta_key' => 'wpcf-zotero-key',
			'meta_value' => $zotero_key,
		);

		$existing = get_posts( $args );
		if (count($existing) == 1) {
			return reset($existing);
		}
	}

	private function do_update_post_meta($post_id, $post_item) {
		foreach ($post_item['meta'] as $key=>$value) {
			update_post_meta($post_id, $key, $value);
		}
	}

	private function do_add_coauthors($post_id, $post_item) {
		global $coauthors_plus;

		if (!empty($coauthors_plus)) {
			$coauthors_plus->add_coauthors($post_id, $post_item['authors']);
		} else {
			$author = $post_item['authors'][0];
			$user = get_user_by( 'slug', $author );
			wp_update_post( array(
				'ID' => $post_id,
				'post_author' => $user->ID,
			) );
		}
	}

	public function create_posts($posts) {
		foreach ($posts as $post_item) {
			$zotero_key = $post_item['meta']['wpcf-zotero-key'];
			$existing = $this->get_by_zotero_key( $zotero_key );
			if ($existing) {
				if ($existing->modified > $post_item['dateUpdated']) {
					// don't alter if Zotero entry is outdated
				} else {
					wp_update_post( array(
						'ID' => $existing->ID,
						'post_excerpt' => $post_item['abstract'],
					) );

					$this->do_update_post_meta($existing->ID, $post_item);
				}
			} else {
				$args = array(
					'post_type' => 'publication',
					'post_name' => sanitize_title( $post_item['title'] ),
					'post_title' => $post_item['title'],
					'post_status' => 'publish',
					'post_excerpt' => $post_item['abstract'],
					'post_content' => '',
				);
				$post_id = wp_insert_post( $args );
				if ($post_item['categories']) {
					$term_taxonomy_ids = wp_set_object_terms( $post_id,  $post_item['categories'], 'category' );
					if ( is_wp_error( $term_taxonomy_ids ) ) {
						echo "Warning: failed to set terms on post " . $post_id . "\n";
					}
				}
				$this->do_update_post_meta($post_id, $post_item);
				$this->do_add_coauthors($post_id, $post_item);
			}
		}
	}

	public function sync( $items = null) {
		$this->set_categories();
		$config = get_option( 'wpzs_settings' );
		if (!empty($config)) {
			if ($items == null) {
				$items = $this->get_items($config);
			}
			$post_items = $this->convert_to_posts($items);
			$this->create_posts($post_items);
		}
	}
}

global $WP_Zotero_Sync_Plugin;
$WP_Zotero_Sync_Plugin = WP_Zotero_Sync_Plugin::get_instance();
