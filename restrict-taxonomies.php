<?php
/*
Plugin Name: Restrict Taxonomies
Description: Based on Restrict Categories, restrict the taxonomies terms that users can view, add, and edit in the admin panel.
Author: Sladix
Author URI: https://twitter.com/sladix
Version: 1.3.0
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Instantiate new class
$restrict_categories_load = new RestrictTaxonomies();

// Restrict Categories class
class RestrictTaxonomies{

	private $cat_list = NULL;

	public function __construct(){
		// Make sure we are in the admin before proceeding.
		$frontOptions = get_option('RestrictTaxs_general_options');
		if ( is_admin() ) {
			$post_type = $this->get_current_post_type();

			// Build options and settings pages.
			add_action( 'admin_init', array( &$this, 'init' ) );
			add_action( 'admin_menu', array( &$this, 'add_admin' ) );

			$post_type_options = get_option('RestrictTaxs_post_type_options');

			// If the page is the Posts screen, do our thing, otherwise chill
			if ( $post_type == false || $post_type == 'post' ||  $post_type == 'page' || in_array($post_type,$post_type_options['post_types']) )
				add_action( 'admin_init', array( &$this, 'posts' ) );

			// Adds a Settings link to the Plugins page
			add_filter( 'plugin_action_links', array( &$this, 'rc_plugin_action_links' ), 10, 2 );
			add_filter( 'screen_settings', array( &$this, 'add_screen_options' ) );

			add_action( 'admin_notices', array( &$this, 'admin_notices' ) );

			register_deactivation_hook( __FILE__ , array( &$this, 'desactivate' ) );
			register_activation_hook( __FILE__ , array( &$this, 'activate' ) );
		}elseif(isset($frontOptions['frontend']) && $frontOptions['frontend'])
		{
			//Front
			add_action( 'pre_get_posts', array( &$this, 'posts' ) );
		}

		// Make sure XML-RPC requests are filtered to match settings
		if ( defined ( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			add_action( 'xmlrpc_call', array( &$this, 'posts' ) );
	}


	/**
	 * Delete the options from database
	 *
	 * @since 1.0
	 * @uses delete_option()
	 */
	function desactivate(){
		unregister_setting( 'RestrictTaxs_options', 'RestrictTaxs_options', array( &$this, 'options_sanitize' ) );
		unregister_setting( 'RestrictTaxs_general_options', 'RestrictTaxs_general_options', array( &$this, 'options_sanitize' ) );
		unregister_setting( 'RestrictTaxs_user_options', 'RestrictTaxs_user_options', array( &$this, 'options_sanitize' ) );
		unregister_setting( 'RestrictTaxs_post_type_options', 'RestrictTaxs_post_type_options', array( &$this, 'options_sanitize' ) );
	}

	/**
	 * Register the options and option group
	 *
	 * @since 1.0
	 * @uses delete_option()
	 */
	function activate(){
		if(false === get_option('RestrictTaxs_post_type_options'))
		{
			//Default CPT options
			$defaultsPt = array(
				'post_types'	=>	array('post'),
				'taxonomies'	=>	array('category')
			);
			update_option( 'RestrictTaxs_post_type_options',$defaultsPt);
		}

		if(false === get_option('RestrictTaxs_general_options')){
			//Default CPT options
			$defaults = array(
				'frontend'	=>	false
			);
			update_option( 'RestrictTaxs_general_options',$defaults);
		}
	}

	/**
	 * Register database options and set defaults, which are blank
	 *
	 * @since 1.0
	 * @uses register_setting() Register a setting in the database
	 */
	public function init() {

		register_setting( 'RestrictTaxs_options', 'RestrictTaxs_options', array( &$this, 'options_sanitize' ) );
		register_setting( 'RestrictTaxs_general_options', 'RestrictTaxs_general_options', array( &$this, 'options_sanitize' ) );
		register_setting( 'RestrictTaxs_user_options', 'RestrictTaxs_user_options', array( &$this, 'options_sanitize' ) );
		register_setting( 'RestrictTaxs_post_type_options', 'RestrictTaxs_post_type_options', array( &$this, 'options_sanitize' ) );

		$screen_options = get_option( 'RestrictTaxs-screen-options' );
		$this->activate();
		// Default is 20 per page
		$defaults = array(
			'roles_per_page' => 20,
			'users_per_page' => 20
		);
		// If the option doesn't exist, add it with defaults
		if ( !$screen_options )
			update_option( 'RestrictTaxs-screen-options', $defaults );

		// If the user has saved the Screen Options, update
		if ( isset( $_REQUEST['restrict-taxonomies-screen-options-apply'] ) && in_array( $_REQUEST['restrict-taxonomies-screen-options-apply'], array( 'Apply', 'apply' ) ) ) {
			$roles_per_page = absint( $_REQUEST['RestrictTaxs-screen-options']['roles_per_page'] );
			$users_per_page = absint( $_REQUEST['RestrictTaxs-screen-options']['users_per_page'] );

			$updated_options = array(
				'roles_per_page' => $roles_per_page,
				'users_per_page' => $users_per_page
			);

			update_option( 'RestrictTaxs-screen-options', $updated_options );
		}

		// Resets the options
		if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'restrict-taxonomies' ) :

			if ( !isset( $_REQUEST['action'] ) )
				return;

			if ( 'reset' !== $_REQUEST['action'] )
				return;

			$nonce = $_REQUEST['_wpnonce'];

			// Security check to verify the nonce
			if ( ! wp_verify_nonce( $nonce, 'rc-reset-nonce' ) )
				wp_die( __( 'Security check', 'restrict-taxonomies' ) );

			//Default CPT options
			$defaultsPt = array(
				'post_types'	=>	array('post'),
				'taxonomies'	=>	array('category')
			);
			// Reset Roles and Users options
			update_option( 'RestrictTaxs_options', array() );
			update_option( 'RestrictTaxs_user_options', array() );
			update_option( 'RestrictTaxs_post_type_options', $defaultsPt );

		endif;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0
	 */
	public function admin_notices(){
		if ( isset( $_REQUEST['action'] ) ) :

			switch( $_REQUEST['action'] ) :
				case 'reset' :
					echo '<div id="message" class="updated"><p>' . __( 'Restrict Categories reset' , 'restrict-taxonomies') . '</p></div>';
					break;
			endswitch;

		endif;
	}

	/**
	 * Load JS in admin
	 *
	 * @since 1.0
	 */
	public function admin_scripts() {
		wp_enqueue_script( 'restrict-taxonomies-admin', plugins_url( '/js/restrict-taxonomies.js', __FILE__ ), array( 'jquery' ), false, true );
	}

	/**
	 * Add Settings link to Plugins page
	 *
	 * @since 1.0
	 * @return $links array Links to add to plugin name
	 */
	public function rc_plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename(__FILE__) )
			$links[] = '<a href="options-general.php?page=restrict-taxonomies">' . __( 'Settings', 'restrict-taxonomies' ) . '</a>';

		return $links;
	}

	/**
	 * Set up the options array which will output all roles with categories.
	 *
	 * @since 1.0
	 * @uses get_roles() Returns an array of all user roles.
	 * @return $rc_options array Multidimensional array with options.
	 */
	public function populate_opts(){
		$rc_options = array();

		$roles 	= $this->get_roles();
		$fo = get_option('RestrictTaxs_general_options');
		if(isset($fo['frontend']) && $fo['frontend'])
		{
			$rc_options[] = array(
				'name'      => 'Non logged users',
				'id'        => "non_logged_cats"
				// 'options'   => $cats
			);
		}
		foreach ( $roles as $name => $id ) {
			$rc_options[] = array(
				'name'      => $name,
				'id'        => "{$id}_cats"
				// 'options'   => $cats
			);
		}
		return $rc_options;
	}

	/**
	 * Set up the user options array which will output all users with categories.
	 *
	 * @since 1.0
	 * @uses get_logins() Returns an array of all user logins.
	 * @return $rc_user_options array Multidimensional array with options.
	 */
	public function populate_user_opts(){
		$rc_user_options = array();

		$logins	= $this->get_logins();

		foreach ( $logins as $name => $id ) {
			$rc_user_options[] = array(
				'name'     => $name,
				'id'       => "{$id}_user_cats"
				// 'options'  => $cats
			);
		}

		return $rc_user_options;
	}

	/**
	 * Set up the roles array which uses similar code to wp_dropdown_roles().
	 *
	 * @since 1.0
	 * @uses get_editable_roles() Fetch a filtered list of user roles that the current user is allowed to edit.
	 * @return $roles array Returns array of user roles with the "pretty" name and the slug.
	 */
	public function get_roles(){
		$roles = array();

		$editable_roles = get_editable_roles();

		foreach ( $editable_roles as $role => $name ) {
			$roles[ $name['name'] ] = $role;
		}

		return $roles;
	}

	/**
	 * Set up the user logins array.
	 *
	 * @since 1.0
	 * @uses get_users Returns an array filled with information about the blog's users. WP 3.1
	 * @uses get_users_of_blog() Returns an array filled with information about the blog's users. WP 3.0
	 * @return $users array Returns array of user logins.
	 */
	public function get_logins(){
		$users = array();

		$args = array();

		if ( isset( $_POST['rc-search-users'] ) ) {
			$search = ( isset( $_REQUEST['rc-search'] ) && !empty( $_REQUEST['rc-search'] ) ) ? esc_html( $_POST['rc-search'] ) : '';
			$args = array( 'search' => $search );
		}

		$blogusers = get_users( $args );

		foreach ( $blogusers as $login ) {
			$users[ $login->user_login ] = $login->user_nicename;
		}

		return $users;
	}


	/**
	 * Adds the Screen Options tab
	 *
	 * @since 1.0
	 */
	public function add_screen_options( $current ){
		global $current_screen;

		if ( $current_screen->id == 'settings_page_restrict-taxonomies' ){
			$options = get_option( 'RestrictTaxs-screen-options' );
			$current = '<h5>Show on screen</h5>
					<input type="text" value="' . $options['roles_per_page'] . '" maxlength="3" id="restrict-taxonomies-roles-per-page" name="RestrictTaxs-screen-options[roles_per_page]" class="screen-per-page"> <label for="restrict-taxonomies-roles-per-page">Roles</label>
					<input type="text" value="' . $options['users_per_page'] . '" maxlength="3" id="restrict-taxonomies-users-per-page" name="RestrictTaxs-screen-options[users_per_page]" class="screen-per-page"> <label for="restrict-taxonomies-users-per-page">Users</label>
					<input type="submit" value="Apply" class="button" id="restrict-taxonomies-screen-options-apply" name="restrict-taxonomies-screen-options-apply">';
		}

		return $current;
	}

	/**
	 * Sanitize input
	 *
	 * @since 1.0
	 * @return $input array Returns array of input if available
	 */
	public function options_sanitize( $input ){

		if ( !isset( $_REQUEST['option_page'] ) )
			return;
		switch($_REQUEST['option_page'])
		{
			case 'RestrictTaxs_user_options' :
				$options = get_option( 'RestrictTaxs_user_options' );
				break;
			case 'RestrictTaxs_options' :
				$options = get_option( 'RestrictTaxs_options' );
				break;
			default :
				$options = get_option( 'RestrictTaxs_post_type_options' );
				break;
		}

		if ( is_array( $input ) ) {
			foreach( $input as $k => $v ) {
				$options[ $k ] = $v;
			}
		}

		return $options;
	}

	/**
	 * Add options page
	 *
	 *
	 * @since 1.0
	 * @uses add_options_page() Creates a menu item under the Settings menu.
	 */
	public function add_admin() {
		$current_page = add_options_page( __('Restrict Taxonomies', 'restrict-taxonomies'), __('Restrict Taxonomies', 'restrict-taxonomies'), 'manage_categories', 'restrict-taxonomies', array( &$this, 'admin' ) );

		// Load admin scripts
		add_action( "load-$current_page", array( &$this, 'admin_scripts' ) );
	}

	/**
	 * Builds the options settings page
	 *
	 * @since 1.0
	 * @global $rc_options array The global options array populated by populate_opts().
	 * @global $rc_user_options array The global options array populated by populate_user_opts().
	 * @uses get_option() A safe way to get options from the options database table.
	 * @uses wp_list_categories() Displays a list of categories
	 */
	public function admin() {

		// Default main tab is Roles
		$tab = 'roles';

		// Set variables if the Users tab is selected
		if ( isset( $_GET['type'] ) && $_GET['type'] == 'users' )
			$tab = 'users';

		// Setup links for Roles/Users tabs
		$roles_tab = esc_url( admin_url( 'options-general.php?page=restrict-taxonomies' ) );
		$users_tab = add_query_arg( 'type', 'users', $roles_tab );
		?>

		<div class="wrap">
			<h2>
				<?php
				_e('Restrict Taxonomies', 'restrict-taxonomies');

				// If searched, output the query
				if ( isset( $_REQUEST['rc-search'] ) && !empty( $_REQUEST['rc-search'] ) ) {
					echo '<span class="subtitle">' . sprintf( __( 'Search results for "%s"' , 'restrict-taxonomies'), $_REQUEST['rc-search'] ) . '</span>';
					echo sprintf( '<span class="subtitle"><a href="%1$s">%2$s</a></span>', $users_tab, __( 'View All Users', 'restrict-taxonomies' ) );
				}
				?>
			</h2>
			<h3><?php _e('Post types and Taxonomy Settings', 'restrict-taxonomies'); ?></h3>
			<?php $options = get_option('RestrictTaxs_post_type_options'); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'RestrictTaxs_post_type_options' ); ?>
				<h4>Custom post Types to handle</h4>
				<fieldset>
					<?php
					$pts = get_post_types( array('public'=>true,'_builtin'=>false), 'objects');
					$isposts = false;
					foreach ($pts as $cpt): ?>
						<label><input type="checkbox" name="RestrictTaxs_post_type_options[post_types][]" value="<?php echo $cpt->name ?>" <?php if(in_array($cpt->name,$options['post_types'])){echo "checked";} ?>><?php echo $cpt->labels->menu_name ?> </label>
						<?php if($cpt->name == 'post'){$isposts = true;} ?>
					<?php endforeach;
					?>
					<?php if (!$isposts): ?>
						<label><input type="checkbox" name="RestrictTaxs_post_type_options[post_types][]" value="post" <?php if(in_array("post",$options['post_types'])){echo "checked";} ?>><?php _e('Posts','restrict-taxonomies') ?> </label>
					<?php endif ?>

					<h4>Custom Taxonomies to handle</h4>
					<?php
					$cts = get_taxonomies(array('_builtin'=>false), 'objects'); ?>
					<?php foreach ($cts as $ct): ?>
						<label><input type="checkbox" name="RestrictTaxs_post_type_options[taxonomies][]" value="<?php echo $ct->name ?>" <?php if(in_array($ct->name,$options['taxonomies'])){echo "checked";} ?>><?php echo $ct->labels->name ?> </label>
					<?php endforeach;
					?>
					<label><input type="checkbox" name="RestrictTaxs_post_type_options[taxonomies][]" value="category" <?php if(in_array("category",$options['taxonomies'])){echo "checked";} ?>><?php _e('Categories','restrict-taxonomies') ?> </label>
				</fieldset>
				<?php submit_button(); ?>
			</form>
			<?php $frontOptions = get_option('RestrictTaxs_general_options'); ?>
			<form method = "post" action="options.php">
				<?php settings_fields('RestrictTaxs_general_options'); ?>
				<h4>General options</h4>
				<label><input type="checkbox" name="RestrictTaxs_general_options[frontend]" value="1" <?php if(isset($frontOptions['frontend']) && $frontOptions['frontend']){echo "checked";} ?>> Front end restrictions</label>
				<?php submit_button(); ?>
			</form>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo $roles_tab; ?>" class="nav-tab <?php echo ( $tab == 'roles' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'Roles', 'restrict-taxonomies' ); ?></a>
				<a href="<?php echo $users_tab; ?>" class="nav-tab <?php echo ( $tab == 'users' ) ? 'nav-tab-active' : ''; ?>"><?php _e( 'Users', 'restrict-taxonomies' ); ?></a>
			</h2>

			<?php
			// Create a new instance of our user/roles boxes class
			$boxes = new RestrictTaxs_User_Role_Boxes();

			if ( $tab == 'roles' ) :

				$rc_options = $this->populate_opts();

				?>
				<form method="post" action="options.php">

					<?php
					settings_fields( 'RestrictTaxs_options' );
					foreach ($options['taxonomies'] as $tax) {
						$t = get_taxonomy( $tax );
						echo "<fieldset>";
						echo "<h3>".$t->labels->name."</h3>";
						// Create boxes for Roles
						$boxes->start_box( get_option( 'RestrictTaxs_options' ), $rc_options, 'RestrictTaxs_options['.$tax.']', $tax );
						echo "</fieldset>";
					}
					?>

					<?php submit_button(); ?>
				</form>
				<?php
			elseif ( $tab == 'users' ) :

				$rc_user_options = $this->populate_user_opts();
				?>
				<form method="post" action="options-general.php?page=restrict-taxonomies&type=users">
					<fieldset>
						<p style="float: left; margin-top:8px;">Selecting categories for a user will <em>override</em> the categories you have chosen for that user's role.</p>
						<p style="float:right; margin-top:8px;">
							<input type="search" id="rc-search-users" name="rc-search" value="">
							<?php submit_button( __( 'Search Users', 'restrict-taxonomies' ), 'secondary', 'rc-search-users', false ); ?>
						</p>
					</fieldset>
				</form>

				<form method="post" action="options.php">
					<?php
					settings_fields( 'RestrictTaxs_user_options' );
					foreach ($options['taxonomies'] as $tax) {
						$t = get_taxonomy( $tax );
						echo "<fieldset>";
						echo "<h3>".$t->labels->name."</h3>";
						// Create boxes for Users
						$boxes->start_box( get_option( 'RestrictTaxs_user_options' ), $rc_user_options, 'RestrictTaxs_user_options['.$tax.']',$tax );
						echo "</fieldset>";
					}
					?>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>

			<h3><?php _e('Reset to Default Settings', 'restrict-taxonomies'); ?></h3>
			<p><?php _e('This option will reset all changes you have made to the default configuration.  <strong>You cannot undo this process</strong>.', 'restrict-taxonomies'); ?></p>
			<form method="post">
				<?php submit_button( __( 'Reset', 'restrict-taxonomies' ), 'secondary', 'reset' ); ?>
				<input type="hidden" name="action" value="reset" />
				<?php wp_nonce_field( 'rc-reset-nonce' ); ?>
			</form>
		</div>
		<?php

	}

	private function get_current_post_type() {
		global $post, $typenow, $current_screen, $wp_query;

		//we have a post so we can just get the post type from that
		if ( $post && $post->post_type )
			return $post->post_type;

		//check the global $typenow - set in admin.php
		elseif( $typenow )
			return $typenow;

		//check the global $current_screen object - set in screen.php
		elseif( $current_screen && $current_screen->post_type )
			return $current_screen->post_type;

		//check the post_type querystring
		elseif( isset( $_REQUEST['post_type'] ) )
			return sanitize_key( $_REQUEST['post_type'] );

		//lastly check with the request post
		elseif (isset($_REQUEST['post']) && get_post_type($_REQUEST['post']))
			return get_post_type($_REQUEST['post']);

		//Dokan seller dashboard products creation
		if(function_exists('dokan_get_option'))
		{
			$page_id = dokan_get_option( 'dashboard', 'dokan_pages' );
			if(is_page($page_id))
				return 'product';
		}
		//Geo dir post type
		if(isset($wp_query->query_vars['gd_is_geodir_page']))
			return 'gd_place';

		if(isset($wp_query->query_vars['post_type']))
			return $wp_query->query_vars['post_type'];
		//we do not know the post type!
		return 'post';
	}

	/**
	 * Rewrites the query to only display the selected categories from the settings page
	 *
	 * @since 1.0
	 * @global $wp_query object The global WP_Query object.
	 * @global $current_user object The global user object.
	 * @uses WP_User() Retrieve user object.
	 * @uses get_option() A safe way to get options from the options database table.
	 */
	public function posts() {
		global $wp_query, $current_user;

		// Placeholder category (only used to ensure saving while paging works)
		$defaults = array( 'RestrictCategoriesDefault' );

		if($current_user->ID == 0)
		{
			$settings = get_option( 'RestrictTaxs_options' );
			$settings_user = '';
			$user_cap = array('non_logged');
		}else
		{

			// Get the current user in the admin
			$user = new WP_User( $current_user->ID );

			// Get the user role
			$user_cap = $user->roles;

			// Get the user login name/ID
			if ( function_exists( 'get_users' ) )
				$user_login = $user->user_nicename;
			elseif ( function_exists( 'get_users_of_blog' ) )
				$user_login = $user->ID;

			// Get selected categories for Roles
			$settings = get_option( 'RestrictTaxs_options' );

			// Get selected categories for Users
			$settings_user = get_option( 'RestrictTaxs_user_options' );
		}


		// Get handled taxonomies
		$options = get_option('RestrictTaxs_post_type_options');
		if(!is_array($options))
		{
			$options = array(
				'post_types'	=>	array(),
				'taxonomies'	=>	array()
			);
		}
		$taxs = get_object_taxonomies( $this->get_current_post_type(), 'names' );
		$lestax = array_intersect($taxs, $options['taxonomies']);
		$this->cat_list = array();
		foreach ($lestax as $taxonomy) {
			// For users, strip out the placeholder category, which is only used to make sure the checkboxes work
			if ( is_array( $settings_user ) && array_key_exists( $taxonomy, $settings_user ) )
				$settings_user[$taxonomy][ $user_login . '_user_cats' ] = array_values( array_diff( $settings_user[$taxonomy][ $user_login . '_user_cats' ], $defaults ) );
			if(!isset($this->cat_list[$taxonomy]))
				$this->cat_list[$taxonomy] = '';
			// Selected categories for User overwrites Roles selection
			if ( is_array( $settings_user ) && is_array($settings_user[$taxonomy]) &&!empty( $settings_user[$taxonomy][ $user_login . '_user_cats' ] ) ) {
				// Build the category list
				foreach ($settings_user[$taxonomy][ $user_login . '_user_cats' ] as $category) {
					$term_id = get_term_by( 'slug', $category, $taxonomy )->term_id;

					// If WPML is installed, return the translated ID
					if ( function_exists( 'icl_object_id' ) )
						$term_id = icl_object_id( $term_id, $taxonomy, true );

					$this->cat_list[$taxonomy] .= $term_id . ',';
				}


				$this->cat_filters( $this->cat_list[$taxonomy],$taxonomy );
			}
			else {
				foreach ( $user_cap as $key ) {
					// Make sure the settings from the DB isn't empty before building the category list
					if ( is_array( $settings ) && is_array($settings[$taxonomy]) && !empty( $settings[$taxonomy][ $key . '_cats' ] ) ) {
						// Strip out the placeholder category, which is only used to make sure the checkboxes work
						$settings[$taxonomy][ $key . '_cats' ] = array_values( array_diff( $settings[$taxonomy][ $key . '_cats' ], $defaults ) );

						// Build the category list
						foreach ($settings[$taxonomy][ $key . '_cats' ] as $category) {
							$term_id = get_term_by( 'slug', $category, $taxonomy )->term_id;

							// If WPML is installed, return the translated ID
							if ( function_exists( 'icl_object_id' ) )
								$term_id = icl_object_id( $term_id, $taxonomy, true );

							$this->cat_list[$taxonomy] .= $term_id . ',';
						}

					}

					$this->cat_filters( $this->cat_list[$taxonomy],$taxonomy );
				}
			}
		}


	}

	/**
	 * Adds filters for category restriction
	 *
	 * @since 1.0
	 * @global $cat_list string The global comma-separated list of restricted categories.
	 */
	public function cat_filters( $categories, $t ){
		// Clean up the category list
		$this->cat_list[$t] = rtrim( $categories, ',' );
		// If there are no categories, don't do anything
		if ( empty( $this->cat_list[$t] ) )
			return;

		global $pagenow;
		$frontOptions = get_option('RestrictTaxs_general_options');
		// Only restrict the posts query if we're on the Posts screen
		if ( $pagenow == 'edit.php' || ( defined ( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || (isset($frontOptions['frontend']) && $frontOptions['frontend']))
			add_filter( 'pre_get_posts', array( &$this, 'posts_query' ),9999 );

		// Allowed pages for term exclusions
		$pages = array( 'edit.php', 'post-new.php', 'post.php' );
		$options = get_option('RestrictTaxs_post_type_options');
		// Make sure to exclude terms from $pages array as well as the Category screen
		if ((isset($frontOptions['frontend']) && $frontOptions['frontend']) || in_array( $pagenow, $pages ) || ( $pagenow == 'edit-tags.php' && in_array($_GET['taxonomy'],$options['taxonomies']) ) || ( defined ( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) )
		{
			add_filter( 'list_terms_exclusions', array( &$this, 'exclusions' ) );
		}
	}

	/**
	 * Remove posts from edit.php with restricted categories
	 *
	 * @since 1.0
	 * @global $cat_list string The global comma-separated list of restricted categories.
	 * @return $query array Sets 'category__in' query_var with an array of category IDs
	 */
	public function posts_query( $query ){
		if ( count($this->cat_list) > 0) {
			// Make sure the posts are removed by default or if filter category is ran
			$taxs = get_object_taxonomies( $this->get_current_post_type(), 'names' );
			$taxquery = array(
				'relation'	=>	'OR'
			);
			foreach ($taxs as $taxonomy) {
				if(!empty($this->cat_list[$taxonomy])){
					$larray = explode( ',', $this->cat_list[$taxonomy] );
					$taxquery[] = array(
						'taxonomy' => $taxonomy,
						'field' => 'id',
						'terms' => $larray ,
						'operator'=> 'IN'
					);
				}
			}
			$query->set( 'tax_query', $taxquery );
		}

		return $query;
	}

	/**
	 * Explicitly remove extra categories from view that user can manage
	 * Will affect Category management page, Posts dropdown filter, and New/Edit post category list
	 *
	 * @since 1.0
	 * @global $cat_list string The global comma-separated list of restricted categories.
	 * @return $excluded string Appended clause on WHERE of get_taxonomy
	 */
	public function exclusions(){
		$options = get_option('RestrictTaxs_post_type_options');
		$str = "";
		foreach ($options['taxonomies'] as $value) {
			$str.= "'".$value."',";
		}
		$str_cats = "";
		foreach($this->cat_list as $tax=>$list){
			$str_cats .= $list.",";
		}
		$str = rtrim($str, ",");
		$str_cats = rtrim($str_cats,",");

		//If there is no posts in the allowed categories, don't display any
		if(!empty($str_cats))
			$excluded = " AND ( t.term_id IN ( $str_cats ) OR tt.taxonomy NOT IN ( $str ) )";
		else
			$excluded = " AND t.term_id = 0";

		return $excluded;
	}
}

/**
 * Creates each box for users and roles.
 *
 * @since 1.0
 */
class RestrictTaxs_User_Role_Boxes {

	/**
	 * Various information needed for displaying the pagination
	 *
	 * @since 1.0
	 * @var array
	 */
	var $_pagination_args = array();

	public function start_box($settings, $options, $options_name, $taxonomy){

		// Create a new instance of our custom walker class
		$walker = new RestrictTaxs_Walker_Category_Checklist();


		// Get screen options from the wp_options table
		$screen_options = get_option( 'RestrictTaxs-screen-options' );

		// How many to show per page
		$per_page = ( 'RestrictTaxs_options' == $options_name  ) ? $screen_options['roles_per_page'] : $screen_options['users_per_page'];

		// What page are we looking at?
		$current_page = $this->get_pagenum();

		// How many do we have?
		$total_items = count( $options );

		// Calculate pagination
		$options = array_slice( $options, ( ( $current_page - 1 ) * $per_page ), $per_page );

		// Register our pagination
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );

		// Display pagination
		echo '<div class="tablenav">';
		$this->pagination( 'top' );
		echo '<br class="clear" /></div>';

		// Loop through each role and build the checkboxes
		foreach ( $options as $key => $value ) :

			$id = $value['id'];
			// Get selected categories from database, if available
			if ( isset($settings[$taxonomy]) && isset( $settings[$taxonomy][ $id ] ) && is_array( $settings[$taxonomy][ $id ] ) )
				$selected = $settings[$taxonomy][ $id ];
			else
				$selected = array();

			// Setup links for Roles/Users tabs in this class
			$roles_tab = esc_url( admin_url( 'options-general.php?page=restrict-taxonomies' ) );
			$users_tab = add_query_arg( $id . '-tab', 'popular', $roles_tab );

			// If the Users tab is selected, setup query_arg for checkbox tabs
			if ( isset( $_REQUEST['type'] ) && $_REQUEST['type'] == 'users' ) {
				$roles_tab = add_query_arg( array( 'type' => 'users', $id . '-tab' => 'all' ), $roles_tab );
				$users_tab = add_query_arg( array( 'type' => 'users', $id . '-tab' => 'popular' ), $roles_tab );
			}

			// Make sure View All and Most Used tabs work when paging
			if ( isset( $_REQUEST['paged'] ) ) {
				$roles_tab = add_query_arg( array( 'paged' => absint( $_REQUEST['paged'] ) ), $roles_tab );
				$users_tab = add_query_arg( array( 'paged' => absint( $_REQUEST['paged'] ) ), $users_tab );
			}

			// View All tab is default
			$current_tab = 'all';

			// Check which checkbox tab is selected
			if ( isset( $_REQUEST[ $id . '-tab' ] ) && in_array( $_REQUEST[ $id . '-tab' ], array( 'all', 'popular' ) ) )
				$current_tab = $_REQUEST[ $id . '-tab' ];
			?>
			<div id="side-sortables" class="metabox-holder" style="float:left; padding:5px;">
				<div class="postbox">
					<h3 class="hndle"><span><?php echo $value['name']; ?></span></h3>

					<div class="inside" style="padding:0 10px;">
						<div class="taxonomydiv">
							<ul id="taxonomy-category-tabs" class="taxonomy-tabs add-menu-item-tabs">
								<li<?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>><a href="<?php echo add_query_arg( $id . '-tab', 'all', $roles_tab ); ?>" class="nav-tab-link">View All</a></li>
								<li<?php echo ( 'popular' == $current_tab ? ' class="tabs"' : '' ); ?>><a href="<?php echo $users_tab; ?>" class="nav-tab-link">Most Used</a></li>
							</ul>
							<div id="<?php echo $id; ?>-all" class="tabs-panel <?php echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' ); ?>">
								<ul class="categorychecklist form-no-clear">
									<?php
									wp_list_categories(
										array(
											'admin'          => $id,
											'selected_cats'  => $selected,
											'options_name'   => $options_name,
											'hide_empty'     => 0,
											'title_li'       => '',
											'disabled'       => ( 'all' == $current_tab ? false : true ),
											'walker'         => $walker,
											'taxonomy'		 => $taxonomy
										)
									);

									$disable_checkbox = ( 'all' == $current_tab ) ? '' : 'disabled="disabled"';
									?>
									<input style="display:none;" <?php echo $disable_checkbox; ?> type="checkbox" value="RestrictCategoriesDefault" checked="checked" name="<?php echo $options_name; ?>[<?php echo $id; ?>][]">
								</ul>
							</div>
							<div id="<?php echo $id; ?>-popular" class="tabs-panel <?php echo ( 'popular' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' ); ?>">
								<ul class="categorychecklist form-no-clear">
									<?php
									wp_list_categories(
										array(
											'admin'          => $id,
											'selected_cats'  => $selected,
											'options_name'   => $options_name,
											'hide_empty'     => 0,
											'title_li'       => '',
											'orderby'        => 'count',
											'order'          => 'DESC',
											'disabled'       => ( 'popular' == $current_tab ? false : true ),
											'walker'         => $walker,
											'taxonomy'		 => $taxonomy
										)
									);

									$disable_checkbox = ( 'popular' == $current_tab ) ? '' : 'disabled="disabled"';
									?>
									<input style="display:none;" <?php echo $disable_checkbox; ?> type="checkbox" value="RestrictCategoriesDefault" checked="checked" name="<?php echo $options_name; ?>[<?php echo $id; ?>][]">
								</ul>
							</div>
						</div>

						<?php
						$shift_default = array_diff( $selected, array( 'RestrictCategoriesDefault' ) );
						$selected = array_values( $shift_default );
						?>
						<p style="padding-left:10px;">
							<strong><?php echo count( $selected ); ?></strong> <?php echo ( count( $selected ) > 1 || count( $selected ) == 0 ) ? 'categories' : 'category'; ?> selected
							<span class="list-controls" style="float:right; margin-top: 0;">
								<a class="select-all" id="<?php echo $id; ?>-select-all" href="#"><?php _e( 'Select All', 'restrict-taxonomies' ); ?></a>
							</span>
						</p>

					</div>
				</div>
			</div>
			<?php
		endforeach;
	}

	/**
	 * Get the current page number
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @return int
	 */
	protected function get_pagenum() {
		$pagenum = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;

		if( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] )
			$pagenum = $this->_pagination_args['total_pages'];

		return max( 1, $pagenum );
	}

	/**
	 * Get number of items to display on a single page
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @return int
	 */
	protected function get_items_per_page( $option, $default = 20 ) {
		$per_page = (int) get_user_option( $option );
		if ( empty( $per_page ) || $per_page < 1 )
			$per_page = $default;

		return (int) apply_filters( $option, $per_page );
	}

	/**
	 * Display the pagination.
	 *
	 * @since 1.0
	 * @access protected
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) )
			return;

		extract( $this->_pagination_args );

		$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

		$current = $this->get_pagenum();

		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

		$page_links = array();

		$disable_first = $disable_last = '';
		if ( $current == 1 )
			$disable_first = ' disabled';
		if ( $current == $total_pages )
			$disable_last = ' disabled';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page' ),
			esc_url( remove_query_arg( 'paged', $current_url ) ),
			'&laquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page' ),
			esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
			'&lsaquo;'
		);

		if ( 'bottom' == $which )
			$html_current_page = $current;
		else
			$html_current_page = sprintf( "<input class='current-page' title='%s' type='text' name='%s' value='%s' size='%d' />",
				esc_attr__( 'Current page' ),
				esc_attr( 'paged' ),
				$current,
				strlen( $total_pages )
			);

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span>';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page' ),
			esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
			'&rsaquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page' ),
			esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
			'&raquo;'
		);

		$output .= "\n<span class='pagination-links'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages )
			$page_class = $total_pages < 2 ? ' one-page' : '';
		else
			$page_class = ' no-pages';

		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;
	}

	/**
	 * An internal method that sets all the necessary pagination arguments
	 *
	 * @since 1.0
	 * @param array $args An associative array with information about the pagination
	 * @access protected
	 */
	protected function set_pagination_args( $args ) {
		$args = wp_parse_args( $args, array(
			'total_items' => 0,
			'total_pages' => 0,
			'per_page' => 0,
		) );

		if ( !$args['total_pages'] && $args['per_page'] > 0 )
			$args['total_pages'] = ceil( $args['total_items'] / $args['per_page'] );

		// redirect if page number is invalid and headers are not already sent
		if ( ! headers_sent() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && $args['total_pages'] > 0 && $this->get_pagenum() > $args['total_pages'] ) {
			wp_redirect( add_query_arg( 'paged', $args['total_pages'] ) );
			exit;
		}

		$this->_pagination_args = $args;
	}
}

/**
 * Custom walker class to create a category checklist
 *
 * @since 1.0
 */
class RestrictTaxs_Walker_Category_Checklist extends Walker {
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el( &$output, $category, $depth = 0, $args = array(), $current_object_id = 0 ) {
		extract($args);

		if ( empty( $taxonomy ) )
			$taxonomy = 'category';

		$output .= sprintf(
			'<li id="%4$s-category-%1$d"><label class="selectit"><input value="%2$s" type="checkbox" name="%3$s[%4$s][]" %5$s %6$s /> %7$s</label>',
			$category->term_id,
			$category->slug,
			$options_name,
			$admin,
			checked( in_array( $category->slug, $selected_cats ), true, false ),
			( $disabled === true ? 'disabled="disabled"' : '' ),
			esc_html( apply_filters( 'the_category', $category->name ) )
		);
	}

	function end_el( &$output, $category, $depth = 0, $args= array() ) {
		$output .= "</li>\n";
	}
}
