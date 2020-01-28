<?php

    function my_theme_enqueue_styles() {
        $parent_style = 'woffice-style'; // This is 'twentyfifteen-style' for the Twenty Fifteen theme.
        wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
        wp_enqueue_style(
            'child-style',
            get_stylesheet_directory_uri() . '/style.css',
            array( $parent_style ),
            wp_get_theme()->get('Version')
        );
    }
    add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );


    // Helper function that returns true or false depending on whether the user has tegoeden enabled
    function tegoeden_are_enabled($user) {
        if (!$user) return;
        $has_tegoed = get_user_meta( $user->ID, 'enable_tegoeden', 'single' );
        return $has_tegoed === "true";
    }

    // Helper function returning an array containing all userIds of users that have tegoeden enabled
    function getDeveloperIds() {
        $user_ids = array();
        $employees = get_users();
        foreach ($employees as $user) {           
            if (tegoeden_are_enabled($user)) {
                $user_ids[] = $user->ID;
            }
        }
        return $user_ids;
    }


    // Helper function used by display_tegoed() and ibenic_buddypress_tab() to calculate the total of studie bedrag or studie dagen
    function calculateTotal($type) {
        $user_id = get_current_user_id();
        $the_query = new WP_Query(array(
            'post_type'         => 'tegoeden',
            'orderby'           => 'publish_date',
            'order'             => 'ASC',
            'posts_per_page'    => -1,
            'meta_query'        => array(
                array(
                    array( 'key' => 'type', 'value' => $type, 'compare' => '=' ),
                    array( 'key' => 'medewerker', 'value' => $user_id, 'compare' => '=' ),
                    'relation' => 'AND'
                )
            )
        ));

        $sum = 0;
        if ( $the_query->have_posts() ) {
            while ($the_query->have_posts()) : $the_query->the_post();
            $sum += get_post_meta( get_the_ID(), 'aanpassing', 'single' );
            endwhile;
        }
        wp_reset_postdata();
        return $sum;
    }


    /* 
     *  BuddyPress add additional tabs Studie bedrag & Studie dagen
     */

    function ibenic_buddypress_tab() {
        $user = wp_get_current_user();
        if (!tegoeden_are_enabled($user)) return;


        $bedrag_count  = calculateTotal('bedrag');
        $bedrag_class  = ( 0 === $bedrag_count ) ? 'no-count' : 'count';
        $dagen_count  = calculateTotal('dagen');
        $dagen_class  = ( 0 === $dagen_count ) ? 'no-count' : 'count';

        global $bp;
        bp_core_new_nav_item( array( 
            'name'  =>  sprintf( __( 'Studie bedrag <span class="%s">%s</span>', 'woffice' ), esc_attr( $bedrag_class ), number_format_i18n( $bedrag_count ) ),
            'slug' => 'studie-bedrag', 
            'position' => 100,
            'screen_function' => 'ibenic_budypress_studie_bedrag',
            'show_for_displayed_user' => true,
            'item_css_id' => 'fa-money',
        ) );
        bp_core_new_nav_item( array( 
            'name'  =>  sprintf( __( 'Studie dagen <span class="%s">%s</span>', 'woffice' ), esc_attr( $dagen_class ), number_format_i18n( $dagen_count ) ),
            'slug' => 'studie-dagen', 
            'position' => 100,
            'screen_function' => 'ibenic_budypress_studie_dagen',
            'show_for_displayed_user' => true,
            'item_css_id' => 'fa-calendar',
        ) );    
    }
    add_action( 'bp_setup_nav', 'ibenic_buddypress_tab', 1000 );

    // Populate the bedrag tab
    function ibenic_budypress_studie_bedrag () {
        add_action( 'bp_template_title', function () { _e( 'Bedrag aan studie tegoed', 'woffice' ); } );
        add_action( 'bp_template_content', function () { display_tegoed('bedrag'); } );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }

    // Populate the dagen tab
    function ibenic_budypress_studie_dagen () {
        add_action( 'bp_template_title', function () { _e( 'Tegoed aantal studie dagen', 'woffice' ); } );
        add_action( 'bp_template_content', function () { display_tegoed('dagen'); } );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }

    // Function for displaying the various rows of tegoed (dagen en bedrag) a medewerker has
    function display_tegoed($type) {
        $user_id = get_current_user_id();
        $the_query = new WP_Query(array(
            'post_type'         => 'tegoeden',
            'orderby'           => 'publish_date',
            'order'             => 'DESC',
            'posts_per_page'    => 36,
            'meta_query'        => array(
                array(
                array( 'key' => 'type', 'value' => $type, 'compare' => '=' ),
                array( 'key' => 'medewerker', 'value' => $user_id, 'compare' => '=' ),
                'relation' => 'AND'
                )
            )
        ));

        echo '<div class="tegoed__row">' .
            '<div class="tegoed__title"><strong>Totaal</strong></div>' .
            '<div class="tegoed__aanpassing"><strong>' . calculateTotal($type) . '</strong></div>' .
        '</div>';

        if ( $the_query->have_posts() ) {
            echo '<div class="tegoed__wrapper">';
            while ($the_query->have_posts()) : $the_query->the_post();
                get_template_part('tegoed-overview');
            endwhile;
            echo '</div>';
        }
        wp_reset_postdata();
    }


    /* 
     *  Register custom post type tegoeden.
     */

    function cptui_register_my_cpts_tegoeden() {    
        $labels = [
            "name" => __( "Tegoeden", "woffice" ),
            "singular_name" => __( "Tegoed", "woffice" ),
            "menu_name" => __( "Tegoeden", "woffice" ),
            "all_items" => __( "Alle Tegoeden", "woffice" ),
            "add_new" => __( "Add tegoed", "woffice" ),
            "add_new_item" => __( "Add new Tegoed", "woffice" ),
            "edit_item" => __( "Update Tegoed", "woffice" ),
            "new_item" => __( "New Tegoed", "woffice" ),
            "view_item" => __( "View Tegoed", "woffice" ),
            "view_items" => __( "View Tegoeden", "woffice" ),
            "search_items" => __( "Search Tegoeden", "woffice" ),
            "not_found" => __( "No Tegoeden found", "woffice" ),
            "not_found_in_trash" => __( "No Tegoeden found in trash", "woffice" ),
            "parent" => __( "Parent Tegoed:", "woffice" ),
            "featured_image" => __( "Featured image for this Tegoed", "woffice" ),
            "set_featured_image" => __( "Set featured image for this Tegoed", "woffice" ),
            "remove_featured_image" => __( "Remove featured image for this Tegoed", "woffice" ),
            "use_featured_image" => __( "Use as featured image for this Tegoed", "woffice" ),
            "archives" => __( "Tegoed archives", "woffice" ),
            "insert_into_item" => __( "Insert into Tegoed", "woffice" ),
            "uploaded_to_this_item" => __( "Upload to this Tegoed", "woffice" ),
            "filter_items_list" => __( "Filter Tegoeden list", "woffice" ),
            "items_list_navigation" => __( "Tegoeden list navigation", "woffice" ),
            "items_list" => __( "Tegoeden list", "woffice" ),
            "attributes" => __( "Tegoeden attributes", "woffice" ),
            "name_admin_bar" => __( "Tegoed", "woffice" ),
            "item_published" => __( "Tegoed published", "woffice" ),
            "item_published_privately" => __( "Tegoed published privately.", "woffice" ),
            "item_reverted_to_draft" => __( "Tegoed reverted to draft.", "woffice" ),
            "item_scheduled" => __( "Tegoed scheduled", "woffice" ),
            "item_updated" => __( "Tegoed updated.", "woffice" ),
            "parent_item_colon" => __( "Parent Tegoed:", "woffice" ),
        ];
    
        $args = [
            "label" => __( "Tegoeden", "woffice" ),
            "labels" => $labels,
            "description" => "",
            "public" => true,
            "publicly_queryable" => false,
            "show_ui" => true,
            "delete_with_user" => false,
            "show_in_rest" => true,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "has_archive" => false,
            "show_in_menu" => true,
            "show_in_nav_menus" => false,
            "delete_with_user" => false,
            "exclude_from_search" => false,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "hierarchical" => false,
            "rewrite" => [ "slug" => "tegoeden", "with_front" => true ],
            "query_var" => true,
            "supports" => [ "title", "custom-fields" ],
            'capabilities' => array(
                'edit_post'          => 'update_core',
                'read_post'          => 'update_core',
                'delete_post'        => 'update_core',
                'edit_posts'         => 'update_core',
                'edit_others_posts'  => 'update_core',
                'delete_posts'       => 'update_core',
                'publish_posts'      => 'update_core',
                'read_private_posts' => 'update_core'
            ),
        ];
    
        register_post_type( "tegoeden", $args );
    }
    add_action( 'init', 'cptui_register_my_cpts_tegoeden' );
    
    // Adjust title field for custom post type tegoeden.
    function tegoeden_title_placeholder($title , $post){
        
        if( $post->post_type == 'tegoeden' ){
            $tegoed_title = "Omschrijving van de aanpassing";
            return $tegoed_title;
        }
        return $title;
    }
    add_filter('enter_title_here', 'tegoeden_title_placeholder' , 20 , 2 );

    // Admin sectie in backend, add meta data columns
    function filter_tegoed_posts_columns( $columns ) {
        $columns = array(
            'cb' => $columns['cb'],
            'date' => __( 'Datum' ),
            'medewerker' => __( 'Medewerker' ),
            'type' => __( 'Type' ),
            'title' => __( 'Omschrijving aanpassing' ),
            'aanpassing' => __( 'Aanpassing' ),
        );
        return $columns;
    }
    add_filter( 'manage_tegoeden_posts_columns', 'filter_tegoed_posts_columns' );

    // Admin sectie in backend, add meta data column data
    function populate_tegoed_column( $column, $post_id ) {
        if ( 'medewerker' === $column ) {
            $user_id = get_post_meta( $post_id, 'medewerker', true );
            $user = get_userdata($user_id);
            echo $user->display_name;
        }
        if ( 'type' === $column ) {
            echo get_post_meta( $post_id, 'type', true );
        }
        if ( 'aanpassing' === $column ) {
            echo get_post_meta( $post_id, 'aanpassing', true );
        }
    }
    add_action( 'manage_tegoeden_posts_custom_column', 'populate_tegoed_column', 10, 2);

    // Function for populating the medewerkers custom field
    function acf_load_medewerker_choices( $field ) {
        $field['choices'] = array();

        $employees = get_users();
        foreach ($employees as $user) {           
            if (tegoeden_are_enabled($user)) {
                $field['choices'][$user->ID] = $user->display_name;
            }
        }
        return $field;
    }
    add_filter('acf/load_field/name=medewerker', 'acf_load_medewerker_choices');


    /* 
     *  Crop Job code
     *  For debugging, the Crontrol WP plugin can be helpful to see the scheduled cron jobs
     */

    // When no cron is set yet, add an hourly cron to fire the tegoeden_hourly_cron_job action.
    function activate_tegoeden_cron() {
        if (!wp_next_scheduled('tegoeden_hourly_cron_job')) {
            $at_six_am = strtotime('06:00:00');
            wp_schedule_event($at_six_am, 'daily', 'tegoeden_hourly_cron_job');
        }    
    }

    // Call the actual function to activate the cron. It'll only run once.
    activate_tegoeden_cron();

    // Linking the tegoeden_hourly_cron_job to the check_dates_add_tegoeden function
    add_action( 'tegoeden_hourly_cron_job',  'check_dates_add_tegoeden' );

    // Check if the it's the 1st of the month and fire the monthly or half yearly functions.
    function check_dates_add_tegoeden() {
        $month = date('m');
        $day = date('d');
        if ('01' === $day) {
            // run the monthly studie dagen function every 1st day of the month
            monthly_dagen_update();

            if ('01' === $month || '06' === $month) {
                // run the six monthly studie tegoeden function only in January and June.
                half_yearly_bedrag_update();
            }
        }
    }
    add_action('check_dates_add_tegoeden_cron', 'check_dates_add_tegoeden');

    // Add for each developer 1 studie dag
    function monthly_dagen_update() {
        $developerIds = getDeveloperIds();
        foreach($developerIds as $id) {
            $post_id = wp_insert_post(array(
                'post_title' => 'Maandelijkse studie dag',
                'post_type' => 'tegoeden', 
                'post_content' => '',
                'post_status' => 'publish',
                'meta_input' => array(
                    'type' => 'dagen',
                    'medewerker' => $id,
                    'aanpassing' => 1,
                ),
            ));    
        }
    }

    // Add for each developer 250 studie tegoed
    function half_yearly_bedrag_update() {
        $developerIds = getDeveloperIds();
        foreach($developerIds as $id) {
            $post_id = wp_insert_post(array(
                'post_title' => 'Halfjaarlijks studie tegoed', 
                'post_type' => 'tegoeden', 
                'post_content' => '',
                'post_status' => 'publish',
                'meta_input' => array(
                    'type' => 'bedrag',
                    'medewerker' => $id,
                    'aanpassing' => 250,
                ),
            ));    
        }
    }

?>