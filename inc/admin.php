<?php
defined( 'ABSPATH' ) || exit;

// ── Handle actions before any output ─────────────────────────────────────────
add_action( 'admin_init', function () {
    if (
        ! isset( $_GET['page'] ) ||
        $_GET['page'] !== 'sbf-enquiries' ||
        ! current_user_can( 'manage_options' )
    ) {
        return;
    }

    global $wpdb;
    $table = sbf_get_table();

    // Status update
    if (
        isset( $_GET['sbf_action'], $_GET['id'], $_GET['_wpnonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sbf_status' )
    ) {
        $allowed = [ 'new', 'read', 'actioned', 'archived' ];
        $status  = sanitize_text_field( wp_unslash( $_GET['sbf_action'] ) );
        $id      = absint( $_GET['id'] );

        if ( in_array( $status, $allowed, true ) && $id ) {
            $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sbf-enquiries&updated=1' ) );
        exit;
    }

    // Bulk delete
    if (
        isset( $_POST['sbf_bulk'], $_POST['ids'], $_POST['_wpnonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'sbf_bulk' ) &&
        $_POST['sbf_bulk'] === 'delete'
    ) {
        $ids = array_map( 'absint', (array) $_POST['ids'] );
        foreach ( $ids as $id ) {
            $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=sbf-enquiries&deleted=' . count( $ids ) ) );
        exit;
    }
} );

// ── Register menu ─────────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_menu_page(
        'SixByFive Forms',
        'SBF Forms',
        'manage_options',
        'sbf-enquiries',
        'sbf_admin_page',
        'dashicons-email-alt',
        30
    );
    add_submenu_page(
        'sbf-enquiries',
        'Enquiries',
        'Enquiries',
        'manage_options',
        'sbf-enquiries',
        'sbf_admin_page'
    );
    add_submenu_page(
        'sbf-enquiries',
        'Settings',
        'Settings',
        'manage_options',
        'sbf-settings',
        'sbf_settings_page'
    );
} );

// ── Enqueue admin styles ──────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( string $hook ) {
    if ( ! in_array( $hook, [ 'toplevel_page_sbf-enquiries', 'sbf-forms_page_sbf-settings' ], true ) ) {
        return;
    }
    wp_enqueue_style( 'sbf-admin', SBF_URL . 'assets/css/admin.css', [], SBF_VERSION );
} );

// ── Badge count on menu ───────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    global $menu;
    $count = sbf_count_new();
    if ( $count < 1 ) return;

    foreach ( $menu as $i => $item ) {
        if ( isset( $item[2] ) && $item[2] === 'sbf-enquiries' ) {
            $menu[ $i ][0] .= ' <span class="awaiting-mod">' . $count . '</span>';
        }
    }
}, 999 );

function sbf_count_new(): int {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare( 'SELECT COUNT(*) FROM ' . sbf_get_table() . ' WHERE status = %s', 'new' )
    );
}

