<?php
/*
Plugin Name: Members Manager – Summer‑House Edition (Owner‑Private‑Address)
Description: Tracks summer‑houses (unique property names) and their owners. Includes a searchable member list with e‑mail copy and Excel export.
Version: 1.9
Author: Daniel (with Lumo help)
Text Domain: am
Domain Path: /languages
GitHub Plugin URI: dan1eln1el5en/medlemskartotek
Primary Branch: main
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --------------------------------------------------------------
   0️⃣ TRANSLATIONS
      Admin language follows each user's own profile language
      (Danish translations live in languages/am-da_DK.l10n.php).
-------------------------------------------------------------- */
function am_load_textdomain() {
    load_plugin_textdomain( 'am', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'am_load_textdomain', 0 );

/* --------------------------------------------------------------
   1️⃣ POST TYPES
-------------------------------------------------------------- */
function am_register_property_cpt() {
    $labels = [
        'name'          => __( 'Properties', 'am' ),
        'singular_name' => __( 'Property', 'am' ),
        'add_new'       => __( 'Add New Property', 'am' ),
        'add_new_item'  => __( 'Add New Property', 'am' ),
        'edit_item'     => __( 'Edit Property', 'am' ),
        'all_items'     => __( 'All Properties', 'am' ),
        'search_items'  => __( 'Search Properties', 'am' ),
        'not_found'     => __( 'No properties found.', 'am' ),
        'menu_name'     => __( 'Properties', 'am' ),
    ];
    $args = [
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'capability_type'=> 'post',
        'supports'      => [ 'title' ],
        'show_in_menu'  => false, // lives under the Member List menu
    ];
    register_post_type( 'am_property', $args );
}
add_action( 'init', 'am_register_property_cpt' );

function am_register_person_cpt() {
    $labels = [
        'name'          => __( 'Owners', 'am' ),
        'singular_name' => __( 'Owner', 'am' ),
        'add_new'       => __( 'Add New Owner', 'am' ),
        'add_new_item'  => __( 'Add New Owner', 'am' ),
        'edit_item'     => __( 'Edit Owner', 'am' ),
        'all_items'     => __( 'All Owners', 'am' ),
        'search_items'  => __( 'Search Owners', 'am' ),
        'not_found'     => __( 'No owners found.', 'am' ),
        'menu_name'     => __( 'Owners', 'am' ),
    ];
    $args = [
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'capability_type'=> 'post',
        'supports'      => [ 'title' ],
        'show_in_menu'  => false, // lives under the Member List menu
    ];
    register_post_type( 'am_person', $args );
}
add_action( 'init', 'am_register_person_cpt' );


/* --------------------------------------------------------------
   2️⃣ DATA HELPERS
      The owner of a property is stored once, on the property
      (_am_owner_id). Everything "owner → properties" is derived
      from that, so the two views can never disagree.
-------------------------------------------------------------- */

/* Owner meta keys (key in arrays => meta key). co_* = co‑owner, e.g. spouse. */
function am_person_fields() {
    return [
        'email'    => '_am_email',
        'phone'    => '_am_phone',
        'street'   => '_am_private_street',
        'postcode' => '_am_private_postcode',
        'city'     => '_am_private_city',
        'co_name'  => '_am_coowner_name',
        'co_email' => '_am_coowner_email',
        'co_phone' => '_am_coowner_phone',
    ];
}

/* All published owners, keyed by ID. */
function am_get_owners() {
    static $owners = null;
    if ( null !== $owners ) { return $owners; }

    $owners = [];
    $posts  = get_posts( [
        'post_type'      => 'am_person',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    foreach ( $posts as $p ) {
        $owner = [ 'id' => $p->ID, 'name' => $p->post_title ];
        foreach ( am_person_fields() as $key => $meta_key ) {
            $owner[ $key ] = (string) get_post_meta( $p->ID, $meta_key, true );
        }
        $owners[ $p->ID ] = $owner;
    }
    return $owners;
}

/* All published properties with their owner ID (0 = none, or owner trashed/deleted). */
function am_get_properties() {
    static $props = null;
    if ( null !== $props ) { return $props; }

    $owners = am_get_owners();
    $props  = [];
    $posts  = get_posts( [
        'post_type'      => 'am_property',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    foreach ( $posts as $p ) {
        $owner_id = (int) get_post_meta( $p->ID, '_am_owner_id', true );
        $props[]  = [
            'id'       => $p->ID,
            'name'     => $p->post_title,
            'owner_id' => isset( $owners[ $owner_id ] ) ? $owner_id : 0,
        ];
    }
    return $props;
}

/* Properties owned by one owner. */
function am_get_owner_properties( $owner_id ) {
    return array_values( array_filter( am_get_properties(), function ( $p ) use ( $owner_id ) {
        return $p['owner_id'] === (int) $owner_id;
    } ) );
}

function am_edit_link( $post_id, $label ) {
    return '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $label ) . '</a>';
}


/* --------------------------------------------------------------
   3️⃣ METABOXES (property ↔ owner, owner private address, co‑owner)
-------------------------------------------------------------- */
function am_property_meta_boxes() {
    add_meta_box(
        'am_prop_details',
        __( 'Property Details', 'am' ),
        'am_render_property_meta',
        'am_property',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'am_property_meta_boxes' );

function am_render_property_meta( $post ) {
    wp_nonce_field( 'am_save_prop', 'am_prop_nonce' );
    $owner_id = get_post_meta( $post->ID, '_am_owner_id', true );
    ?>
    <p>
        <label><?php _e( 'Owner (select existing)', 'am' ); ?></label>
        <select name="am_owner_id" style="width:100%;">
            <option value=""><?php _e( '-- none --', 'am' ); ?></option>
            <?php foreach ( am_get_owners() as $o ) : ?>
                <option value="<?php echo esc_attr( $o['id'] ); ?>"
                    <?php selected( $owner_id, $o['id'] ); ?>>
                    <?php echo esc_html( $o['name'] . ( $o['co_name'] ? ' & ' . $o['co_name'] : '' ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}
function am_save_property_meta( $post_id ) {
    if ( ! isset( $_POST['am_prop_nonce'] ) ||
         ! wp_verify_nonce( $_POST['am_prop_nonce'], 'am_save_prop' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    $owner_id = intval( $_POST['am_owner_id'] ?? 0 );
    update_post_meta( $post_id, '_am_owner_id', $owner_id );
}
add_action( 'save_post_am_property', 'am_save_property_meta' );

function am_person_meta_boxes() {
    add_meta_box(
        'am_person_details',
        __( 'Owner Details (Private Residence)', 'am' ),
        'am_render_person_meta',
        'am_person',
        'normal',
        'high'
    );
    add_meta_box(
        'am_person_properties',
        __( 'Owned Properties', 'am' ),
        'am_render_person_properties',
        'am_person',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'am_person_meta_boxes' );

function am_render_person_meta( $post ) {
    wp_nonce_field( 'am_save_person', 'am_person_nonce' );

    $v = [];
    foreach ( am_person_fields() as $key => $meta_key ) {
        $v[ $key ] = get_post_meta( $post->ID, $meta_key, true );
    }
    ?>
    <p>
        <label><?php _e( 'Email:', 'am' ); ?></label>
        <input type="email" name="am_email" value="<?php echo esc_attr( $v['email'] ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Phone / Mobile:', 'am' ); ?></label>
        <input type="text" name="am_phone" value="<?php echo esc_attr( $v['phone'] ); ?>" style="width:100%;">
    </p>
    <hr/>
    <p><strong><?php _e( 'Co-owner (optional, e.g. spouse)', 'am' ); ?></strong></p>
    <p>
        <label><?php _e( 'Name:', 'am' ); ?></label>
        <input type="text" name="am_co_name" value="<?php echo esc_attr( $v['co_name'] ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Email:', 'am' ); ?></label>
        <input type="email" name="am_co_email" value="<?php echo esc_attr( $v['co_email'] ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Phone / Mobile:', 'am' ); ?></label>
        <input type="text" name="am_co_phone" value="<?php echo esc_attr( $v['co_phone'] ); ?>" style="width:100%;">
    </p>
    <hr/>
    <p><strong><?php _e( 'Private Residential Address (official residence)', 'am' ); ?></strong></p>
    <p>
        <label><?php _e( 'Street:', 'am' ); ?></label>
        <input type="text" name="am_street" value="<?php echo esc_attr( $v['street'] ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Postcode:', 'am' ); ?></label>
        <input type="text" name="am_postcode" value="<?php echo esc_attr( $v['postcode'] ); ?>" style="width:100px;">
    </p>
    <p>
        <label><?php _e( 'City:', 'am' ); ?></label>
        <input type="text" name="am_city" value="<?php echo esc_attr( $v['city'] ); ?>" style="width:100%;">
    </p>
    <?php
}

function am_render_person_properties( $post ) {
    $props = am_get_owner_properties( $post->ID );
    if ( $props ) {
        echo '<ul style="margin:0;">';
        foreach ( $props as $p ) {
            echo '<li>' . am_edit_link( $p['id'], $p['name'] ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p><em>' . esc_html__( 'This owner has no property.', 'am' ) . '</em></p>';
    }
    echo '<p class="description">' . esc_html__( 'Ownership is changed on the property itself.', 'am' ) . '</p>';
}

function am_save_person_meta( $post_id ) {
    if ( ! isset( $_POST['am_person_nonce'] ) ||
         ! wp_verify_nonce( $_POST['am_person_nonce'], 'am_save_person' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    foreach ( am_person_fields() as $key => $meta_key ) {
        $raw   = wp_unslash( $_POST[ 'am_' . $key ] ?? '' );
        $value = in_array( $key, [ 'email', 'co_email' ], true ) ? sanitize_email( $raw ) : sanitize_text_field( $raw );
        update_post_meta( $post_id, $meta_key, $value );
    }
}
add_action( 'save_post_am_person', 'am_save_person_meta' );


/* --------------------------------------------------------------
   4️⃣ ADMIN LIST COLUMNS (email / phone / who‑owns‑what)
-------------------------------------------------------------- */
function am_property_columns( $cols ) {
    return [
        'cb'       => $cols['cb'],
        'title'    => __( 'Property', 'am' ),
        'am_owner' => __( 'Owner', 'am' ),
        'am_email' => __( 'E‑mail', 'am' ),
        'am_phone' => __( 'Phone', 'am' ),
    ];
}
add_filter( 'manage_am_property_posts_columns', 'am_property_columns' );

function am_property_column_content( $col, $post_id ) {
    $owners   = am_get_owners();
    $owner_id = (int) get_post_meta( $post_id, '_am_owner_id', true );
    $o        = $owners[ $owner_id ] ?? null;

    if ( ! $o ) {
        if ( 'am_owner' === $col ) { echo '—'; }
        return;
    }
    am_render_owner_column( $col, $o );
}
add_action( 'manage_am_property_posts_custom_column', 'am_property_column_content', 10, 2 );

function am_person_columns( $cols ) {
    return [
        'cb'            => $cols['cb'],
        'title'         => __( 'Owner', 'am' ),
        'am_email'      => __( 'E‑mail', 'am' ),
        'am_phone'      => __( 'Phone', 'am' ),
        'am_properties' => __( 'Properties', 'am' ),
    ];
}
add_filter( 'manage_am_person_posts_columns', 'am_person_columns' );

function am_person_column_content( $col, $post_id ) {
    if ( 'am_properties' === $col ) {
        $links = array_map( function ( $p ) { return am_edit_link( $p['id'], $p['name'] ); }, am_get_owner_properties( $post_id ) );
        echo $links ? implode( ', ', $links ) : '—';
        return;
    }
    $owners = am_get_owners();
    if ( isset( $owners[ $post_id ] ) ) {
        am_render_owner_column( $col, $owners[ $post_id ] );
    }
}
add_action( 'manage_am_person_posts_custom_column', 'am_person_column_content', 10, 2 );

/* Shared cell output; co‑owner shown on a second line. */
function am_render_owner_column( $col, $o ) {
    $lines = [];
    switch ( $col ) {
        case 'am_owner':
            $lines = [ am_edit_link( $o['id'], $o['name'] ), esc_html( $o['co_name'] ) ];
            break;
        case 'am_email':
            foreach ( [ $o['email'], $o['co_email'] ] as $e ) {
                $lines[] = $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '';
            }
            break;
        case 'am_phone':
            $lines = [ esc_html( $o['phone'] ), esc_html( $o['co_phone'] ) ];
            break;
    }
    echo implode( '<br>', array_filter( $lines ) );
}

/* Default admin lists to alphabetical order. */
function am_default_admin_order( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) { return; }
    if ( ! in_array( $query->get( 'post_type' ), [ 'am_property', 'am_person' ], true ) ) { return; }
    if ( ! isset( $_GET['orderby'] ) ) {
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'am_default_admin_order' );


/* --------------------------------------------------------------
   5️⃣ CSV IMPORT (menu entry registered in am_members_menu)
-------------------------------------------------------------- */
function am_render_import_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Import Summer‑House CSV (Owner Private Address)', 'am' ); ?></h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'am_import_csv', 'am_import_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="am_csv_file"><?php _e( 'CSV File', 'am' ); ?></label></th>
                    <td><input type="file" name="am_csv_file" id="am_csv_file" accept=".csv" required></td>
                </tr>
            </table>
            <?php submit_button( __( 'Start Import', 'am' ) ); ?>
        </form>

        <?php
        if ( isset( $_POST['am_import_nonce'] )
            && wp_verify_nonce( $_POST['am_import_nonce'], 'am_import_csv' )
            && ! empty( $_FILES['am_csv_file']['tmp_name'] ) ) {

            $summary = am_process_csv( $_FILES['am_csv_file']['tmp_name'] );

            echo '<h2>' . __( 'Import Summary', 'am' ) . '</h2><ul>';
            foreach ( $summary as $msg ) {
                echo '<li>' . esc_html( $msg ) . '</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
    <?php
}

function am_process_csv( $filepath ) {
    $handle = fopen( $filepath, 'r' );
    if ( ! $handle ) {
        return [ __( 'Unable to open the uploaded file.', 'am' ) ];
    }

    $log = []; $row_num = 0;
    while ( ( $raw = fgets( $handle ) ) !== false ) {
        $row_num++;
        if ( preg_match( '/^;+$/', trim( $raw ) ) ) { continue; }

        $cols = str_getcsv( $raw, ';' );
        $cols = array_map( 'trim', $cols );
        if ( count( array_filter( $cols ) ) === 0 ) { continue; }

        $prop_name   = $cols[0] ?? '';
        $first_name  = $cols[1] ?? '';
        $last_name   = $cols[2] ?? '';
        $priv_street = $cols[4] ?? '';
        $email       = $cols[5] ?? '';
        $priv_pc     = $cols[6] ?? '';
        $priv_city   = $cols[7] ?? '';
        $priv_phone  = $cols[8] ?? '';

        if ( empty( $prop_name ) ) {
            $log[] = sprintf( __( 'Row %d: skipped (no property name).', 'am' ), $row_num );
            continue;
        }

        // ----- Owner -----
        $owner_full = trim( $first_name . ' ' . $last_name );
        $owner_id   = null;
        $contact    = [
            '_am_email'            => $email,
            '_am_phone'            => $priv_phone,
            '_am_private_street'   => $priv_street,
            '_am_private_postcode' => $priv_pc,
            '_am_private_city'     => $priv_city,
        ];

        if ( $email ) {
            $found = get_posts( [
                'post_type'      => 'am_person',
                'meta_key'       => '_am_email',
                'meta_value'     => $email,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ] );
            if ( $found ) { $owner_id = $found[0]; }
        }

        if ( ! $owner_id && $owner_full ) {
            $found = get_posts( [
                'post_type' => 'am_person',
                'title'     => $owner_full,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ] );
            if ( $found ) { $owner_id = $found[0]; }
        }

        if ( $owner_id ) {
            // Existing owner: refresh contact info with whatever the CSV provides.
            foreach ( $contact as $meta_key => $value ) {
                if ( '' !== $value ) { update_post_meta( $owner_id, $meta_key, $value ); }
            }
        } else {
            $owner_id = wp_insert_post( [
                'post_type'   => 'am_person',
                'post_title'  => $owner_full,
                'post_status' => 'publish',
            ] );
            if ( $owner_id && ! is_wp_error( $owner_id ) ) {
                foreach ( $contact as $meta_key => $value ) {
                    update_post_meta( $owner_id, $meta_key, $value );
                }
                $log[] = sprintf( __( 'Created owner "%s".', 'am' ), $owner_full );
            }
        }

        // ----- Property -----
        $existing = get_posts( [
            'post_type'      => 'am_property',
            'title'          => $prop_name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( $existing ) {
            $prop_id = $existing[0];
            update_post_meta( $prop_id, '_am_owner_id', $owner_id );
            $log[] = sprintf( __( 'Updated property "%s" (owner refreshed).', 'am' ), $prop_name );
        } else {
            $prop_id = wp_insert_post( [
                'post_type'   => 'am_property',
                'post_title'  => $prop_name,
                'post_status' => 'publish',
            ] );
            if ( $prop_id && ! is_wp_error( $prop_id ) ) {
                update_post_meta( $prop_id, '_am_owner_id', $owner_id );
                $log[] = sprintf( __( 'Added property "%s".', 'am' ), $prop_name );
            } else {
                $log[] = sprintf( __( 'Row %d: failed to create property.', 'am' ), $row_num );
            }
        }
    }
    fclose( $handle );
    return $log;
}


/* --------------------------------------------------------------
   6️⃣ MEMBER LIST
      Searchable/sortable table, per property or per owner,
      with "copy e‑mails" and Excel (CSV) download of what's shown.
      Rendering happens in assets/members-list.js.
-------------------------------------------------------------- */
define( 'AM_OWNER_VIEW_SLUG', 'admin.php?page=am_members&view=owner' );

/* One menu for everything: Properties / Owners both open the member list. */
function am_members_menu() {
    add_menu_page(
        __( 'Member List', 'am' ),
        __( 'Member List', 'am' ),
        'manage_options',
        'am_members',
        'am_render_members_page',
        'dashicons-groups',
        19
    );
    add_submenu_page( 'am_members', __( 'Member List', 'am' ), __( 'Properties', 'am' ), 'manage_options', 'am_members', 'am_render_members_page' );
    add_submenu_page( 'am_members', __( 'Owners', 'am' ), __( 'Owners', 'am' ), 'manage_options', AM_OWNER_VIEW_SLUG );
    add_submenu_page( 'am_members', __( 'Add New Property', 'am' ), __( 'Add New Property', 'am' ), 'manage_options', 'post-new.php?post_type=am_property' );
    add_submenu_page( 'am_members', __( 'Add New Owner', 'am' ), __( 'Add New Owner', 'am' ), 'manage_options', 'post-new.php?post_type=am_person' );
    add_submenu_page( 'am_members', __( 'Import CSV', 'am' ), __( 'Import CSV', 'am' ), 'manage_options', 'am_import_csv', 'am_render_import_page' );
}
add_action( 'admin_menu', 'am_members_menu' );

/* The old list screens redirect to the member list (the trash view stays reachable). */
function am_redirect_list_screens() {
    global $typenow;
    if ( ! in_array( $typenow, [ 'am_property', 'am_person' ], true ) ) { return; }
    if ( isset( $_REQUEST['post_status'] ) ) { return; }

    $args = [ 'page' => 'am_members', 'view' => 'am_person' === $typenow ? 'owner' : 'property' ];
    if ( isset( $_GET['trashed'] ) ) {
        $args['trashed'] = absint( $_GET['trashed'] );
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'load-edit.php', 'am_redirect_list_screens' );

/* Keep the Member List menu open and the right item highlighted on edit screens. */
function am_menu_parent( $parent_file ) {
    global $typenow;
    return in_array( $typenow, [ 'am_property', 'am_person' ], true ) ? 'am_members' : $parent_file;
}
add_filter( 'parent_file', 'am_menu_parent' );

function am_menu_submenu( $submenu_file ) {
    global $typenow, $pagenow, $plugin_page;
    if ( 'am_members' === $plugin_page ) {
        return ( $_GET['view'] ?? '' ) === 'owner' ? AM_OWNER_VIEW_SLUG : 'am_members';
    }
    if ( ! in_array( $typenow, [ 'am_property', 'am_person' ], true ) ) { return $submenu_file; }
    if ( 'post-new.php' === $pagenow ) {
        return 'post-new.php?post_type=' . $typenow;
    }
    return 'am_person' === $typenow ? AM_OWNER_VIEW_SLUG : 'am_members';
}
add_filter( 'submenu_file', 'am_menu_submenu' );

/* "Back to member list" link on the edit screens. */
function am_edit_screen_back_link( $post ) {
    if ( ! in_array( $post->post_type, [ 'am_property', 'am_person' ], true ) ) { return; }
    $url = admin_url( 'am_person' === $post->post_type ? AM_OWNER_VIEW_SLUG : 'admin.php?page=am_members&view=property' );
    echo '<p class="am-back"><a href="' . esc_url( $url ) . '">' . esc_html__( '← Back to member list', 'am' ) . '</a></p>';
}
add_action( 'edit_form_top', 'am_edit_screen_back_link' );

function am_members_assets( $hook ) {
    if ( 'toplevel_page_am_members' !== $hook ) { return; }

    $url = plugin_dir_url( __FILE__ ) . 'assets/';
    $ver = '1.9';
    wp_enqueue_style( 'am-members', $url . 'members-list.css', [], $ver );
    wp_enqueue_script( 'am-members', $url . 'members-list.js', [], $ver, true );

    $view = sanitize_key( $_GET['view'] ?? '' );
    $data = [
        'owners'     => array_values( am_get_owners() ),
        'properties' => am_get_properties(),
        'view'       => in_array( $view, [ 'property', 'owner', 'map' ], true ) ? $view : '',
        'editUrl'    => admin_url( 'post.php?action=edit&post=' ),
        'mapUrl'     => $url . 'map-data.json?ver=' . $ver,
        'i18n'       => [
            'property'        => __( 'Property', 'am' ),
            'owner'           => __( 'Owner', 'am' ),
            'coOwner'         => __( 'Co-owner', 'am' ),
            'email'           => __( 'E‑mail', 'am' ),
            'coEmail'         => __( 'Co-owner e‑mail', 'am' ),
            'phone'           => __( 'Phone', 'am' ),
            'coPhone'         => __( 'Co-owner phone', 'am' ),
            'address'         => __( 'Private address', 'am' ),
            'street'          => __( 'Private Street', 'am' ),
            'postcode'        => __( 'Postcode', 'am' ),
            'city'            => __( 'City', 'am' ),
            'noOwner'         => __( 'No owner', 'am' ),
            'noProperty'      => __( 'No property', 'am' ),
            'stats'           => __( '%1$d properties · %2$d owners', 'am' ),
            'statsNoOwner'    => __( '%d properties without owner', 'am' ),
            'statsNoProperty' => __( '%d owners without property', 'am' ),
            'showing'         => __( 'Showing %1$d of %2$d', 'am' ),
            'copied'          => __( '%d e‑mail addresses copied – paste them into BCC.', 'am' ),
            'copyFailed'      => __( 'Could not copy automatically. Select the addresses below and copy them:', 'am' ),
            'noEmails'        => __( 'No e‑mail addresses in the current list.', 'am' ),
            'fileName'        => __( 'members', 'am' ),
            'map'             => __( 'Map', 'am' ),
            'mapHint'         => __( 'Hover over a property to see the owner – click to open.', 'am' ),
            'mapError'        => __( 'Could not load the map.', 'am' ),
            'mapSource'       => __( 'Map: Matriklen, Dataforsyningen (CC BY 4.0)', 'am' ),
            'legendOwned'     => __( 'Has owner', 'am' ),
            'legendNoOwner'   => __( 'No owner registered', 'am' ),
            'legendUnknown'   => __( 'Not in the member list', 'am' ),
        ],
    ];
    wp_add_inline_script( 'am-members', 'window.amMembers = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'am_members_assets' );

function am_render_members_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.', 'am' ) );
    }
    ?>
    <div class="wrap am-members">
        <h1><?php _e( 'Member List', 'am' ); ?></h1>
        <?php if ( ! empty( $_GET['trashed'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e( 'Moved to trash.', 'am' ); ?></p></div>
        <?php endif; ?>
        <p class="am-stats" id="am-stats"></p>

        <div class="am-toolbar">
            <span class="am-view-toggle" role="group" aria-label="<?php esc_attr_e( 'View', 'am' ); ?>">
                <button type="button" class="button" data-view="property"><?php _e( 'Per property', 'am' ); ?></button>
                <button type="button" class="button" data-view="owner"><?php _e( 'Per owner', 'am' ); ?></button>
                <button type="button" class="button" data-view="map"><?php _e( 'Map', 'am' ); ?></button>
            </span>
            <input type="search" id="am-search" class="am-search"
                   placeholder="<?php esc_attr_e( 'Search name, property, e‑mail, phone…', 'am' ); ?>">
            <button type="button" class="button button-primary" id="am-copy-emails"><?php _e( 'Copy e‑mails', 'am' ); ?></button>
            <button type="button" class="button" id="am-download-csv"><?php _e( 'Download for Excel', 'am' ); ?></button>
            <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=am_person' ) ); ?>"><?php _e( 'Add New Owner', 'am' ); ?></a>
            <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=am_property' ) ); ?>"><?php _e( 'Add New Property', 'am' ); ?></a>
        </div>

        <div class="am-notice" id="am-notice" hidden></div>
        <p class="am-count" id="am-count"></p>

        <table class="widefat striped am-table" id="am-table">
            <thead></thead>
            <tbody></tbody>
        </table>
        <div class="am-map" id="am-map" hidden></div>

        <?php
        $trash = [];
        foreach ( [ 'am_property' => __( 'Properties (%d)', 'am' ), 'am_person' => __( 'Owners (%d)', 'am' ) ] as $type => $label ) {
            $count = (int) wp_count_posts( $type )->trash;
            if ( $count ) {
                $trash[] = '<a href="' . esc_url( admin_url( "edit.php?post_type={$type}&post_status=trash" ) ) . '">' . esc_html( sprintf( $label, $count ) ) . '</a>';
            }
        }
        if ( $trash ) {
            echo '<p class="am-trash">' . esc_html__( 'Trash', 'am' ) . ': ' . implode( ' · ', $trash ) . '</p>';
        }
        ?>
    </div>
    <?php
}
