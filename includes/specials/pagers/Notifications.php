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
use SpecialPage;
use TablePager;
use Title;

class Notifications extends TablePager {

	/** @var Request */
	private $request;

	/** @var SpecialEmailNotifications */
	private $parentClass;

	/** @var array */
	private $groups;

	/**
	 * @inheritDoc
	 */
	public function __construct( $parentClass, $request, LinkRenderer $linkRenderer ) {
		parent::__construct( $parentClass->getContext(), $linkRenderer );

		$this->request = $request;
		$this->parentClass = $parentClass;
		$this->groups = $this->parentClass->groupsList();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFieldNames() {
		$headers = [
			'created_by' => 'emailnotifications-manage-pager-header-created_by',
			'groups' => 'emailnotifications-manage-pager-header-groups',
			'page' => 'emailnotifications-manage-pager-header-page',
			'subject' => 'emailnotifications-manage-pager-header-subject',
			'frequency' => 'emailnotifications-manage-pager-header-frequency',
			'enabled' => 'emailnotifications-manage-pager-header-enabled',
			'actions' => 'emailnotifications-manage-pager-header-actions',
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
			case 'created_by':
				$services = MediaWikiServices::getInstance();
				$userIdentityLookup = $services->getUserIdentityLookup();
				$user = $userIdentityLookup->getUserIdentityByUserId( $row->created_by );
				$formatted = $user->getName();
				break;

			case 'groups':
				$groups = array_flip( $this->groups );
				$formatted = implode( ', ', array_map( static function ( $value ) use ( $groups ) {
					return $groups[$value];
				}, explode( ',', $row->groups ) ) );
				break;

			case 'page':
				$title_ = Title::newFromId( $row->page );
				$query = [];
				$formatted = Linker::link( $title_, $title_->getFullText(), [], $query );
				break;

			case 'enabled':
				$formatted = $this->msg( 'emailnotifications-manage-table-enabled-'
					. ( $row->enabled ? 'yes' : 'no' ) )->text();
				break;

			case 'actions':
				$formatted = '<span style="white-space:nowrap">';
				$link = '<span class="mw-ui-button mw-ui-progressive">' .
					$this->msg( 'emailnotifications-manage-table-button-edit' )->text() . '</span>';
				$title = SpecialPage::getTitleFor( 'EmailNotifications', $row->id );
				$query = [ 'action' => 'edit' ];
				$formatted .= Linker::link( $title, $link, [], $query );

				$query = [ 'action' => 'view' ];
				$link = '<span style="margin-left:2px" class="mw-ui-button mw-ui-progressive">' .
					$this->msg( 'emailnotifications-manage-table-button-view' )->text() . '</span>';
				$formatted .= Linker::link( $title, $link, [], $query );
				$formatted .= '</span>';
				break;

			case 'subject':
			case 'frequency':
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

		$tables = [ 'emailnotifications_notifications' ];
		$fields = [ '*' ];
		$join_conds = [];
		$conds = [];
		$options = [];

		$created_by = $this->request->getVal( 'created_by' );

		if ( !empty( $created_by ) ) {
			$services = MediaWikiServices::getInstance();
			$userIdentityLookup = $services->getUserIdentityLookup();
			$user = $userIdentityLookup->getUserIdentityByName( $created_by );
			$created_by = ( $user ? $user->getId() : 0 );
			$conds[ 'created_by' ] = $created_by;
		}

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
