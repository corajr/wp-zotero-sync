<?php

define("LIVE_API", false);
define("NUM_ITEMS", 34);

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
			array(
				'first_name' => 'Anne Anlin',
				'last_name' => 'Cheng',
			),
		);

		foreach ($user_args as $args) {
			$user = $this->factory->user->create_and_get( $args );
			$this->users[$args['last_name']] = $user->user_nicename;
		}

		$this->create_categories();
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

	function create_categories() {
		$categories = unserialize( file_get_contents( 'tests/fixture_categories.php' ) );
		foreach ($categories as $category) {
			if ($category->name != 'Uncategorized') {
				$cat_id = $this->factory->category->create( array( 'name' => $category->name ));
				if (is_wp_error($cat_id)) {
					die(print_r($cat_id, true));
				}
			}
		}
	}

	function test_get_subcollections_from_api() {
		global $WP_Zotero_Sync_Plugin;
		if (LIVE_API) {
			$config = $this->get_config();
			$library = $WP_Zotero_Sync_Plugin->get_server_connection($config);

			$collections = $WP_Zotero_Sync_Plugin->get_sub_collections($library, $config['collection_key']);

			$this->assertEquals( 1, count($collections));
			$this->assertEquals( array('FCSUBFSM'), $collections );
		}
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

	function test_commaify() {
		global $WP_Zotero_Sync_Plugin;

		$list = array('A', 'B', 'C');
		$res1 = $WP_Zotero_Sync_Plugin->commaify( array_slice( $list, 0, 1 ) );
		$res2 = $WP_Zotero_Sync_Plugin->commaify( array_slice( $list, 0, 2 ) );
		$res3 = $WP_Zotero_Sync_Plugin->commaify( array_slice( $list, 0, 3 ) );
		$this->assertEquals('A', $res1);
		$this->assertEquals('A and B', $res2);
		$this->assertEquals('A, B, and C', $res3);
	}

	function test_convert_date() {
		global $WP_Zotero_Sync_Plugin;
		$date1 = $WP_Zotero_Sync_Plugin->convert_date('2014');
		$expected1 = '2014-01-01T00:00:00+00:00';
		$this->assertEquals($expected1, gmdate('c', $date1));
		$date2 = $WP_Zotero_Sync_Plugin->convert_date('May 29, 2014');
		$expected2 = '2014-05-29T00:00:00+00:00';
		$this->assertEquals($expected2, gmdate('c', $date2));
		$invalid = $WP_Zotero_Sync_Plugin->convert_date('201');
		$this->assertEquals(false, $invalid);
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

		$this->check_authors($items[0], array('Cheng'));
		$this->check_authors($items[1], array('Benjamin'));
		$this->check_authors($items[2], array('Belcher'));
		$this->check_authors($items[3], array('Okeke-Agulu'));
		$this->check_authors($items[23], array('Belcher', 'Kleiner'));
	}

	function test_categories() {
		global $WP_Zotero_Sync_Plugin;
		$wp_categories = get_categories( array( 'hide_empty' => false ) );
		$WP_Zotero_Sync_Plugin->set_categories( $wp_categories );

		$items = $this->get_items();
		$categories = $WP_Zotero_Sync_Plugin->get_categories_for( $items[1] );
		$this->assertEquals( 1, count( $categories ) );
		$this->assertTrue( is_int($categories[0]) );
		// Must be an integer for setting categories to work

		$cat_id = $categories[0];
		$cat = get_category($cat_id);
		$cat_name = $cat ? $cat->name : null;
		$this->assertEquals( 'Research Articles', $cat_name );
	}

	function test_convert_items_to_posts() {
		global $WP_Zotero_Sync_Plugin;
		$items = $this->get_items();

		$post_items = $WP_Zotero_Sync_Plugin->convert_to_posts( $items );
		$article = $post_items[1];
		$this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $article['title'] );
		$this->assertEquals( '2015-07-12T19:41:00Z', $article['dateUpdated'] );
		$this->assertEquals( 'ZHT8VRSH', $article['meta']['wpcf-zotero-key'] );

		$this->assertEquals( '2009', $article['meta']['wpcf-publication-year'] );
		$date = gmdate('c', $article['meta']['wpcf-publication-date']);
		$this->assertEquals( '2009-01-01T00:00:00+00:00', $date);

		$this->assertEquals( 'Policy and Society', $article['meta']['wpcf-journal'] );
		$this->assertEquals( 1, count($article['categories']) );
		$this->assertStringStartsWith( 'This paper analyzes', $article['abstract'] );

		$this->assertArrayNotHasKey( 'wpcf-editors', $article['meta'] );
		$this->assertArrayNotHasKey( 'wpcf-publisher', $article['meta'] );

		$this->assertArrayHasKey( 'wpcf-citation', $article['meta'] );

		$book = $post_items[2];
		$this->assertEquals( "Abyssinia's Samuel Johnson: Ethiopian Thought in the Making of an English Author", $book['title'] );
		$this->assertEquals( 'Oxford University Press', $book['meta']['wpcf-publisher'] );

		$book_section = $post_items[3];
		$this->assertEquals( 'SIQEK7CP', $book_section['meta']['wpcf-zotero-key'] );
		$this->assertEquals( 'Art History and Globalization', $book_section['title'] );
		$this->assertEquals( 'Is Art History Global', $book_section['meta']['wpcf-journal'] );
		$this->assertEquals( 'James Elkins', $book_section['meta']['wpcf-editors'] );
		$this->assertEquals( 1, count($book_section['authors']) );

		// Don't include authors in citation format.
		$joint_authored = $post_items[23];
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

		$example = $publications[1];

		$this->assertEquals( 'A lab of their own: Genomic sovereignty as postcolonial science policy', $example->post_title );
		$this->assertStringStartsWith( 'This paper analyzes', $example->post_excerpt );
		$author = get_user_by( 'id', $example->post_author );
		$this->assertEquals( $this->users['Benjamin'], $author->user_nicename );
	}

	function test_sync() {
		global $WP_Zotero_Sync_Plugin;

		update_option( 'wpzs_settings', $this->get_config() );

		$WP_Zotero_Sync_Plugin->sync();
		$publications = $this->get_publications();

		$this->assertEquals( NUM_ITEMS, count($publications) );

		$example = $publications[1];
		$meta = get_post_meta( $example->ID );

		$categories = wp_get_post_categories( $example->ID );

		$cat = get_category_by_slug('research-articles');
		$cat_ID = $cat->term_id;
		$this->assertEquals( array( $cat_ID ), $categories );
	}
}
