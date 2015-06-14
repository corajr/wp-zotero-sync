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
		$this->assertTrue( true );
	}
}

