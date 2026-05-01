<?php
defined( 'ABSPATH' ) || exit;

// ── Shortcode & enqueue ───────────────────────────────────────────────────────
add_shortcode( 'sbf_form', 'sbf_render_form' );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'sbf-form',
        SBF_URL . 'assets/css/form.css',
        [],
        SBF_VERSION
    );
    wp_enqueue_script(
        'sbf-form',
        SBF_URL . 'assets/js/form.js',
        [],
        SBF_VERSION,
        [ 'strategy' => 'defer' ]
    );
    wp_localize_script( 'sbf-form', 'SBF', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'sbf_submit' ),
        'strings'  => [
            'sending'  => 'Sending…',
            'success'  => 'Thank you — we\'ll be in touch shortly.',
            'error'    => 'Something went wrong. Please try again or email us directly.',
            'required' => 'Please fill in all required fields.',
            'invalid'  => 'Please enter a valid email address.',
        ],
    ] );
} );

// ── Form output ───────────────────────────────────────────────────────────────
function sbf_render_form( array $atts = [] ): string {
    $atts = shortcode_atts( [
        'title'       => 'Make an enquiry',
        'subtitle'    => 'We\'ll get back to you as soon as possible.',
        'button_text' => 'Send enquiry',
    ], $atts, 'sbf_form' );

    ob_start();
    ?>
    <div class="sbf-wrap" id="sbf-form-wrap">

        <?php if ( $atts['title'] ) : ?>
            <h3 class="sbf-title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <?php endif; ?>

        <?php if ( $atts['subtitle'] ) : ?>
            <p class="sbf-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
        <?php endif; ?>

        <form class="sbf-form" id="sbf-form" novalidate>

            <?php echo sbf_honeypot_field(); ?>

            <input type="hidden" name="sbf_token" value="<?php echo esc_attr( sbf_generate_token() ); ?>" />
            <input type="hidden" name="action" value="sbf_submit" />
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sbf_submit' ) ); ?>" />

            <div class="sbf-row sbf-row--2">
                <div class="sbf-field">
                    <label for="sbf_name">Full name <span class="sbf-req">*</span></label>
                    <input type="text" id="sbf_name" name="sbf_name" required autocomplete="name" placeholder="Jane Smith" />
                </div>
                <div class="sbf-field">
                    <label for="sbf_email">Email address <span class="sbf-req">*</span></label>
                    <input type="email" id="sbf_email" name="sbf_email" required autocomplete="email" placeholder="jane@example.com" />
                </div>
            </div>

            <div class="sbf-row sbf-row--2">
                <div class="sbf-field">
                    <label for="sbf_phone">Phone number</label>
                    <input type="tel" id="sbf_phone" name="sbf_phone" autocomplete="tel" placeholder="+44 7700 000000" />
                </div>
                <div class="sbf-field">
                    <label for="sbf_organisation">Organisation</label>
                    <input type="text" id="sbf_organisation" name="sbf_organisation" autocomplete="organization" placeholder="Local authority / company" />
                </div>
            </div>

            <div class="sbf-field">
                <label for="sbf_enquiry_type">Enquiry type <span class="sbf-req">*</span></label>
                <select id="sbf_enquiry_type" name="sbf_enquiry_type" required>
                    <option value="">— Please select —</option>
                    <option value="Placement Enquiry">Placement enquiry</option>
                    <option value="Join Our Team">Join our team</option>
                    <option value="Family / Parent">Family / parent enquiry</option>
                    <option value="Commissioning Authority">Commissioning authority</option>
                    <option value="Media / Press">Media / press</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="sbf-field">
                <label for="sbf_message">Message <span class="sbf-req">*</span></label>
                <textarea id="sbf_message" name="sbf_message" required rows="5" placeholder="Tell us how we can help…"></textarea>
            </div>

            <div class="sbf-field sbf-field--consent">
                <label class="sbf-checkbox">
                    <input type="checkbox" name="sbf_consent" required />
                    <span>I agree to my details being stored and used to respond to this enquiry. <a href="/privacy-policy" target="_blank">Privacy policy</a>.</span>
                </label>
            </div>

            <div class="sbf-submit-row">
                <button type="submit" class="sbf-btn" id="sbf-submit">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <div class="sbf-status" id="sbf-status" role="alert" aria-live="polite"></div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ── AJAX handler ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_sbf_submit',        'sbf_handle_submission' );
add_action( 'wp_ajax_nopriv_sbf_submit', 'sbf_handle_submission' );

function sbf_handle_submission(): void {
    // Nonce
    if ( ! check_ajax_referer( 'sbf_submit', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
    }

    // Honeypot
    if ( sbf_honeypot_triggered() ) {
        wp_send_json_success(); // silently discard
    }

    // Token
    $token = sanitize_text_field( wp_unslash( $_POST['sbf_token'] ?? '' ) );
    if ( ! sbf_verify_token( $token ) ) {
        wp_send_json_error( [ 'message' => 'Form expired. Please refresh and try again.' ], 400 );
    }

    // Rate limit
    if ( ! sbf_rate_limit_check() ) {
        wp_send_json_error( [ 'message' => 'Too many submissions. Please try again later.' ], 429 );
    }

    // Sanitise & validate
    $data = sbf_sanitise_submission();

    if ( is_wp_error( $data ) ) {
        wp_send_json_error( [ 'message' => $data->get_error_message() ], 422 );
    }

    // Save
    $id = sbf_save_submission( $data );

    if ( ! $id ) {
        wp_send_json_error( [ 'message' => 'Could not save enquiry. Please try again.' ], 500 );
    }

    // Notify
    sbf_send_notifications( $data );

    wp_send_json_success( [ 'message' => 'Thank you — we\'ll be in touch shortly.' ] );
}

// ── Sanitise ──────────────────────────────────────────────────────────────────
function sbf_sanitise_submission(): array|WP_Error {
    $name         = sanitize_text_field( wp_unslash( $_POST['sbf_name']         ?? '' ) );
    $email        = sanitize_email( wp_unslash( $_POST['sbf_email']        ?? '' ) );
    $phone        = sanitize_text_field( wp_unslash( $_POST['sbf_phone']        ?? '' ) );
    $organisation = sanitize_text_field( wp_unslash( $_POST['sbf_organisation'] ?? '' ) );
    $enquiry_type = sanitize_text_field( wp_unslash( $_POST['sbf_enquiry_type'] ?? '' ) );
    $message      = sanitize_textarea_field( wp_unslash( $_POST['sbf_message']  ?? '' ) );
    $consent      = ! empty( $_POST['sbf_consent'] );

    if ( empty( $name ) ) {
        return new WP_Error( 'validation', 'Please enter your name.' );
    }
    if ( ! is_email( $email ) ) {
        return new WP_Error( 'validation', 'Please enter a valid email address.' );
    }
    if ( empty( $enquiry_type ) ) {
        return new WP_Error( 'validation', 'Please select an enquiry type.' );
    }
    if ( empty( $message ) ) {
        return new WP_Error( 'validation', 'Please enter a message.' );
    }
    if ( ! $consent ) {
        return new WP_Error( 'validation', 'Please confirm you agree to our privacy policy.' );
    }

    return [
        'name'         => $name,
        'email'        => $email,
        'phone'        => $phone,
        'organisation' => $organisation,
        'enquiry_type' => $enquiry_type,
        'message'      => $message,
        'ip_address'   => sbf_get_ip(),
        'user_agent'   => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
    ];
}

// ── Save to DB ────────────────────────────────────────────────────────────────
function sbf_save_submission( array $data ): int|false {
    global $wpdb;

    $result = $wpdb->insert(
        sbf_get_table(),
        [
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'organisation' => $data['organisation'],
            'enquiry_type' => $data['enquiry_type'],
            'message'      => $data['message'],
            'ip_address'   => $data['ip_address'],
            'user_agent'   => $data['user_agent'],
            'status'       => 'new',
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    return $result ? (int) $wpdb->insert_id : false;
}