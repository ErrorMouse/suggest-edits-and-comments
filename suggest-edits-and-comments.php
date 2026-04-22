<?php
/**
 * Plugin Name: 		Suggest Edits and Comments
 * Description: 		Allows users to highlight text within posts to easily submit comments and suggested edits.
 * Version: 			1.0
 * Requires at least: 	5.2
 * Requires PHP:      	7.2
 * Author:        		Chout
 * Author URI:    		https://profiles.wordpress.org/nmtnguyen56/
 * License:        		GPL-2.0-or-later
 * License URI:    		https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 		suggest-edits-and-comments
 * Domain Path:         /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SEACO_VERSION' ) ) {
    define( 'SEACO_VERSION', '1.0' );
}

// Hàm trợ giúp: Mã hóa an toàn cho Text Fragment
function seaco_encode_fragment($string) {
    if (empty($string)) return '';
    $encoded = rawurlencode($string);
    
    // Ký tự bắt buộc phải encode trong Text Fragment theo chuẩn
    $encoded = str_replace('-', '%2D', $encoded);
    
    // Giải mã các ký tự an toàn để URL giống Chrome nhất và không bị lỗi khi tìm kiếm
    $safe_chars = array('(', ')', '!', '*', "'", ';', ':', '@', '$', '/', '?', '=', '[', ']');
    foreach ($safe_chars as $char) {
        $encoded = str_replace(rawurlencode($char), $char, $encoded);
    }
    return $encoded;
}

function seaco_generate_fragment_link($post_url, $selected_text, $prefix = '', $suffix = '') {
    if (empty($selected_text)) return $post_url;

    $encoded_text = seaco_encode_fragment($selected_text);
    
    $encoded_prefix = '';
    if (!empty($prefix)) {
        $encoded_prefix = seaco_encode_fragment($prefix) . '-,';
    }
    
    $encoded_suffix = '';
    if (!empty($suffix)) {
        $encoded_suffix = ',-' . seaco_encode_fragment($suffix);
    }

    return $post_url . '#:~:text=' . $encoded_prefix . $encoded_text . $encoded_suffix;
}

function seaco_get_settings() {
    $defaults = array(
        'excluded_roles'            => array(),
        'allowed_post_types'        => array('post', 'page'),
        'target_selectors'          => '.entry-content',
        'recaptcha_enable'          => false,
        'recaptcha_site_key'        => '',
        'recaptcha_secret_key'      => '',
        'max_text_length'           => 1000, 
        'daily_limit'               => 10,   
        'quote_border_color'        => '#1a73e8',
        'quote_text_color'          => '#1a73e8',
        'tooltip_btn_text'          => '', 
        'tooltip_btn_color'         => '#1a73e8',
        'tooltip_btn_hover_color'   => '#1557b0',
        'submit_btn_text'           => '', 
        'submit_btn_color'          => '#1a73e8',
        'submit_btn_hover_color'    => '#1557b0',
        'uninstall_delete_comments' => false,
        'uninstall_delete_settings' => false,
    );
    return wp_parse_args(get_option('seaco_settings', array()), $defaults);
}

// 1. Enqueue Scripts and Styles
add_action('wp_enqueue_scripts', 'seaco_enqueue_scripts');
function seaco_enqueue_scripts() {
    $settings = seaco_get_settings();

    // Luôn tải CSS vì Widget có thể được đặt ở mọi nơi (Trang chủ, Sidebar, Footer)
    wp_enqueue_style('seaco-style', plugin_dir_url(__FILE__) . 'assets/suggest-edits-and-comments.css', array(), SEACO_VERSION);
    
    $custom_css = "
        .suggest-edits-quote-box { border-left-color: {$settings['quote_border_color']} !important; }
        .suggest-edits-quote-box > div { color: {$settings['quote_text_color']} !important; }
        #suggest-edits-tooltip button { background-color: {$settings['tooltip_btn_color']} !important; }
        #suggest-edits-tooltip button:hover { background-color: {$settings['tooltip_btn_hover_color']} !important; }
        #suggest-edits-submit { background-color: {$settings['submit_btn_color']} !important; }
        #suggest-edits-submit:hover { background-color: {$settings['submit_btn_hover_color']} !important; }
        #suggest-edits-form-box textarea:focus { border: thin solid {$settings['submit_btn_color']} !important; }
    ";
    wp_add_inline_style('seaco-style', wp_strip_all_tags($custom_css));

    // Luôn tải JS vì Widget cần script để chạy AJAX làm mới thời gian thực
    wp_enqueue_script('seaco-script', plugin_dir_url(__FILE__) . 'assets/suggest-edits-and-comments.js', array('jquery'), SEACO_VERSION, true);

    $excluded_roles = is_array($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
    $current_user   = wp_get_current_user();
    $user_roles     = (array) $current_user->roles;
    
    $allowed_post_types = is_array($settings['allowed_post_types']) ? $settings['allowed_post_types'] : array('post', 'page');
    
    $is_valid_page = is_singular($allowed_post_types) && comments_open();
    $can_submit    = false;
    
    if ($is_valid_page) {
        if (!is_user_logged_in()) {
            $can_submit = !in_array('guest', $excluded_roles);
        } else {
            $can_submit = empty(array_intersect($user_roles, $excluded_roles));
        }
    }

    $perm_msg = esc_html__('You do not have permission to submit suggested edits.', 'suggest-edits-and-comments');
    if (!is_user_logged_in() && in_array('guest', $excluded_roles)) {
        $perm_msg = esc_html__('Please log in to submit your suggested edit!', 'suggest-edits-and-comments');
    }

    if ($is_valid_page && $can_submit && !empty($settings['recaptcha_enable']) && !empty($settings['recaptcha_site_key'])) {
        wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($settings['recaptcha_site_key']), array(), SEACO_VERSION, true);
    }

    wp_localize_script('seaco-script', 'seaco_vars', array(
        'ajax_url'           => admin_url('admin-ajax.php'),
        'nonce'              => wp_create_nonce('seaco_nonce'),
        'post_id'            => get_the_ID(),
        'can_submit'         => $can_submit,
        'is_valid_page'      => $is_valid_page, 
        'target_selectors'   => !empty($settings['target_selectors']) ? $settings['target_selectors'] : '.entry-content',
        'max_text_length'    => (isset($settings['max_text_length']) && $settings['max_text_length'] !== '') ? absint($settings['max_text_length']) : 0,
        'recaptcha_enable'   => !empty($settings['recaptcha_enable']),
        'recaptcha_site_key' => $settings['recaptcha_site_key'],
        'i18n'               => array(
            'suggest_edit' => !empty($settings['tooltip_btn_text']) ? esc_html($settings['tooltip_btn_text']) : esc_html__('💬 Suggest Edit', 'suggest-edits-and-comments'),
            'placeholder'  => esc_html__('Enter your suggested changes...', 'suggest-edits-and-comments'),
            'submit'       => !empty($settings['submit_btn_text']) ? esc_html($settings['submit_btn_text']) : esc_html__('Submit', 'suggest-edits-and-comments'),
            'cancel'       => esc_html__('Cancel', 'suggest-edits-and-comments'),
            'perm_msg'     => $perm_msg,
            'empty_se'     => esc_html__('Please enter your suggested edit.', 'suggest-edits-and-comments'),
            'sending'      => esc_html__('Sending...', 'suggest-edits-and-comments'),
            'rc_error'     => esc_html__('reCAPTCHA Error. Please refresh the page.', 'suggest-edits-and-comments')
        )
    ));
}

// Nút Cài đặt (Settings)
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'seaco_add_settings_link' );
function seaco_add_settings_link( $links ) {
	$settings_url = admin_url( 'options-general.php?page=suggest-edits-and-comments-settings' );
	$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'suggest-edits-and-comments' ) . '</a>';
	
	// array_unshift giúp đẩy nút Settings lên đầu tiên (trước chữ Deactivate/Ngừng kích hoạt)
	array_unshift( $links, $settings_link );
    
	return $links;
}

// 2. Handle AJAX Request for Feedback
add_action('wp_ajax_seaco_submit_feedback', 'seaco_handle_feedback');
add_action('wp_ajax_nopriv_seaco_submit_feedback', 'seaco_handle_feedback');
function seaco_handle_feedback() {
    check_ajax_referer('seaco_nonce', 'security');
    $settings = seaco_get_settings();
    $excluded_roles = is_array($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
    
    $daily_limit = (isset($settings['daily_limit']) && $settings['daily_limit'] !== '') ? absint($settings['daily_limit']) : 0;

    $is_guest = !is_user_logged_in();
    if ($is_guest && in_array('guest', $excluded_roles)) {
        wp_send_json_error( esc_html__('Guests are not allowed to submit suggested edits.', 'suggest-edits-and-comments') );
    } elseif (!$is_guest) {
        $user_roles = (array) wp_get_current_user()->roles;
        if (!empty(array_intersect($user_roles, $excluded_roles))) {
            wp_send_json_error( esc_html__('You do not have permission to submit suggested edits.', 'suggest-edits-and-comments') );
        }
    }

    if (!empty($settings['recaptcha_enable']) && !empty($settings['recaptcha_secret_key'])) {
        $recaptcha_token = isset($_POST['recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_token'])) : '';
        if (empty($recaptcha_token)) {
            wp_send_json_error( esc_html__('reCAPTCHA verification failed.', 'suggest-edits-and-comments') );
        }
        $response = wp_remote_get( 'https://www.google.com/recaptcha/api/siteverify?secret=' . $settings['recaptcha_secret_key'] . '&response=' . $recaptcha_token );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( esc_html__('reCAPTCHA connection error.', 'suggest-edits-and-comments') );
        }
        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body );
        if ( ! $result || ! $result->success || $result->score < 0.5 ) {
            wp_send_json_error( esc_html__('Spam detected by reCAPTCHA.', 'suggest-edits-and-comments') );
        }
    }

    $current_time = time();
    $today = gmdate('Y-m-d', $current_time);

    if (!$is_guest) {
        $user_id = get_current_user_id();
        $last_submit = (int) get_user_meta($user_id, 'seaco_last_submit_time', true);
        if ($last_submit && ($current_time - $last_submit) < 30) {
            wp_send_json_error( esc_html__('Please wait 30 seconds before submitting another edit.', 'suggest-edits-and-comments') );
        }
        $daily_date  = get_user_meta($user_id, 'seaco_daily_date', true);
        $daily_count = (int) get_user_meta($user_id, 'seaco_daily_count', true);
        if ($daily_date !== $today) { $daily_count = 0; update_user_meta($user_id, 'seaco_daily_date', $today); }
        
        if ($daily_limit > 0 && $daily_count >= $daily_limit) {
            /* translators: %d: Limit number */
            wp_send_json_error( sprintf(esc_html__('You have reached the daily limit of %d suggested edits.', 'suggest-edits-and-comments'), $daily_limit) );
        }
    } else {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
        $ip_hash = md5($remote_addr);
        if (get_transient('seaco_cd_' . $ip_hash)) {
            wp_send_json_error( esc_html__('Please wait 30 seconds before submitting another edit.', 'suggest-edits-and-comments') );
        }
        $daily_count = (int) get_transient('seaco_dy_' . $ip_hash);
        
        if ($daily_limit > 0 && $daily_count >= $daily_limit) {
            /* translators: %d: Limit number */
            wp_send_json_error( sprintf(esc_html__('You have reached the daily limit of %d suggested edits.', 'suggest-edits-and-comments'), $daily_limit) );
        }
    }

    $post_id          = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
    $selected_text    = isset($_POST['selected_text']) ? sanitize_text_field(wp_unslash($_POST['selected_text'])) : '';
    $context_prefix   = isset($_POST['context_prefix']) ? sanitize_text_field(wp_unslash($_POST['context_prefix'])) : '';
    $context_suffix   = isset($_POST['context_suffix']) ? sanitize_text_field(wp_unslash($_POST['context_suffix'])) : '';
    $seaco_content    = isset($_POST['seaco_content']) ? sanitize_textarea_field(wp_unslash($_POST['seaco_content'])) : '';
    
    $user = wp_get_current_user();
    $time = current_time('mysql');
    
    $data = array(
        'comment_post_ID'      => $post_id,
        'comment_author'       => $is_guest ? esc_html__('Guest', 'suggest-edits-and-comments') : $user->display_name,
        'comment_author_email' => $is_guest ? '' : $user->user_email,
        'comment_content'      => $seaco_content,
        'comment_type'         => 'seaco_suggest', 
        'comment_parent'       => 0,
        'user_id'              => $is_guest ? 0 : $user->ID,
        'comment_date'         => $time,
        'comment_approved'     => 1, 
    );

    $comment_id = wp_insert_comment($data);

    if ($comment_id) {
        add_comment_meta($comment_id, 'seaco_selected_text', $selected_text);
        if (!empty($context_prefix)) add_comment_meta($comment_id, 'seaco_context_prefix', $context_prefix);
        if (!empty($context_suffix)) add_comment_meta($comment_id, 'seaco_context_suffix', $context_suffix);
        
        if (!$is_guest) {
            update_user_meta($user_id, 'seaco_last_submit_time', $current_time);
            update_user_meta($user_id, 'seaco_daily_count', $daily_count + 1);
        } else {
            set_transient('seaco_cd_' . $ip_hash, true, 30);
            set_transient('seaco_dy_' . $ip_hash, $daily_count + 1, DAY_IN_SECONDS);
        }

        wp_send_json_success( esc_html__('Thank you for your suggestion!', 'suggest-edits-and-comments') );
    } else {
        wp_send_json_error( esc_html__('An error occurred. Please try again.', 'suggest-edits-and-comments') );
    }
}

