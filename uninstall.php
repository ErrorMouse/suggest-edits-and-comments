<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Suggest_Edits_And_Comments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$seaco_settings = get_option( 'seaco_settings', array() );

global $wpdb;

// 1. Kiểm tra xem người dùng có chọn xóa bình luận góp ý không
if ( ! empty( $seaco_settings['uninstall_delete_comments'] ) ) {
    
    // Thay thế $wpdb thuần bằng hàm chuẩn của WordPress để xóa sạch Meta theo Key
    delete_metadata( 'comment', 0, 'seaco_selected_text', '', true );
    delete_metadata( 'comment', 0, 'seaco_context_prefix', '', true );
    delete_metadata( 'comment', 0, 'seaco_context_suffix', '', true );

    // Xóa toàn bộ các bình luận (góp ý) được gửi qua plugin này.
    // Lệnh $wpdb trực tiếp ở đây là bắt buộc để tối ưu hiệu suất (Bulk Delete).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( 
        $wpdb->prepare( 
            "DELETE FROM {$wpdb->comments} WHERE comment_type = %s", 
            'seaco' 
        ) 
    );
}

// 2. Kiểm tra xem người dùng có chọn xóa toàn bộ cấu hình cài đặt không
if ( ! empty( $seaco_settings['uninstall_delete_settings'] ) ) {
    delete_option( 'seaco_settings' );
    delete_option( 'widget_seaco_widget' ); // Xóa cấu hình của Widget (nếu có)
}

// Tối ưu lại bộ đệm (Clear cache) sau khi thao tác với cơ sở dữ liệu
wp_cache_flush();