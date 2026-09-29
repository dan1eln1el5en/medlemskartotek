<?php
/*
Plugin Name: Members Manager – Summer‑House Edition (Owner‑Private‑Address)
Description: Tracks summer‑houses (unique property names) and their owners. Includes admin list and e‑mail export.
Version: 1.4
Author: Daniel (with Lumo help)
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --------------------------------------------------------------
   1️⃣ POST TYPES (unchanged)
-------------------------------------------------------------- */
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
        'supports'      => [ 'title' ],
        'menu_position' => 20,
        'menu_icon'     => 'dashicons-admin-home',
    ];
    register_post_type( 'am_property', $args );
}
add_action( 'init', 'am_register_property_cpt' );

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
        'supports'      => [ 'title' ],
        'menu_position' => 21,
        'menu_icon'     => 'dashicons-id-alt',
    ];
    register_post_type( 'am_person', $args );
}
add_action( 'init', 'am_register_person_cpt' );


/* --------------------------------------------------------------
   2️⃣ METABOXES (property ↔ owner, owner private address)
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
    <p><strong><?php _e( 'Private Residential Address (official residence)', 'am' ); ?></strong></p>
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


/* --------------------------------------------------------------
   3️⃣ CSV IMPORT (unchanged – keep the same function from v1.3)
-------------------------------------------------------------- */
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

