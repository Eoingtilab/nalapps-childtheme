<?php
/**
 * Plugin Name:       NalApps Child Theme
 * Plugin URI:        https://github.com/Eoingtilab/nalapps-childtheme
 * Description:       Create and activate a child theme for the currently active WordPress theme with one click.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            NalApps
 * Author URI:        https://nal.la
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nalapps-childtheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NalApps_Child_Theme {
	private const PAGE_SLUG = 'nalapps-childtheme';
	private const ACTION    = 'nalapps_create_child_theme';
	private const NONCE     = 'nalapps_childtheme_nonce';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
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
			<p><?php esc_html_e( 'Create and activate a child theme for the currently active theme.', 'nalapps-childtheme' ); ?></p>

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

		$parent_slug = sanitize_key( $theme->get_stylesheet() );
		$child_slug  = self::available_child_slug( $parent_slug );
		$child_dir   = trailingslashit( get_theme_root() ) . $child_slug;

		if ( ! wp_mkdir_p( $child_dir ) ) {
			self::redirect_with_notice( 'directory-failed' );
		}

		$style_content = self::build_style_css( $theme, $parent_slug );
		$functions     = self::build_functions_php();

		if ( false === file_put_contents( $child_dir . '/style.css', $style_content, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::cleanup_directory( $child_dir );
			self::redirect_with_notice( 'write-failed' );
		}

		if ( false === file_put_contents( $child_dir . '/functions.php', $functions, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::cleanup_directory( $child_dir );
			self::redirect_with_notice( 'write-failed' );
		}

		self::copy_screenshot( $theme, $child_dir );
		wp_clean_themes_cache();

		$created_theme = wp_get_theme( $child_slug );
		if ( $created_theme->errors() ) {
			self::cleanup_directory( $child_dir );
			wp_clean_themes_cache();
			self::redirect_with_notice( 'invalid-theme' );
		}

		switch_theme( $child_slug );
		self::redirect_with_notice( 'success' );
	}

	private static function available_child_slug( string $parent_slug ): string {
		$base_slug = $parent_slug . '-child';
		$slug      = $base_slug;
		$index     = 2;

		while ( is_dir( trailingslashit( get_theme_root() ) . $slug ) ) {
			$slug = $base_slug . '-' . $index;
			++$index;
		}

		return $slug;
	}

	private static function build_style_css( WP_Theme $theme, string $parent_slug ): string {
		$name = sanitize_text_field( $theme->get( 'Name' ) );

		return "/*\nTheme Name: {$name} Child\nTemplate: {$parent_slug}\nVersion: 1.0.0\nText Domain: {$parent_slug}-child\n*/\n";
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

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'parent-style',
			get_template_directory_uri() . '/style.css',
			array(),
			wp_get_theme( get_template() )->get( 'Version' )
		);

		wp_enqueue_style(
			'child-style',
			get_stylesheet_uri(),
			array( 'parent-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}
);
PHP;
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
			'directory-failed' => array( 'error', __( 'The child theme directory could not be created. Check server file permissions.', 'nalapps-childtheme' ) ),
			'write-failed'     => array( 'error', __( 'The child theme files could not be written. Check server file permissions.', 'nalapps-childtheme' ) ),
			'invalid-theme'    => array( 'error', __( 'The generated child theme is not valid and was not activated.', 'nalapps-childtheme' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);
	}
}

NalApps_Child_Theme::init();
