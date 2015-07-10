<?php

class ApiTest extends WP_UnitTestCase {
    function get_config() {
        $config = array(
            'libraryType' => 'group',
            'libraryID' => 359247,
            'librarySlug' => 'wordpress_sync_test_data',
            'apiKey' => '',
            'collectionKey' => 'IPJWZUAI',
        );
        return $config;
    }

	function test_get_items_from_api() {
        global $WP_Zotero_Sync_Plugin;
        $items = $WP_Zotero_Sync_Plugin->get_items( $this->get_config() );
		$this->assertEquals( 21, count($items) );
	}

    function test_convert_items_to_posts() {
        global $WP_Zotero_Sync_Plugin;
        $items = unserialize( file_get_contents( 'tests/fixture_items.php' ) );
		$this->assertEquals( 21, count($items) );        
    }
}

