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
 * @copyright Copyright ©2024-2025, https://wikisphere.org
 */

if ( is_readable( __DIR__ . '/../../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../../vendor/autoload.php';
}

require_once __DIR__ . '/EmailNotificationsForm.php';

use MediaWiki\Extension\EmailNotifications\Aliases\Linker as LinkerClass;
use MediaWiki\Parser\ParserOptions;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialEmailNotificationsActivity extends SpecialPage {

	/** @var User */
	private $user;

	/** @var Request */
	private $request;

	/** @var int */
	public $par;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'EmailNotificationsActivity' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		$this->setHeaders();
		$this->outputHeader();
		$this->par = (int)$par;

		$user = $this->getUser();
		$isAuthorized = \EmailNotifications::isAuthorizedGroup( $user );

		if ( !$isAuthorized ) {
			if ( !$user->isAllowed( 'emailnotifications-can-manage-notifications' ) ) {
				$this->displayRestrictionError();
				return;
			}
		}

		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );

		$out->addModules( [ 'ext.EmailNotifications' ] );

		$this->addHelpLink( 'Extension:EmailNotifications' );

		$request = $this->getRequest();

		$this->request = $request;

		$this->user = $user;

		$this->addJsConfigVars( $out );

		$notificationId = $request->getVal( 'notification_id' );

		$class = 'Activity';
		$class = "MediaWiki\\Extension\\EmailNotifications\\Pagers\\$class";
		$pager = new $class(
			$this,
			$request,
			$this->getLinkRenderer()
		);

		$out->enableOOUI();

		$subject = $request->getVal( 'subject' );
		if ( $subject ) {
			$notificationId = \EmailNotifications::notificationIdFromSubject( $subject );
			$title_ = SpecialPage::getTitleFor( 'EmailNotifications', $notificationId );
			$query = [ 'action' => 'view' ];
			$link = $this->msg( 'emailnotifications-activity-form-returnlink-text' )->text();
			$out->addHTML( LinkerClass::link( $title_, $link, [], $query ) );
			$out->addHTML( '<br />' );
		}

		$out->addWikiMsg( 'emailnotifications-manage-description-activity' );

		$out->addHTML( $this->showOptions( $request ) );
		$out->addHTML( '<br />' );

		if ( $pager->getNumRows() ) {
			$out->addParserOutputContent(
				$pager->getFullOutput(),
				ParserOptions::newFromContext( $this->getContext() )
			);

		} else {
			$out->addWikiMsg( 'emailnotifications-manage-table-empty' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return false;
	}

	/**
	 * @param Output $out
	 */
	private function addJsConfigVars( $out ) {
		$out->addJsConfigVars( [
			// 'emailnotifications-config' => [
			// 	'canManageNotifications' => $this->user->isAllowed( 'emailnotifications-can-manage-notifications' ),
			// 	'showNoticeOutdatedVersion' => empty( $GLOBALS['wgEmailNotificationsDisableVersionCheck'] )
			// ]
		] );
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	protected function showOptions( $request ) {
		$formDescriptor = [];

		$subject = $request->getVal( 'subject' );
		// $datetime = $request->getVal( 'datetime' );
		// $user = $request->getVal( 'user' );

		$formDescriptor['subject'] = [
			'label-message' => 'emailnotifications-manage-form-search-subject-label',
			'type' => 'text',
			'name' => 'subject',
			'required' => false,
			'help-message' => 'emailnotifications-manage-form-search-subject-help',
			'default' => ( !empty( $subject ) ? $subject : null ),
		];

		// $formDescriptor['user'] = [
		// 	'label-message' => 'emailnotifications-manage-form-search-user-label',
		// 	'type' => 'user',
		// 	'name' => 'user',
		// 	'required' => false,
		// 	'help-message' => 'emailnotifications-manage-form-search-user-help',
		// 	'default' => ( !empty( $created_by ) ? $created_by : null ),
		// ];

		// $formDescriptor['datetime'] = [
		// 	'label-message' => 'emailnotifications-manage-form-search-datetime-label',
		// 	'type' => 'datetime',
		// 	'name' => 'datetime',
		// 	'required' => false,
		// 	'help-message' => 'emailnotifications-manage-form-search-datetime-help',
		// 	'default' => ( !empty( $datetime ) ? $datetime : null ),
		// ];

		$htmlForm = new EmailNotificationsForm( $formDescriptor, $this->getContext() );

		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'emailnotifications-managepermissions-form-search-legend' )
			->setSubmitText( $this->msg( 'emailnotifications-managepermissions-form-search-submit' )->text() );

		return $htmlForm->prepareForm()->getHTML( false );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'emailnotifications';
	}
}
