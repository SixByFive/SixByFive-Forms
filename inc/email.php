<?php
defined( 'ABSPATH' ) || exit;

function sbf_send_notifications( array $data ): void {
    sbf_email_admin( $data );
    sbf_email_enquirer( $data );
}

// ── Admin notification ────────────────────────────────────────────────────────
function sbf_email_admin( array $data ): void {
    $to      = get_option( 'sbf_notify_email', get_option( 'admin_email' ) );
    $subject = sprintf(
        '[%s] New enquiry from %s',
        get_bloginfo( 'name' ),
        sanitize_text_field( $data['name'] )
    );

    $rows = [
        'Name'          => $data['name'],
        'Email'         => $data['email'],
        'Phone'         => $data['phone'] ?: '—',
        'Organisation'  => $data['organisation'] ?: '—',
        'Enquiry type'  => $data['enquiry_type'] ?: '—',
        'Message'       => $data['message'],
        'Submitted'     => current_time( 'mysql' ),
        'IP address'    => $data['ip_address'],
    ];

    $body  = sbf_email_header( 'New Enquiry Received' );
    $body .= '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:sans-serif;font-size:14px;">';

    foreach ( $rows as $label => $value ) {
        $bg    = ( $label === 'Message' ) ? '#f9f9f9' : '#ffffff';
        $val   = nl2br( esc_html( $value ) );
        $body .= "<tr style=\"border-bottom:1px solid #eee;\">
            <td style=\"padding:12px 16px;font-weight:600;color:#0D1B2A;width:160px;vertical-align:top;background:{$bg}\">{$label}</td>
            <td style=\"padding:12px 16px;color:#333;vertical-align:top;background:{$bg}\">{$val}</td>
        </tr>";
    }

    $body .= '</table>';

    $admin_url = admin_url( 'admin.php?page=sbf-enquiries' );
    $body .= "<p style=\"margin:24px 0 0;text-align:center;\">
        <a href=\"{$admin_url}\" style=\"display:inline-block;padding:12px 28px;background:#D4952A;color:#0D1B2A;text-decoration:none;border-radius:100px;font-weight:700;font-size:13px;\">View in dashboard</a>
    </p>";

    $body .= sbf_email_footer();

    wp_mail( $to, $subject, $body, sbf_email_headers() );
}

// ── Enquirer confirmation ─────────────────────────────────────────────────────
function sbf_email_enquirer( array $data ): void {
    if ( empty( $data['email'] ) ) {
        return;
    }

    $to      = $data['email'];
    $subject = sprintf( 'We\'ve received your enquiry — %s', get_bloginfo( 'name' ) );

    $body  = sbf_email_header( 'Thank you for getting in touch.' );
    $body .= '<p style="font-family:sans-serif;font-size:15px;color:#444;line-height:1.7;margin:0 0 16px;">
        Hi ' . esc_html( $data['name'] ) . ',
    </p>
    <p style="font-family:sans-serif;font-size:15px;color:#444;line-height:1.7;margin:0 0 16px;">
        We\'ve received your message and a member of our team will be in touch shortly.
    </p>
    <p style="font-family:sans-serif;font-size:15px;color:#444;line-height:1.7;margin:0 0 32px;">
        If your matter is urgent, please email us directly at
        <a href="mailto:info@christiescaregroup.co.uk" style="color:#D4952A;">info@christiescaregroup.co.uk</a>.
    </p>';

    $body .= '<div style="border-left:3px solid #D4952A;padding:12px 20px;background:#fdf8f0;margin:0 0 32px;">
        <p style="font-family:sans-serif;font-size:13px;color:#888;margin:0 0 4px;text-transform:uppercase;letter-spacing:0.1em;">Your message</p>
        <p style="font-family:sans-serif;font-size:14px;color:#333;margin:0;line-height:1.7;">' . nl2br( esc_html( $data['message'] ) ) . '</p>
    </div>';

    $body .= sbf_email_footer();

    wp_mail( $to, $subject, $body, sbf_email_headers() );
}

// ── Shared helpers ────────────────────────────────────────────────────────────
function sbf_email_headers(): array {
    $from_name  = get_option( 'sbf_from_name', get_bloginfo( 'name' ) );
    $from_email = get_option( 'sbf_from_email', get_option( 'admin_email' ) );

    return [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
    ];
}

function sbf_email_header( string $heading ): string {
    $site = get_bloginfo( 'name' );
    return "<!DOCTYPE html><html><body style=\"margin:0;padding:0;background:#f4f4f4;\">
    <table cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;margin:32px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);\">
    <tr><td style=\"background:#0D1B2A;padding:28px 32px;\">
        <p style=\"margin:0;font-family:sans-serif;font-size:11px;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#D4952A;\">{$site}</p>
        <h1 style=\"margin:8px 0 0;font-family:sans-serif;font-size:22px;font-weight:700;color:#F9F6F0;line-height:1.2;\">{$heading}</h1>
    </td></tr>
    <tr><td style=\"padding:32px;\">";
}

function sbf_email_footer(): string {
    $site = get_bloginfo( 'name' );
    $url  = get_home_url();
    return "</td></tr>
    <tr><td style=\"background:#f9f9f9;padding:20px 32px;border-top:1px solid #eee;\">
        <p style=\"margin:0;font-family:sans-serif;font-size:12px;color:#999;text-align:center;\">
            &copy; " . date( 'Y' ) . " <a href=\"{$url}\" style=\"color:#D4952A;text-decoration:none;\">{$site}</a> &nbsp;·&nbsp; Regulated by Ofsted
        </p>
    </td></tr>
    </table></body></html>";
}