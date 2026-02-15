<?php
/*
Plugin Name: Members Manager – Summer‑House Edition (Owner‑Private‑Address)
Description: Tracks summer‑houses (identified by a unique property name) and their owners. Owner records store the private residential address.
Version: 1.3
Author: Daniel (with Lumo help)
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------
   1️⃣ POST TYPES
------------------------------------------------------------------- */
// 1️⃣ Property – each summer‑house (unique name from column 0)
function am_register_property_cpt() {
    $labels = [
        'name'          => __( 'Properties', 'am' ),
        'singular_name' => __( 'Property', 'am' ),
        'add_new'       => __( 'Add New Property', 'am' ),
        'menu_name'     => __( 'Properties', 'am' ),
    ];
    $args = [
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'capability_type'=> 'post',
        'supports'      => [ 'title' ], // title = unique property name (e.g. KT1, SV15)
        'menu_position' => 20,
        'menu_icon'     => 'dashicons-admin-home',
    ];
    register_post_type( 'am_property', $args );
}
add_action( 'init', 'am_register_property_cpt' );

// 2️⃣ Owner – one record per person (stores private address)
function am_register_person_cpt() {
    $labels = [
        'name'          => __( 'Owners', 'am' ),
        'singular_name' => __( 'Owner', 'am' ),
        'add_new'       => __( 'Add New Owner', 'am' ),
        'menu_name'     => __( 'Owners', 'am' ),
    ];
    $args = [
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'capability_type'=> 'post',
        'supports'      => [ 'title' ], // title = "First Last"
        'menu_position' => 21,
        'menu_icon'     => 'dashicons-id-alt',
    ];
    register_post_type( 'am_person', $args );
}
add_action( 'init', 'am_register_person_cpt' );


