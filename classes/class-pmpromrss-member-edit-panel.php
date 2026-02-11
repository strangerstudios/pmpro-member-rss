<?php
class PMPROMRSS_Member_Edit_Panel extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug        = 'member-rss';
		$this->title       = __( 'Member RSS', 'pmpro-member-rss' );
	}

	/**
	 * Display the panel contents.
	 */
	protected function display_panel_contents() {
		pmpromrss_memberkeys_profile( self::get_user() );
	}
}