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

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialEmailNotificationsEvents extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = false;
		parent::__construct( 'EmailNotificationsEvents', '', $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		$this->setHeaders();
		$this->outputHeader();
		$user = $this->getUser();
		$request = $this->getRequest();
		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Extension:EmailNotifications' );

		$action = $request->getVal( 'action' );
		$notificationId = $par;

		switch ( $action ) {
			case 'unsubscribe':
				$subject = \EmailNotifications::unsubscribe( $user->getId(), $notificationId );
				$out->addWikiMsg( 'emailnotifications-unsubscribe-unsubscribe', $subject );
				break;
			case 'tracking':
				$messageId = $request->getVal( 'msgId' );
				$tablename = 'emailnotifications_events';
				$date = date( 'Y-m-d H:i:s' );
				[ $notificationId, $userid, $datetime ] = \EmailNotifications::parseMessageId( $messageId );
				$row = [
					'notification_id' => $notificationId,
					'notification_datetime' => $datetime,
					'message_id' => $messageId,
					'type' => 'read',
				];
				$dbw = \EmailNotifications::getDB( DB_PRIMARY );
				$options = [ 'IGNORE' ];
				$res = $dbw->insert(
					$tablename,
					$row + [ 'created_at' => $date ],
					__METHOD__,
					$options
				);
				ob_clean();
				// header( 'Content-Disposition: inline; filename="' . $filename . '"' );
				// transparent pixel
				// GIF89a�������!����,�������D�;
				$data = 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
				$contents = base64_decode( $data );
				header( 'Content-Length: ' . strlen( $contents ) );
				header( 'Content-type: image/gif' );
				echo $contents;
				$mediaWiki = new MediaWiki();
				$mediaWiki->restInPeace();
				exit();
		}
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'emailnotifications';
	}
}
