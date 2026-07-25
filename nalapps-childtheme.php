<?php
/**
 * Plugin Name:       NalApps Child Theme
 * Plugin URI:        https://github.com/Eoingtilab/nalapps-childtheme
 * Description:       Create and activate a child theme for the currently active WordPress theme with one click.
 * Version:           1.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            NalApps
 * Author URI:        https://nal.la
 * Update URI:        https://app.nal.la/downloads/nalapps-child-theme/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nalapps-childtheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NalApps_Child_Theme {
	private const VERSION            = '1.0.2';
	private const PAGE_SLUG          = 'nalapps-childtheme';
	private const ACTION             = 'nalapps_create_child_theme';
	private const NONCE              = 'nalapps_childtheme_nonce';
	private const ACTIVATION_FLAG    = 'nalapps_childtheme_activation_redirect';
	private const LAST_CREATED_THEME = 'nalapps_childtheme_last_created';

	public static function init(): void {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );

		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_action_link' ) );
	}

	public static function activate(): void {
		set_transient( self::ACTIVATION_FLAG, '1', 60 );
	}

	public static function maybe_redirect_after_activation(): void {
		if ( ! get_transient( self::ACTIVATION_FLAG ) ) {
			return;
		}

		delete_transient( self::ACTIVATION_FLAG );

		if ( wp_doing_ajax() || wp_doing_cron() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		if ( ! current_user_can( 'switch_themes' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'themes.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public static function add_action_link( array $links ): array {
		if ( current_user_can( 'switch_themes' ) ) {
			array_unshift(
				$links,
				'<a href="' . esc_url( admin_url( 'themes.php?page=' . self::PAGE_SLUG ) ) . '">' .
				esc_html__( 'Create Child Theme', 'nalapps-childtheme' ) .
				'</a>'
			);
		}

		return $links;
	}

	public static function add_admin_page(): void {
		add_theme_page(
			__( 'Create Child Theme', 'nalapps-childtheme' ),
			__( 'Create Child Theme', 'nalapps-childtheme' ),
			'switch_themes',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'switch_themes' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nalapps-childtheme' ) );
		}

		$theme    = wp_get_theme();
		$is_child = $theme->parent() instanceof WP_Theme;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NalApps Child Theme', 'nalapps-childtheme' ); ?></h1>
			<p><?php esc_html_e( 'The plugin does not create a child theme merely by being activated. Review the active theme below, then click the button once.', 'nalapps-childtheme' ); ?></p>

			<table class="widefat striped" style="max-width:720px;margin:24px 0;">
				<tbody>
					<tr>
						<th scope="row" style="width:180px;"><?php esc_html_e( 'Active theme', 'nalapps-childtheme' ); ?></th>
						<td><strong><?php echo esc_html( $theme->get( 'Name' ) ); ?></strong></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Theme directory', 'nalapps-childtheme' ); ?></th>
						<td><code><?php echo esc_html( $theme->get_stylesheet() ); ?></code></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $is_child ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'The active theme is already a child theme. No action is needed.', 'nalapps-childtheme' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
					<?php submit_button( __( 'Create and Activate Child Theme', 'nalapps-childtheme' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_create(): void {
		if ( ! current_user_can( 'switch_themes' ) ) {
			wp_die( esc_html__( 'You do not have permission to create a child theme.', 'nalapps-childtheme' ) );
		}

		check_admin_referer( self::ACTION, self::NONCE );

		$theme = wp_get_theme();

		if ( $theme->parent() instanceof WP_Theme ) {
			self::redirect_with_notice( 'already-child' );
		}

		$parent_slug = $theme->get_stylesheet();
		$theme_root  = get_theme_root( $parent_slug );

		if ( ! is_dir( $theme_root ) || ! is_writable( $theme_root ) ) {
			self::redirect_with_notice( 'not-writable' );
		}

		$child_slug = self::available_child_slug( $parent_slug, $theme_root );
		$child_dir  = trailingslashit( $theme_root ) . $child_slug;

		if ( ! wp_mkdir_p( $child_dir ) ) {
			self::redirect_with_notice( 'directory-failed' );
		}

		$style_content = self::build_style_css( $theme, $parent_slug );
		$functions     = self::build_functions_php();

		if ( ! self::write_file( $child_dir . '/style.css', $style_content ) ) {
			self::cleanup_directory( $child_dir );
			self::redirect_with_notice( 'write-failed' );
		}

		if ( ! self::write_file( $child_dir . '/functions.php', $functions ) ) {
			self::cleanup_directory( $child_dir );
			self::redirect_with_notice( 'write-failed' );
		}

		self::copy_screenshot( $theme, $child_dir );
		clearstatcache( true, $child_dir );
		wp_clean_themes_cache( true );

		$created_theme = wp_get_theme( $child_slug, $theme_root );
		if ( $created_theme->errors() ) {
			self::cleanup_directory( $child_dir );
			wp_clean_themes_cache( true );
			self::redirect_with_notice( 'invalid-theme' );
		}

		set_transient(
			self::LAST_CREATED_THEME,
			array(
				'slug' => $child_slug,
				'path' => $child_dir,
			),
			120
		);

		switch_theme( $child_slug );
		self::redirect_with_notice( 'success' );
	}

	private static function available_child_slug( string $parent_slug, string $theme_root ): string {
		$base_slug = sanitize_key( $parent_slug ) . '-child';
		$slug      = $base_slug;
		$index     = 2;

		while ( is_dir( trailingslashit( $theme_root ) . $slug ) ) {
			$slug = $base_slug . '-' . $index;
			++$index;
		}

		return $slug;
	}

	private static function build_style_css( WP_Theme $theme, string $parent_slug ): string {
		$name = sanitize_text_field( $theme->get( 'Name' ) );

		return "/*\nTheme Name: {$name} Child\nDescription: Child theme for {$name}.\nAuthor: NalApps\nAuthor URI: https://nal.la\nTemplate: {$parent_slug}\nVersion: 1.0.0\nText Domain: " . sanitize_key( $parent_slug ) . "-child\n*/\n";
	}

	private static function build_functions_php(): string {
		return <<<'PHP'
<?php
/**
 * Child theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the child stylesheet only when the parent theme has not already loaded it.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$child_style_uri = get_stylesheet_uri();
		$wp_styles       = wp_styles();

		foreach ( $wp_styles->queue as $handle ) {
			if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}

			$source = $wp_styles->registered[ $handle ]->src;
			if ( is_string( $source ) && strtok( $source, '?' ) === strtok( $child_style_uri, '?' ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'nalapps-child-style',
			$child_style_uri,
			array(),
			wp_get_theme()->get( 'Version' )
		);
	},
	100
);
PHP;
	}

	private static function write_file( string $path, string $contents ): bool {
		$bytes = file_put_contents( $path, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return false !== $bytes && strlen( $contents ) === $bytes;
	}

	private static function copy_screenshot( WP_Theme $theme, string $child_dir ): void {
		foreach ( array( 'screenshot.png', 'screenshot.jpg', 'screenshot.jpeg', 'screenshot.gif', 'screenshot.webp' ) as $filename ) {
			$source = trailingslashit( $theme->get_stylesheet_directory() ) . $filename;
			if ( is_readable( $source ) ) {
				copy( $source, $child_dir . '/' . $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				break;
			}
		}
	}

	private static function cleanup_directory( string $directory ): void {
		foreach ( array( 'style.css', 'functions.php', 'screenshot.png', 'screenshot.jpg', 'screenshot.jpeg', 'screenshot.gif', 'screenshot.webp' ) as $filename ) {
			$file = $directory . '/' . $filename;
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		if ( is_dir( $directory ) ) {
			rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	private static function redirect_with_notice( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				'nalapps_childtheme_notice',
				sanitize_key( $notice ),
				admin_url( 'themes.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	public static function show_notice(): void {
		if ( empty( $_GET['nalapps_childtheme_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['nalapps_childtheme_notice'] ) );
		$map    = array(
			'success'          => array( 'success', __( 'Child theme created and activated successfully.', 'nalapps-childtheme' ) ),
			'already-child'    => array( 'info', __( 'The active theme is already a child theme.', 'nalapps-childtheme' ) ),
			'not-writable'     => array( 'error', __( 'The themes directory is not writable. Check server file permissions.', 'nalapps-childtheme' ) ),
			'directory-failed' => array( 'error', __( 'The child theme directory could not be created. Check server file permissions.', 'nalapps-childtheme' ) ),
			'write-failed'     => array( 'error', __( 'The child theme files could not be written completely. Check server file permissions.', 'nalapps-childtheme' ) ),
			'invalid-theme'    => array( 'error', __( 'The generated child theme is not valid and was not activated.', 'nalapps-childtheme' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$message = $map[ $notice ][1];

		if ( 'success' === $notice ) {
			$created = get_transient( self::LAST_CREATED_THEME );
			delete_transient( self::LAST_CREATED_THEME );

			if ( is_array( $created ) && ! empty( $created['slug'] ) ) {
				$message = sprintf(
					/* translators: %s: generated child theme directory name. */
					__( 'Child theme "%s" was created and activated successfully.', 'nalapps-childtheme' ),
					sanitize_text_field( $created['slug'] )
				);
			}
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $message )
		);
	}
}

NalApps_Child_Theme::init();
