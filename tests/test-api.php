<?php

class ApiTest extends WP_UnitTestCase {
    function get_config() {
        $api_key = file_get_contents( 'api-key.txt' );
        $config = array(
            'libraryType' => 'group',
            'libraryID' => 359247,
            'librarySlug' => 'wordpress_sync_test_data',
            'apiKey' => $api_key,
            'collectionKey' => 'IPJWZUAI',
        );
        return $config;
    }

	function test_get_items_from_api() {
        global $WP_Zotero_Sync_Plugin;
        $items = $WP_Zotero_Sync_Plugin->get_items( $this->get_config() );
		$this->assertEqual( 21, count($items) );
	}
    
}

