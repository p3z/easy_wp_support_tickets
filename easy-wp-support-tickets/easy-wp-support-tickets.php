<?php
/**
 * Plugin Name:Easy Wp Support Tickets
 * Description: A simple support ticket system for WordPress.
 * Version: 1.7
 * Author: Shay Pottle
 */



if (!defined('ABSPATH')) exit;


// plugin prefix: ewst25

class EWST25 {
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'install_db_tables']);
        register_deactivation_hook(__FILE__, [$this, 'uninstall_ewst_tables']); // Add deactivation hook

        add_action('admin_menu', [$this, 'init_admin_menu']);
        add_shortcode('ewst_ticket_form', [$this, 'frontend_view']);
        add_action('init', [$this, 'handle_form_submission']);
    }// end constructor
    
    public function install_db_tables(){
        
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Force collation to match existing tables
        $charset_collate = "DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci";
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
    
    public function uninstall_ewst_tables() {
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'ewst25_user_tickets';
        $table_responses = $wpdb->prefix . 'ewst25_ticket_responses';

        $wpdb->query("DROP TABLE IF EXISTS $table_responses");
        $wpdb->query("DROP TABLE IF EXISTS $table_tickets");
    }// end fn
    
    public function init_admin_menu() {
        
        add_menu_page(
            'Easy WP Support Tickets',
            'Easy WP Support Tickets',
            'manage_options',
            'ticket_list',
            [$this, 'admin_ticket_list'],
            'dashicons-tickets-alt'
        );
        
    }// end fn

    public function admin_ticket_list() {
        
        global $wpdb;
        $tickets = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets ORDER BY created_at DESC");
        
        echo '<h2>Easy WP Support Tickets</h2>';
        if( count($tickets) ){
            
            $css = "
                <style>
                    #ewst-ticket-list{
                        width: 80%;
                        
                    }
                    
                    #ewst-ticket-list tr{
                        border: 1px solid blue;
                    }
                    
                    #ewst-ticket-list th{
                        background: rgba(0,0,0,0.3);
                        color: #fff;
                        padding: 10px;
                    }
                    
                    #ewst-ticket-list td{
                        
                        padding: 10px;
                        text-align: center;
                    }
                </style>
            ";
            
            echo $css;
            
            echo '
                    <table id="ewst-ticket-list" >
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

    public function frontend_view() {
        ob_start();
        if (!is_user_logged_in()) {
            echo '<p>You must be logged in to create or view tickets.</p>';
            return ob_get_clean();
        }
        $this->ticket_form();
        $this->ticket_list();
        return ob_get_clean();
    }// end fn

    public function ticket_form() {
        echo '<h2>Create a Ticket</h2>
            <form method="post">
                <input type="text" name="subject" placeholder="Subject" required>
                <textarea name="message" placeholder="Message" required></textarea>
                <input type="hidden" name="action" value="create_ticket">
                <button type="submit">Submit</button>
            </form>';
    }// end fn

    public function ticket_list() {
        
        global $wpdb;
        $user_id = get_current_user_id();
        $tickets = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ewst25_user_tickets WHERE wp_user_id = %d", $user_id));
        
        echo '<h2>Your Tickets</h2>';
        foreach ($tickets as $ticket) {
            echo "<p><strong>{$ticket->subject}</strong> ({$ticket->status}) - <a href='?ticket={$ticket->id}'>View</a></p>";
        }
    }// end fn

    public function handle_form_submission() {
        if (!isset($_POST['action']) || !is_user_logged_in()) return;
        global $wpdb;
        $user_id = get_current_user_id();

        if ($_POST['action'] == 'create_ticket') {
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
        }
    }// end fn
    
}// end class

new EWST25();
