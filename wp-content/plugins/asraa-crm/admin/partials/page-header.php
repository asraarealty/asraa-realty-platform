<?php
/**
 * Shared admin page header/chrome, printed by Asraa_CRM_Admin_Menu::render_page()
 * before the page file itself is included. Expects $title (string) in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="asraa-page-head">
	<h1 class="asraa-page-head__title"><?php echo esc_html( $title ); ?></h1>
</div>