// ── Enquiries list page ───────────────────────────────────────────────────────
function sbf_admin_page(): void {
    global $wpdb;
    $table = sbf_get_table();

    // Filters
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    $search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $per_page      = 20;
    $current_page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $offset        = ( $current_page - 1 ) * $per_page;

    // Query
    $where  = 'WHERE 1=1';
    $params = [];

    if ( $status_filter ) {
        $where   .= ' AND status = %s';
        $params[] = $status_filter;
    }
    if ( $search ) {
        $where   .= ' AND (name LIKE %s OR email LIKE %s OR message LIKE %s)';
        $like     = '%' . $wpdb->esc_like( $search ) . '%';
        $params   = array_merge( $params, [ $like, $like, $like ] );
    }

    $total_sql = "SELECT COUNT(*) FROM {$table} {$where}";
    $total     = $params
        ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, ...$params ) )
        : (int) $wpdb->get_var( $total_sql );

    $rows_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $rows     = $params
        ? $wpdb->get_results( $wpdb->prepare( $rows_sql, ...array_merge( $params, [ $per_page, $offset ] ) ) )
        : $wpdb->get_results( $wpdb->prepare( $rows_sql, $per_page, $offset ) );

    $total_pages = ceil( $total / $per_page );

    // Status counts for tabs
    $counts = [];
    foreach ( [ 'new', 'read', 'actioned', 'archived' ] as $s ) {
        $counts[ $s ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $s ) );
    }
    $counts['all'] = array_sum( $counts );
    ?>

    <div class="wrap sbf-admin">
        <h1>SixByFive Forms — Enquiries</h1>
        <hr class="wp-header-end">

        <?php if ( ! empty( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Enquiry updated.</p></div>
        <?php endif; ?>
        <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo absint( $_GET['deleted'] ); ?> enquir<?php echo absint( $_GET['deleted'] ) === 1 ? 'y' : 'ies'; ?> deleted.</p></div>
        <?php endif; ?>

        <!-- Status tabs -->
        <ul class="subsubsub">
            <?php
            $tabs  = [ 'all' => 'All', 'new' => 'New', 'read' => 'Read', 'actioned' => 'Actioned', 'archived' => 'Archived' ];
            $links = [];
            foreach ( $tabs as $slug => $label ) {
                $url     = admin_url( 'admin.php?page=sbf-enquiries' . ( $slug !== 'all' ? '&status=' . $slug : '' ) );
                $current = ( $status_filter === $slug ) || ( $slug === 'all' && ! $status_filter );
                $class   = $current ? ' class="current"' : '';
                $links[] = "<li><a href=\"{$url}\"{$class}>{$label} <span class=\"count\">({$counts[$slug]})</span></a></li>";
            }
            echo implode( ' | ', $links );
            ?>
        </ul>

        <!-- Search -->
        <form method="get" action="">
            <input type="hidden" name="page" value="sbf-enquiries" />
            <?php if ( $status_filter ) : ?>
                <input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
            <?php endif; ?>
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, email or message…" style="width:300px" />
                <button type="submit" class="button">Search</button>
            </p>
        </form>

        <!-- Bulk form -->
        <form method="post">
            <?php wp_nonce_field( 'sbf_bulk' ); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="sbf_bulk">
                        <option value="">Bulk actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="button action">Apply</button>
                </div>
                <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total; ?> items</span>
                    <?php if ( $current_page > 1 ) : ?>
                        <a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1 ) ); ?>">‹</a>
                    <?php endif; ?>
                    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                    <?php if ( $current_page < $total_pages ) : ?>
                        <a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1 ) ); ?>">›</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped sbf-table">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox" id="sbf-check-all" /></td>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="8" style="text-align:center;padding:2rem;color:#999;">No enquiries found.</td></tr>
                <?php else : foreach ( $rows as $row ) :
                    $is_new    = $row->status === 'new';
                    $row_class = $is_new ? 'sbf-row--new' : '';
                    $nonce     = wp_create_nonce( 'sbf_status' );
                    ?>
                    <tr class="<?php echo esc_attr( $row_class ); ?>">
                        <td><input type="checkbox" name="ids[]" value="<?php echo absint( $row->id ); ?>" /></td>
                        <td>
                            <?php echo esc_html( date( 'd M Y', strtotime( $row->created_at ) ) ); ?><br>
                            <span class="sbf-time"><?php echo esc_html( date( 'H:i', strtotime( $row->created_at ) ) ); ?></span>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $row->name ); ?></strong>
                            <?php if ( $row->organisation ) : ?>
                                <br><span class="sbf-org"><?php echo esc_html( $row->organisation ); ?></span>
                            <?php endif; ?>
                            <?php if ( $row->phone ) : ?>
                                <br><span class="sbf-phone"><?php echo esc_html( $row->phone ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
                        <td><?php echo esc_html( $row->enquiry_type ); ?></td>
                        <td>
                            <span class="sbf-message-preview"><?php echo esc_html( wp_trim_words( $row->message, 12 ) ); ?></span>
                            <details class="sbf-details">
                                <summary>Read more</summary>
                                <p><?php echo nl2br( esc_html( $row->message ) ); ?></p>
                            </details>
                        </td>
                        <td>
                            <span class="sbf-badge sbf-badge--<?php echo esc_attr( $row->status ); ?>">
                                <?php echo esc_html( ucfirst( $row->status ) ); ?>
                            </span>
                        </td>
                        <td class="sbf-actions">
                            <?php foreach ( [ 'read' => 'Mark read', 'actioned' => 'Actioned', 'archived' => 'Archive' ] as $s => $label ) :
                                if ( $row->status === $s ) continue;
                                $url = add_query_arg( [ 'sbf_action' => $s, 'id' => $row->id, '_wpnonce' => $nonce ], admin_url( 'admin.php?page=sbf-enquiries' ) );
                                ?>
                                <a href="<?php echo esc_url( $url ); ?>" class="sbf-action-link"><?php echo esc_html( $label ); ?></a>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tr>
                    <th><label for="sbf_github_user">GitHub username</label></th>
                    <td>
                        <input type="text" id="sbf_github_user" name="sbf_github_user" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_github_user', 'SixByFive' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="sbf_github_repo">GitHub repository</label></th>
                    <td>
                        <input type="text" id="sbf_github_repo" name="sbf_github_repo" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_github_repo', 'SixByFive-Forms' ) ); ?>" />
                        <p class="description">Repository name only, not the full URL.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sbf_github_token">GitHub token</label></th>
                    <td>
                        <input type="password" id="sbf_github_token" name="sbf_github_token" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_github_token', '' ) ); ?>" />
                        <p class="description">Only required for private repositories. Generate at GitHub → Settings → Developer settings → Personal access tokens.</p>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <script>
    document.getElementById('sbf-check-all')?.addEventListener('change', function() {
        document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
    });
    </script>
    <?php
}

