<?php

namespace Actions;

use Helper\WpSubPage;

class SettingsPage extends WpSubPage {

	public function render_settings_page() {
		$option_name = $this->settings_page_properties['option_name'];
		$option_group = $this->settings_page_properties['option_group'];
		$settings_data = $this->get_settings_data();
		?>
        <div class="wrap">

        </div>
		<?php
	}

	public function get_default_settings_data() {
		$defaults = array();
		$defaults['textbox'] = '';

		return $defaults;
	}
}
