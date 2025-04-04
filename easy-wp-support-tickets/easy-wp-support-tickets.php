<?php
/**
 * Plugin Name:Easy Wp Support Tickets
 * Description: A simple support ticket system for WordPress.
 * Version: 1.10
 * Author: Shay Pottle
 */



if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';


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
    
}// end class

// plugin prefix: ewst25

class EwstSetup{
    
    public static function init_plugin(){
        
        register_activation_hook(__FILE__, ['EwstSetup', 'install_db_tables']);
        //register_deactivation_hook(__FILE__, ['EwstSetup', 'uninstall_db_tables']);
        register_uninstall_hook(__FILE__, ['EwstSetup', 'uninstall_db_tables']);
        
        add_action('admin_menu', ['EwstSetup', 'init_admin_menu']);
        add_shortcode('ewst_create_ticket_form', ['EwstLoadViews', 'create_ticket_form']);
        add_shortcode('ewst_view_ticket_list', ['EwstLoadViews', 'view_ticket_list']);
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
    
        dbDelta( $user_tickets_table );
        dbDelta( $ticket_responses_table );
        
    }// end fn
    
    public static function uninstall_db_tables() {
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ewst25_user_tickets';
        $table_responses = $wpdb->prefix . 'ewst25_ticket_responses';

        $wpdb->query("DROP TABLE IF EXISTS $table_responses");
        $wpdb->query("DROP TABLE IF EXISTS $table_tickets");
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
        
        switch( $_POST['action'] ){
            
            case 'create_ticket':
                $wpdb->insert("{$wpdb->prefix}ewst25_user_tickets", [
                    'wp_user_id' => $user_id,
                    'subject' => sanitize_text_field($_POST['subject']),
                    'status' => 'open'
                ]);
                
                $ticket_id = $wpdb->insert_id;
                
                $wpdb->insert("{$wpdb->prefix}ewst25_ticket_responses", [
                    'wp_user_id' => $user_id,
                    'ticket_id' => $ticket_id,
                    'message' => sanitize_textarea_field($_POST['message'])
                ]);
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
                    <table class="ewst-ticket-list" >
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
            
            echo "No tickets detected";
            
        }

    }// end fn

}// end class

class EwstLoadViews{
    
    public static function create_ticket_form() {
        
        ob_start();
        
        if (!is_user_logged_in()) {
            echo '<p>You must be logged in to create or view tickets.</p>';
            return ob_get_clean();
        }
        
        $attribution_tag = VALID_EWST_LICENSE ? "" : "<span class='ewst-attribution'>Powered by Easy WP Support Tickets</span>";
        
        
        echo "
            <form class='ewst-create-ticket-form' method='post'>
            
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
            
            echo '
                    <table class="ewst-ticket-list" >
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>View</th>
                        </tr>
                    ';
                    
            foreach ($user_tickets as $ticket) {
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
            
            echo "No tickets detected";
            
        }
       
        
        foreach ($user_tickets as $ticket) {
            echo "<p><strong>{$ticket->subject}</strong> ({$ticket->status}) - <a href='?ticket={$ticket->id}'>View</a></p>";
        }
        
    }// end fn
    
}// end class


EwstSetup::init_plugin();
// add_action('plugins_loaded', ['EwstSetup', 'init_plugin']);
