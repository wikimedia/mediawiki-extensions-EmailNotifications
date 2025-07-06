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

namespace MediaWiki\Extension\EmailNotifications\Pagers;

use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MWException;
use ParserOutput;
use SpecialPage;
use TablePager;

class Activity extends TablePager {

	/** @var Request */
	private $request;

	/** @var SpecialEmailNotifications */
	private $parentClass;

		/** @var int */
	private $notificationId;

	// @IMPORTANT!, otherwise the pager won't show !
	/** @var mLimit */
	public $mLimit = 30;

	/**
	 * @param SpecialEmailNotifications $parentClass
	 * @param Request $request
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct( $parentClass, $request, LinkRenderer $linkRenderer ) {
		parent::__construct( $parentClass->getContext(), $linkRenderer );

		$this->request = $request;
		$this->parentClass = $parentClass;
		$this->notificationId = $parentClass->par;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultDirections() {
		return self::DIR_DESCENDING;
	}

	/**
	 * @inheritDoc
	 */
	public function getFullOutput() {
		$navigation = $this->getNavigationBar();
		// $body = parent::getBody();

		$parentParent = get_parent_class( get_parent_class( $this ) );
		$body = $parentParent::getBody();

		$pout = new ParserOutput;
		// $navigation .
		$pout->setText( $body . $navigation );
		$pout->addModuleStyles( $this->getModuleStyles() );
		return $pout;
	}

	/**
	 * @return array
	 */
	protected function getFieldNames() {
		$headers = [
			'user' => 'emailnotifications-manage-pager-header-user',
			'notification' => 'emailnotifications-manage-pager-header-notification',
			'date' => 'emailnotifications-manage-pager-header-date',
			'event' => 'emailnotifications-manage-pager-header-event',
		];

		foreach ( $headers as $key => $val ) {
			$headers[$key] = $this->msg( $val )->text();
		}

		return $headers;
	}

	/**
	 * @param string $field
	 * @param string $value
	 * @return string HTML
	 * @throws MWException
	 */
	public function formatValue( $field, $value ) {
		/** @var object $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();
		$formatted = '';

		switch ( $field ) {
			case 'user':
				[ $notificationId, $userid, $datetime ] = \EmailNotifications::parseMessageId( $row->message_id ) + [ null, 0, null ];
				$services = MediaWikiServices::getInstance();
				$userIdentityLookup = $services->getUserIdentityLookup();
				$user = $userIdentityLookup->getUserIdentityByUserId( $userid );
				if ( $user ) {
					$formatted = $user->getName();
				}
				break;

			case 'notification':
				$link = \EmailNotifications::getNotificationSubject( $row->notification_id );
				$title = SpecialPage::getTitleFor( 'EmailNotifications', $row->notification_id );
				$query = [ 'action' => 'view' ];
				$formatted = Linker::link( $title, $link, [], $query );
				break;

			case 'date':
				$formatted = htmlspecialchars(
					$this->getLanguage()->userTimeAndDate(
						wfTimestamp( TS_MW, $row->created_at ),
						$this->getUser()
					)
				);
				break;

			case 'event':
				$formatted = $row->type;
				break;

			default:
				throw new MWException( "Unknown field '$field'" );
		}

		return $formatted;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$ret = [];

		$tables = [ 'emailnotifications_events' ];
		$fields = [ '*' ];
		$join_conds = [];
		$conds = [];
		$subject = $this->request->getVal( 'subject' );

		if ( !empty( $this->notificationId ) ) {
			$conds['notification_id'] = $this->notificationId;

		} elseif ( !empty( $subject ) ) {
			$conds['notification_id'] = \EmailNotifications::notificationIdFromSubject( $subject );
		}

		$datetime = $this->request->getVal( 'datetime' );
		if ( !empty( $datetime ) ) {
			$conds['notification_datetime'] = $datetime;
		}

		$options = [];

		array_unique( $tables );

		$ret['tables'] = $tables;
		$ret['fields'] = $fields;
		$ret['join_conds'] = $join_conds;
		$ret['conds'] = $conds;
		$ret['options'] = $options;

		return $ret;
	}

	/**
	 * @return string
	 */
	protected function getTableClass() {
		return parent::getTableClass() . ' emailnotifications-manage-notifications-pager-table';
	}

	/**
	 * @return string
	 */
	public function getIndexField() {
		return 'created_at';
	}

	/**
	 * @return string
	 */
	public function getDefaultSort() {
		return 'created_at';
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	protected function isFieldSortable( $field ) {
		// no index for sorting exists
		return false;
	}
}
