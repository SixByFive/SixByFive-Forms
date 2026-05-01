<?php
defined( 'ABSPATH' ) || exit;

// ── Honeypot ──────────────────────────────────────────────────────────────────
// Renders a hidden field. Bots fill it in. Humans never see it.
function sbf_honeypot_field(): string {
    return '<div class="sbf-hp" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;height:0;overflow:hidden;">
        <label for="sbf_website">Website</label>
        <input type="text" id="sbf_website" name="sbf_website" value="" autocomplete="off" tabindex="-1" />
    </div>';
}

function sbf_honeypot_triggered(): bool {
    return ! empty( $_POST['sbf_website'] );
}

// ── Token ─────────────────────────────────────────────────────────────────────
// A time-based token ensures the form was loaded before submission.
function sbf_generate_token(): string {
    $token = wp_hash( 'sbf_' . floor( time() / 300 ) ); // valid for 5 min window
    return $token;
}

function sbf_verify_token( string $token ): bool {
    // Accept current 5-min window and the previous one (handles boundary edge cases)
    $current  = wp_hash( 'sbf_' . floor( time() / 300 ) );
    $previous = wp_hash( 'sbf_' . floor( ( time() - 300 ) / 300 ) );
    return hash_equals( $current, $token ) || hash_equals( $previous, $token );
}

// ── Rate limiting ─────────────────────────────────────────────────────────────
// Max 3 submissions per IP per hour using WordPress transients.
function sbf_rate_limit_check(): bool {
    $ip      = sbf_get_ip();
    $key     = 'sbf_rl_' . md5( $ip );
    $count   = (int) get_transient( $key );

    if ( $count >= 3 ) {
        return false; // blocked
    }

    set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    return true; // allowed
}

// ── IP helper ─────────────────────────────────────────────────────────────────
function sbf_get_ip(): string {
    $keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ( $keys as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }

    return 'unknown';
}