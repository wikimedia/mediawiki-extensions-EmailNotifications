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

use MediaWiki\Extension\EmailNotifications\Aliases\Title as TitleClass;
use MediaWiki\Extension\EmailNotifications\Widgets\HTMLMenuTagMultiselectField;
use MediaWiki\Parser\ParserOptions;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialEmailNotifications extends SpecialPage {

	/** @var int */
	public $par;

	/** @var par */
	public $action;

	/** @var Title|MediaWiki\Title\Title */
	public $localTitle;

	/** @var Title|MediaWiki\Title\Title */
	public $localTitlePar;

	/** @var User */
	private $user;

	/** @var Request */
	private $request;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'EmailNotifications' );
	}

	/**
	 * @return string|Message
	 */
	public function getDescription() {
		$action = $this->getRequest()->getVal( 'action' );

		if ( !$action ) {
			$msg = $this->msg( 'emailnotifications' );
		} else {
			$title = $this->getContext()->getTitle();
			$bits = explode( '/', $title->getDbKey(), 2 );
			if ( count( $bits ) === 1 ) {
				$msg = $this->msg( 'emailnotifications-item-create' );
			} else {
				$par = (int)$bits[1];
				$msg = $this->msg( 'emailnotifications-item' . ( $action === 'edit' ? '-edit' : '-view' ),
					\EmailNotifications::getNotificationSubject( $par ) );
			}
		}

		if ( version_compare( MW_VERSION, '1.40', '>' ) ) {
			return $msg;
		}
		return $msg->text();
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

		$this->localTitle = SpecialPage::getTitleFor( 'EmailNotifications' );
		$this->localTitlePar = SpecialPage::getTitleFor( 'EmailNotifications', $this->par );

		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );
		$out->enableOOUI();

		$out->addModules( [ 'ext.EmailNotifications' ] );

		$this->addHelpLink( 'Extension:EmailNotifications' );

		$request = $this->getRequest();

		$this->request = $request;

		$this->user = $user;

		$this->addJsConfigVars( $out );

		if ( !class_exists( 'Cron\CronExpression' ) ) {
			$out->addHTML( new \OOUI\MessageWidget( [
				'type' => 'error',
				'label' => new \OOUI\HtmlSnippet( $this->msg( 'emailnotifications-manage-missing-libraries' )->parse() )
			] ) );
			return;
		}

		$action = $request->getVal( 'action' );

		switch ( $action ) {
			case 'delete':
				\EmailNotifications::deleteItem( [ 'id' => $this->par ] );
				header( 'Location: ' . $this->localTitle->getLocalURL() );
				return;

			case 'cancel':
				header( 'Location: ' . $this->localTitle->getLocalURL() );
				return;

			case 'edit':
				$this->editItem( $request, $out );
				return;

			default:
				$out->addWikiMsg( 'emailnotifications-manage-description' );
				$class = 'Notifications';

				$layout = new OOUI\PanelLayout(
					[ 'id' => 'emailnotifications-panel-layout', 'expanded' => false, 'padded' => false, 'framed' => false ]
				);

				$layout->appendContent(
					new OOUI\ButtonWidget(
						[
							'href' => wfAppendQuery( $this->localTitle->getLocalURL(), [ 'action' => 'edit' ] ),
							'label' => $this->msg( 'emailnotifications-manage-form-button-add-notification' )->text(),
							'infusable' => true,
							'flags' => [ 'progressive', 'primary' ],
						]
					)
				);

				$out->addHTML( $layout );
				$out->addHTML( '<br />' );
				$out->addHTML( $this->showOptions( $request ) );
				$out->addHTML( '<br />' );

				break;
			case 'view':
				$class = 'Sent';
				$out->addWikiMsg(
					'emailnotifications-manage-form-returnlink',
					$this->localTitle->getFullText()
				);
				$out->addWikiMsg( 'emailnotifications-manage-description-view' );
				break;
		}

		$class = "MediaWiki\\Extension\\EmailNotifications\\Pagers\\$class";
		$pager = new $class(
			$this,
			$request,
			$this->getLinkRenderer()
		);

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
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function editItem( $request, $out ) {
		$action = $request->getVal( 'action' );
		$new = !$this->par;

		$row = [];
		if ( !$new ) {
			$dbr = \EmailNotifications::getDb( DB_REPLICA );
			$tablename = 'emailnotifications_notifications';
			$row = $dbr->selectRow( $tablename, '*', [ 'id' => $this->par ], __METHOD__ );
			$row = (array)$row;
		}

		if ( !$row || $row == [ false ] ) {
			if ( !$new ) {
				$out->addWikiMsg( 'emailnotifications-manage-form-missing-item' );
				$out->addHTML( '<br />' );
				return;
			}

			$row = [
				'ugroups' => '',
				'page' => '',
				'subject' => '',
				'frequency' => '',
				'must_differ' => true,
				'skip_strategy' => 'contains',
				'skip_text' => null,
				'enabled' => true
			];

		} else {
			$row['ugroups'] = str_replace( ",", "\n", $row['ugroups'] );
			$page = TitleClass::newFromID( $row['page'] );
			if ( $page ) {
				$row['page'] = $page->getFullText();
			} else {
				$row['page'] = '';
			}
		}

		$formDescriptor = $this->getFormDescriptor( $row, $out );

		$messagePrefix = 'emailnotifications-manage';
		$htmlForm = new EmailNotificationsForm( $formDescriptor, $this->getContext(), $messagePrefix );

		$htmlForm->setId( 'emailnotifications-form-notifications' );

		$htmlForm->setMethod( 'post' );

		$htmlForm->setSubmitCallback( [ $this, 'onSubmit' ] );

		$htmlForm->showCancel();

		$htmlForm->setCancelTarget( $this->localTitle->getLocalURL() );

		$htmlForm->setSubmitTextMsg( 'emailnotifications-manage-form-button-submit' );

		$out->addWikiMsg(
			'emailnotifications-manage-form-returnlink',
			$this->localTitle->getFullText()
		);

		$out->addWikiMsg( 'emailnotifications-manage-form-preamble' );

		$htmlForm->prepareForm();

		$result = $htmlForm->tryAuthorizedSubmit();

		$htmlForm->setAction(
			wfAppendQuery( $this->localTitlePar->getLocalURL(),
				[ 'action' => 'edit' ] )
		);

		if ( !$new ) {
			$htmlForm->addButton(
				[
				'type' => 'button',
				'name' => 'action',
				'value' => 'delete',
				'href' => $this->localTitle->getLocalURL(),
				'label-message' => 'emailnotifications-manage-form-button-delete',
				'flags' => [ 'destructive' ]
				]
			);
		}

		$htmlForm->displayForm( $result );
	}

	/**
	 * @param array $row
	 * @param Output $out
	 * @return array
	 */
	protected function getFormDescriptor( $row, $out ) {
		$formDescriptor = [];
		$section_prefix = '';

		HTMLForm::$typeMappings['menutagmultiselect'] = HTMLMenuTagMultiselectField::class;

		$groups = $this->groupsList();
		$formDescriptor['ugroups'] = [
			'label-message' => 'emailnotifications-manage-form-groups-label',
			'type' => 'menutagmultiselect',
			'name' => 'ugroups',
			'required' => true,
			'infusable' => true,
			'allowArbitrary' => true,
			// computed: "emailnotifications-manage-form-fieldset-notifications-main"
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-groups-help',
			'default' => $row['ugroups'],
			'options' => array_flip( $groups ),
		];

		$formDescriptor['page'] = [
			'label-message' => 'emailnotifications-manage-form-page-label',
			'type' => 'title',
			'name' => 'page',
			'required' => true,
			'exists' => true,
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-page-help',
			'default' => $row['page']
		];

		$formDescriptor['subject'] = [
			'label-message' => 'emailnotifications-manage-form-subject-label',
			'type' => 'text',
			'name' => 'subject',
			'required' => true,
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-subject-help',
			'default' => $row['subject']
		];

		$formDescriptor['frequency'] = [
			'label-message' => 'emailnotifications-manage-form-frequency-label',
			'type' => 'text',
			'name' => 'frequency',
			'required' => true,
			'exists' => true,
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-frequency-help',
			'default' => $row['frequency'],
			'validation-callback' => static function ( $value ) {
				try {
					$cron = new Cron\CronExpression( $value );
				} catch ( Exception $e ) {
					return $e->getMessage() . PHP_EOL;
				}
				return true;
			},
		];

		$formDescriptor['skip_strategy'] = [
			'label-message' => 'emailnotifications-manage-form-skip_strategy-label',
			'type' => 'select',
			'name' => 'skip_strategy',
			'required' => false,
			'cssclass' => 'emailnotifications-skip_strategy',
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-skip_strategy-help',
			'default' => $row['skip_strategy'],
			'options' => [
				$this->msg( 'emailnotifications-manage-form-options-skip-contains' )->text() => 'contains',
				$this->msg( 'emailnotifications-manage-form-options-skip-does_not_contain' )->text() => 'does not contain',
				$this->msg( 'emailnotifications-manage-form-options-skip-regex' )->text() => 'regex',
			]
		];

		$formDescriptor['skip_text'] = [
			'label-message' => 'emailnotifications-manage-form-skip_text-label',
			'type' => 'text',
			'name' => 'skip_text',
			'required' => false,
			'cssclass' => 'emailnotifications-skip_text',
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-skip_text-help',
			'default' => $row['skip_text'],
		];

		$formDescriptor['must_differ'] = [
			'label-message' => 'emailnotifications-manage-form-must_differ-label',
			'type' => 'toggle',
			'name' => 'must_differ',
			'required' => false,
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-must_differ-help',
			'default' => $row['must_differ']
		];

		$formDescriptor['enabled'] = [
			'label-message' => 'emailnotifications-manage-form-enabled-label',
			'type' => 'toggle',
			'name' => 'enabled',
			'required' => false,
			'section' => $section_prefix . 'form-fieldset-notifications-main',
			'help-message' => 'emailnotifications-manage-form-enabled-help',
			'default' => $row['enabled']
		];

		$submitted = $this->request->getCheck( 'usernames' );

		return $formDescriptor;
	}

	/**
	 * @param Output $out
	 */
	private function addJsConfigVars( $out ) {
		$out->addJsConfigVars( [
			'emailnotifications-config' => [
				'canManageNotifications' => $this->user->isAllowed( 'emailnotifications-can-manage-notifications' ),
				'showNoticeOutdatedVersion' => empty( $GLOBALS['wgEmailNotificationsDisableVersionCheck'] )
			]
		] );
	}

	/**
	 * @see includes/specials/SpecialListGroupRights.php
	 * @return bool
	 */
	public function groupsList() {
		$ret = [];

		$config = $this->getConfig();
		$groupPermissions = $config->get( 'GroupPermissions' );
		$revokePermissions = $config->get( 'RevokePermissions' );
		$addGroups = $config->get( 'AddGroups' );
		$removeGroups = $config->get( 'RemoveGroups' );
		$groupsAddToSelf = $config->get( 'GroupsAddToSelf' );
		$groupsRemoveFromSelf = $config->get( 'GroupsRemoveFromSelf' );
		$allGroups = array_unique(
			array_merge(
				array_keys( $groupPermissions ),
				array_keys( $revokePermissions ),
				array_keys( $addGroups ),
				array_keys( $removeGroups ),
				array_keys( $groupsAddToSelf ),
				array_keys( $groupsRemoveFromSelf )
			)
		);
		asort( $allGroups );

		$linkRenderer = $this->getLinkRenderer();
		if ( method_exists( Language::class, 'getGroupName' ) ) {
			// MW 1.38+
			$lang = $this->getLanguage();
		} else {
			$lang = null;
		}

		foreach ( $allGroups as $group ) {
			$permissions = $groupPermissions[ $group ] ?? [];

			// Replace * with a more descriptive groupname
			$groupname = ( $group == '*' )
			? 'all' : $group;

			if ( $lang !== null ) {
				// MW 1.38+
				$groupnameLocalized = $lang->getGroupName( $groupname );
			} else {
				$groupnameLocalized = UserGroupMembership::getGroupName( $groupname );
			}

			$ret[$groupnameLocalized] = $groupname;
		}

		ksort( $ret );

		return $ret;
	}

	/**
	 * @param array $data
	 * @param HTMLForm $htmlForm
	 * @return bool
	 */
	public function onSubmit( $data, $htmlForm ) {
		$request = $this->getRequest();
		$new = !$this->par;

		if ( empty( $data['ugroups'] ) ) {
			return false;
		}

		$page = TitleClass::newFromText( $data['page'] );
		$data['page'] = $page->getArticleID();
		$data['ugroups'] = preg_split( "/[\r\n]+/", $data['ugroups'], -1, PREG_SPLIT_NO_EMPTY );

		\EmailNotifications::setNotifications( $this->user->getId(), $data, ( !$new ? $this->par : null ) );

		$localTitle = $this->localTitle;

		if ( $new ) {
			// $tablename = 'emailnotifications_notifications';
			// $dbr = \EmailNotifications::getDb( DB_REPLICA );
			// $id = $dbr->selectField(
			// 	$tablename,
			// 	'id',
			// 	[],
			// 	__METHOD__,
			// 	[ 'ORDER BY' => 'id DESC' ]
			// );
			// $localTitle = SpecialPage::getTitleFor( 'EmailNotifications', $id );
			header( 'Location: ' . $this->localTitle->getLocalURL() );
		}

		return true;
	}

	public function onSuccess() {
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	protected function showOptions( $request ) {
		$formDescriptor = [];

		$created_by = $request->getVal( 'created_by' );

		$formDescriptor['created_by'] = [
			'label-message' => 'emailnotifications-manage-form-search-created_by-label',
			'type' => 'user',
			'name' => 'created_by',
			'required' => false,
			'help-message' => 'emailnotifications-manage-form-search-created_by-help',
			'default' => ( !empty( $created_by ) ? $created_by : null ),
		];

		// @TODO, add other fields ...

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
