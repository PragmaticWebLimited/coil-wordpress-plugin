<?php
declare(strict_types=1);
/**
 * Coil admin screens and options.
 */

namespace Coil\Admin;

use const \Coil\PLUGIN_VERSION;
use \Coil\Gating;

/**
 * Customise the environment where we want to show the Coil metabox.
 *
 * @return void
 */
function load_metaboxes() : void {
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\add_metabox' );
}

/**
 * Add metabox to the content editing screen.
 *
 * @return void
 */
function add_metabox() : void {
	$show_metabox = false;

	if ( ! function_exists( '\is_gutenberg_page' ) ) {
		// Show meta box if Gutenberg not installed.
		$show_metabox = true;
	} elseif ( ! \use_block_editor_for_post( $GLOBALS['post'] ) ) {
		// Show meta box if post is NOT using Gutenberg.
		$show_metabox = true;
	}

	if ( ! $show_metabox ) {
		return;
	}

	add_meta_box(
		'coil',
		__( 'Web Monetization - Coil', 'coil-monetize-content' ),
		__NAMESPACE__ . '\render_coil_metabox',
		[ 'page', 'post' ],
		'side',
		'high'
	);
}

/**
 * Render the Coil metabox.
 *
 * @return void
 */
function render_coil_metabox() : void {
	global $post;

	// Explicitly use the post gating option to render whatever is saved on this post,
	// instead of what is saved globally. This is used to output the correct meta box
	// option.
	$post_gating   = Gating\get_post_gating( $post->ID );
	$use_gutenberg = function_exists( '\use_block_editor_for_post' ) && use_block_editor_for_post( $post );
	$settings      = Gating\get_monetization_setting_types( true );

	if ( $use_gutenberg ) {
		$settings['gate-tagged-blocks'] = esc_html__( 'Split Content', 'coil-monetize-content' );
	}

	do_action( 'coil_before_render_metabox', $settings );
	?>

	<fieldset>
		<legend>
			<?php
			if ( $use_gutenberg ) {
				esc_html_e( 'Set the type of monetization for the article. Note: If "Split Content" selected, you will need to save the article and reload the editor to view the options at block level.', 'coil-monetize-content' );
			} else {
				esc_html_e( 'Set the type of monetization for the article.', 'coil-monetize-content' );
			}
			?>
		</legend>

		<?php foreach ( $settings as $option => $name ) : ?>
			<label for="<?php echo esc_attr( $option ); ?>">
				<input type="radio" name="coil_monetize_post_status" id="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( $option ); ?>" <?php checked( $post_gating, $option ); ?>/>
				<?php echo esc_html( $name ); ?>
				<br />
			</label>
		<?php endforeach; ?>
	</fieldset>

	<?php
	wp_nonce_field( 'coil_metabox_nonce_action', 'coil_metabox_nonce' );

	do_action( 'coil_after_render_metabox' );
}

/**
 * Maybe save the Coil metabox data on content save.
 *
 * @param int $post_id The ID of the post being saved.
 *
 * @return void
 */
function maybe_save_post_metabox( int $post_id ) : void {

	if ( ! current_user_can( 'edit_post', $post_id ) || empty( $_REQUEST['coil_metabox_nonce'] ) ) {
		return;
	}

	// Check the nonce.
	check_admin_referer( 'coil_metabox_nonce_action', 'coil_metabox_nonce' );

	$do_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
	if ( $do_autosave || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$post_gating = sanitize_text_field( $_REQUEST['coil_monetize_post_status'] ?? '' );

	if ( $post_gating ) {
		Gating\set_post_gating( $post_id, $post_gating );
	} else {
		delete_post_meta( $post_id, '_coil_monetize_post_status' );
	}
}

/**
 * Fires after a term has been updated, but before the term cache has been cleaned.
 *
 * @param int $term_id Term ID.
 * @return void
 */
function maybe_save_term_meta( int $term_id ) : void {

	if ( ! current_user_can( 'edit_post', $term_id ) || empty( $_REQUEST['term_gating_nonce'] ) ) {
		return;
	}

	// Check the nonce.
	check_admin_referer( 'coil_term_gating_nonce_action', 'term_gating_nonce' );

	$term_gating = sanitize_text_field( $_REQUEST['coil_monetize_term_status'] ?? '' );

	if ( $term_gating ) {
		Gating\set_term_gating( $term_id, $term_gating );
	} else {
		delete_term_monetization_meta( $term_id );
	}

}

/**
 * Deletes any term meta when a term is deleted.
 *
 * @param int $term The term id.
 * @return void
 */
function delete_term_monetization_meta( $term_id ) {
	if ( empty( $term_id ) ) {
		return;
	}
	delete_term_meta( $term_id, '_coil_monetize_term_status' );
}

/**
 * Add action links to the list on the plugins screen.
 *
 * @param array $links An array of action links.
 *
 * @return array $links Updated array of action links.
 */
function add_plugin_action_links( array $links ) : array {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $links;
	}

	$action_links = [
		'settings' => '<a href="' . add_query_arg( [ 'page' => 'coil' ], admin_url( 'admin.php' ) ) . '" aria-label="' . esc_attr__( 'Settings for Coil', 'coil-monetize-content' ) . '">' . esc_attr__( 'Settings', 'coil-monetize-content' ) . '</a>',
	];

	return array_merge( $action_links, $links );
}

