<?php
/*
Plugin Name: WP Zotero Sync
Version: 0.1-alpha
Description: Syncs Zotero items into a custom "Publications" post type.
Author: Cora Johnson-Roberson
Author URI: http://www.corajr.com
Plugin URI: http://github.com/corajr/wp-zotero-sync
Text Domain: wp-zotero-sync
Domain Path: /languages
*/

require_once( dirname(__FILE__) . '/libZoteroSingle.php' );

class WP_Zotero_Sync_Plugin {
    private static $instance = false;
    private static $libraries = array();

    private static $api_fields = array(
        'publicationTitle' => 'wpcf-journal',
        'bookTitle' => 'wpcf-journal', // the name for the overall collection
        'publisher' => 'wpcf-publisher',
    );
    
	/**
	 * This is our constructor
	 *
	 * @return void
	 */
    private function __construct() {
    }

	public static function get_instance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

    private function get_server_connection($config) {
        $lib_key = $config['libraryType'] . $config['libraryID'] . $config['collectionKey'];

        if (isset(static::$libraries[''])) {
            $library = static::$libraries[$lib_key];
        } else {
            $library = new Zotero_Library(
                $config['libraryType'],
                $config['libraryID'],
                $config['librarySlug']
            );
            $library->setCacheTtl(1800);

            static::$libraries[$lib_key] = $library;
        }
        return $library;
    }

    public function get_items($config, $total_item_limit = -1) {
        $library = $this->get_server_connection($config);

        $per_request_limit = 100;

        $params = array(
            'order' => 'title',
            'limit' => $per_request_limit,
            'collectionKey' => $config['collectionKey'],
            'content' => 'json,bib',
        );

        $more_items = true;
        $fetched_items_count = 0;
        $offset = 0;
        $items = array();

        while (($fetched_items_count < $total_item_limit || $total_item_limit == -1)
               && $more_items) {
            $fetched_items = $library->fetchItemsTop(
                array_merge($params, array('start'=>$offset))
            );
            $items = array_merge($items, $fetched_items);
            $fetched_items_count += count($fetched_items);
            $offset = $fetched_items_count;

            if(!isset($library->getLastFeed()->links['next'])){
                $more_items = false;
            }
        }
        return $items;
    }

    public function find_creator($creator) {
        if (isset($creator['firstName'])) {
            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'first_name',
                        'value'   => $creator['firstName'],
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $creator['lastName'],
                        'compare' => 'LIKE'
                    ),
                ),
            );
        } else {
            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'last_name',
                        'value'   => $creator['lastName'],
                        'compare' => 'LIKE'
                    ),
                ),
            );
        }

        $user_query = new WP_User_Query( $args );
        if (! empty( $user_query->results) ) {
            $authors = $user_query->get_results();
            return reset( $authors );
        }
    }

    public function get_or_create_wp_author($creator) {
        $author_id = null;

        $author = $this->find_creator($creator);
        if (!empty($author)) { // use existing author
            $author_id = $author->ID;
        } else { // create a guest author
            $author_id = $this->add_guest_author( $creator );
        }
        return $author_id;
    }

    public function add_guest_author( $creator ) {
        global $coauthors_plus;

        $user_id = null;

        $display_name = $creator['firstName'] . ' ' . $creator['lastName'];
        $args = array(
            'display_name' => $display_name,
            'user_login' => sanitize_title($display_name),
            'first_name' => $creator['firstName'],
            'last_name' => $creator['lastName'],
        );
        
        if (!empty( $coauthors_plus )) {
            $user_id = $coauthors_plus->guest_authors->create( $args );
        } else {
            $args['user_pass'] = wp_generate_password();
            $user_id = wp_insert_user( $args );  
        }
        return $user_id;
    }

    public function get_wp_authors_for($item) {
        $authors = array();
        foreach ($item->creators as $creator) {
            if ($creator['creatorType'] == 'author') {
                $authors[] = $this->get_or_create_wp_author($creator);
            }
        }
        return $authors;
    }

    public function convert_to_posts($items) {
        $posts = array();
        foreach ($items as $item) {
            $post = array(
                'title' => $item->title,
                'authors' => $this->get_wp_authors_for($item),
                'dateUpdated' => $item->dateUpdated,
                'meta' => array(
                    'wpcf-date' => $item->year,
                    'wpcf-zotero-key' => $item->itemKey,
                    'wpcf-citation' => $item->bibContent,
                ),
            );

            $api_obj = $item->apiObject;
            foreach (static::$api_fields as $field=>$custom) {
                if (isset($api_obj[$field])) {
                    $post['meta'][$custom] = $api_obj[$field];
                }
            }

            $posts[] = $post;
        }
        return $posts;
    }
}

global $WP_Zotero_Sync_Plugin;
$WP_Zotero_Sync_Plugin = WP_Zotero_Sync_Plugin::get_instance();
