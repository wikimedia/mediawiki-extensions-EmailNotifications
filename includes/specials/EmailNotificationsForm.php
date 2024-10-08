<?php

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

class EmailNotificationsForm extends OOUIHTMLForm {

	/**
	 * @inheritDoc
	 */
	public function getHTML( $submitResult ) {
		$html = parent::getHTML( $submitResult );

		if ( !class_exists( 'DOMDocument' ) ) {
			return $html;
		}

		// *** hiding annoying "hidden" textarea on pageload
		// unfortunately the style onBeforePageDisplay
		// is not sufficient

		libxml_use_internal_errors( true );
		$dom = new DOMDocument;
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		$finder = new DomXPath( $dom );
		$classname = 'mw-widgets-tagMultiselectWidget-multilineTextInputWidget';
		$nodes = $finder->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]" );
		foreach ( $nodes as $item ) {
			$item->setAttribute( 'style', 'display:none' );
		}
		return $dom->saveHTML();
	}

}
