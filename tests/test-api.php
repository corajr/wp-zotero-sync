<?php

define("LIVE_API", false);
define("NUM_ITEMS", 21);

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
            $user = $this->factory->user->create_and_get( $args );
            $this->users[$args['last_name']] = $user->user_nicename;
        }
    }

    function get_config() {
        $config = array(
            'library_type' => 'group',
            'library_id' => 359247,
            'library_slug' => 'wordpress_sync_test_data',
            'api_key' => '',
            'collection_key' => 'IPJWZUAI',
        );
        return $config;
    }

    function get_items() {
        global $WP_Zotero_Sync_Plugin;
        if (LIVE_API) {
            $items = $WP_Zotero_Sync_Plugin->get_items( $this->get_config() );
            file_put_contents( 'tests/fixture_items.php', serialize( $items ) );
        } else {
            $items = unserialize( file_get_contents( 'tests/fixture_items.php' ) );
        }
        return $items;
    }

    function get_research_areas_field() {
        return unserialize( file_get_contents( 'tests/fixture_fields.php' ) );
    }

	function test_get_items_from_api() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();
		$this->assertEquals( NUM_ITEMS, count($items) );
	}

    function test_add_guest_author() {
        global $WP_Zotero_Sync_Plugin;

        $args = array(
            'firstName' => 'Michael',
            'lastName' => 'Kleiner'
        );

        $author = $WP_Zotero_Sync_Plugin->add_guest_author( $args );
        $this->assertNotEmpty( $author );
    }

    function test_get_or_create_wp_author() {
        global $WP_Zotero_Sync_Plugin;

        $args = array(
            'firstName' => 'Michael',
            'lastName' => 'Kleiner'
        );

        $author = $WP_Zotero_Sync_Plugin->get_or_create_wp_author( $args );
        $this->assertNotEmpty( $author );
        $author2 = $WP_Zotero_Sync_Plugin->get_or_create_wp_author( $args );
        $this->assertEquals( $author, $author2 );
    }
    
    function check_authors($item, $last_names) {
        global $WP_Zotero_Sync_Plugin;

        $authors = $WP_Zotero_Sync_Plugin->get_wp_authors_for($item);

        $users = $this->users;

        $real_ids = array_map(function($last_name) use ($users, $WP_Zotero_Sync_Plugin) {
                if (isset($users[$last_name])) {
                    return $users[$last_name];
                } else {
                    $user = $WP_Zotero_Sync_Plugin->find_creator(
                        array('lastName' => $last_name)
                    );
                    return $user->user_nicename;
                }
        }, $last_names);

        $this->assertEquals( $real_ids, $authors );
    }

    function test_get_wp_authors() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();

        $this->check_authors($items[0], array('Benjamin'));
        $this->check_authors($items[1], array('Belcher'));
        $this->check_authors($items[2], array('Okeke-Agulu'));
        $this->check_authors($items[18], array('Belcher', 'Kleiner'));
    }

    function test_research_areas() {
        global $WP_Zotero_Sync_Plugin;
        $field = $this->get_research_areas_field();
        $WP_Zotero_Sync_Plugin->set_research_areas( $field );

        $items = $this->get_items();
        $areas = $WP_Zotero_Sync_Plugin->get_areas_for( $items[0] );

        $this->assertEquals( 2, count( $areas ) );

        $expected_areas = array(
            'wpcf-fields-checkboxes-option-8aae55f870d165de229c4244c7c0cb43-1' => array("Post-Colonialization"),
            'wpcf-fields-checkboxes-option-7909242e2a2efa6de8880164ebd29a75-1' => array("Participatory Science"),            
        );
        $this->assertEquals( $expected_areas, $areas );
    }

    function test_convert_items_to_posts() {
        global $WP_Zotero_Sync_Plugin;
        $items = $this->get_items();

        $post_items = $WP_Zotero_Sync_Plugin->convert_to_posts( $items );
        $article = $post_items[0];
        $this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $article['title'] );
        $this->assertEquals( '2015-07-12T19:41:00Z', $article['dateUpdated'] );
        $this->assertEquals( 'ZHT8VRSH', $article['meta']['wpcf-zotero-key'] );
        $this->assertEquals( '2009', $article['meta']['wpcf-date'] );
        $this->assertEquals( 'Policy and Society', $article['meta']['wpcf-journal'] );
        $this->assertStringStartsWith( 'This paper analyzes', $article['abstract'] );

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

        // Don't include authors in citation format.
        $joint_authored = $post_items[18];
        $this->assertNotContains( 'Belcher, Wendy', $joint_authored['meta']['wpcf-citation'] );
        $this->assertNotContains( 'Michael Kleiner', $joint_authored['meta']['wpcf-citation'] );
    }

    function get_publications() {
        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'publication',
            'order' => 'ASC',
            'order_by' => 'ID',
        );

        $publications = get_posts( $args );
        return $publications;
    }

    function test_create_posts() {
        global $WP_Zotero_Sync_Plugin;

        $items = $this->get_items();
        $post_items = $WP_Zotero_Sync_Plugin->convert_to_posts( $items );

        $WP_Zotero_Sync_Plugin->create_posts( $post_items );

        $publications = $this->get_publications();

        $this->assertEquals( NUM_ITEMS, count($publications) );

        $example = $publications[0];

        $this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $example->post_title );
        $this->assertStringStartsWith( 'This paper analyzes', $example->post_content );
        $author = get_user_by( 'id', $example->post_author );
        $this->assertEquals( $this->users['Benjamin'], $author->user_nicename );
    }

    function test_sync() {
        global $WP_Zotero_Sync_Plugin;

        update_option( 'wpzs_settings', $this->get_config() );

        $WP_Zotero_Sync_Plugin->sync( $this->get_research_areas_field() );
        $publications = $this->get_publications();

        $this->assertEquals( NUM_ITEMS, count($publications) );

        $example = $publications[0];
        $meta = get_post_meta( $example->ID );

        $expected_areas = array(
            'wpcf-fields-checkboxes-option-8aae55f870d165de229c4244c7c0cb43-1' => array("Post-Colonialization"),
            'wpcf-fields-checkboxes-option-7909242e2a2efa6de8880164ebd29a75-1' => array("Participatory Science"),            
        );

        $areas = maybe_unserialize( $meta['wpcf-research-areas'][0] );
        $this->assertEquals( $expected_areas,  $areas );

    }
}

