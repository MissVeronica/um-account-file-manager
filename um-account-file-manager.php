<?php
/**
 * Plugin Name:     Ultimate Member - Account File Manager
 * Description:     Extension to Ultimate Member for Management of User Account Images and Files from the backend.
 * Version:         1.1.2
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Account_File_Manager {

    public $detached_bytes        = 0;
    public $detached_counter      = 0;
    public $detached_user_counter = 0;
    public $upload_basedir        = '';
    public $failed_counter        = 0;
    public $thumbnail_height      = '32px';
    public $thumbnail_scale       = '3.5';

    public $button_type = '';
    public $directories = array();
    public $heading     = array();
    public $buttons     = array();
    public $transient   = array( 'search' => 'detached_files_search',
                                 'trash'  => 'detached_files_trashed',
                               );

    public $action_types    = array( 'search',  'trash', 'user' );
    public $include_forms   = array( 'profile', 'register' );
    public $include_fields  = array( 'image',   'file' );

    public $trash_folder    = '';
    public $user_meta_files = array();
    public $empty_message   = array();
    public $image_types     = array( '.jpg', '.gif', '.png', '.webp', '.jpeg', '.svg', '.bmp', '.tif', '.tiff' );

    public $cache_time_hours     = 24;
    public $valid_profile_photos = array();
    public $valid_cover_photos   = array();

    public $larger_modal_size    = '<style>.um-admin-modal.larger {width:900px;margin-left:-450px;}</style>';


    function __construct() {

        if ( is_admin()) {

            add_action( 'load-toplevel_page_ultimatemember',                       array( $this, 'load_toplevel_page_remove_detached_files' ) );
            add_action( 'um_admin_do_action__remove_detached_files',               array( $this, 'remove_detached_files_trash' ) );
            add_action( 'um_admin_do_action__search_detached_files',               array( $this, 'remove_detached_files_search' ) );
            add_filter( 'um_adm_action_custom_update_notice',                      array( $this, 'remove_detached_files_notice' ), 10, 2 );

            add_action( 'um_admin_ajax_modal_content__hook_remove_detached_files', array( $this, 'remove_detached_files_ajax_modal' ));
            add_action( 'um_admin_ajax_modal_content__hook_user_uploaded_files',   array( $this, 'user_uploaded_files_ajax_modal' ));
            add_action( 'admin_footer',                                            array( $this, 'load_modal_remove_detached_files' ), 9 );
            add_action( 'admin_footer',                                            array( $this, 'load_modal_user_uploaded_files' ), 9 );

            add_filter( 'um_admin_user_row_actions',                               array( $this, 'um_admin_user_row_actions_user_uploaded_files' ), 10, 2 );
            add_filter( 'um_admin_bulk_user_actions_hook',                         array( $this, 'um_admin_bulk_user_actions_detached_files' ), 10, 1 );
            add_action( "um_admin_custom_hook_um_detached_files_user_rollback",    array( $this, 'um_detached_files_user_rollback' ), 10, 1 );
            add_action( "um_admin_custom_hook_um_detached_files_all_rollback",     array( $this, 'um_detached_files_all_rollback' ), 10 );

            $this->heading = array( 'search'  => __( 'Search for Detached Files',        'ultimate-member' ),
                                    'trash'   => __( 'Detached Files in Trash Folder',   'ultimate-member' ),
                                    'lost'    => __( 'Detached meta_keys without files', 'ultimate-member' ),
                                    'user'    => '',
                                  );

            $this->buttons = array( 'search'  => __( 'Search Detached Files', 'ultimate-member' ),
                                    'trash'   => __( 'Move to Trash Folder',  'ultimate-member' ),
                                    'show'    => __( 'Show Trash Folder',     'ultimate-member' ),
                                    'found'   => __( 'Show Detached Files',   'ultimate-member' ));

            $this->empty_message = array( 'search'  => __( 'Search cache has expired. You must Search for User Account detached Images and Files again', 'ultimate-member' ),
                                          'trash'   => __( 'Trash folder is empty', 'ultimate-member' ));

            $upload_dir = wp_upload_dir();
            $this->upload_basedir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'ultimatemember' . DIRECTORY_SEPARATOR;
            $this->trash_folder = $this->upload_basedir . 'detached_files_trash' . DIRECTORY_SEPARATOR;

        }
    }

    public function um_admin_user_row_actions_user_uploaded_files( $actions, $user_id ) {

        $actions['view_account_files'] = '<a href="javascript:void(0);" data-modal="UM_user_uploaded_files" 
                                          data-modal-size="larger" data-dynamic-content="user_uploaded_files" 
                                          data-arg1="' . esc_attr( $user_id ) . '" data-arg2="user_uploaded_files">' .
                                          __( 'User Files', 'ultimate-member' ) . '</a>';
        return $actions;
    }

    public function modal_link( $text, $type ) {

        return '<a href="javascript:void(0);" data-modal="UM_remove_detached_files" 
                data-modal-size="larger" data-dynamic-content="remove_detached_files" class="button"
                data-arg1="' . esc_attr( $type ) . '" data-arg2="remove_detached_files">' . esc_attr( $text ) . '</a>';
    }

    public function load_modal_user_uploaded_files() {

        $this->load_modal_html_code( 'UM_user_uploaded_files', __( 'User Account Uploaded Images and Files', 'ultimate-member' ));
    }

    public function load_modal_remove_detached_files() {

        $this->load_modal_html_code( 'UM_remove_detached_files', __( 'Detached User Account Images and Files', 'ultimate-member' ));
    }

    public function load_modal_html_code( $id, $hdr ) {

        echo $this->larger_modal_size; ?>
            <div id="<?php echo esc_attr( $id ); ?>" style="display:none">
                <div class="um-admin-modal-head">
                    <h3><?php echo esc_attr( $hdr ); ?></h3>
                </div>
                <div class="um-admin-modal-body"></div>
                <div class="um-admin-modal-foot"></div>
            </div>
<?php
   }

    public function load_toplevel_page_remove_detached_files() {

        add_meta_box( 'um-metaboxes-sidebox-remove-detached-files',
                       __( 'Detached User Account Images and Files', 'ultimate-member' ),
                       array( $this, 'toplevel_page_remove_detached_files' ), 
                      'toplevel_page_ultimatemember', 'side', 'core' );
    }

    public function user_uploaded_files_ajax_modal() {

        if ( isset( $_POST['arg1'] ) && ! empty( $_POST['arg1'] )) {
            $user_id = sanitize_text_field( $_POST['arg1'] );

            echo '<div class="um-admin-infobox">';
            if ( current_user_can( 'administrator' ) && is_numeric( $user_id ) && um_can_view_profile( $user_id )) {

                echo $this->get_user_uploaded_files_html( $user_id );

            } else {

                echo '<p><label>' . __( 'No access', 'ultimate-member' ) . '</label></p>';
            }
            echo '</div>';
        }
    }

    public function remove_detached_files_ajax_modal() {

        if ( isset( $_POST['arg1'] ) && ! empty( $_POST['arg1'] )) {
            $type = sanitize_text_field( $_POST['arg1'] );

            echo '<div class="um-admin-infobox">';
            if ( current_user_can( 'administrator' ) && in_array( $type, $this->action_types )) {

                echo $this->create_detached_files_list_html( $type );

            } else {

                echo '<p><label>' . __( 'No access', 'ultimate-member' ) . '</label></p>';
            }
            echo '</div>';
        }
    }

    public function toplevel_page_remove_detached_files() {

        if ( $this->button_type == 'search' ) { ?>

            <div><?php _e( 'Search results', 'ultimate-member' ); ?></div>

<?php       if ( $this->detached_bytes > 0 ) {

                $detached_files = get_transient( $this->transient['search'] ); ?>

                <div><?php echo sprintf( __( '%s detached User Account Images and Files found', 'ultimate-member' ),
                                             '<span class="red">' . $this->detached_counter . '</span>' ); ?></div>
                <div><?php echo sprintf( __( '%s User Accounts with detached Images and Files', 'ultimate-member' ),
                                             '<span class="red">' . count( $detached_files ) . '</span>' ); ?></div>
                <div><?php echo sprintf( __( '%s MByte of Web Hosting disc space', 'ultimate-member' ),
                                             '<span class="red">' . number_format( $this->detached_bytes/1024/1024, 2 ) . '</span>' ); ?></div>
                <hr>
                <div>
                    <?php echo $this->modal_link( $this->buttons['found'], 'search' );

                    if ( $this->detached_counter > 0 && $this->button_type == 'search' ) {
                        $url_remove_detached_files = add_query_arg( array(  'um_adm_action' => 'remove_detached_files',
                                                                            '_wpnonce'      => wp_create_nonce( 'remove_detached_files' ),
                                                                    )); ?>
                        <a href="<?php echo esc_url( $url_remove_detached_files ); ?>" class="button" 
                            title="<?php _e( 'Move the listed detached Images and Files to the Trash folder', 'ultimate-member' ); ?>">
                                <?php echo $this->buttons['trash']; ?>
                        </a>
<?php               } ?>
                </div>

<?php       } else { ?>

                <div><?php _e( 'No detached User Account Images or Files found', 'ultimate-member' ); ?></div>
<?php       } ?>

            <hr>
<?php   }

        if ( $this->button_type == 'trash' ) { ?>

            <div><?php _e( 'Remove results', 'ultimate-member' ); ?></div>
<?php       if ( $this->detached_bytes > 0 ) { ?>
                <div><?php echo sprintf( __( '%s detached User Account Images and Files moved to the Trash folder', 'ultimate-member' ),
                                             '<span class="red">' . $this->detached_counter . '</span>' ); ?></div>
                <div><?php echo sprintf( __( '%s MByte of Web Hosting disc space saved', 'ultimate-member' ),
                                             '<span class="red">' . number_format( $this->detached_bytes/1024/1024, 2 ) . '</span>' ); ?></div>
                <div><?php echo sprintf( __( '%s file move failures', 'ultimate-member' ),
                                             '<span class="red">' . $this->failed_counter . '</span>' ); ?></div>

<?php       } else { ?>

                <div><?php _e( 'No detached User Account Images or Files in the Trash folder', 'ultimate-member' ); ?></div>
<?php       } ?>

            <hr>

<?php   } 

        if ( empty( $this->button_type )) {

            $detached_files = get_transient( $this->transient['search'] );

            if ( is_array( $detached_files ) && ! empty( $detached_files )) { ?>
                <div>
<?php               echo sprintf( __( 'There is a list of %s Images and Files from %s User Accounts saved in the %d hours cache since the last Search for Detached Files', 'ultimate-member' ), 
                                                        '<span class="red">' . array_sum(array_map( 'count', $detached_files )) . '</span>', 
                                                        '<span class="red">' . count( $detached_files ) . '</span>',
                                                        $this->cache_time_hours ); ?>
                </div>
                <div>
<?php               echo __( 'To move current list of cached detached Images and Files to the Trash folder you must do a new Search', 'ultimate-member' ); ?>
                </div>
                <hr>
                <div>
                    <?php echo $this->modal_link( $this->buttons['found'], 'search' ); ?>
                </div>
                <hr>
<?php       } 
        } ?>
            <div>
<?php           _e( 'Run this task from time to time to keep your User Account upload folders clean from detached Files and multiple Image formats',  'ultimate-member' ); ?></div>
<?php           $url_search_detached_files = add_query_arg( array(  'um_adm_action' => 'search_detached_files',
                                                                '_wpnonce'      => wp_create_nonce( 'search_detached_files' ),
                                                            )); ?>
                <hr>
                <p>
                    <a href="<?php echo esc_url( $url_search_detached_files ); ?>" class="button"
                        title="<?php _e( 'Search for detached Images and Files', 'ultimate-member' ); ?>">
                        <?php echo $this->buttons['search']; ?>
                    </a>

<?php               if ( file_exists( $this->trash_folder )) {
                        $detached_files = $this->search_files_trash();
                        if ( ! empty( $detached_files )) {
                            echo $this->modal_link( $this->buttons['show'], 'trash' );
                        }
                    } ?>
                </p>
            </div>
<?php

    }

    public function remove_detached_files_search() {

        $detached_files = $this->search_remove_detached_files();
        set_transient( $this->transient['search'], $detached_files, $this->cache_time_hours*3600 );

        $url = add_query_arg( array( 'page'   => 'ultimatemember',
                                     'update' => 'remove_detached_files_search',
                                     'result' =>  $this->detached_counter,
                                     'bytes'  =>  $this->detached_bytes,
                                    ),
                                    admin_url( 'admin.php' )
                             );

        wp_safe_redirect( $url );
        exit;
    }

    public function remove_detached_files_trash() {

        $detached_files = $this->trash_remove_detached_files();

        $url = add_query_arg( array( 'page'   => 'ultimatemember',
                                     'update' => 'remove_detached_files_trash',
                                     'result' =>  $this->detached_counter,
                                     'bytes'  =>  $this->detached_bytes,
                                     'failed' =>  $this->failed_counter,
                                    ),
                                    admin_url( 'admin.php' )
                            );

        wp_safe_redirect( $url );
        exit;
    }

    public function remove_detached_files_notice( $message, $update ) {

        $message = array();

        if ( isset( $_REQUEST['result'] ) && isset( $_REQUEST['bytes'] )) {

            $this->detached_counter = sanitize_text_field( $_REQUEST['result'] );
            $this->detached_bytes   = sanitize_text_field( $_REQUEST['bytes'] );

            if ( isset( $_REQUEST['failed'] )) {
                $this->failed_counter = sanitize_text_field( $_REQUEST['failed'] );
            }

            $counter = $this->detached_counter;
            if ( $this->detached_counter == 0 ) {
                $counter = __( 'no', 'ultimate-member' );
            }

            if ( $update == 'remove_detached_files_search' ) {

                $this->button_type = 'search';
                $message[0]['content'] = sprintf( __( 'Search found %s detached User Account Images and Files', 'ultimate-member' ), $counter );
            }

            if ( $update == 'remove_detached_files_trash' ) {

                $this->button_type = 'trash';
                $message[0]['content'] = sprintf( __( 'Moved %s detached User Account Images and Files to the Trash folder', 'ultimate-member' ), $counter );
            }
        }

        return $message;
    }

    public function create_detached_files_list_html( $type ) {

        if ( $type == 'trash' ) {
            $detached_files = $this->search_files_trash();
            $this->heading['trash'] = sprintf( __( 'Trash folder with %d files from %d users with a total file size of %s MB', 'ultimate-member' ), 
                                                                    $this->detached_counter, 
                                                                    $this->detached_user_counter, 
                                                                    number_format( $this->detached_bytes/1024/1024, 2 ));
        }

        if ( $type == 'search' ) {
            $detached_files = get_transient( $this->transient[$type] );
        }

        if ( empty( $detached_files )) {
            return $this->empty_message[$type];
        }

        return $this->user_files_modal_format( $detached_files, $type );
    }

    public function user_files_modal_format( $show_files, $type, $user_id = null ) {

        ksort( $show_files );

        if ( $type == 'user' && ! empty( $user_id )) {

            $detached_files = $this->search_remove_detached_files( $user_id );
            $db_meta_keys = array_keys( $this->user_meta_files[$user_id] );
        }

        ob_start(); ?>

        <div style="margin-left:15px;">
        <h2><?php echo $this->heading[$type]; ?></h2>

        <table>
            <tr>
                <th style="text-align:left;"><?php  _e( 'Mod Date', 'ultimate-member' ); ?></th>
                <th style="text-align:center;"><?php _e( 'Photo', 'ultimate-member' ); ?></th>
<?php
                if ( $type != 'user' ) { ?>
                    <th style="text-align:right;"><?php _e( 'ID', 'ultimate-member' ); ?></th>
                    <th style="text-align:left;"><?php  _e( 'Username', 'ultimate-member' ); ?></th>
<?php           } ?>
                <th style="text-align:right;"><?php _e( 'KBytes', 'ultimate-member' ); ?></th>
<?php           if ( $type == 'user' ) { ?>
                    <th style="text-align:right;"><?php _e( 'MetaKey', 'ultimate-member' ); ?></th>
<?php           } ?>
                <th style="text-align:left;"><?php  _e( 'File name', 'ultimate-member' ); ?></th>
            </tr>
            <style>
                .modal-img-show{
                        transition: transform .2s;
                        height:<?php echo esc_attr( $this->thumbnail_height ); ?>;
                        border-radius: 3px;
                        justify-content: center;
                        align-items: center;
                        display:flex;
                        margin:0 auto;
                    }
                .modal-img-show:hover{
                        transform:scale(<?php echo esc_attr( $this->thumbnail_scale ); ?>);
                    }
            </style>

<?php       foreach( $show_files as $key => $files ) {

                if ( is_array( $files ) && is_numeric( $key ) && um_can_view_profile( $key )) {

                    if ( $type != 'user' ) {
                        $user = get_user_by( 'id', $key );
                        $user_link = '<a href="' . esc_url( um_user_profile_url( $key )) . '" target="_blank">' . esc_attr( $user->user_login ) . '</a>';
                    }

                    foreach( $files as $file ) {

                        $current_file = $this->upload_basedir . $key . DIRECTORY_SEPARATOR . $file;
                        if ( $type == 'trash' ) {
                            $current_file = $this->trash_folder . $key . DIRECTORY_SEPARATOR . $file;
                        }

                        $filemtime = filemtime( $current_file );
                        $mod_date = date( get_option( 'date_format' ), $filemtime );
                        $mod_time = date( get_option( 'time_format' ), $filemtime );
                        $size = number_format( filesize( $current_file )/1024, 1 );
                        $style = '';
                        $img = '';
                        $meta_key = '';

                        if ( in_array( strtolower( strrchr( $file, '.' ) ), $this->image_types )) {

                            $img = esc_url( site_url( str_replace( '/', DIRECTORY_SEPARATOR, '/wp-content/uploads/ultimatemember/' ) . $key . DIRECTORY_SEPARATOR . $file . '?=' . $filemtime ));
                        }

                        if ( $type == 'search' ) {
                            $style = $this->search_meta_value( $key, $file );
                        }

                        if ( $type == 'user' ) {

                            if ( isset( $detached_files[$key] ) && in_array( $file, $detached_files[$key] )) {

                                if ( substr( $file, 0, 13 ) == 'profile_photo' || substr( $file, 0, 11 ) == 'cover_photo' ) {
                                    $style = 'style="color:red;" title="' . __( 'This is a detached image file older than the original image file or with an unsupported image size format', 'ultimate-member' ) . '"';

                                } else {

                                    $style = $this->search_meta_value( $user_id, $file );
                                }

                            } else {

                                if ( in_array( $file, $this->user_meta_files[$user_id] )) {
                                    $meta_key = array_search( $file, $this->user_meta_files[$user_id] );

                                } else {

                                    if ( substr( $file, 0, 13 ) == 'profile_photo' ) {
                                        $meta_key = '';
                                    }

                                    if ( substr( $file, 0, 11 ) == 'cover_photo' ) {
                                        $meta_key = '';
                                    }
                                }
                            }

                            $db_meta_key = array_search( $meta_key, $db_meta_keys );
                            if ( $db_meta_key !== false ) {
                                unset( $db_meta_keys[$db_meta_key] );
                            }
                        }

                        $file = '<a href="' . esc_url( site_url( str_replace( '/', DIRECTORY_SEPARATOR, '/wp-content/uploads/ultimatemember/' ) . $key . DIRECTORY_SEPARATOR . $file . '?=' . $filemtime )) . '"' . $style . ' target="_blank">' . esc_attr( $file ) . '</a>'; ?>

                        <tr>
                            <td style="text-align:left;" title="<?php echo esc_attr( $mod_time ); ?>"><?php echo esc_attr( $mod_date ); ?></td>
                            <td>
<?php                       if ( ! empty( $img )) { ?>
                                <img class="modal-img-show" src="<?php echo $img ?>"/>
<?php                       } ?>
                            </td>
<?php                       if ( $type != 'user' ) { ?>
                                <td style="text-align:right;"><?php echo esc_attr( $key ); ?></td>
                                <td><?php echo $user_link; ?></td>
<?php                       } ?>
                            <td style="text-align:right;"><?php echo esc_attr( $size ); ?></td>
<?php                       if ( $type == 'user' ) { ?>
                                <td style="text-align:right;"><?php echo esc_attr( $meta_key ); ?></td>
<?php                       } ?>
                            <td style="text-align:left;"><?php echo $file; ?></td>
                        </tr>
<?php               }
                }
            } ?>
            </table>
<?php   if ( $type == 'user' && ! empty( $db_meta_keys )) { ?>
            <h2><?php echo $this->heading['lost']; ?></h2>
            <table>
                <tr>
                    <th style="text-align:right;"><?php  _e( 'MetaKey', 'ultimate_member' ); ?></th>
                    <th style="text-align:left;"><?php  _e( 'MetaValue', 'ultimate_member' ); ?></th>
                </tr>
<?php           foreach( $db_meta_keys as $db_meta_key ) { ?>
                    <tr>
                        <td><?php echo esc_attr( $db_meta_key ); ?></td>
                        <td><?php echo esc_attr( $this->user_meta_files[$user_id][$db_meta_key] ); ?></td>
                    </tr>
<?php           } ?>
            </table>
<?php   } ?>
        </div>
<?php
        return ob_get_clean();
    }

    public function search_meta_value( $user_id, $meta_value ) {

        global $wpdb;

        $sql = "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = '{$user_id}' AND meta_value LIKE '%{$meta_value}%'";
        $result = $wpdb->get_results( $sql );

        if ( empty( $result )) {
            $style = '';

        } else {

            $result = $result[0];
            $meta_key = $result->meta_key;

            if ( empty( $meta_key )) {
                $style = 'style="color:red;" title="' . __( 'This is a detached file not found in any of this user\'s metakey values for image and file uploads', 'ultimate-member' ) . '"';
            } else {
                $style = 'style="color:DarkRed;" title="' . sprintf( __( 'Warning: This is a detached file found in this user\'s meta-value for the meta_key = %s', 'ultimate-member' ), $meta_key ) . '"';
            }
        }

        return $style;
    }

    public function trash_remove_detached_files() {

        $detached_files = get_transient( $this->transient['search'] );

        if ( is_array( $detached_files ) && ! empty( $detached_files )) {

            if ( ! is_dir( $this->upload_basedir . 'detached_files_trash' )) {
                wp_mkdir_p( $this->upload_basedir . 'detached_files_trash' );
            }

            foreach( $detached_files as $key => $files ) {

                if ( is_array( $files ) && ! empty( $files )) {

                    if ( ! is_dir( $this->trash_folder . $key )) {
                        wp_mkdir_p( $this->trash_folder . $key );
                    }

                    foreach( $files as $file ) {

                        $current_file = $this->upload_basedir . $key . DIRECTORY_SEPARATOR . $file;
                        $trash_file = $this->trash_folder . $key . DIRECTORY_SEPARATOR . $file;

                        if ( file_exists( $current_file ) && is_writable( $this->trash_folder . $key )) {

                            $filesize = filesize( $current_file );
                            $verify = rename( $current_file, $trash_file );

                            if ( $verify ) {
                                $this->detached_bytes += $filesize;
                                $this->detached_counter++;
                            }
                        }
                    }
                }
            }

            delete_transient( $this->transient['search'] );
        }
    }

    public function search_files_trash() {

        $directories = glob( $this->trash_folder . '*', GLOB_ONLYDIR );

        $detached_files = array();

        if ( is_array( $directories ) && ! empty( $directories )) {

            foreach( $directories as $directory ) {

                $user_id = substr( strrchr( $directory, DIRECTORY_SEPARATOR ), 1 );
                if ( is_numeric( $user_id )) {

                    $files = glob( $directory . DIRECTORY_SEPARATOR . '*' );

                    if ( is_array( $files ) && ! empty( $files )) {

                        foreach( $files as $file ) { 

                            $this->detached_bytes += filesize( $file );
                            $this->detached_counter++;
                            $detached_files[$user_id][] = substr( str_replace( $directory, '', $file ), 1 );
                        }
                    }
                }
            }
        }

        $this->detached_user_counter = count( $detached_files );

        return $detached_files;
    }

    public function get_form_fields_uploads() {

        $um_forms = get_posts( array( 'post_type' => 'um_form', 'numberposts' => -1, 'post_status' => array( 'publish' )));

        $um_user_meta = array();
        if ( is_array( $um_forms ) && ! empty( $um_forms )) {

            foreach( $um_forms as $um_form ) {

                $um_form_meta = get_post_meta( $um_form->ID );

                if ( isset( $um_form_meta['_um_mode'][0] ) && in_array( $um_form_meta['_um_mode'][0], $this->include_forms )) {

                    $form_fields = maybe_unserialize( $um_form_meta['_um_custom_fields'][0] );
                    foreach( $form_fields as $form_field ) {

                        if ( isset( $form_field['type'] ) && in_array( $form_field['type'], $this->include_fields )) {

                            $um_user_meta[$form_field['metakey']] = $form_field;
                        }
                    }
                }
            }

            $um_user_meta = array_merge( $um_user_meta, UM()->builtin()->get_specific_fields( 'profile_photo,cover_photo' ));
        }

        return $um_user_meta;
    }

    public function search_remove_detached_files( $user_id = null ) {

        $detached_files = array();
        $um_user_meta = $this->get_form_fields_uploads();

        if ( $this->get_any_user_meta_files( $um_user_meta, $user_id )) {

            if ( $this->get_any_account_directories( $user_id )) {

                $this->set_valid_cover_profile_sizes();

                foreach( $this->directories as $directory ) {

                    $user_id = substr( strrchr( $directory, DIRECTORY_SEPARATOR ), 1 );

                    if ( is_numeric( $user_id )) {

                        $files = $this->prepare_directory_files( $directory );

                        if ( is_array( $files ) && ! empty( $files )) {

                            $keep_time = $this->get_keep_times( $directory, $user_id, $files );

                            foreach( $files as $file => $filemtime ) { 

                                if ( isset( $this->user_meta_files[$user_id] )) {

                                    $file_name = substr( strrchr( $file, DIRECTORY_SEPARATOR ), 1 );

                                    if ( substr( $file_name, 0, 13 ) == 'profile_photo' ) {
                                        if ( in_array( str_replace( strrchr( $file, '.' ), '', $file_name ), $this->valid_profile_photos )) {

                                            if ( $filemtime < $keep_time['profile_photo'] ) {
                                                $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                                            }

                                        }  else {
                                            $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                                        }
                                        continue;
                                    }

                                    if ( substr( $file_name, 0, 11 ) == 'cover_photo' ) {
                                        if ( in_array( str_replace( strrchr( $file, '.' ), '', $file_name ), $this->valid_cover_photos )) {
                                            if ( $filemtime < $keep_time['cover_photo'] ) {
                                                $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                                            }

                                        } else {
                                            $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                                        }
                                        continue;
                                    }

                                    if ( ! in_array( $file_name, $this->user_meta_files[$user_id] )) {
                                        $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                                    }
                                    continue;
                                }

                                $detached_files[$user_id][] = $this->add_detached_file( $directory, $file );
                            }
                        }
                    }
                }
            }

            return $detached_files;
        }

        return false;
    }

    public function get_any_user_meta_files( $um_user_meta, $user_id = false ) {

        global $wpdb;

        if ( ! empty( $um_user_meta )) {

            $args = implode( "','", array_keys( $um_user_meta ));
            $sql = "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('{$args}')";

            if ( ! empty ( $user_id )) {
                $sql .= " AND user_id = '{$user_id}'";
            }

            $users = $wpdb->get_results( $sql );
            if ( is_array( $users ) && ! empty( $users )) {

                foreach( $users as $user ) {
                    $this->user_meta_files[$user->user_id][$user->meta_key] = $user->meta_value;
                }

                return true;
            }
        }

        return false;
    }

    public function get_any_account_directories( $user_id = false ) {

        if ( ! empty( $user_id )) {
            $this->directories[] = $this->upload_basedir . $user_id;

        } else {

            $this->directories = glob( $this->upload_basedir . '*', GLOB_ONLYDIR );
        }

        if ( is_array( $this->directories ) && ! empty( $this->directories )) {
            return true;
        }
        return false;
    }

    public function set_valid_cover_profile_sizes() {

        $this->valid_profile_photos = array( 'profile_photo' );
        foreach( UM()->options()->get( 'photo_thumb_sizes' ) as $size ) {
            $this->valid_profile_photos[] = 'profile_photo-' . $size . 'x' . $size;
        }

        $this->valid_cover_photos = array( 'cover_photo' );
        foreach( UM()->options()->get( 'cover_thumb_sizes' ) as $size ) {
            $this->valid_cover_photos[] = 'cover_photo-' . $size;
        }
    }

    public function add_detached_file( $directory, $file ) {

        $this->detached_bytes += filesize( $file );
        $this->detached_counter++;

        return substr( str_replace( $directory, '', $file ), 1 );
    }

    public function get_keep_times( $directory, $user_id, $files ) {

        $keep_time = array( 'profile_photo' => time(), 'cover_photo' => time());

        if ( isset( $this->user_meta_files[$user_id]['profile_photo'] )) {
            $index = $directory . DIRECTORY_SEPARATOR . $this->user_meta_files[$user_id]['profile_photo'];

            $keep_time['profile_photo'] = $files[$index];

        } else {

            if ( isset( $this->user_meta_files[$user_id]['register_profile_photo'] )) {
                $index = $directory . DIRECTORY_SEPARATOR . $this->user_meta_files[$user_id]['register_profile_photo'];

                $keep_time['profile_photo'] = $files[$index];
            }
        }

        if ( isset( $this->user_meta_files[$user_id]['cover_photo'] )) {
            $index = $directory . DIRECTORY_SEPARATOR . $this->user_meta_files[$user_id]['cover_photo'];
            $keep_time['cover_photo'] = $files[$index];
        }

        return $keep_time;
    }

    public function prepare_directory_files( $directory ) {

        $files = glob( $directory . DIRECTORY_SEPARATOR . '*' );

        if ( is_array( $files ) && ! empty( $files )) {

            $files = array_flip( $files );

            foreach( $files as $file => $value ) {
                $files[$file] = strval( filemtime( $file ) - 3 );
            }

            return $files;
        }
        return false;
    }

    public function get_user_uploaded_files_html( $user_id ) {

        $directory = $this->upload_basedir . $user_id;
        $files = glob( $directory . DIRECTORY_SEPARATOR . '*' );

        $user = get_user_by( 'id', $user_id );

        if ( is_array( $files ) && count( $files ) > 0 ) {

            $user_files = array();
            foreach( $files as $file ) {
                $user_files[$user_id][] = substr( str_replace( $directory, '', $file ), 1 );
            }

            $this->heading['user'] = sprintf( __( 'Current User Account Images and Files for username %s', 'ultimate-member' ), $user->user_login );
            $file_names = $this->user_files_modal_format( $user_files, 'user', $user_id );

        } else {

            ob_start(); ?>
            <div style="margin-left:15px;">
                <h3><?php echo sprintf( __( 'No User Account Images and Files found for username %s', 'ultimate-member' ), $user->user_login ); ?></h3>
            </div>
<?php
            $file_names = ob_get_clean();
        }

        return $file_names;
    }

    public function um_admin_bulk_user_actions_detached_files( $actions ) {

        $actions['um_detached_files_user_rollback'] = array( 'label' => __( 'Restore Trashed Files',     'ultimate-member' ));
        $actions['um_detached_files_all_rollback']  = array( 'label' => __( 'Restore all Trashed Files', 'ultimate-member' ));

        return $actions;
    }

    public function um_detached_files_user_rollback( $user_id ) {

        if ( current_user_can( 'administrator' ) && is_numeric( $user_id ) && um_can_view_profile( $user_id )) {

            $trash_files = glob( $this->trash_folder . $user_id . DIRECTORY_SEPARATOR . '*' );
            $target = $this->upload_basedir . $user_id . DIRECTORY_SEPARATOR;

            if ( file_exists( $target ) && is_array( $trash_files ) && ! empty( $trash_files )) {

                foreach( $trash_files as $trash_file ) { 

                    $target = str_replace( 'detached_files_trash' . DIRECTORY_SEPARATOR, '', $trash_file );
                    $verify = rename( $trash_file, $target );

                    if ( ! $verify ) {
                        $this->failed_counter++;
                    } 
                }
            }
        }
    }

    public function um_detached_files_all_rollback() {

        $directories = glob( $this->trash_folder . '*', GLOB_ONLYDIR );

        if ( is_array( $directories ) && ! empty( $directories )) {
            foreach( $directories as $directory ) {

                $user_id = substr( strrchr( $directory, '/' ), 1 );
                $this->um_detached_files_user_rollback( $user_id );
            }
        }
    } 

}

new UM_Account_File_Manager();
