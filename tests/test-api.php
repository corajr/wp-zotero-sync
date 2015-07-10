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

    function get_items() {
        return unserialize( file_get_contents( 'tests/fixture_items.php' ) );
    }

	function test_get_items_from_api() {
        global $WP_Zotero_Sync_Plugin;
        $items = $WP_Zotero_Sync_Plugin->get_items( $this->get_config() );
		$this->assertEquals( 21, count($items) );
	}

    function test_convert_items_to_posts() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();
		$this->assertEquals( 21, count($items) );

        $post_items = $WP_Zotero_Sync_Plugin->convert_to_posts( $items );
        $item = $post_items[0];
        $this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $item['title'] );
        $this->assertEquals( '2015-06-14T19:38:59Z', $item['dateUpdated'] );
        $this->assertEquals( 'ZHT8VRSH', $item['meta']['wpcf-zotero-key'] );
        $this->assertEquals( '2009', $item['meta']['wpcf-date'] );
        $this->assertEquals( 'Policy and Society', $item['meta']['wpcf-journal'] );

        $this->assertFalse( array_key_exists( 'wpcf-editors', $item['meta'] ) );
        $this->assertFalse( array_key_exists( 'wpcf-publisher', $item['meta'] ) );

        $this->assertTrue( array_key_exists( 'wpcf-citation', $item['meta'] ) );

        $book = $post_items[1];
        $this->assertEquals( "Abyssinia's Samuel Johnson: Ethiopian Thought in the Making of an English Author", $book['title'] );
        $this->assertEquals( 'Oxford University Press', $book['meta']['wpcf-publisher'] );
        
    }
}