/* ------------------------------------------------------------------
   2️⃣ PROPERTY METABOX (link to owner)
------------------------------------------------------------------- */
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

    // Owner dropdown (list all owners)
    $owners = get_posts( [
        'post_type'      => 'am_person',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    ?>
    <p>
        <label><?php _e( 'Owner (select existing)', 'am' ); ?></label> 
        <select name="am_owner_id" style="width:100%;">
            <option value=""><?php _e( '-- none --', 'am' ); ?></option>
            <?php foreach ( $owners as $o ) : ?>
                <option value="<?php echo esc_attr( $o->ID ); ?>"
                    <?php selected( $owner_id, $o->ID ); ?>>
                    <?php echo esc_html( $o->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

/* ------------------------------------------------------------------
   3️⃣ SAVE PROPERTY (store owner link)
------------------------------------------------------------------- */
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


/* ------------------------------------------------------------------
   4️⃣ OWNER METABOX (private address fields)
------------------------------------------------------------------- */
function am_person_meta_boxes() {
    add_meta_box(
        'am_person_details',
        __( 'Owner Details (Private Residence)', 'am' ),
        'am_render_person_meta',
        'am_person',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'am_person_meta_boxes' );

function am_render_person_meta( $post ) {
    wp_nonce_field( 'am_save_person', 'am_person_nonce' );

    $email    = get_post_meta( $post->ID, '_am_email', true );
    $phone    = get_post_meta( $post->ID, '_am_phone', true );
    $addr_str = get_post_meta( $post->ID, '_am_private_street', true );
    $addr_pc  = get_post_meta( $post->ID, '_am_private_postcode', true );
    $addr_ct  = get_post_meta( $post->ID, '_am_private_city', true );
    ?>
    <p>
        <label><?php _e( 'Email:', 'am' ); ?></label> 
        <input type="email" name="am_email" value="<?php echo esc_attr( $email ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Phone / Mobile:', 'am' ); ?></label> 
        <input type="text" name="am_phone" value="<?php echo esc_attr( $phone ); ?>" style="width:100%;">
    </p>
    <hr/>
    <p><strong><?php _e( 'Private Residential Address (where the owner officially lives)', 'am' ); ?></strong></p>
    <p>
        <label><?php _e( 'Street:', 'am' ); ?></label> 
        <input type="text" name="am_private_street" value="<?php echo esc_attr( $addr_str ); ?>" style="width:100%;">
    </p>
    <p>
        <label><?php _e( 'Postcode:', 'am' ); ?></label> 
        <input type="text" name="am_private_postcode" value="<?php echo esc_attr( $addr_pc ); ?>" style="width:100px;">
    </p>
    <p>
        <label><?php _e( 'City:', 'am' ); ?></label> 
        <input type="text" name="am_private_city" value="<?php echo esc_attr( $addr_ct ); ?>" style="width:100%;">
    </p>
    <?php
}
function am_save_person_meta( $post_id ) {
    if ( ! isset( $_POST['am_person_nonce'] ) ||
         ! wp_verify_nonce( $_POST['am_person_nonce'], 'am_save_person' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    $email    = sanitize_email( $_POST['am_email'] ?? '' );
    $phone    = sanitize_text_field( $_POST['am_phone'] ?? '' );
    $street   = sanitize_text_field( $_POST['am_private_street'] ?? '' );
    $postcode = sanitize_text_field( $_POST['am_private_postcode'] ?? '' );
    $city     = sanitize_text_field( $_POST['am_private_city'] ?? '' );

    update_post_meta( $post_id, '_am_email', $email );
    update_post_meta( $post_id, '_am_phone', $phone );
    update_post_meta( $post_id, '_am_private_street', $street );
    update_post_meta( $post_id, '_am_private_postcode', $postcode );
    update_post_meta( $post_id, '_am_private_city', $city );
}
add_action( 'save_post_am_person', 'am_save_person_meta' );


/* ------------------------------------------------------------------
   5️⃣ ADMIN PAGE – CSV IMPORT
------------------------------------------------------------------- */
function am_import_menu() {
    add_submenu_page(
        'edit.php?post_type=am_property',
        __( 'Import CSV', 'am' ),
        __( 'Import CSV', 'am' ),
        'manage_options',
        'am_import_csv',
        'am_render_import_page'
    );
}
add_action( 'admin_menu', 'am_import_menu' );

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
        // Process the upload if the form was submitted
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

/* ------------------------------------------------------------------
   6️⃣ CSV PARSER & IMPORT LOGIC
------------------------------------------------------------------- */
function am_process_csv( $filepath ) {
    $handle = fopen( $filepath, 'r' );
    if ( ! $handle ) {
        return [ 'Unable to open the uploaded file.' ];
    }

    $log     = [];
    $row_num = 0;

    while ( ( $raw = fgets( $handle ) ) !== false ) {
        $row_num++;

        // Skip pure separator lines (lots of semicolons)
        if ( preg_match( '/^;+$/', trim( $raw ) ) ) { continue; }

        // Split on semicolon, keep empty fields
        $cols = str_getcsv( $raw, ';' );
        $cols = array_map( 'trim', $cols );

        // Ignore completely empty rows
        if ( count( array_filter( $cols ) ) === 0 ) { continue; }

        /* -------------------------------------------------
           Mapping based on your latest description
           0 = Unique property name (e.g. KT1, SV15)   → Property title
           1 = Owner first name
           2 = Owner last name
           4 = Owner private street (official residence)
           5 = Owner email
           6 = Owner private postcode
           7 = Owner private city
           8 = Owner private phone (optional)
        ------------------------------------------------- */
        $prop_name   = $cols[0] ?? '';
        $first_name  = $cols[1] ?? '';
        $last_name   = $cols[2] ?? '';
        $priv_street = $cols[4] ?? '';
        $email       = $cols[5] ?? '';
        $priv_pc     = $cols[6] ?? '';
        $priv_city   = $cols[7] ?? '';
        $priv_phone  = $cols[8] ?? '';

        // Basic sanity check
        if ( empty( $prop_name ) ) {
            $log[] = "Row {$row_num}: skipped (no property name).";
            continue;
        }

        // ------- 1️⃣ FIND OR CREATE OWNER -------
        $owner_full = trim( $first_name . ' ' . $last_name );
        $owner_id   = null;

        // Prefer lookup by e‑mail (most reliable)
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

        // Fallback: lookup by full name
        if ( ! $owner_id && $owner_full ) {
            $found = get_posts( [
                'post_type' => 'am_person',
                'title'     => $owner_full,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ] );
            if ( $found ) { $owner_id = $found[0]; }
        }

        // If still not found, create a new owner record
        if ( ! $owner_id ) {
            $owner_id = wp_insert_post( [
                'post_type'   => 'am_person',
                'post_title'  => $owner_full,
                'post_status' => 'publish',
            ] );
            if ( $owner_id && ! is_wp_error( $owner_id ) ) {
                update_post_meta( $owner_id, '_am_email', $email );
                update_post_meta( $owner_id, '_am_phone', $priv_phone );
                update_post_meta( $owner_id, '_am_private_street', $priv_street );
                update_post_meta( $owner_id, '_am_private_postcode', $priv_pc );
                update_post_meta( $owner_id, '_am_private_city', $priv_city );
                $log[] = "Created owner '{$owner_full}'.";
            }
        }

        // ------- 2️⃣ CREATE / UPDATE PROPERTY -------
        // Check if a property with this exact name already exists (avoid duplicates)
        $existing = get_posts( [
            'post_type'      => 'am_property',
            'title'          => $prop_name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( $existing ) {
            $prop_id = $existing[0];
            // Update owner link if needed
            update_post_meta( $prop_id, '_am_owner_id', $owner_id );
            $log[] = "Updated property '{$prop_name}' (owner link refreshed).";
        } else {
            $prop_id = wp_insert_post( [
                'post_type'   => 'am_property',
                'post_title'  => $prop_name,
                'post_status' => 'publish',
            ] );
            if ( $prop_id && ! is_wp_error( $prop_id ) ) {
                update_post_meta( $prop_id, '_am_owner_id', $owner_id );
                $log[] = "Added property '{$prop_name}'.";
            } else {
                $log[] = "Row {$row_num}: failed to create property.";
            }
        }
    }

    fclose( $handle );
    return $log;
}