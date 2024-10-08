<?php

namespace MediaWiki\Extension\EmailNotifications\Widgets;

/**
 * This file is part of the MediaWiki extension EmailNotifications.
 *
 * EmailNotifications is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * EmailNotifications is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EmailNotifications.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2024, https://wikisphere.org
 */
class MenuTagMultiselectWidget extends TagMultiselectWidget {

	/**
	 * @var config
	 */
	protected $config;

	/** @inheritDoc */
	public function __construct( array $config = [] ) {
		// $this->config = $config;
		parent::__construct( $config );
	}

	/** @inheritDoc */
	public function getConfig( &$config ) {
		// $config = array_merge( $config, $this->config );
		return parent::getConfig( $config );
	}

	/** @inheritDoc */
	protected function getJavaScriptClassName() {
		return 'mw.widgets.MenuTagMultiselectWidget';
	}

}
