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
        $items = $WP_Zotero_Sync_Plugin->getItems(static::$config);
		$this->assertEqual( 21, count($items) );
	}
    
}