// ── Settings page ─────────────────────────────────────────────────────────────
function sbf_settings_page(): void {
    if (
        isset( $_POST['sbf_settings_nonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbf_settings_nonce'] ) ), 'sbf_settings' )
    ) {
        update_option( 'sbf_notify_email', sanitize_email( wp_unslash( $_POST['sbf_notify_email'] ?? '' ) ) );
        update_option( 'sbf_from_name',    sanitize_text_field( wp_unslash( $_POST['sbf_from_name']    ?? '' ) ) );
        update_option( 'sbf_from_email',   sanitize_email( wp_unslash( $_POST['sbf_from_email']   ?? '' ) ) );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }
    ?>
    <div class="wrap sbf-admin">
        <h1>SixByFive Forms — Settings</h1>
        <form method="post" style="max-width:600px;margin-top:1.5rem;">
            <?php wp_nonce_field( 'sbf_settings', 'sbf_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="sbf_notify_email">Notification email</label></th>
                    <td>
                        <input type="email" id="sbf_notify_email" name="sbf_notify_email" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_notify_email', get_option( 'admin_email' ) ) ); ?>" />
                        <p class="description">Where new enquiry notifications are sent.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sbf_from_name">From name</label></th>
                    <td>
                        <input type="text" id="sbf_from_name" name="sbf_from_name" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_from_name', get_bloginfo( 'name' ) ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="sbf_from_email">From email</label></th>
                    <td>
                        <input type="email" id="sbf_from_email" name="sbf_from_email" class="regular-text"
                            value="<?php echo esc_attr( get_option( 'sbf_from_email', get_option( 'admin_email' ) ) ); ?>" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save settings</button>
            </p>
        </form>
        <hr style="margin-top:2rem;">
        <h2 style="font-size:1rem;">Shortcode usage</h2>
        <p>Place this shortcode anywhere on a page:</p>
        <code>[sbf_form]</code>
        <p>With options:</p>
        <code>[sbf_form title="Get in touch" subtitle="We'll respond within 24 hours." button_text="Send message"]</code>
    </div>
    <?php
}
