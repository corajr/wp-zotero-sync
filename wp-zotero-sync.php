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

class WP_Zotero_Sync_Plugin {
    private static $instance = false;
    private static $table_name = 'zotero-cache';
    private static $db_version = '1.0';
    
	/**
	 * This is our constructor
	 *
	 * @return void
	 */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'create_cache'));
    }

	public static function get_instance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

    public function create_cache() {
        global $wpdb;
        $installed_ver = get_option( "wp_zotero_sync_db_version" );
        
        if ( $installed_ver != static::$db_version ) {
            $table_name = $wpdb->prefix . static::$table_name;
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
               id int NOT NULL AUTO_INCREMENT,
               time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
               library_type varchar(5) NOT NULL,
               library_id int NOT NULL,
               api_key varchar(255) NOT NULL,
               collection_key varchar(8) NOT NULL,
               response longtext NOT NULL,
               PRIMARY KEY (id),
               UNIQUE KEY library_type_id_col (library_type, library_id, collection_key)
               ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );

            update_option( "wp_zotero_sync_db_version", $db_version );
        }
    }

    public function getFromDB($config) {
        global $wpdb;
        $table_name = $wpdb->prefix . static::$table_name;

        $sql = $wpdb->prepare("SELECT * FROM %s WHERE library_type = %s" .
                              " AND library_id = %d" .
                              " AND collection_key = %s",
                              $table_name,
                              $config['libraryType'],
                              $config['libraryID'],
                              $config['collectionKey']
        );

        $result = $wpdb->get_row($sql, ARRAY_A);
        return $result;
    }

    private function apiCall($config) {
    }

    public function getItems($config) {
        
    }
}

global $WP_Zotero_Sync_Plugin;
$WP_Zotero_Sync_Plugin = WP_Zotero_Sync_Plugin::get_instance();
