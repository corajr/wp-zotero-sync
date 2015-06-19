<?php

class ApiTest extends WP_UnitTestCase {
    static $config = array(
        'libraryType' => 'group',
        'libraryID' => 359247,
        'librarySlug' => 'wordpress_sync_test_data',
        'apiKey' => '',
        'collectionKey' => 'IPJWZUAI',
    );

	function test_get_items_from_api() {
        global $WP_Zotero_Sync_Plugin;
        $items = $WP_Zotero_Sync_Plugin->get_items(static::$config);
		$this->assertEqual( 21, count($items) );
	}
    
}