// 3. Suggest Edits History Widget
class SEACO_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('seaco_widget', esc_html__('Suggest Edits History', 'suggest-edits-and-comments'));
    }

    public function widget($args, $instance) {
        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        
        if (!empty($instance['title'])) {
            echo $args['before_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo esc_html(apply_filters('widget_title', $instance['title']));
            echo $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $limit = (!empty($instance['limit'])) ? absint($instance['limit']) : 5;
        $max_height = (isset($instance['max_height'])) ? absint($instance['max_height']) : 300;
        $text_limit = (isset($instance['text_limit'])) ? absint($instance['text_limit']) : 50; 
        $show_time = isset($instance['show_time']) ? (bool) $instance['show_time'] : true; 

        $comments = get_comments(array(
            'type'   => array('comment', '', 'seaco_suggest'), 
            'number' => $limit, 
            'status' => 'approve'
        ));

        $scroll_style = '';
        if ($max_height > 0) {
            $scroll_style = sprintf('style="max-height: %dpx; overflow-y: auto; overflow-x: hidden; padding-right: 8px;"', esc_attr($max_height));
        }

        echo '<ul class="suggest-edits-widget-list" data-limit="' . esc_attr($limit) . '" data-textlimit="' . esc_attr($text_limit) . '" data-showtime="' . esc_attr($show_time ? 1 : 0) . '" ' . $scroll_style . '>'; 
        
        if ($comments) {
            foreach ($comments as $comment) {
                $selected_text = get_comment_meta($comment->comment_ID, 'seaco_selected_text', true);
                $context_prefix = get_comment_meta($comment->comment_ID, 'seaco_context_prefix', true);
                $context_suffix = get_comment_meta($comment->comment_ID, 'seaco_context_suffix', true);
                $post_url = get_permalink($comment->comment_post_ID);
                $comment_link = get_comment_link($comment); 
                
                echo '<li style="line-height: 1.6; margin-bottom: 8px;">'; 
                
                if (!empty($selected_text)) {
                    $exact_link = seaco_generate_fragment_link($post_url, $selected_text, $context_prefix, $context_suffix);
                    
                    $display_text = $selected_text;
                    if ($text_limit > 0 && mb_strlen($selected_text, 'UTF-8') > $text_limit) {
                        $display_text = mb_substr($selected_text, 0, $text_limit, 'UTF-8') . '...';
                    }
                    
                    echo wp_kses_post( sprintf( 
                        /* translators: 1: Author name, 2: Comment content, 3: Link URL, 4: Highlighted text */
                        __('<strong>%1$s</strong> commented "%2$s" on <a href="%3$s" target="_blank">"%4$s"</a>', 'suggest-edits-and-comments'), 
                        esc_html($comment->comment_author), 
                        esc_html($comment->comment_content), 
                        esc_url($exact_link), 
                        esc_html($display_text) 
                    ) );
                } else {
                    echo wp_kses_post( sprintf( 
                        /* translators: 1: Author name, 2: Comment content, 3: Link URL */
                        __('<strong>%1$s</strong> commented "%2$s" on <a href="%3$s" target="_blank">this post</a>', 'suggest-edits-and-comments'), 
                        esc_html($comment->comment_author), 
                        esc_html($comment->comment_content), 
                        esc_url($comment_link) 
                    ) );
                }

                if ($show_time) {
                    $time_str = seaco_format_time($comment->comment_date);
                    echo ' <span style="color: #888; font-size: 0.85em; white-space: nowrap;">- ' . esc_html($time_str) . '</span>';
                }
                
                echo '</li>';
            }
        } else {
            echo '<li>' . esc_html__('No comments yet.', 'suggest-edits-and-comments') . '</li>';
        }
        echo '</ul>';

        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : esc_html__('Recent Suggested Edits', 'suggest-edits-and-comments');
        $limit = !empty($instance['limit']) ? absint($instance['limit']) : 5;
        $max_height = isset($instance['max_height']) ? absint($instance['max_height']) : 300;
        $text_limit = isset($instance['text_limit']) ? absint($instance['text_limit']) : 50; 
        $show_time = isset($instance['show_time']) ? (bool) $instance['show_time'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'suggest-edits-and-comments'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>"><?php esc_html_e('Number of suggested edits to show:', 'suggest-edits-and-comments'); ?></label>
            <input class="small-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($limit); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('max_height')); ?>"><?php esc_html_e('Max Height (px):', 'suggest-edits-and-comments'); ?></label>
            <input class="small-text" id="<?php echo esc_attr($this->get_field_id('max_height')); ?>" name="<?php echo esc_attr($this->get_field_name('max_height')); ?>" type="number" step="10" min="0" value="<?php echo esc_attr($max_height); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('text_limit')); ?>"><?php esc_html_e('Max Characters for Referenced Text:', 'suggest-edits-and-comments'); ?></label>
            <input class="small-text" id="<?php echo esc_attr($this->get_field_id('text_limit')); ?>" name="<?php echo esc_attr($this->get_field_name('text_limit')); ?>" type="number" step="5" min="0" value="<?php echo esc_attr($text_limit); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_time')); ?>" name="<?php echo esc_attr($this->get_field_name('show_time')); ?>" <?php checked($show_time, true); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_time')); ?>"><?php esc_html_e('Show comment time', 'suggest-edits-and-comments'); ?></label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? absint($new_instance['limit']) : 5;
        $instance['max_height'] = (isset($new_instance['max_height'])) ? absint($new_instance['max_height']) : 300;
        $instance['text_limit'] = (isset($new_instance['text_limit'])) ? absint($new_instance['text_limit']) : 50;
        $instance['show_time'] = !empty($new_instance['show_time']) ? 1 : 0;
        return $instance;
    }
}

// --- HÀM TÍNH TOÁN THỜI GIAN ---
function seaco_format_time($comment_date) {
    $timestamp = strtotime($comment_date);
    $current_time = current_time('timestamp');
    $diff = max(0, $current_time - $timestamp); 

    if ($diff < 60) {
        return esc_html__('Just now', 'suggest-edits-and-comments');
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        /* translators: %s: Number of minutes */
        return sprintf( _n('%s min ago', '%s mins ago', $mins, 'suggest-edits-and-comments'), $mins );
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        /* translators: %s: Number of hours */
        return sprintf( _n('%s hour ago', '%s hours ago', $hours, 'suggest-edits-and-comments'), $hours );
    } else {
        $days = floor($diff / 86400);
        if ($days <= 3) {
            /* translators: %s: Number of days */
            return sprintf( _n('%s day ago', '%s days ago', $days, 'suggest-edits-and-comments'), $days );
        } else {
            return gmdate('d/m/Y', $timestamp); 
        }
    }
}

// --- TÍNH NĂNG REAL-TIME WIDGET ---
add_action('wp_ajax_seaco_refresh_widget', 'seaco_refresh_widget_ajax');
add_action('wp_ajax_nopriv_seaco_refresh_widget', 'seaco_refresh_widget_ajax');

function seaco_refresh_widget_ajax() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public read-only AJAX action.
    $limit      = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 5;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public read-only AJAX action.
    $text_limit = isset($_POST['text_limit']) ? absint(wp_unslash($_POST['text_limit'])) : 50;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public read-only AJAX action.
    $show_time  = isset($_POST['show_time']) ? rest_sanitize_boolean(wp_unslash($_POST['show_time'])) : true; 

    $comments = get_comments(array(
        'type'   => array('comment', '', 'seaco_suggest'),
        'number' => $limit, 
        'status' => 'approve'
    ));

    $html = '';

    if ($comments) {
        foreach ($comments as $comment) {
            $selected_text = get_comment_meta($comment->comment_ID, 'seaco_selected_text', true);
            $context_prefix = get_comment_meta($comment->comment_ID, 'seaco_context_prefix', true);
            $context_suffix = get_comment_meta($comment->comment_ID, 'seaco_context_suffix', true);
            
            $post_url = get_permalink($comment->comment_post_ID);
            $comment_link = get_comment_link($comment); 
            
            $html .= '<li style="line-height: 1.6; margin-bottom: 8px;">'; 
            
            if (!empty($selected_text)) {
                $exact_link = seaco_generate_fragment_link($post_url, $selected_text, $context_prefix, $context_suffix);
                
                $display_text = $selected_text;
                if ($text_limit > 0 && mb_strlen($selected_text, 'UTF-8') > $text_limit) {
                    $display_text = mb_substr($selected_text, 0, $text_limit, 'UTF-8') . '...';
                }
                
                $html .= wp_kses_post( sprintf( 
                    /* translators: 1: Author name, 2: Comment content, 3: Link URL, 4: Highlighted text */
                    __('<strong>%1$s</strong> commented "%2$s" on <a href="%3$s" target="_blank">"%4$s"</a>', 'suggest-edits-and-comments'), 
                    esc_html($comment->comment_author), 
                    esc_html($comment->comment_content), 
                    esc_url($exact_link), 
                    esc_html($display_text) 
                ) );
            } else {
                $html .= wp_kses_post( sprintf( 
                    /* translators: 1: Author name, 2: Comment content, 3: Link URL */
                    __('<strong>%1$s</strong> commented "%2$s" on <a href="%3$s" target="_blank">this post</a>', 'suggest-edits-and-comments'), 
                    esc_html($comment->comment_author), 
                    esc_html($comment->comment_content), 
                    esc_url($comment_link) 
                ) );
            }
            
            if ($show_time) {
                $time_str = seaco_format_time($comment->comment_date);
                $html .= ' <span style="color: #888; font-size: 0.85em; white-space: nowrap;">- ' . esc_html($time_str) . '</span>';
            }

            $html .= '</li>';
        }
    } else {
        $html .= '<li>' . esc_html__('No comments yet.', 'suggest-edits-and-comments') . '</li>';
    }

    wp_send_json_success($html);
}

add_action('widgets_init', function() {
    register_widget('SEACO_Widget');
});

// Thêm cột mới vào bảng danh sách bình luận trong Admin
add_filter( 'manage_edit-comments_columns', 'seaco_add_custom_column' );
function seaco_add_custom_column( $columns ) {
    $columns['highlighted_text'] = esc_html__( 'Referenced Text', 'suggest-edits-and-comments' );
    return $columns;
}

// Đổ dữ liệu vào cột vừa tạo
add_action( 'manage_comments_custom_column', 'seaco_fill_custom_column', 10, 2 );
function seaco_fill_custom_column( $column, $comment_id ) {
    if ( 'highlighted_text' === $column ) {
        $selected_text = get_comment_meta( $comment_id, 'seaco_selected_text', true );
        $context_prefix = get_comment_meta( $comment_id, 'seaco_context_prefix', true );
        $context_suffix = get_comment_meta( $comment_id, 'seaco_context_suffix', true );
        
        if ( $selected_text ) {
            $post_id = get_comment( $comment_id )->comment_post_ID;
            $post_url = get_permalink( $post_id );
            
            $exact_link = seaco_generate_fragment_link($post_url, $selected_text, $context_prefix, $context_suffix);

            echo '<div style="font-style: italic; color: #666; margin-bottom: 5px;">"' . esc_html( $selected_text ) . '"</div>';
            echo '<a href="' . esc_url( $exact_link ) . '" target="_blank" class="button button-small">' . esc_html__( 'View on Post', 'suggest-edits-and-comments' ) . '</a>';
        } else {
            echo '<span class="description">-</span>';
        }
    }
}

// Tạo Meta Box trong trang chi tiết bình luận
add_action( 'add_meta_boxes_comment', 'seaco_add_meta_box' );
function seaco_add_meta_box() {
    add_meta_box( 
        'seaco_meta_box', 
        esc_html__( 'Suggested Edit Details', 'suggest-edits-and-comments' ), 
        'seaco_meta_box_callback', 
        'comment', 
        'normal', 
        'high' 
    );
}

function seaco_meta_box_callback( $comment ) {
    $selected_text = get_comment_meta( $comment->comment_ID, 'seaco_selected_text', true );
    $context_prefix = get_comment_meta( $comment->comment_ID, 'seaco_context_prefix', true );
    $context_suffix = get_comment_meta( $comment->comment_ID, 'seaco_context_suffix', true );
    
    if ( $selected_text ) {
        $post_url = get_permalink( $comment->comment_post_ID );
        $exact_link = seaco_generate_fragment_link($post_url, $selected_text, $context_prefix, $context_suffix);
        ?>
        <p><strong><?php esc_html_e( 'Referenced Text:', 'suggest-edits-and-comments' ); ?></strong></p>
        <blockquote style="background: #f9f9f9; padding: 10px; border-left: 4px solid #1a73e8; margin: 0 0 10px 0;">
            <?php echo esc_html( $selected_text ); ?>
        </blockquote>
        <p>
            <a href="<?php echo esc_url( $exact_link ); ?>" target="_blank" class="button button-primary">
                <?php esc_html_e( 'Go to Referenced Location', 'suggest-edits-and-comments' ); ?>
            </a>
        </p>
        <?php
    } else {
        echo '<p>' . esc_html__( 'This is a standard comment (no Referenced Text).', 'suggest-edits-and-comments' ) . '</p>';
    }
}

// TÍCH HỢP HIỂN THỊ ĐOẠN BÔI ĐEN VÀO LUỒNG BÌNH LUẬN (WPDISCUZ)
add_filter( 'comment_text', 'seaco_wpdiscuz_integration', 10, 2 );
function seaco_wpdiscuz_integration( $comment_text, $comment ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $comment_text;
    }

    if ( ! is_object( $comment ) || empty( $comment->comment_ID ) ) {
        return $comment_text;
    }

    $selected_text = get_comment_meta( $comment->comment_ID, 'seaco_selected_text', true );
    $context_prefix = get_comment_meta( $comment->comment_ID, 'seaco_context_prefix', true );
    $context_suffix = get_comment_meta( $comment->comment_ID, 'seaco_context_suffix', true );

    if ( ! empty( $selected_text ) ) {
        $post_url = get_permalink( $comment->comment_post_ID );
        $exact_link = seaco_generate_fragment_link($post_url, $selected_text, $context_prefix, $context_suffix);

        $highlight_box = '<div class="suggest-edits-quote-box">';
        $highlight_box .= '<div>';
        $highlight_box .= '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>';
        $highlight_box .= esc_html__( 'Referenced Text', 'suggest-edits-and-comments' ) . '</div>';
        $highlight_box .= '<a href="' . esc_url( $exact_link ) . '">"' . esc_html( $selected_text ) . '"</a>';
        $highlight_box .= '</div>';

        // Đẩy hộp trích dẫn lên TRƯỚC nội dung bình luận
        $comment_text = wp_kses_post($highlight_box) . $comment_text;
    }

    return $comment_text;
}

