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
 * along with EmailNotifications.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2024, https://wikisphere.org
 */

use MediaWiki\Extension\EmailNotifications\Mailer;
use MediaWiki\Logger\LoggerFactory;

class EmailNotificationsHooks {

	/**
	 * @param array $credits
	 * @return void
	 */
	public static function initExtension( $credits = [] ) {
		EmailNotifications::$Logger = LoggerFactory::getInstance( 'EmailNotifications' );
	}

	/**
	 * @param Title &$title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki|MediaWiki\Actions\ActionEntryPoint $mediaWiki $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize( &$title, $unused, $output, $user, $request, $mediaWiki ) {
		// this will enforce required true in
		// LoginSignupSpecialPage form
		if ( $GLOBALS['wgEmailNotificationsCreateAccountEmailRequired']
			&& $title->getFullText() === 'Special:CreateAccount' ) {
			$GLOBALS['wgEmailConfirmToEdit'] = true;
		}
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$base = __DIR__;
		$dbType = $updater->getDB()->getType();
		$tables = [
			'emailnotifications_notifications',
			'emailnotifications_unsubscribe',
			'emailnotifications_sent',
			'emailnotifications_events',
		];
		foreach ( $tables as $value ) {
			if ( file_exists( "$base/../$dbType/$value.sql" ) ) {
				$updater->addExtensionUpdate(
					[
						'addTable',
						$value,
						"$base/../$dbType/$value.sql",
						true
					]
				);
			}
		}
	}

	public static function onGetPreferences( $user, &$preferences ) {
	}

	/**
	 * @param array $headers
	 * @param MailAddress $to
	 * @param MailAddress $from
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 */
	public static function onAlternateUserMailer( array $headers, array $to, MailAddress $from, $subject, $body ) {
		if ( array_key_exists( 'EmailNotifications-ListUnsubscribe', $headers ) ) {
			$headers['List-Unsubscribe'] = $headers['EmailNotifications-ListUnsubscribe'];
			unset( $headers['EmailNotifications-ListUnsubscribe'] );
			if ( $GLOBALS['wgEmailNotificationsUnsubscribeLink'] ) {
				$body .= wfMessage( 'emailnotifications-email-unsubscribe',
					str_replace( [ '<', '>' ], '', $headers['List-Unsubscribe'] ) )->text();	
			}
		}

		if ( array_key_exists( 'EmailNotifications-TrackingUrl', $headers ) ) {
			if ( $GLOBALS['wgEmailNotificationsEmailTracking'] ) {
				$body .= '<img alt="" border="0" width="1" height="1" src="' . $headers['EmailNotifications-TrackingUrl'] . '" style="height: 1.0px;width: 1.0px;border-width: 0;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;" />';
			}
		}

		$errors = [];
		EmailNotifications::sendEmail(
			$headers,
			$to,
			$from,
			$subject,
			null,
			$body,
			$errors
		);

		if ( count( $errors ) ) {
			foreach ( $errors as $msg ) {
				EmailNotifications::$Logger->error( $msg );
			}
		}

		// prevent regular sending
		return false;
	}

}
