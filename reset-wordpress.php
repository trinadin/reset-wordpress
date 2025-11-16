<?php
/**
 * Plugin Name: Reset WordPress
 * Description: Reset the site to a fresh WordPress install state, with an option to keep existing users and/or delete uploads.
 * Version: 0.0.3
 * Author: Nathan Noom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reset_WordPress_Fresh_Install {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu() {
		add_management_page(
			'Reset WordPress',
			'Reset WordPress',
			'manage_options',
			'reset-wordpress-fresh-install',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'reset-wp-fresh' ) );
		}

		$result = null;
		$error  = null;

		if ( isset( $_POST['rwfi_run'] ) ) {
			check_admin_referer( 'rwfi_reset' );

			$confirm = isset( $_POST['rwfi_confirm'] ) ? trim( wp_unslash( $_POST['rwfi_confirm'] ) ) : '';

			if ( 'RESET' !== $confirm ) {
				$error = 'You must type "RESET" in the confirmation box to proceed.';
			} else {
				$keep_users     = ! empty( $_POST['rwfi_keep_users'] );
				$delete_uploads = ! empty( $_POST['rwfi_delete_uploads'] );

				$result = $this->reset( $keep_users, $delete_uploads );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reset WordPress', 'reset-wp-fresh' ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( $result ) : ?>
				<div class="notice notice-success">
					<p><strong><?php esc_html_e( 'The site has been reset to a fresh install.', 'reset-wp-fresh' ); ?></strong></p>

					<?php if ( 'fresh_users' === $result['mode'] ) : ?>
						<p><?php esc_html_e( 'All previous users have been removed. A new administrator user has been created:', 'reset-wp-fresh' ); ?></p>
						<ul>
							<li><?php printf( 'Username: <code>%s</code>', esc_html( $result['new_admin_login'] ) ); ?></li>
							<li><?php printf( 'Password: <code>%s</code>', esc_html( $result['new_admin_pass'] ) ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Please write this down now. You will need to log in again with these credentials.', 'reset-wp-fresh' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Existing users were preserved and re-created. You may need to log in again.', 'reset-wp-fresh' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $result['deleted_uploads'] ) ) : ?>
						<p><?php esc_html_e( 'All files in the uploads directory were deleted.', 'reset-wp-fresh' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="notice notice-warning">
				<p><strong>WARNING:</strong></p>
				<ul>
					<li>This will delete <strong>all content</strong> (posts, pages, terms, comments, etc.).</li>
					<li>This will reset <strong>all core options and settings</strong> to a fresh-install state.</li>
					<li>This will <strong>deactivate all plugins</strong> and reset themes/widgets.</li>
					<li>If you choose to delete uploads, all files in <code>wp-content/uploads</code> will be permanently removed.</li>
					<li>You will need to <strong>log in again</strong> after the reset.</li>
				</ul>
				<p><strong>This action cannot be undone. Make a full backup before continuing.</strong></p>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'rwfi_reset' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Keep Users</th>
							<td>
								<label>
									<input type="checkbox" name="rwfi_keep_users" value="1" />
									Keep existing users (they will be re-created after the reset).
								</label>
								<p class="description">
									If unchecked, a single new administrator account will be created and all existing users will be removed.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Delete Uploads</th>
							<td>
								<label>
									<input type="checkbox" name="rwfi_delete_uploads" value="1" />
									Delete all files in <code>wp-content/uploads</code>.
								</label>
								<p class="description">
									This will permanently delete all uploaded media files. Use with extreme caution.
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">Confirmation</th>
							<td>
								<p>
									To confirm, type <code>RESET</code> in the box below:
								</p>
								<input type="text" name="rwfi_confirm" value="" class="regular-text" />
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button
						class="button button-primary"
						type="submit"
						name="rwfi_run"
						value="1"
						onclick="return confirm('Are you absolutely sure? This will reset the site to a fresh install and may delete all content, settings, and uploads.');"
					>
						<?php esc_html_e( 'Reset Site to Fresh Install', 'reset-wp-fresh' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Perform the reset.
	 *
	 * @param bool $keep_users     Whether to preserve and re-create existing users.
	 * @param bool $delete_uploads Whether to delete uploads directory contents.
	 *
	 * @return array Result data.
	 */
	public function reset( $keep_users, $delete_uploads ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to reset this site.', 'reset-wp-fresh' ) );
		}

		if ( is_multisite() ) {
			wp_die( esc_html__( 'This plugin cannot be used on multisite installations.', 'reset-wp-fresh' ) );
		}

		global $wpdb;

		@set_time_limit( 0 );

		$result = [
			'mode'            => $keep_users ? 'keep_users' : 'fresh_users',
			'new_admin_login' => null,
			'new_admin_pass'  => null,
			'deleted_uploads' => false,
		];

		// Preserve basic site info for re-install.
		$blogname     = get_option( 'blogname', 'My WordPress Site' );
		$admin_email  = get_option( 'admin_email', '' );
		$blog_public  = (int) get_option( 'blog_public', 1 );

		// Preserve users if requested.
		$preserved_users = [];

		if ( $keep_users ) {
			$users = get_users(
				[
					'fields' => 'all_with_meta',
				]
			);

			foreach ( $users as $user ) {
				$user_meta = get_user_meta( $user->ID );

				$preserved_users[] = [
					'user_login'     => $user->user_login,
					'user_email'     => $user->user_email,
					'user_nicename'  => $user->user_nicename,
					'user_registered'=> $user->user_registered,
					'display_name'   => $user->display_name,
					'user_url'       => $user->user_url,
					'user_pass'      => $user->user_pass, // hashed password
					'meta'           => $user_meta,
				];
			}
		}

		// Drop all existing tables for this prefix.
		$all_tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $wpdb->prefix ) . '%'
			)
		);

		if ( $all_tables ) {
			foreach ( $all_tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		// Re-create core DB schema and run standard WordPress install logic.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( wp_get_db_schema() );

		if ( ! defined( 'WP_INSTALLING' ) ) {
			define( 'WP_INSTALLING', true );
		}

		$admin_user = 'admin';
		$admin_pass = wp_generate_password( 16 );

		$result['new_admin_login'] = $admin_user;
		$result['new_admin_pass']  = $admin_pass;

		// wp_install sets options and creates the default admin user & content.
		// This gives us a true "fresh install" baseline.
		wp_install(
			$blogname ? $blogname : 'My WordPress Site',
			$admin_user,
			$admin_email ? $admin_email : 'admin@example.com',
			(bool) $blog_public,
			'',
			$admin_pass
		);

		// At this point, DB is like a fresh install.
		// If we want to keep users, re-create them on top of this fresh DB.
		if ( $keep_users && ! empty( $preserved_users ) ) {
			foreach ( $preserved_users as $user_data ) {

				// Avoid clashing with the new admin user.
				if ( $user_data['user_login'] === $admin_user ) {
					continue;
				}

				// If a user with this login already exists (unlikely right after install),
				// skip to avoid collisions.
				if ( username_exists( $user_data['user_login'] ) ) {
					continue;
				}

				$new_user_id = wp_insert_user(
					[
						'user_login'    => $user_data['user_login'],
						'user_email'    => $user_data['user_email'],
						'user_pass'     => wp_generate_password( 20 ), // temporary, will be overridden.
						'user_nicename' => $user_data['user_nicename'],
						'user_url'      => $user_data['user_url'],
						'display_name'  => $user_data['display_name'],
						'role'          => '', // we'll restore from meta if present.
					]
				);

				if ( is_wp_error( $new_user_id ) ) {
					continue;
				}

				// Override hashed password directly in DB to preserve original login credentials.
				$wpdb->update(
					$wpdb->users,
					[ 'user_pass' => $user_data['user_pass'] ],
					[ 'ID' => $new_user_id ],
					[ '%s' ],
					[ '%d' ]
				);

				// Restore user meta.
				if ( ! empty( $user_data['meta'] ) && is_array( $user_data['meta'] ) ) {
					foreach ( $user_data['meta'] as $meta_key => $meta_values ) {

						// Remove any auto-added meta first to avoid duplication of things like session tokens.
						delete_user_meta( $new_user_id, $meta_key );

						foreach ( $meta_values as $meta_value ) {
							// get_user_meta() already returns unserialized values.
							add_user_meta( $new_user_id, $meta_key, $meta_value );
						}
					}
				}
			}

			$result['mode'] = 'keep_users';
		}

		// Optionally delete uploads directory contents.
		if ( $delete_uploads ) {
			$upload_dir = wp_get_upload_dir();

			if ( ! empty( $upload_dir['basedir'] ) && is_dir( $upload_dir['basedir'] ) ) {
				$this->delete_directory_contents( $upload_dir['basedir'] );
				$result['deleted_uploads'] = true;
			}
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		flush_rewrite_rules();

		return $result;
	}

	/**
	 * Recursively delete all files and folders inside a directory (but not the directory itself).
	 *
	 * @param string $dir Directory path.
	 */
	protected function delete_directory_contents( $dir ) {
		$dir = realpath( $dir );

		if ( ! $dir || ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $item;

			// Safety: never go above this directory.
			if ( strpos( realpath( $path ), $dir ) !== 0 ) {
				continue;
			}

			if ( is_dir( $path ) ) {
				$this->delete_directory_contents( $path );
				@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}
}

new Reset_WordPress_Fresh_Install();