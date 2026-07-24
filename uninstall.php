<?php
/**
 * Runs only when the plugin is deleted from wp-admin (not on simple
 * deactivation). Same deliberately conservative approach as the rest
 * of this family — removes the plugin's own settings/version marker,
 * but leaves every listing and its meta in place. Cron is already
 * unscheduled on deactivation, which always happens before deletion.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tcar_plugin_version' );
delete_option( 'tcar_settings' );
