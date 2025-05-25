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
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright © 2024, https://wikisphere.org
 */

$( () => {
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#emailnotifications-form-notifications button[type="submit"]' ).on(
		'click',
		// eslint-disable-next-line no-unused-vars
		function ( val ) {
			if ( $( this ).val() === 'delete' ) {
				// eslint-disable-next-line no-alert
				if ( !confirm( mw.msg( 'emailnotifications-jsmodule-deleteitemconfirm' ) ) ) {
					return false;
				}
			}
		}
	);
} );