// --- BẮT ĐẦU: TRANG CÀI ĐẶT PLUGIN (SETTINGS PAGE) ---
add_action('admin_menu', 'seaco_add_settings_menu');
function seaco_add_settings_menu() {
    add_options_page(
        esc_html__('SE & Comments Settings', 'suggest-edits-and-comments'),
        esc_html__('Suggest Edits and Comments', 'suggest-edits-and-comments'),
        'manage_options',
        'suggest-edits-and-comments-settings',
        'seaco_settings_page_html'
    );
}

add_action('admin_enqueue_scripts', 'seaco_admin_assets');
function seaco_admin_assets($hook) {
    global $hook_suffix;
    $is_plugins_page  = ( 'plugins.php' === $hook_suffix );

    if ($hook !== 'settings_page_suggest-edits-and-comments-settings' && !$is_plugins_page) return;
    
    wp_enqueue_style('seaco-admin-style', plugin_dir_url(__FILE__) . 'assets/suggest-edits-and-comments-admin.css', array(), SEACO_VERSION);

    if ( $is_plugins_page ) {
        $donate_css = "
            .err-donate-link {
                font-weight: bold;
                background: linear-gradient(90deg, #0066ff, #00a1ff, rgb(255, 0, 179), #0066ff);
                background-size: 200% auto;
                color: #fff;
                -webkit-background-clip: text;
                -moz-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                animation: errGradientText 2s linear infinite;
            }
            @keyframes errGradientText {
                to { background-position: -200% center; }
            }";
        wp_add_inline_style( 'seaco-admin-style', wp_strip_all_tags($donate_css) );
    }

    if ($hook === 'settings_page_suggest-edits-and-comments-settings') {
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        wp_enqueue_script('seaco-admin-script', plugin_dir_url(__FILE__) . 'assets/suggest-edits-and-comments-admin.js', array('jquery', 'wp-color-picker'), SEACO_VERSION, true);
        
        wp_localize_script('seaco-admin-script', 'seaco_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('seaco_admin_settings_nonce')
        ));
    }
}

