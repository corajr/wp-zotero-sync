<?php

define("LIVE_API", false);

class ApiTest extends WP_UnitTestCase {
    public $users = array();

    public function setUp() {
        parent::setUp();

        $user_args = array(
            array(
                'first_name' => 'Ruha',
                'last_name' => 'Benjamin',
            ),
            array(
                'first_name' => 'Wendy Laura',
                'last_name' => 'Belcher',
            ),
            array(
                'first_name' => 'Chika',
                'last_name' => 'Okeke-Agulu',
            ),
        );

        foreach ($user_args as $args) {
            $this->users[$args['last_name']] = $this->factory->user->create( $args );
        }        
    }

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
        if (LIVE_API) {
            $items = $WP_Zotero_Sync_Plugin->get_items( $this->get_config() );
        } else {
            $items = $this->get_items();
        }
		$this->assertEquals( 21, count($items) );
	}

    function check_authors($item, $last_names) {
        global $WP_Zotero_Sync_Plugin;

        $authors = $WP_Zotero_Sync_Plugin->get_wp_authors_for($item);

        $users = $this->users;

        $real_ids = array_map(function($last_name) use ($users) {
                return $users[$last_name];
        }, $last_names);

        $this->assertEquals( $real_ids, $authors );
    }

    function test_get_wp_authors() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();

        $this->check_authors($items[0], array('Benjamin'));
        $this->check_authors($items[1], array('Belcher'));
        $this->check_authors($items[2], array('Okeke-Agulu'));
    }

    function test_convert_items_to_posts() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();

        $post_items = $WP_Zotero_Sync_Plugin->convert_to_posts( $items );
        $article = $post_items[0];
        $this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $article['title'] );
        $this->assertEquals( '2015-06-14T19:38:59Z', $article['dateUpdated'] );
        $this->assertEquals( 'ZHT8VRSH', $article['meta']['wpcf-zotero-key'] );
        $this->assertEquals( '2009', $article['meta']['wpcf-date'] );
        $this->assertEquals( 'Policy and Society', $article['meta']['wpcf-journal'] );

        $this->assertArrayNotHasKey( 'wpcf-editors', $article['meta'] );
        $this->assertArrayNotHasKey( 'wpcf-publisher', $article['meta'] );

        $this->assertArrayHasKey( 'wpcf-citation', $article['meta'] );

        $book = $post_items[1];
        $this->assertEquals( "Abyssinia's Samuel Johnson: Ethiopian Thought in the Making of an English Author", $book['title'] );
        $this->assertEquals( 'Oxford University Press', $book['meta']['wpcf-publisher'] );

        $book_section = $post_items[2];
        $this->assertEquals( 'SIQEK7CP', $book_section['meta']['wpcf-zotero-key'] );
        $this->assertEquals( 'Art History and Globalization', $book_section['title'] );
        $this->assertEquals( 'Is Art History Global', $book_section['meta']['wpcf-journal'] );
        $this->assertEquals( 1, count($book_section['authors']) );
        
    }
}

