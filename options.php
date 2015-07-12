<?php

class WPZoteroSyncOptionHandler {
    private $settings_group = 'wp-zotero-sync';
    private $settings_section_name = 'wp-zotero-sync-settings';
    private $setting_name = 'wpzs_settings';
    private $page_name = 'wp-zotero-sync-page';
    
    public function __construct() {
    }

    public function add_submenu() {
        add_submenu_page( 'edit.php?post_type=publication',
                          __('Zotero Sync', 'wp-zotero-sync'),
                          __('Zotero Sync', 'wp-zotero-sync'),
                          'edit_plugins',
                          $this->page_name,
                          array( $this, 'submenu' )
        );
    }

    public function register_settings() {
        add_option( $this->setting_name, array() );
        register_setting(
            $this->settings_group,
            $this->setting_name
        );

        add_settings_section(
            $this->settings_section_name,
            __('Library Configuration', 'wp-zotero-sync'),
            array( $this, 'settings_section' ),
            $this->page_name
        );

        $this->add_field( 'library_type', 'Library Type' );
        $this->add_field( 'library_id', 'Library ID' );
        $this->add_field( 'library_slug', 'Library Slug' );
        $this->add_field( 'collection_key', 'Collection Key' );
    }

    private function add_field( $name, $label ) {
        add_settings_field(
            $name,
            __( $label, 'wp-zotero-sync' ),
            array( $this, 'text_field' ),
            $this->page_name,
            $this->settings_section_name,
            array( 'field' => $name,
                   'setting' => $this->setting_name,
            )
        );
    }

    public function text_field( $args ) {
        $field = $args['field'];
        $setting = $args['setting'];

        $options = get_option( $setting );
        $name = $setting . '[' . $field . ']';
        $value = $options[$field];
        ?>
	<input type='text' name='<?php echo $name; ?>' value='<?php echo $value; ?>'/>
    <?php
    }

    public function settings_section() {
        echo '<p>' . __( 'Enter the details of your Zotero library.', 'wp-zotero-sync' ) . '</p>';
    }

    public function submenu() {
        if ( !current_user_can( 'edit_plugins' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        echo '<div class="wrap">';
        echo '<h2>' . __( 'Zotero Sync Setup', 'wp-zotero-sync' ) . '</h2>';

        settings_errors();

        echo "<form action='options.php' method='post'>";

        settings_fields( $this->settings_group );
        do_settings_sections( $this->page_name );
        submit_button();

        echo '</form>';
        echo '</div>';
    }
}