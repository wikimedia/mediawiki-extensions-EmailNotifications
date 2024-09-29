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
use ParserOutput;
use SpecialPage;
use TablePager;

class Sent extends TablePager {

	/** @var Request */
	private $request;

	/** @var SpecialEmailNotifications */
	private $parentClass;

	/** @var int */
	private $notificationId;

	// @IMPORTANT!, otherwise the pager won't show !
	/** @var mLimit */
	public $mLimit = 20;

	/**
	 * @inheritDoc
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
	 * @inheritDoc
	 */
	protected function getFieldNames() {
		$headers = [
			'recipients' => 'emailnotifications-manage-pager-header-recipients',
			'date' => 'emailnotifications-manage-pager-header-date',
			'text' => 'emailnotifications-manage-pager-header-text',
			'actions' => 'emailnotifications-manage-pager-header-actions'
		];

		foreach ( $headers as $key => $val ) {
			$headers[$key] = $this->msg( $val )->text();
		}

		return $headers;
	}

	/**
	 * @inheritDoc
	 */
	public function formatValue( $field, $value ) {
		/** @var object $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();

		switch ( $field ) {
			case 'date':
				$formatted = htmlspecialchars(
					$this->getLanguage()->userTimeAndDate(
						wfTimestamp( TS_MW, $row->created_at ),
						$this->getUser()
					)
				);
				break;

			case 'text':
				$formatted = substr( $row->text, 0, 100 )
					. ( strlen( $row->text ) > 100 ? '...' : '' );
				break;

			case 'actions':
				$link = '<span class="mw-ui-button mw-ui-progressive">' .
					$this->msg( 'emailnotifications-manage-table-button-activity' )->text() . '</span>';
				$title = SpecialPage::getTitleFor( 'EmailNotificationsActivity' );
				$subject = \EmailNotifications::getNotificationSubject( $this->notificationId );
				$query = [ 'subject' => $subject, 'datetime' => $row->created_at ];
				$formatted = Linker::link( $title, $link, [], $query );
				break;

			case 'recipients':
				$formatted = ( (array)$row )[$field];
				break;

			default:
				throw new MWException( "Unknown field '$field'" );
		}

		return $formatted;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$ret = [];

		$tables = [ 'emailnotifications_sent' ];
		$fields = [ '*' ];
		$join_conds = [];
		$conds = [ 'notification_id' => $this->notificationId ];
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
	 * @inheritDoc
	 */
	protected function getTableClass() {
		return parent::getTableClass() . ' emailnotifications-manage-notifications-pager-table';
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'created_at';
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return 'created_at';
	}

	/**
	 * @inheritDoc
	 */
	protected function isFieldSortable( $field ) {
		// no index for sorting exists
		return false;
	}
}