function seaco_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    $settings = seaco_get_settings();
    
    $wp_roles  = wp_roles()->get_names();
    $all_roles = array('guest' => esc_html__('Guest (Not logged in)', 'suggest-edits-and-comments')) + $wp_roles;
    ?>
    <div class="suggest-edits-admin-wrap">
        <h1 style="margin-bottom: 20px;"><?php esc_html_e('SE & Comments Settings', 'suggest-edits-and-comments'); ?><span class="author">by <a href="https://profiles.wordpress.org/nmtnguyen56/" target="_blank" rel="noopener noreferrer">Err</a></span><span class="donate"><?php seaco_donate_link_html(); ?></span></h1>
        
        <form id="suggest-edits-settings-form" method="post" autocomplete="off">
            
            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Permissions', 'suggest-edits-and-comments'); ?></h2>
                <p class="description"><?php esc_html_e('Select which user roles are EXCLUDED from highlighting text and submitting suggested edits.', 'suggest-edits-and-comments'); ?></p>
                <div class="suggest-edits-checkbox-group" style="margin-top: 15px;">
                    <?php 
                    $excluded = is_array($settings['excluded_roles']) ? $settings['excluded_roles'] : array();
                    foreach ($all_roles as $role_key => $role_name) {
                        $translated_name = translate_user_role( $role_name );
                        ?>
                        <label>
                            <input type="checkbox" name="excluded_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $excluded, true)); ?>> 
                            <?php echo esc_html($translated_name); ?>
                        </label>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Display Settings', 'suggest-edits-and-comments'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Allowed Post Types', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <div class="suggest-edits-checkbox-group">
                                <?php 
                                $post_types = get_post_types(array('public' => true), 'objects');
                                $allowed_pts = is_array($settings['allowed_post_types']) ? $settings['allowed_post_types'] : array('post', 'page');
                                foreach ($post_types as $pt) {
                                    if ($pt->name === 'attachment') continue; ?>
                                    <label>
                                        <input type="checkbox" name="allowed_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $allowed_pts, true)); ?>> 
                                        <?php echo esc_html($pt->label); ?>
                                    </label>
                                    <?php
                                } ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="target_selectors"><?php esc_html_e('Target Selectors', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="text" id="target_selectors" value="<?php echo esc_attr($settings['target_selectors']); ?>" class="regular-text" placeholder=".entry-content" />
                            <p class="description"><?php esc_html_e('CSS Class or ID where the highlight feature is active (e.g., .entry-content, #main-content, .product-desc). Separate multiple selectors with commas.', 'suggest-edits-and-comments'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Limitations & Anti-Spam', 'suggest-edits-and-comments'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="max_text_length"><?php esc_html_e('Max Highlight Length', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="number" id="max_text_length" value="<?php echo esc_attr($settings['max_text_length'] !== '' ? $settings['max_text_length'] : ''); ?>" class="regular-text" min="0" step="1" />
                            <p class="description"><?php esc_html_e('Maximum number of characters users can highlight. Leave blank or set to 0 for unlimited.', 'suggest-edits-and-comments'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="daily_limit"><?php esc_html_e('Daily Suggested Edits Limit', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="number" id="daily_limit" value="<?php echo esc_attr($settings['daily_limit'] !== '' ? $settings['daily_limit'] : ''); ?>" class="regular-text" min="0" step="1" />
                            <p class="description"><?php esc_html_e('Maximum number of suggested edits a user (or IP address) can submit per day. Leave blank or set to 0 for unlimited.', 'suggest-edits-and-comments'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Google reCAPTCHA v3', 'suggest-edits-and-comments'); ?></h2>
                <p class="description"><?php esc_html_e('Protect your suggested edits form from spam bots using invisible reCAPTCHA v3.', 'suggest-edits-and-comments'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="recaptcha_enable"><?php esc_html_e('Enable reCAPTCHA', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="checkbox" id="recaptcha_enable" <?php checked(!empty($settings['recaptcha_enable'])); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="recaptcha_site_key"><?php esc_html_e('Site Key', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>" class="regular-text" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="recaptcha_secret_key"><?php esc_html_e('Secret Key', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="password" id="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>" class="regular-text" autocomplete="new-password" /></td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Quote Box', 'suggest-edits-and-comments'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="quote_border_color"><?php esc_html_e('Border Left Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="quote_border_color" value="<?php echo esc_attr($settings['quote_border_color']); ?>" class="my-color-field" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="quote_text_color"><?php esc_html_e('Title Text Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="quote_text_color" value="<?php echo esc_attr($settings['quote_text_color']); ?>" class="my-color-field" /></td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Highlight Tooltip Button', 'suggest-edits-and-comments'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tooltip_btn_text"><?php esc_html_e('Button Text', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="text" id="tooltip_btn_text" value="<?php echo esc_attr($settings['tooltip_btn_text']); ?>" placeholder="<?php esc_attr_e('💬 Suggest Edit', 'suggest-edits-and-comments'); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Leave blank to use default text.', 'suggest-edits-and-comments'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tooltip_btn_color"><?php esc_html_e('Background Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="tooltip_btn_color" value="<?php echo esc_attr($settings['tooltip_btn_color']); ?>" class="my-color-field" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tooltip_btn_hover_color"><?php esc_html_e('Background Hover Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="tooltip_btn_hover_color" value="<?php echo esc_attr($settings['tooltip_btn_hover_color']); ?>" class="my-color-field" /></td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Submit Suggestion Button', 'suggest-edits-and-comments'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="submit_btn_text"><?php esc_html_e('Button Text', 'suggest-edits-and-comments'); ?></label></th>
                        <td>
                            <input type="text" id="submit_btn_text" value="<?php echo esc_attr($settings['submit_btn_text']); ?>" placeholder="<?php esc_attr_e('Submit', 'suggest-edits-and-comments'); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Leave blank to use default text.', 'suggest-edits-and-comments'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="submit_btn_color"><?php esc_html_e('Background Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="submit_btn_color" value="<?php echo esc_attr($settings['submit_btn_color']); ?>" class="my-color-field" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="submit_btn_hover_color"><?php esc_html_e('Background Hover Color', 'suggest-edits-and-comments'); ?></label></th>
                        <td><input type="text" id="submit_btn_hover_color" value="<?php echo esc_attr($settings['submit_btn_hover_color']); ?>" class="my-color-field" /></td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-card">
                <h2><?php esc_html_e('Uninstall Options', 'suggest-edits-and-comments'); ?></h2>
                <p class="description"><?php esc_html_e('What should happen when you delete this plugin?', 'suggest-edits-and-comments'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <input type="checkbox" id="uninstall_delete_comments" <?php checked(!empty($settings['uninstall_delete_comments'])); ?> />
                            <label for="uninstall_delete_comments"><?php esc_html_e('Delete all comments submitted via this plugin.', 'suggest-edits-and-comments'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <input type="checkbox" id="uninstall_delete_settings" <?php checked(!empty($settings['uninstall_delete_settings'])); ?> />
                            <label for="uninstall_delete_settings"><?php esc_html_e('Delete all plugin settings.', 'suggest-edits-and-comments'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="suggest-edits-save-section">
                <button type="submit" class="button button-primary button-large" id="suggest-edits-save-btn"><?php esc_html_e('Save Settings', 'suggest-edits-and-comments'); ?></button>
                <span id="suggest-edits-save-spinner" class="spinner" style="float:none; margin-left:10px;"></span>
                <div style="margin-top:15px;">
                    <span id="suggest-edits-save-message" style="font-weight:600;"></span>
                </div>
            </div>
        </form>
    </div>
<?php }

add_action('wp_ajax_seaco_save_settings', 'seaco_ajax_save_settings');
function seaco_ajax_save_settings() {
    check_ajax_referer('seaco_admin_settings_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    $settings = array(
        'excluded_roles'             => isset($_POST['excluded_roles']) && is_array($_POST['excluded_roles']) ? array_map('sanitize_text_field', wp_unslash($_POST['excluded_roles'])) : array(),
        'allowed_post_types'        => isset($_POST['allowed_post_types']) && is_array($_POST['allowed_post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_post_types'])) : array(),
        'target_selectors'          => isset($_POST['target_selectors']) ? sanitize_text_field(wp_unslash($_POST['target_selectors'])) : '.entry-content',
        'recaptcha_enable'          => isset($_POST['recaptcha_enable']) ? rest_sanitize_boolean(wp_unslash($_POST['recaptcha_enable'])) : false,
        'recaptcha_site_key'        => isset($_POST['recaptcha_site_key']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_site_key'])) : '',
        'recaptcha_secret_key'      => isset($_POST['recaptcha_secret_key']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_secret_key'])) : '',
        'max_text_length'           => (isset($_POST['max_text_length']) && $_POST['max_text_length'] !== '') ? absint(wp_unslash($_POST['max_text_length'])) : '',
        'daily_limit'               => (isset($_POST['daily_limit']) && $_POST['daily_limit'] !== '') ? absint(wp_unslash($_POST['daily_limit'])) : '',
        'quote_border_color'        => isset($_POST['quote_border_color']) ? sanitize_hex_color(wp_unslash($_POST['quote_border_color'])) : '',
        'quote_text_color'          => isset($_POST['quote_text_color']) ? sanitize_hex_color(wp_unslash($_POST['quote_text_color'])) : '',
        'tooltip_btn_text'          => isset($_POST['tooltip_btn_text']) ? sanitize_text_field(wp_unslash($_POST['tooltip_btn_text'])) : '',
        'tooltip_btn_color'         => isset($_POST['tooltip_btn_color']) ? sanitize_hex_color(wp_unslash($_POST['tooltip_btn_color'])) : '',
        'tooltip_btn_hover_color'   => isset($_POST['tooltip_btn_hover_color']) ? sanitize_hex_color(wp_unslash($_POST['tooltip_btn_hover_color'])) : '',
        'submit_btn_text'           => isset($_POST['submit_btn_text']) ? sanitize_text_field(wp_unslash($_POST['submit_btn_text'])) : '',
        'submit_btn_color'          => isset($_POST['submit_btn_color']) ? sanitize_hex_color(wp_unslash($_POST['submit_btn_color'])) : '',
        'submit_btn_hover_color'    => isset($_POST['submit_btn_hover_color']) ? sanitize_hex_color(wp_unslash($_POST['submit_btn_hover_color'])) : '',
        'uninstall_delete_comments' => isset($_POST['uninstall_delete_comments']) ? rest_sanitize_boolean(wp_unslash($_POST['uninstall_delete_comments'])) : false,
        'uninstall_delete_settings' => isset($_POST['uninstall_delete_settings']) ? rest_sanitize_boolean(wp_unslash($_POST['uninstall_delete_settings'])) : false,
    );

    update_option('seaco_settings', $settings);
    wp_send_json_success(esc_html__('Settings saved successfully!', 'suggest-edits-and-comments'));
}

/* Donate */
function seaco_donate_link_html() {
	$donate_url = 'https://chout.id.vn/donate';
	printf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="err-donate-link" aria-label="%2$s"><span>%3$s 🚀</span></a>',
		esc_url( $donate_url ),
		esc_attr__( 'Donate to support this plugin', 'suggest-edits-and-comments' ),
		esc_html__( 'Donate', 'suggest-edits-and-comments' )
	);
}