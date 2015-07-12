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
        add_action( 'init', array( $this, 'ensure_publication_post_type' ) );
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
        $args = array(
            'meta_query' => array(
                'relation' => 'AND',
            ),
        );

        if (isset($creator['firstName'])) {
            $args['meta_query'][] = array(
                'key'     => 'first_name',
                'value'   => $creator['firstName'],
                'compare' => 'LIKE'
            );
        }

        $args['meta_query'][] = array(
            'key'     => 'last_name',
            'value'   => $creator['lastName'],
            'compare' => 'LIKE'
        );

        $authors = get_users( $args );
        return reset( $authors );
    }

    public function add_guest_author( $creator ) {
        global $coauthors_plus;

        $user_id = null;

        $display_name = $creator['firstName'] . ' ' . $creator['lastName'];
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
                    $this->do_update_post_meta($existing->ID, $post_item);
                }
            } else {
                $args = array(
                    'post_type' => 'publication',
                    'post_name' => sanitize_title( $post_item['title'] ),
                    'post_title' => $post_item['title'],
                    'post_status' => 'publish',
                    'post_content' => '',
                    'post_excerpt' => '',
                );
                $post_id = wp_insert_post( $args );
                $this->do_update_post_meta($post_id, $post_item);
                $this->do_add_coauthors($post_id, $post_item);
            }
        }
    }
}

global $WP_Zotero_Sync_Plugin;
$WP_Zotero_Sync_Plugin = WP_Zotero_Sync_Plugin::get_instance();
