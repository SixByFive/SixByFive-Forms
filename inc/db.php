<?php
defined( 'ABSPATH' ) || exit;

function sbf_install() {
    global $wpdb;

    $table   = $wpdb->prefix . SBF_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id          BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status      VARCHAR(20)  NOT NULL DEFAULT 'new',
        name        VARCHAR(150) NOT NULL DEFAULT '',
        email       VARCHAR(255) NOT NULL DEFAULT '',
        phone       VARCHAR(50)  NOT NULL DEFAULT '',
        organisation VARCHAR(255) NOT NULL DEFAULT '',
        enquiry_type VARCHAR(100) NOT NULL DEFAULT '',
        message     TEXT         NOT NULL,
        ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
        user_agent  VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY status (status),
        KEY created_at (created_at),
        KEY email (email(100))
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    add_option( 'sbf_db_version', SBF_VERSION );
}

function sbf_get_table(): string {
    global $wpdb;
    return $wpdb->prefix . SBF_TABLE;
}