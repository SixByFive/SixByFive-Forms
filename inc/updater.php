<?php
defined( 'ABSPATH' ) || exit;

class SBF_GitHub_Updater {

    private string  $plugin_file;
    private string  $plugin_slug;
    private string  $version;
    private string  $github_user;
    private string  $github_repo;
    private ?string $token;
    private ?object $release = null;

    public function __construct(
        string $plugin_file,
        string $github_user,
        string $github_repo,
        string $version,
        ?string $token = null
    ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename( $plugin_file );
        $this->version     = $version;
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->token       = $token;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection',             [ $this, 'fix_source_dir' ], 10, 4 );
        add_filter( 'http_request_args',                     [ $this, 'inject_token' ], 10, 2 );
        add_action( 'admin_init',                            [ $this, 'maybe_flush_cache' ] );
    }

    // ── Fetch latest release from GitHub ──────────────────────────────────────
    private function get_release(): ?object {
        if ( $this->release !== null ) {
            return $this->release;
        }

        $cache_key   = 'sbf_gh_release_' . md5( $this->github_user . $this->github_repo );
        $force_check = isset( $_GET['force-check'] );

        if ( ! $force_check ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) {
                $this->release = $cached;
                return $this->release;
            }
        }

        $url  = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $args = [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ],
        ];

        if ( $this->token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $data->tag_name ) ) {
            return null;
        }

        set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
        $this->release = $data;

        return $this->release;
    }

    private function clean_version( string $tag ): string {
        return ltrim( $tag, 'vV' );
    }

    public function inject_token( array $args, string $url ): array {
        if ( $this->token && str_contains( $url, 'api.github.com/repos/' . $this->github_user . '/' . $this->github_repo ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        return $args;
    }

    public function flush_cache(): void {
        delete_transient( 'sbf_gh_release_' . md5( $this->github_user . $this->github_repo ) );
        $this->release = null;
    }

    public function maybe_flush_cache(): void {
        if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
            $this->flush_cache();
            // Also clear WordPress's own update cache so it re-runs the check
            delete_site_transient( 'update_plugins' );
        }
    }

    // ── Inject into WordPress update transient ────────────────────────────────
    public function check_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_release();

        if ( ! $release ) {
            return $transient;
        }

        $new_version = $this->clean_version( $release->tag_name );

        if ( version_compare( $new_version, $this->version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $new_version,
                'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'     => $release->zipball_url,
                'icons'       => [
                    '1x' => plugins_url( 'assets/images/sbf-forms-icon-128.png', $this->plugin_file ),
                    '2x' => plugins_url( 'assets/images/sbf-forms-logo.png', $this->plugin_file ),
                ],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    // ── Populate the plugin info popup ────────────────────────────────────────
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ( $args->slug ?? '' ) !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_release();

        if ( ! $release ) {
            return $result;
        }

        return (object) [
            'name'          => 'SixByFive Forms',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $this->clean_version( $release->tag_name ),
            'author'        => '<a href="https://dev.sixbyfive.co.uk">SixByFive</a>',
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'requires'      => '6.4',
            'tested'        => '6.9.4',
            'last_updated'  => $release->published_at ?? '',
            'download_link' => $release->zipball_url,
            'sections'      => [
                'description' => 'Enquiry form with custom DB storage, admin screen, email notifications and spam protection.',
                'changelog'   => ! empty( $release->body )
                    ? '<pre>' . esc_html( $release->body ) . '</pre>'
                    : '<p>See <a href="https://github.com/' . esc_attr( $this->github_user ) . '/' . esc_attr( $this->github_repo ) . '/releases">GitHub releases</a>.</p>',
            ],
        ];
    }

    // ── Rename GitHub's extracted zip folder to match the plugin slug ─────────
    // GitHub zips extract to e.g. username-repo-abc123/ — WordPress needs the
    // folder to match the plugin slug exactly.
    public function fix_source_dir(
        string $source,
        string $remote_source,
        object $upgrader,
        array  $hook_extra
    ): string {
        if ( ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_slug ) {
            return $source;
        }

        global $wp_filesystem;

        $plugin_folder = trailingslashit( $remote_source ) . dirname( $this->plugin_slug ) . '/';

        if ( $source !== $plugin_folder && $wp_filesystem->move( $source, $plugin_folder ) ) {
            return $plugin_folder;
        }

        return $source;
    }
}