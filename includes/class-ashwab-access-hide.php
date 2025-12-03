<?php

class Ashwab_Access_Hide {

	private $option_name = 'ashwab_access_hide_items';
	private $excluded_users_option = 'ashwab_access_hide_excluded_users';
	private $redirect_settings_option = 'ashwab_redirect_settings';
	private $hidden_widgets_option = 'ashwab_hidden_widgets';
	private $hide_notices_option = 'ashwab_hide_notices';
	private $hide_plugin_option = 'ashwab_hide_plugin';
	private $css_hide_elements_option = 'ashwab_css_hide_elements';
	private $css_file_version_option = 'ashwab_css_file_version';

	public static function install() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ashwab_access_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			user_email varchar(100) NOT NULL,
			attempted_url text NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			ip_address varchar(100) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// AJAX Actions
		add_action( 'wp_ajax_ashwab_save_item', array( $this, 'ajax_save_item' ) );
		add_action( 'wp_ajax_ashwab_remove_item', array( $this, 'ajax_remove_item' ) );
		add_action( 'wp_ajax_ashwab_save_excluded_user', array( $this, 'ajax_save_excluded_user' ) );
		add_action( 'wp_ajax_ashwab_remove_excluded_user', array( $this, 'ajax_remove_excluded_user' ) );
		add_action( 'wp_ajax_ashwab_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ashwab_save_hidden_widget', array( $this, 'ajax_save_hidden_widget' ) );
		add_action( 'wp_ajax_ashwab_remove_hidden_widget', array( $this, 'ajax_remove_hidden_widget' ) );
		add_action( 'wp_ajax_ashwab_save_css_element', array( $this, 'ajax_save_css_element' ) );
		add_action( 'wp_ajax_ashwab_remove_css_element', array( $this, 'ajax_remove_css_element' ) );
		add_action( 'wp_ajax_ashwab_regenerate_css', array( $this, 'ajax_regenerate_css' ) );
		add_action( 'wp_ajax_ashwab_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_ashwab_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_ashwab_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_ashwab_get_pages', array( $this, 'ajax_get_pages' ) );
		
		// Access control hooks
		add_action( 'current_screen', array( $this, 'check_access' ) );
		add_action( 'admin_menu', array( $this, 'hide_menu_items' ), PHP_INT_MAX );
		
