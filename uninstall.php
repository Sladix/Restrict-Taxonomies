<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

delete_option( 'RestrictTaxs_options' );
delete_option( 'RestrictTaxs_user_options' );
delete_option( 'RestrictTaxs_post_type_options' );