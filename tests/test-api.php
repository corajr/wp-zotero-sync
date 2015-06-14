<?php

class ApiTest extends WP_UnitTestCase {

	function test_get_items_from_api() {
        $config = array(
            'libraryType' => 'group',
            'libraryID' => 359247,
            'librarySlug' => 'wordpress_sync_test_data',
            'apiKey' => '',
            'collectionKey' => 'IPJWZUAI',
        );

        $items = $WP_Zotero_Sync_Plugin->getItems($config);
		$this->assertEqual( 21, count($items) );
	}
}