/* ---- CSV parser – unchanged from the previous version ---- */
function am_process_csv( $filepath ) {
    $handle = fopen( $filepath, 'r' );
    if ( ! $handle ) {
        return [ 'Unable to open the uploaded file.' ];
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
            $log[] = "Row {$row_num}: skipped (no property name).";
            continue;
        }

        // ----- Owner -----
        $owner_full = trim( $first_name . ' ' . $last_name );
        $owner_id   = null;

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
            $log[] = "Updated property '{$prop_name}' (owner refreshed).";
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

/* --------------------------------------------------------------
   4️⃣ NEW ADMIN PAGES
      • All Data (combined view)
      • Export Emails (CSV download)
-------------------------------------------------------------- */

/* ---- Sub‑menu registration ---- */
function am_admin_pages() {
    // All Data page
    add_submenu_page(
        'edit.php?post_type=am_property',
        __( 'All Data', 'am' ),
        __( 'All Data', 'am' ),
        'manage_options',
        'am_all_data',
        'am_render_all_data_page'
    );

    // Export Emails page
    add_submenu_page(
        'edit.php?post_type=am_property',
        __( 'Export Emails', 'am' ),
        __( 'Export Emails', 'am' ),
        'manage_options',
        'am_export_emails',
        'am_render_export_emails_page'
    );
}
add_action( 'admin_menu', 'am_admin_pages' );

/* ---- Helper: fetch all property‑owner rows ---- */
function am_get_combined_rows() {
    $rows = [];

    $properties = get_posts( [
        'post_type'      => 'am_property',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    foreach ( $properties as $prop ) {
        $owner_id = get_post_meta( $prop->ID, '_am_owner_id', true );
        $owner    = $owner_id ? get_post( $owner_id ) : null;

        $rows[] = [
            'property_name' => $prop->post_title,
            'owner_name'    => $owner ? $owner->post_title : '',
            'email'         => $owner ? get_post_meta( $owner->ID, '_am_email', true ) : '',
            'phone'         => $owner ? get_post_meta( $owner->ID, '_am_phone', true ) : '',
            'street'        => $owner ? get_post_meta( $owner->ID, '_am_private_street', true ) : '',
            'postcode'      => $owner ? get_post_meta( $owner->ID, '_am_private_postcode', true ) : '',
            'city'          => $owner ? get_post_meta( $owner->ID, '_am_private_city', true ) : '',
            'owner_id'      => $owner ? $owner->ID : 0,
        ];
    }

    return $rows;
}

/* ---- All Data page (HTML table) ---- */
function am_render_all_data_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.', 'am' ) );
    }

    $rows = am_get_combined_rows();
    ?>
    <div class="wrap">
        <h1><?php _e( 'All Summer‑House Data', 'am' ); ?></h1>
        <style>
            .am-table { width:100%; border-collapse:collapse; margin-top:20px; }
            .am-table th, .am-table td { border:1px solid #ddd; padding:8px; }
            .am-table th { background:#f1f1f1; text-align:left; }
        </style>
        <table class="am-table">
            <thead>
                <tr>
                    <th><?php _e( 'Property', 'am' ); ?></th>
                    <th><?php _e( 'Owner', 'am' ); ?></th>
                    <th><?php _e( 'E‑mail', 'am' ); ?></th>
                    <th><?php _e( 'Phone', 'am' ); ?></th>
                    <th><?php _e( 'Private Street', 'am' ); ?></th>
                    <th><?php _e( 'Postcode', 'am' ); ?></th>
                    <th><?php _e( 'City', 'am' ); ?></th>
                    <th class="am-edit-link"><?php _e( 'Edit Owner', 'am' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['property_name'] ); ?></td>
                        <td><?php echo esc_html( $row['owner_name'] ); ?></td>
                        <td><?php echo esc_html( $row['email'] ); ?></td>
                        <td><?php echo esc_html( $row['phone'] ); ?></td>
                        <td><?php echo esc_html( $row['street'] ); ?></td>
                        <td><?php echo esc_html( $row['postcode'] ); ?></td>
                        <td><?php echo esc_html( $row['city'] ); ?></td>
                        <!-- Edit‑owner column -->
                        <td class="am-edit-link">
                            <?php if ( $row['owner_id'] ) : ?>
                                <a href="<?php echo esc_url(
                                    admin_url( 'post.php?post=' . $row['owner_id'] . '&action=edit' )
                                ); ?>" title="<?php esc_attr_e( 'Edit this owner', 'am' ); ?>">
                                    &#9998; <!-- Unicode pencil -->
                                </a>
                            <?php else : ?>
                                <?php _e( '—', 'am' ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* --------------------------------------------------------------
   5️⃣ EXPORT EMAILS PAGE
-------------------------------------------------------------- */
function am_render_export_emails_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.', 'am' ) );
    }

    // If the user clicked “Download CSV”, generate and send it.
    if ( isset( $_POST['am_download_emails'] )
        && check_admin_referer( 'am_export_emails', 'am_export_nonce' ) ) {

        $emails = am_collect_unique_emails();

        // Prepare CSV content
        $csv_output = "email\r\n";
        foreach ( $emails as $email ) {
            $csv_output .= $email . "\r\n";
        }

        // Send headers for download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=owner-emails-' . date( 'Ymd' ) . '.csv' );
        echo $csv_output;
        exit; // stop further output
    }

    // Otherwise, show the button + a quick stats box
    $total_emails = count( am_collect_unique_emails() );
    ?>
    <div class="wrap">
        <h1><?php _e( 'Export Owner E‑mail Addresses', 'am' ); ?></h1>

        <p><?php printf(
            /* translators: %d = number of distinct e‑mail addresses */
            __( 'There are currently %d unique e‑mail addresses in the system.', 'am' ),
            $total_emails
        ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'am_export_emails', 'am_export_nonce' ); ?>
            <?php submit_button( __( 'Download CSV', 'am' ), 'primary', 'am_download_emails' ); ?>
        </form>
    </div>
    <?php
}

/* ---- Helper: gather unique e‑mail addresses ---- */
function am_collect_unique_emails() {
    $emails = [];

    $owners = get_posts( [
        'post_type'      => 'am_person',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ] );

    foreach ( $owners as $owner_id ) {
        $email = get_post_meta( $owner_id, '_am_email', true );
        if ( $email && ! in_array( $email, $emails, true ) ) {
            $emails[] = $email;
        }
    }

    return $emails;
}