/**
 * Add extra information to the meta section on the list on the plugins screen.
 *
 * @param string[] $metadata Plugin metadata.
 * @param string   $file     Path to this plugin's main file. Used to identify which row we're in.
 *
 * @return array $metadata Updated array of plugin meta.
 */
function add_plugin_meta_link( array $metadata, string $file ) : array {

	if ( $file !== 'coil-monetize-content/plugin.php' ) {
		return $metadata;
	}

	$row_meta = [
		'community' => '<a href="' . esc_url( 'https://wordpress.org/support/plugin/coil-monetize-content/' ) . '">' . esc_html__( 'Support forum', 'coil-monetize-content' ) . '</a>',
	];

	return array_merge( $metadata, $row_meta );
}

/**
 * Adds admin body class for the Coil settings screen.
 *
 * @param string $classes CSS classes.
 *
 * @return string $classes Updated CSS classes.
 */
function add_admin_body_class( string $classes ) : string {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return $classes;
	}

	if ( $screen->id === 'toplevel_page_coil' ) {
		$classes = ' coil ';
	}

	return $classes;
}

/**
 * Load admin-only CSS/JS.
 *
 * @return void
 */
function load_admin_assets() : void {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$load_on_screens = [
		'toplevel_page_coil',
	];

	if ( ! in_array( $screen->id, $load_on_screens, true ) ) {
		return;
	}

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_style(
		'coil_admin',
		esc_url_raw( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin/coil' . $suffix . '.css' ),
		[],
		PLUGIN_VERSION
	);
}

/**
 * Get a message saved in the customizer messages section. If no message is set,
 * a default value is returned.
 *
 * @param string $message_id The id of the message control_setting defined in the customizer
 * @param bool $get_default If true, will output the default message instead of getting the cutomizer setting.
 * @return string
 */
function get_customizer_messaging_text( $message_id, $get_default = false ) : string {

	// Set up message defaults.
	$defaults = [
		'coil_unsupported_message'        => __( 'Not using supported browser and extension, this is how to access / get COIL', 'coil-monetize-content' ),
		'coil_unable_to_verify_message'   => __( 'You need a valid Coil account in order to see content, here\'s how..', 'coil-monetize-content' ),
		'coil_voluntary_donation_message' => __( 'This site is monetized using Coil.  We ask for your help to pay for our time in creating this content for you.  Here\'s how...', 'coil-monetize-content' ),
		'coil_verifying_status_message'   => __( 'Verifying Web Monetization status. Please wait...', 'coil-monetize-content' ),
		'coil_partial_gating_message'     => __( 'This content is for Coil subscribers only. To access, subscribe to Coil and install the browser extension.', 'coil-monetize-content' ),
	];

	// Get the message from the customizer.
	$customizer_setting = get_theme_mod( $message_id );

	/**
	 * If an empty string is saved in the customizer,
	 * get_theme_mod returns an empty string instead of the default
	 * setting whcih is defined as an optional second parameter.
	 * This is recognized as a bug (wontfix) in WordPress Core.
	 *
	 * @see https://core.trac.wordpress.org/ticket/28637
	 */
	if ( true === $get_default || empty( $customizer_setting ) || false === $customizer_setting ) {
		$customizer_setting = isset( $defaults[ $message_id ] ) ? $defaults[ $message_id ] : '';
	}
	return $customizer_setting;
}

/**
 * Add custom section to the customizer to allow Coil messaging
 * to be set.
 *
 * @param \WP_Customize_Manager $wp_customize
 * @link http://codex.wordpress.org/Theme_Customization_API
 * @return void
 */
function coil_add_customizer_options( $wp_customize ) : void {

	// Add a new panel section.
	$coil_customizer_panel_id = 'coil_customizer_settings_panel';

	$wp_customize->add_panel(
		$coil_customizer_panel_id,
		[
			'title'      => __( 'Coil Settings', 'coil-monetize-content' ),
			'capability' => apply_filters( 'coil_settings_capability', 'manage_options' ),
		]
	);

	// Messaging section.
	$messaging_section_id = 'coil_customizer_section_messaging';

	$wp_customize->add_section(
		$messaging_section_id,
		[
			'title' => __( 'Messaging', 'coil-monetize-content' ),
			'panel' => $coil_customizer_panel_id,
		]
	);

	// Incorrect browser setup message (textarea 1).
	$incorrect_browser_setup_message_id = 'coil_unsupported_message';

	$wp_customize->add_setting(
		$incorrect_browser_setup_message_id,
		[
			'capability'        => apply_filters( 'coil_settings_capability', 'manage_options' ),
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]
	);

	$wp_customize->add_control(
		$incorrect_browser_setup_message_id,
		[
			'type'        => 'textarea',
			'label'       => __( 'Incorrect browser setup message', 'coil-monetize-content' ),
			'section'     => $messaging_section_id,
			'description' => __( 'This message is shown when content is set to be subscriber-only, and visitor either isn\'t using a supported browser, or doesn\'t have the browser extension installed correctly.', 'coil-monetize-content' ),
			'input_attrs' => [
				'placeholder' => get_customizer_messaging_text( $incorrect_browser_setup_message_id, true ),
			],
		]
	);

	// Invalid Web Monetization message (textarea 2).
	$invalid_web_monetization_message_id = 'coil_unable_to_verify_message';

	$wp_customize->add_setting(
		$invalid_web_monetization_message_id,
		[
			'capability'        => apply_filters( 'coil_settings_capability', 'manage_options' ),
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]
	);

	$wp_customize->add_control(
		$invalid_web_monetization_message_id,
		[
			'type'        => 'textarea',
			'label'       => __( 'Invalid Web Monetization message', 'coil-monetize-content' ),
			'section'     => $messaging_section_id,
			'description' => __( 'This message is shown when content is set to be subscriber-only, browser setup is correct, but Web Monetization doesn\'t start.  It might be due to several reasons, including not having an active Coil account.', 'coil-monetize-content' ),
			'input_attrs' => [
				'placeholder' => get_customizer_messaging_text( $invalid_web_monetization_message_id, true ),
			],
		]
	);

	// Voluntary donation message (textarea 3).
	$voluntary_donation_message_id = 'coil_voluntary_donation_message';

	$wp_customize->add_setting(
		$voluntary_donation_message_id,
		[
			'capability'        => apply_filters( 'coil_settings_capability', 'manage_options' ),
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]
	);

	$wp_customize->add_control(
		$voluntary_donation_message_id,
		[
			'type'        => 'textarea',
			'label'       => __( 'Voluntary donation message', 'coil-monetize-content' ),
			'section'     => $messaging_section_id,
			'description' => __( 'This message is shown when content is set to "Monetized and Public" and visitor does not have Web Monetization in place and active in their browser.', 'coil-monetize-content' ),
			'input_attrs' => [
				'placeholder' => get_customizer_messaging_text( $voluntary_donation_message_id, true ),
			],
		]
	);

	// Pending message (textarea 4).
	$pending_message_id = 'coil_verifying_status_message';

	$wp_customize->add_setting(
		$pending_message_id,
		[
			'capability'        => apply_filters( 'coil_settings_capability', 'manage_options' ),
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]
	);

	$wp_customize->add_control(
		$pending_message_id,
		[
			'type'        => 'textarea',
			'label'       => __( 'Pending message', 'coil-monetize-content' ),
			'section'     => $messaging_section_id,
			'description' => __( 'This message is shown for a short time time only while check is made on browser setup and that an active Web Monetization account is in place.', 'coil-monetize-content' ),
			'input_attrs' => [
				'placeholder' => get_customizer_messaging_text( $pending_message_id, true ),
			],
		]
	);

	// Partial gating message (textarea 5).
	$partial_message_id = 'coil_partial_gating_message';

	$wp_customize->add_setting(
		$partial_message_id,
		[
			'capability'        => apply_filters( 'coil_settings_capability', 'manage_options' ),
			'sanitize_callback' => 'wp_filter_nohtml_kses',
		]
	);

	$wp_customize->add_control(
		$partial_message_id,
		[
			'type'        => 'textarea',
			'label'       => __( 'Partial content gating message', 'coil-monetize-content' ),
			'section'     => $messaging_section_id,
			'description' => __( 'This message is shown in footer bar on pages where only some of the content blocks have been set as Subscriber-Only.', 'coil-monetize-content' ),
			'input_attrs' => [
				'placeholder' => get_customizer_messaging_text( $partial_message_id, true ),
			],
		]
	);
}

/**
 * Gets the taxonomies and allows the output to be filtered.
 *
 * @return array Taxonomies or empty array
 */
function get_valid_taxonomies() : array {

	$all_taxonomies = get_taxonomies(
		[],
		'objects'
	);

	// Set up options to exclude certain taxonomies.
	$taxonomies_exclude = [
		'nav_menu',
		'link_category',
		'post_format',
	];

	$taxonomies_exclude = apply_filters( 'coil_settings_taxonomy_exclude', $taxonomies_exclude );

	// Store the available taxonomies using the above exclusion options.
	$taxonomy_options = [];
	foreach ( $all_taxonomies as $taxonomy ) {

		if ( ! empty( $taxonomies_exclude ) && in_array( $taxonomy->name, $taxonomies_exclude, true ) ) {
			continue;
		}
		if ( ! in_array( $taxonomy->name, $taxonomy_options, true ) ) {
			$taxonomy_options[] = $taxonomy->name;
		}
	}

	return $taxonomy_options;
}