		// New Feature Hooks
		add_action( 'wp_dashboard_setup', array( $this, 'hide_dashboard_widgets' ), 999 );
		add_action( 'admin_head', array( $this, 'hide_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_generated_css' ) );
		add_filter( 'all_plugins', array( $this, 'hide_plugin_from_list' ) );
	}

	public function is_allowed_user() {
		$current_user = wp_get_current_user();
		return $current_user->user_email === ASHWAB_ALLOWED_EMAIL;
	}

	public function is_excluded_user() {
		$current_user_id = get_current_user_id();
		$excluded_users = get_option( $this->excluded_users_option, array() );
		return in_array( $current_user_id, $excluded_users );
	}

	public function register_settings_page() {
		if ( ! $this->is_allowed_user() ) {
			return;
		}

		add_menu_page(
			'Ashwab Access & Hide',
			'Access & Hide',
			'manage_options',
			'ashwab-access-hide',
			array( $this, 'render_settings_page' ),
			'dashicons-shield',
			100
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_ashwab-access-hide' !== $hook ) {
			return;
		}

		// Enqueue Select2 library
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );

		wp_enqueue_style( 'ashwab-access-hide-css', ASHWAB_ACCESS_HIDE_URL . 'assets/css/style.css', array( 'select2' ), '1.0.0' );
		wp_enqueue_script( 'ashwab-access-hide-js', ASHWAB_ACCESS_HIDE_URL . 'assets/js/script.js', array( 'jquery', 'select2' ), '1.0.0', true );

		$hidden_widgets = get_option( $this->hidden_widgets_option, array() );
		if ( ! is_array( $hidden_widgets ) ) {
			$hidden_widgets = array_filter( array_map( 'trim', explode( ',', $hidden_widgets ) ) );
		}

		// Get all pages for the dropdown
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		$pages_list = array();
		foreach ( $pages as $page ) {
			$pages_list[] = array(
				'id'    => $page->ID,
				'title' => $page->post_title,
			);
		}

		wp_localize_script( 'ashwab-access-hide-js', 'ashwabData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ashwab_access_hide_nonce' ),
			'items'   => get_option( $this->option_name, array() ),
			'excludedUsers' => get_option( $this->excluded_users_option, array() ),
			'hiddenWidgets' => array_values( $hidden_widgets ),
			'cssHideElements' => get_option( $this->css_hide_elements_option, array() ),
			'availableItems' => $this->get_blockable_items(),
			'pages' => $pages_list,
			'settings' => array(
				'redirect' => get_option( $this->redirect_settings_option, array( 'type' => 'default', 'value' => '' ) ),
				'hideNotices' => get_option( $this->hide_notices_option, false ),
				'hidePlugin' => get_option( $this->hide_plugin_option, false ),
			),
		) );
	}

	public function render_settings_page() {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'ashwab_access_logs';
		$logs = $wpdb->get_results( "SELECT * FROM $logs_table ORDER BY time DESC LIMIT 50" );
		
		$settings = array(
			'redirect' => get_option( $this->redirect_settings_option, array( 'type' => 'default', 'value' => '' ) ),
			'hideNotices' => get_option( $this->hide_notices_option, false ),
			'hidePlugin' => get_option( $this->hide_plugin_option, false ),
		);

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap ashwab-wrap" dir="ltr">
			<h1>Access & Hide Settings</h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=general' ); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=redirects' ); ?>" class="nav-tab <?php echo $active_tab === 'redirects' ? 'nav-tab-active' : ''; ?>">Redirects</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=widgets' ); ?>" class="nav-tab <?php echo $active_tab === 'widgets' ? 'nav-tab-active' : ''; ?>">Widgets</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=css' ); ?>" class="nav-tab <?php echo $active_tab === 'css' ? 'nav-tab-active' : ''; ?>">CSS Hiding</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=advanced' ); ?>" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=import-export' ); ?>" class="nav-tab <?php echo $active_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">Import/Export</a>
				<a href="<?php echo admin_url( 'admin.php?page=ashwab-access-hide&tab=logs' ); ?>" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">Logs</a>
			</h2>

			<div class="ashwab-tab-content" id="tab-general" style="<?php echo $active_tab !== 'general' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Restricted Items</h2>
					<p>Items listed here will be hidden and blocked for all users except you and excluded users.</p>
					
					<div class="ashwab-actions">
						<button id="ashwab-add-btn" class="button button-primary">Add Element</button>
					</div>

					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th>Type</th>
								<th>Label</th>
								<th>Value (URL/ID)</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="ashwab-items-list">
							<!-- Items will be populated by JS -->
						</tbody>
					</table>
				</div>

				<div class="ashwab-card">
					<h2>Excluded Users</h2>
					<p>Enter User IDs to exclude from restrictions.</p>
					<div class="ashwab-input-group">
						<input type="number" id="ashwab-excluded-user-id" placeholder="User ID">
						<button id="ashwab-add-excluded-user" class="button">Add User</button>
					</div>
					<ul id="ashwab-excluded-users-list">
						<!-- Users will be populated by JS -->
					</ul>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-redirects" style="<?php echo $active_tab !== 'redirects' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Custom Redirects</h2>
					<p>Configure what happens when a user tries to access a restricted page.</p>
					<table class="form-table">
						<tr>
							<th scope="row">Redirect Type</th>
							<td>
								<select id="ashwab-redirect-type">
									<option value="default" <?php selected( $settings['redirect']['type'], 'default' ); ?>>Default (Access Denied Message)</option>
									<option value="custom_url" <?php selected( $settings['redirect']['type'], 'custom_url' ); ?>>Custom URL</option>
									<option value="page_id" <?php selected( $settings['redirect']['type'], 'page_id' ); ?>>Existing Page</option>
								</select>
							</td>
						</tr>
						<tr id="ashwab-redirect-url-row" class="ashwab-redirect-value-row" style="<?php echo $settings['redirect']['type'] !== 'custom_url' ? 'display:none;' : ''; ?>">
							<th scope="row">Custom URL</th>
							<td>
								<input type="url" id="ashwab-redirect-url" class="regular-text" value="<?php echo $settings['redirect']['type'] === 'custom_url' ? esc_attr( $settings['redirect']['value'] ) : ''; ?>" placeholder="https://example.com/access-denied">
								<p class="description">Enter the full URL to redirect to.</p>
							</td>
						</tr>
						<tr id="ashwab-redirect-page-row" class="ashwab-redirect-value-row" style="<?php echo $settings['redirect']['type'] !== 'page_id' ? 'display:none;' : ''; ?>">
							<th scope="row">Select Page</th>
							<td>
								<select id="ashwab-redirect-page" class="ashwab-select2" style="width: 100%; max-width: 400px;">
									<option value="">-- Select a Page --</option>
									<?php
									$pages = get_pages( array( 'post_status' => 'publish' ) );
									$current_page_id = $settings['redirect']['type'] === 'page_id' ? intval( $settings['redirect']['value'] ) : 0;
									foreach ( $pages as $page ) {
										printf(
											'<option value="%d" %s>%s</option>',
											$page->ID,
											selected( $page->ID, $current_page_id, false ),
											esc_html( $page->post_title )
										);
									}
									?>
								</select>
								<p class="description">Select an existing page to redirect to.</p>
							</td>
						</tr>
					</table>
					<button class="button button-primary ashwab-save-settings">Save Changes</button>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-widgets" style="<?php echo $active_tab !== 'widgets' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Dashboard Widget Hiding</h2>
					<p>Enter the IDs of dashboard widgets to hide.</p>
					<p class="description">Common IDs: <code>dashboard_primary</code> (Events & News), <code>dashboard_quick_press</code>, <code>dashboard_activity</code>.</p>
					
					<div class="ashwab-input-group">
						<input type="text" id="ashwab-hidden-widget-id" placeholder="Widget ID">
						<button id="ashwab-add-hidden-widget" class="button">Add Widget</button>
					</div>
					<ul id="ashwab-hidden-widgets-list">
						<!-- Widgets will be populated by JS -->
					</ul>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-css" style="<?php echo $active_tab !== 'css' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>CSS Hiding</h2>
					<p>Enter CSS classes or IDs to hide elements in the dashboard.</p>
					<p class="description">Format: <code>.classname</code> or <code>#idname</code>.</p>
					
					<div class="ashwab-input-group">
						<input type="text" id="ashwab-css-element" placeholder=".class or #id">
						<button id="ashwab-add-css-element" class="button">Add Element</button>
					</div>
					<ul id="ashwab-css-elements-list">
						<!-- CSS Elements will be populated by JS -->
					</ul>
					<hr>
					<button id="ashwab-regenerate-css" class="button button-primary">Save & Regenerate CSS</button>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-advanced" style="<?php echo $active_tab !== 'advanced' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Advanced Settings</h2>
					<table class="form-table">
						<tr>
							<th scope="row">Hide Admin Notices</th>
							<td>
								<label>
									<input type="checkbox" id="ashwab-hide-notices" <?php checked( $settings['hideNotices'] ); ?>>
									Hide all admin notices/notifications for restricted users.
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Hide Plugin</th>
							<td>
								<label>
									<input type="checkbox" id="ashwab-hide-plugin" <?php checked( $settings['hidePlugin'] ); ?>>
									Hide "Ashwab WP Access and Hide" from the plugins list for restricted users.
								</label>
							</td>
						</tr>
					</table>
					<button class="button button-primary ashwab-save-settings">Save Changes</button>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-import-export" style="<?php echo $active_tab !== 'import-export' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Export Settings</h2>
					<p>Export your plugin settings to a JSON file. This includes:</p>
					<ul>
						<li>Dashboard Widget Hiding</li>
						<li>CSS Hiding</li>
						<li>Hide Plugin setting</li>
						<li>Hide Admin Notices setting</li>
						<li>Restricted Items</li>
					</ul>
					<button id="ashwab-export-settings" class="button button-primary">Export Settings</button>
				</div>

				<div class="ashwab-card">
					<h2>Import Settings</h2>
					<p>Import plugin settings from a previously exported JSON file. The CSS file will be automatically regenerated after import.</p>
					<div class="ashwab-input-group" style="margin-bottom: 15px;">
						<input type="file" id="ashwab-import-file" accept=".json" style="margin-bottom: 10px;">
						<button id="ashwab-import-settings" class="button button-primary">Import Settings</button>
					</div>
					<p class="description">Select a JSON file exported from this plugin to import settings.</p>
				</div>
			</div>

			<div class="ashwab-tab-content" id="tab-logs" style="<?php echo $active_tab !== 'logs' ? 'display:none;' : ''; ?>">
				<div class="ashwab-card">
					<h2>Access Logs</h2>
					<div class="ashwab-actions">
						<button id="ashwab-clear-logs" class="button">Clear Logs</button>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Time</th>
								<th>User</th>
								<th>URL</th>
								<th>IP</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $logs ) ) : ?>
								<tr><td colspan="4">No logs found.</td></tr>
							<?php else : ?>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( $log->time ); ?></td>
										<td><?php echo esc_html( $log->user_email ); ?></td>
										<td><code><?php echo esc_html( $log->attempted_url ); ?></code></td>
										<td><?php echo esc_html( $log->ip_address ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Command Menu Modal -->
			<div id="ashwab-command-menu" class="ashwab-modal" style="display:none;">
				<div class="ashwab-modal-content">
					<div class="ashwab-modal-header">
						<input type="text" id="ashwab-search" placeholder="Search for pages, post types...">
						<span class="ashwab-close">&times;</span>
					</div>
					<div class="ashwab-modal-body">
						<ul id="ashwab-results">
							<!-- Search results -->
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_blockable_items() {
		global $menu, $submenu;
		$items = array();

		// Add Admin Menu Items
		if ( ! empty( $menu ) ) {
			foreach ( $menu as $m ) {
				if ( ! empty( $m[0] ) && ! empty( $m[2] ) ) {
					$items[] = array(
						'type'   => 'menu',
						'label'  => strip_tags( $m[0] ),
						'value'  => $m[2],
						'parent' => '',
					);

					// Check for submenus
					if ( isset( $submenu[ $m[2] ] ) ) {
						foreach ( $submenu[ $m[2] ] as $sm ) {
							if ( ! empty( $sm[0] ) && ! empty( $sm[2] ) ) {
								$items[] = array(
									'type'   => 'submenu',
									'label'  => strip_tags( $sm[0] ) . ' (' . strip_tags( $m[0] ) . ')',
									'value'  => $sm[2],
									'parent' => $m[2],
								);
							}
						}
					}
				}
			}
		}

		// Add Post Types
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $pt ) {
			$items[] = array(
				'type'   => 'post_type',
				'label'  => $pt->label,
				'value'  => 'edit.php?post_type=' . $pt->name,
				'parent' => '',
			);
		}

		return $items;
	}

	public function ajax_save_item() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );
		
		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$item = isset( $_POST['item'] ) ? $_POST['item'] : array();
		if ( empty( $item ) ) {
			wp_send_json_error( 'Invalid item' );
		}

		// Sanitize item
		$item = array(
			'type'   => sanitize_text_field( $item['type'] ),
			'label'  => sanitize_text_field( $item['label'] ),
			'value'  => sanitize_text_field( $item['value'] ),
			'parent' => isset( $item['parent'] ) ? sanitize_text_field( $item['parent'] ) : '',
		);

		$items = get_option( $this->option_name, array() );
		// Simple duplicate check
		foreach($items as $existing) {
			if($existing['value'] === $item['value']) {
				wp_send_json_success( $items ); // Already exists
			}
		}
		
		$items[] = $item;
		update_option( $this->option_name, $items );
		wp_send_json_success( $items );
	}

	public function ajax_remove_item() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$value = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '';
		$items = get_option( $this->option_name, array() );

		$items = array_filter( $items, function( $item ) use ( $value ) {
			return $item['value'] !== $value;
		} );

		update_option( $this->option_name, array_values( $items ) );
		wp_send_json_success( array_values( $items ) );
	}

	public function ajax_save_excluded_user() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid User ID' );
		}

		$users = get_option( $this->excluded_users_option, array() );
		if ( ! in_array( $user_id, $users ) ) {
			$users[] = $user_id;
			update_option( $this->excluded_users_option, $users );
		}

		wp_send_json_success( $users );
	}

	public function ajax_remove_excluded_user() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$users = get_option( $this->excluded_users_option, array() );

		$users = array_filter( $users, function( $id ) use ( $user_id ) {
			return $id !== $user_id;
		} );

		update_option( $this->excluded_users_option, array_values( $users ) );
		wp_send_json_success( array_values( $users ) );
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		
		if ( isset( $settings['redirect'] ) ) {
			update_option( $this->redirect_settings_option, array(
				'type'  => sanitize_text_field( $settings['redirect']['type'] ),
				'value' => sanitize_text_field( $settings['redirect']['value'] ),
			) );
		}

		// hiddenWidgets is now handled via separate AJAX actions

		if ( isset( $settings['hideNotices'] ) ) {
			update_option( $this->hide_notices_option, filter_var( $settings['hideNotices'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $settings['hidePlugin'] ) ) {
			update_option( $this->hide_plugin_option, filter_var( $settings['hidePlugin'], FILTER_VALIDATE_BOOLEAN ) );
		}

		wp_send_json_success();
	}

	public function ajax_save_hidden_widget() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( $_POST['widget_id'] ) : '';
		if ( empty( $widget_id ) ) {
			wp_send_json_error( 'Invalid Widget ID' );
		}

		$widgets = get_option( $this->hidden_widgets_option, array() );
		// Handle legacy string format
		if ( ! is_array( $widgets ) ) {
			$widgets = array_filter( array_map( 'trim', explode( ',', $widgets ) ) );
		}

		if ( ! in_array( $widget_id, $widgets ) ) {
			$widgets[] = $widget_id;
			update_option( $this->hidden_widgets_option, array_values( $widgets ) );
		}

		wp_send_json_success( array_values( $widgets ) );
	}

	public function ajax_remove_hidden_widget() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( $_POST['widget_id'] ) : '';
		$widgets = get_option( $this->hidden_widgets_option, array() );
		// Handle legacy string format
		if ( ! is_array( $widgets ) ) {
			$widgets = array_filter( array_map( 'trim', explode( ',', $widgets ) ) );
		}

		$widgets = array_filter( $widgets, function( $id ) use ( $widget_id ) {
			return $id !== $widget_id;
		} );

		update_option( $this->hidden_widgets_option, array_values( $widgets ) );
		wp_send_json_success( array_values( $widgets ) );
	}

	public function ajax_clear_logs() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ashwab_access_logs';
		$wpdb->query( "TRUNCATE TABLE $table_name" );

		wp_send_json_success();
	}

	public function ajax_export_settings() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Get hidden widgets - handle legacy string format
		$hidden_widgets = get_option( $this->hidden_widgets_option, array() );
		if ( ! is_array( $hidden_widgets ) ) {
			$hidden_widgets = array_filter( array_map( 'trim', explode( ',', $hidden_widgets ) ) );
		}

		// Get CSS hide elements
		$css_hide_elements = get_option( $this->css_hide_elements_option, array() );

		// Get hide plugin setting
		$hide_plugin = get_option( $this->hide_plugin_option, false );

		// Get hide notices setting
		$hide_notices = get_option( $this->hide_notices_option, false );

		// Get restricted items
		$restricted_items = get_option( $this->option_name, array() );

		// Build export data structure
		$export_data = array(
			'version'     => '1.0',
			'exported_at' => current_time( 'c' ),
			'data'        => array(
				'hidden_widgets'   => array_values( $hidden_widgets ),
				'css_hide_elements' => array_values( $css_hide_elements ),
				'hide_plugin'       => (bool) $hide_plugin,
				'hide_notices'      => (bool) $hide_notices,
				'restricted_items'  => $restricted_items,
			),
		);

		wp_send_json_success( $export_data );
	}

	public function ajax_import_settings() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$import_data = isset( $_POST['import_data'] ) ? $_POST['import_data'] : '';
		if ( empty( $import_data ) ) {
			wp_send_json_error( 'No import data provided' );
		}

		// Decode JSON
		$data = json_decode( stripslashes( $import_data ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid JSON format: ' . json_last_error_msg() );
		}

		// Validate structure
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			wp_send_json_error( 'Invalid import data structure' );
		}

		$imported_data = $data['data'];
		$errors = array();

		// Import hidden widgets
		if ( isset( $imported_data['hidden_widgets'] ) && is_array( $imported_data['hidden_widgets'] ) ) {
			$hidden_widgets = array_map( 'sanitize_text_field', $imported_data['hidden_widgets'] );
			update_option( $this->hidden_widgets_option, array_values( $hidden_widgets ) );
		}

		// Import CSS hide elements
		if ( isset( $imported_data['css_hide_elements'] ) && is_array( $imported_data['css_hide_elements'] ) ) {
			$css_elements = array();
			foreach ( $imported_data['css_hide_elements'] as $element ) {
				$element = sanitize_text_field( $element );
				// Validate format
				if ( strpos( $element, '.' ) === 0 || strpos( $element, '#' ) === 0 ) {
					$css_elements[] = $element;
				}
			}
			update_option( $this->css_hide_elements_option, array_values( $css_elements ) );
		}

		// Import hide plugin setting
		if ( isset( $imported_data['hide_plugin'] ) ) {
			update_option( $this->hide_plugin_option, filter_var( $imported_data['hide_plugin'], FILTER_VALIDATE_BOOLEAN ) );
		}

		// Import hide notices setting
		if ( isset( $imported_data['hide_notices'] ) ) {
			update_option( $this->hide_notices_option, filter_var( $imported_data['hide_notices'], FILTER_VALIDATE_BOOLEAN ) );
		}

		// Import restricted items
		if ( isset( $imported_data['restricted_items'] ) && is_array( $imported_data['restricted_items'] ) ) {
			$restricted_items = array();
			foreach ( $imported_data['restricted_items'] as $item ) {
				if ( isset( $item['type'] ) && isset( $item['label'] ) && isset( $item['value'] ) ) {
					$restricted_items[] = array(
						'type'   => sanitize_text_field( $item['type'] ),
						'label'  => sanitize_text_field( $item['label'] ),
						'value'  => sanitize_text_field( $item['value'] ),
						'parent' => isset( $item['parent'] ) ? sanitize_text_field( $item['parent'] ) : '',
					);
				}
			}
			update_option( $this->option_name, $restricted_items );
		}

		// Regenerate CSS file after import
		$css_result = $this->generate_css_file();
		if ( is_wp_error( $css_result ) ) {
			$errors[] = 'CSS regeneration failed: ' . $css_result->get_error_message();
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( implode( '; ', $errors ) );
		}

		wp_send_json_success( 'Settings imported successfully' );
	}

	public function ajax_save_css_element() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$element = isset( $_POST['element'] ) ? sanitize_text_field( $_POST['element'] ) : '';
		if ( empty( $element ) ) {
			wp_send_json_error( 'Invalid Element' );
		}

		// Validation: Must start with . or #
		if ( strpos( $element, '.' ) !== 0 && strpos( $element, '#' ) !== 0 ) {
			wp_send_json_error( 'Invalid format. Must start with . or #' );
		}

		$elements = get_option( $this->css_hide_elements_option, array() );
		if ( ! in_array( $element, $elements ) ) {
			$elements[] = $element;
			update_option( $this->css_hide_elements_option, array_values( $elements ) );
		}

		wp_send_json_success( array_values( $elements ) );
	}

	public function ajax_remove_css_element() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$element = isset( $_POST['element'] ) ? sanitize_text_field( $_POST['element'] ) : '';
		$elements = get_option( $this->css_hide_elements_option, array() );

		$elements = array_filter( $elements, function( $el ) use ( $element ) {
			return $el !== $element;
		} );

		update_option( $this->css_hide_elements_option, array_values( $elements ) );
		wp_send_json_success( array_values( $elements ) );
	}

	public function ajax_regenerate_css() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$result = $this->generate_css_file();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( 'CSS Regenerated Successfully' );
	}

	public function ajax_get_pages() {
		check_ajax_referer( 'ashwab_access_hide_nonce', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		
		$args = array(
			'post_status' => 'publish',
			'sort_column' => 'post_title',
			'sort_order'  => 'ASC',
		);

		$pages = get_pages( $args );
		$results = array();

		foreach ( $pages as $page ) {
			if ( empty( $search ) || stripos( $page->post_title, $search ) !== false ) {
				$results[] = array(
					'id'   => $page->ID,
					'text' => $page->post_title,
				);
			}
		}

		wp_send_json_success( $results );
	}

	private function generate_css_file() {
		$elements = get_option( $this->css_hide_elements_option, array() );
		if ( empty( $elements ) ) {
			// If empty, create an empty file or delete it?
			// Let's create an empty file to avoid 404s if we enqueue it
			$css_content = "/* No hidden elements */";
		} else {
			$css_content = implode( ",\n", $elements ) . " {\n    display: none !important;\n}";
		}

		$upload_dir = wp_upload_dir();
		$ashwab_dir = $upload_dir['basedir'] . '/ashwab-access-hide';

		if ( ! file_exists( $ashwab_dir ) ) {
			wp_mkdir_p( $ashwab_dir );
		}

		$file_path = $ashwab_dir . '/custom-hide.css';
		if ( file_put_contents( $file_path, $css_content ) === false ) {
			return new WP_Error( 'file_write_error', 'Could not write CSS file.' );
		}

		// Update version for cache busting
		update_option( $this->css_file_version_option, time() );

		return true;
	}

	public function enqueue_generated_css() {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$file_url = $upload_dir['baseurl'] . '/ashwab-access-hide/custom-hide.css';
		$file_path = $upload_dir['basedir'] . '/ashwab-access-hide/custom-hide.css';
		$version = get_option( $this->css_file_version_option, '1.0.0' );

		if ( file_exists( $file_path ) ) {
			wp_enqueue_style( 'ashwab-custom-hide', $file_url, array(), $version );
		}
	}

	public function check_access() {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return;
		}

		$screen = get_current_screen();
		$items = get_option( $this->option_name, array() );
		
		// Get current URL relative path
		// $current_url = add_query_arg( $_GET, $screen->parent_file ); // Old logic
		
		$denied = false;
		
		foreach ( $items as $item ) {
			// Check if we are on the restricted page
			// Case 1: It's a menu slug (e.g. 'options-general.php')
			if ( strpos( $_SERVER['REQUEST_URI'], $item['value'] ) !== false ) {
				$denied = true;
				break;
			}
			
			// Case 2: It's a specific page slug (e.g. 'my-plugin-page')
			if ( isset( $_GET['page'] ) && $_GET['page'] === $item['value'] ) {
				$denied = true;
				break;
			}
		}

		if ( $denied ) {
			$this->log_access_attempt();
			$this->handle_redirect();
		}
	}

	private function log_access_attempt() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ashwab_access_logs';
		$user = wp_get_current_user();
		
		$wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user->ID,
				'user_email'    => $user->user_email,
				'attempted_url' => $_SERVER['REQUEST_URI'],
				'time'          => current_time( 'mysql' ),
				'ip_address'    => $_SERVER['REMOTE_ADDR'],
			)
		);
	}

	private function handle_redirect() {
		$redirect_settings = get_option( $this->redirect_settings_option, array( 'type' => 'default', 'value' => '' ) );
		
		if ( $redirect_settings['type'] === 'custom_url' && ! empty( $redirect_settings['value'] ) ) {
			wp_redirect( $redirect_settings['value'] );
			exit;
		} elseif ( $redirect_settings['type'] === 'page_id' && ! empty( $redirect_settings['value'] ) ) {
			$permalink = get_permalink( intval( $redirect_settings['value'] ) );
			if ( $permalink ) {
				wp_redirect( $permalink );
				exit;
			}
		}

		wp_die( 'Access Denied.' );
	}

	public function hide_menu_items() {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return;
		}

		global $submenu;
		$items = get_option( $this->option_name, array() );
		
		foreach ( $items as $item ) {
			if ( ! empty( $item['parent'] ) ) {
				remove_submenu_page( $item['parent'], $item['value'] );
			} else {
				remove_menu_page( $item['value'] );
			}

			// Brute force cleanup for submenus to ensure they are removed
			// This handles cases where parent slug might mismatch or priority issues
			if ( ! empty( $submenu ) ) {
				foreach ( $submenu as $parent => $subs ) {
					foreach ( $subs as $index => $sub ) {
						if ( isset( $sub[2] ) && $sub[2] === $item['value'] ) {
							unset( $submenu[$parent][$index] );
						}
					}
				}
			}
		}
	}

	public function hide_dashboard_widgets() {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return;
		}

		$widgets = get_option( $this->hidden_widgets_option, array() );
		
		// Handle legacy string format
		if ( ! is_array( $widgets ) ) {
			$widgets = array_filter( array_map( 'trim', explode( ',', $widgets ) ) );
		}

		if ( empty( $widgets ) ) {
			return;
		}
		foreach ( $widgets as $widget_id ) {
			remove_meta_box( $widget_id, 'dashboard', 'normal' );
			remove_meta_box( $widget_id, 'dashboard', 'side' );
			remove_meta_box( $widget_id, 'dashboard', 'column3' );
			remove_meta_box( $widget_id, 'dashboard', 'column4' );
		}
	}

	public function hide_admin_notices() {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return;
		}

		if ( get_option( $this->hide_notices_option, false ) ) {
			echo '<style>div.updated, div.error, div.notice, .update-nag { display: none !important; }</style>';
		}
	}

	public function hide_plugin_from_list( $plugins ) {
		if ( $this->is_allowed_user() || $this->is_excluded_user() ) {
			return $plugins;
		}

		if ( get_option( $this->hide_plugin_option, false ) ) {
			$plugin_basename = plugin_basename( ASHWAB_ACCESS_HIDE_PATH . 'ashwab-wp-access-and-hide.php' );
			unset( $plugins[ $plugin_basename ] );
		}

		return $plugins;
	}
}
