<?php
/**
 * Plugin Name:Easy Wp Support Tickets
 * Description: A simple support ticket system for WordPress.
 * Version: 1.16
 * Author: Shay Pottle
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';
require_once plugin_dir_path(__FILE__) . 'utils.php';


function ewst_enqueue_global_styles() {
    wp_enqueue_style(
        'ewst-global-style', // handle
        plugin_dir_url(__FILE__) . 'assets/css/global.css',
        [], // dependencies
        '1.0.0' // version
    );
}
add_action('wp_enqueue_scripts', 'ewst_enqueue_global_styles');
add_action('admin_enqueue_scripts', 'ewst_enqueue_global_styles');

// function ewst_enqueue_admin_styles($hook) {
    
    
//     if ($hook !== 'toplevel_page_ticket_list') return; // Load only on ticket_list page

//     wp_enqueue_style(
//         'ewst-admin-style',
//         plugin_dir_url(__FILE__) . 'assets/css/admin.css',
//         [],
//         '1.0.0'
//     );
// }
// add_action('admin_enqueue_scripts', 'ewst_enqueue_admin_styles');

class ModelUtils{
    
    public static function upsert_data( $table_str, $data_object ){
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . $table_str;
    
        // Convert object to array if necessary
        if ( is_object( $data_object ) ) {
            $data_object = (array) $data_object;
        }
    
        // Ensure we have an array
        if ( ! is_array( $data_object ) ) {
            return false;
        }
    
        // Check if ID is present
        $is_update = isset( $data_object['id'] ) && ! empty( $data_object['id'] );
    
        // Separate ID from the data array
        if ( $is_update ) {
            $id = $data_object['id'];
            unset( $data_object['id'] );
        }
    
        // Determine the format types
        $format = [];
        foreach ( $data_object as $key => $value ) {
            if ( is_int( $value ) ) {
                $format[] = '%d';
            } elseif ( is_float( $value ) ) {
                $format[] = '%f';
            } else {
                $format[] = '%s'; // Default to string
            }
        }
    
        if ( $is_update ) {
            // Update existing record
            $where = [ 'id' => $id ];
            $where_format = [ '%d' ];
            $result = $wpdb->update( $table_name, $data_object, $where, $format, $where_format );
        } else {
            // Insert new record
            $result = $wpdb->insert( $table_name, $data_object, $format );
        }
    
        // Return affected rows or new insert ID
        return $is_update ? $result : $wpdb->insert_id;
        
    }// end fn
    
    
}// end fn


class UserTicketModel{
    
    public static function get_all_tickets(){
        
        global $wpdb;
        $tickets = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets ORDER BY created_at DESC");
        $tickets = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets ORDER BY created_at DESC"));

        return $tickets;
        
    }// end fn
    
    public static function get_tickets_by_user( $user_id ){
        
        global $wpdb;
        
        $tickets = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets WHERE wp_user_id = %d", $user_id));
        
        return $tickets;
        
    }// end fn
    
    public static function get_ticket_by_id( $ticket_id ){
        
        global $wpdb;
        
        $this_ticket = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets WHERE id = %d LIMIT 1" , $ticket_id));
        
        return $this_ticket;
        
    }// end fn
    
    
    
}// end class

class TicketResponseModel{
    
    public static function get_responses_by_ticket( $ticket_id ){
        
        global $wpdb;
        
        $responses = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ewst25_ticket_responses WHERE ticket_id = %d", $ticket_id));
        
        return $responses;
        
    }// end fn
    
}// end class

// plugin prefix: ewst25

class EwstSetup{
    
    public static function init_plugin(){
        
        register_activation_hook(__FILE__, ['EwstSetup', 'install_db_tables']);
        register_deactivation_hook(__FILE__, ['EwstSetup', 'uninstall_db_tables']); // Remember to remove this when plugin ready
        register_uninstall_hook(__FILE__, ['EwstSetup', 'uninstall_db_tables']);
        
        add_action('admin_menu', ['EwstSetup', 'init_admin_menu']);
        add_shortcode('ewst_create_ticket_form', ['EwstLoadViews', 'create_ticket_form']);
        add_shortcode('ewst_view_ticket_list', ['EwstLoadViews', 'view_ticket_list']);
        add_shortcode('ewst_view_ticket_responses', ['EwstLoadViews', 'view_ticket_responses']);
        add_action('init', ['EwstSetup', 'handle_form_submission']);
        
    }// end fn
    
    
    public static function install_db_tables(){
        
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Force collation to match existing tables
        $charset_collate = "DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci";
        //$charset_collate = $wpdb->get_charset_collate();

        $user_tickets = $wpdb->prefix . 'ewst25_user_tickets';
        $ticket_responses = $wpdb->prefix . 'ewst25_ticket_responses';
        $ticket_notes = $wpdb->prefix . 'ewst25_ticket_notes';
    
        $user_tickets_table = "
            CREATE TABLE IF NOT EXISTS $user_tickets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                wp_user_id BIGINT UNSIGNED NOT NULL,
                subject TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'open'
            ) $charset_collate;
        ";
        
        $ticket_responses_table = "   
            CREATE TABLE IF NOT EXISTS $ticket_responses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                wp_user_id BIGINT UNSIGNED NOT NULL,
                ticket_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL
            ) $charset_collate;
        ";
        
        $ticket_notes_table = "
            CREATE TABLE $ticket_notes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_ticket_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                note_content TEXT NOT NULL
            ) $charset_collate;
        ";
    
        dbDelta( $user_tickets_table );
        dbDelta( $ticket_responses_table );
        dbDelta( $ticket_notes_table );
        
    }// end fn
    
    public static function uninstall_db_tables() {
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ewst25_user_tickets';
        $table_responses = $wpdb->prefix . 'ewst25_ticket_responses';
        $ticket_notes = $wpdb->prefix . 'ewst25_ticket_notes';

        $wpdb->query("DROP TABLE IF EXISTS $table_responses");
        $wpdb->query("DROP TABLE IF EXISTS $table_tickets");
        $wpdb->query("DROP TABLE IF EXISTS $ticket_notes");
    }// end fn
    
    public static function init_admin_menu() {
        
        add_menu_page(
            'Easy WP Support Tickets', // page_title
            'Easy WP Support Tickets', // menu_title
            'manage_options', // capability
            'ticket_list', // menu_slug
            ['EwstAdminViews', 'load_admin_ticket_list'], // callback 
            'dashicons-tickets-alt' // icon_url 
        );
        
    }// end fn
    
    public static function handle_form_submission() {
        
        if (!isset($_POST['action']) || !is_user_logged_in()) return;
        
        global $wpdb;
        $user_id = get_current_user_id();
        EwstUtils::init_session();
        $previous_url = $_SERVER['HTTP_REFERER'] ?? home_url(); 
        
        switch( $_POST['action'] ){
            
            case 'create_ticket':
                
                $new_ticket = [
                    'wp_user_id' => $user_id,
                    'subject' => sanitize_text_field($_POST['subject']),
                    'status' => 'open'    
                ];
                
                $new_response = [
                    'wp_user_id' => $user_id,
                    'ticket_id' => $ticket_id,
                    'message' => sanitize_textarea_field($_POST['message'])
                ];
                
                ModelUtils::upsert_data( 'ewst25_user_tickets', $new_ticket );
                ModelUtils::upsert_data( 'ewst25_ticket_responses', $new_response );
                
                $_SESSION['success'] = "Ticket successfully created";
                wp_redirect( $previous_url );
                
                break;
                
            default:
                // Be careful, this currently will trigger for every post form submission!
                //echo "Bruh";
            
        }// end switch
        
    }// end fn
    
}// end class

class EwstAdminViews{
    
    public static function load_admin_ticket_list() {
        
        global $wpdb;
        $tickets = UserTicketModel::get_all_tickets();
        
        echo '<h2>Easy WP Support Tickets</h2>';
        
        if( count($tickets) ){
            
            echo '
                    <table class="ewst-basic-table" >
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>View</th>
                        </tr>
                    ';
                    
            foreach ($tickets as $ticket) {
                echo "
                    <tr>
                        <td>{$ticket->id}</td>
                        <td>{$ticket->wp_user_id}</td>
                        <td>$ticket->subject</td>
                        <td>$ticket->status</td>
                        <td>
                            <a href='?page=ticket_list&ticket={$ticket->id}'>View</a>
                        </td>
                    </tr>";
            }
            echo '</table>';
            
        } else{
            
            echo "<span class='ewst-alert'>No tickets detected</span>";
            
        }

    }// end fn

}// end class

class EwstLoadViews{
    
    public static function create_ticket_form() {
        
        EwstUtils::init_session();
        
        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<span class="ewst-alert">You must be logged in to create or view tickets.</span>';
            return ob_get_clean();
        }
        
        $attribution_tag = VALID_EWST_LICENSE ? "" : "<span class='ewst-attribution'>Powered by Easy WP Support Tickets</span>";
        
        if( $SESSION['success'] ){
            echo "<span class='ewst-alert'>" . $SESSION['success'] . "</span>";
        }
        echo "
            <form class='ewst-create-ticket-form' method='post'>
            
                <span class='ewst-basic-title'>Create new ticket</span>
            
                <label for='ewst-ticket-subject'>
                    Ticket subject:
                </label>
                <input id='ewst-ticket-subject' class='ewst-text-input' type='text' name='subject' required>
                
                <label for='ewst-ticket-message'>
                    Message:
                </label>
                <textarea id='ewst-ticket-message' class='ewst-textarea' name='message' required></textarea>
                <input type='hidden' name='action' value='create_ticket'>
                <button class='ewst-submit-btn' type='submit'>Submit</button>
                
                $attribution_tag
                
            </form>
        ";
        
        return ob_get_clean();
            
    }// end fn

    public static function view_ticket_list() {
        
        $user_tickets = UserTicketModel::get_tickets_by_user( get_current_user_id() );
        
        if( count($user_tickets) ){
            
            echo "
                <span class='ewst-basic-title'>Viewing tickets</span>
                
                <table class='ewst-basic-table' >
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                ";
                    
            foreach ($user_tickets as $ticket) {
                echo "
                    <tr>
                        <td>{$ticket->id}</td>
                        <td>{$ticket->wp_user_id}</td>
                        <td>$ticket->subject</td>
                        <td>$ticket->status</td>
                        <td>
                            <a href='?ticket={$ticket->id}'>View</a>
                        </td>
                    </tr>";
            }
            echo '</table>';
            
        } else{
            
            echo "<span class='ewst-alert'>No tickets detected</span>";
            
        }
        
    }// end fn
    
    public static function view_ticket_responses() {
        
        $ticket_id = $_GET['ticket'] ?? NULL;
        
        // Dont forget to make sure only the right user can view this ticket
        if( empty($ticket_id) ){
            echo "<span class='ewst-alert'>No ticket selected</span>";
            return;
        }
        
        $this_ticket = UserTicketModel::get_ticket_by_id( $ticket_id );
        $this_ticket = $this_ticket[0] ?? NULL;
        $ticket_responses = TicketResponseModel::get_responses_by_ticket( $ticket_id );
        
        if( count($ticket_responses) ){
            
            echo "
                <span class='ewst-basic-title'>Viewing ticket $ticket_id</span>
                <div class='ewst-response-list' >
                ";
                    
            foreach ($ticket_responses as $response) {
                echo "
                   <div class='ewst-response-header'>
                   
                        <div>
                            <span>User: $response->wp_user_id</span>
                        </div>
                        
                        <div>
                            <span>Identity (TODO)</span>
                            <span>Last updated: $response->created_at</span>
                        </div>
                        
                    </div><!-- ewst-response-list -->
                    
                    <div class='ewst-response-body'>
                        $response->message
                    </div><!-- ewst-response-body -->
                ";
            }
            echo '</div><!-- ewst-response-list -->';
            
        } else{
            
            echo "<span class='ewst-alert'>No responses detected</span>";
            
        }
        
    }// end fn
    
}// end class


EwstSetup::init_plugin();
// add_action('plugins_loaded', ['EwstSetup', 'init_plugin']);
