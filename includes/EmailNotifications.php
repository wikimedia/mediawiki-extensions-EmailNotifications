<?php
/**
 * This file is part of the MediaWiki extension EmailNotifications.
 *
 * EmailNotifications is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * v is distributed in the hope that it will be useful,
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

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use MediaWiki\Extension\EmailNotifications\Mailer;
use Psr\Log\LoggerInterface;

class EmailNotifications {

	/** @var array */
	public static $cacheIdSubject = [];

	/** @var array */
	public static $cacheSubjectId = [];

	/** @var LoggerInterface */
	public static $Logger;

	/** @var array */
	public static $UserAuthCache = [];

	/**
	 * @param string $creatorUsername
	 * @param array $row
	 * @param int|null $id
	 * @return bool
	 */
	public static function setNotifications( $creatorUsername, $row, $id = null ) {
		$dbw = self::getDB( DB_MASTER );

		if ( !count( $row['groups'] ) ) {
			return false;
		}

		$row['groups'] = implode( ',', $row['groups'] );
		$row['created_by'] = $creatorUsername;

		$tablename = 'emailnotifications_notifications';

		if ( !$id ) {
			$date = date( 'Y-m-d H:i:s' );
			$res = $dbw->insert( $tablename, $row + [ 'updated_at' => $date, 'created_at' => $date ] );

		} else {
			$res = $dbw->update( $tablename, $row, [ 'id' => $id ], __METHOD__ );
		}

		return $res;
	}

	/**
	 * @param array $headers
	 * @param array $to
	 * @param MailAddress $from
	 * @param string $subject
	 * @param string $text
	 * @param string $html
	 * @param array &$errors
	 * @return bool
	 */
	public static function sendEmail( $headers, $to, $from, $subject, $text, $html, &$errors ) {
	/*
$wgEmailNotificationsMailer = 'sendgrid';	// 'native';
$wgEmailNotificationsMailerConf = [
	'transport' => 'api'			// smtp, http, api
	'key' => ''
];
*/
		$mailer = $GLOBALS['wgEmailNotificationsMailer'];
		$conf = $GLOBALS['wgEmailNotificationsMailerConf'];

		if ( empty( $mailer ) || empty( $conf ) ) {
			return false;
		}

		$mailer = new Mailer( $mailer, $conf );
		$errors_ = $mailer->getErrors();

		if ( count( $errors_ ) ) {
			$errors = array_merge( $errors, $errors_ );
			return false;
		}

		$email = $mailer->mail;

		if ( !empty( $headers ) ) {
			$headersEmail = $email->getHeaders();
			$ignore = [ 'From', 'Return-Path', 'Date', 'Message-ID', 
				'MIME-Version', 'Content-type', 'content-transfer-encoding' ];

			foreach ( $headers as $key => $value ) {
				if ( in_array( $key, $ignore ) ) {
					continue;
				}
				$headersEmail->addTextHeader( $key, $value );
			}
		}

		$email->from( !empty( $from->name ) ? $from->name . '<' . $from->address . '>'
			: $from->address );

		if ( empty( $subject ) ) {
			// zero width space, this is a workaround
			// for the annoying error
			// 'Unable to send an email: The subject is required'
			$subject = '​';
		}

		$email->subject( $subject );

		if ( !empty( $html ) ) {
			$email->html( $html );

			if ( empty( $text ) ) {
				$text = $mailer->html2Text( $html );
			}
		}

		if ( !empty( $text ) ) {
			$email->text( $text );
		}

		$email->to( implode( ', ', $to ) );

		$mailer->sendEmail( $email );

		return true;
	}		

	/**
	 * @param array $groups
	 * @return array|bool
	 */
	public static function usersInGroups( $groups, $errors = [] ) {
	 	$context = RequestContext::getMain();

		// @see https://www.mediawiki.org/wiki/API:Allusers
		$row = [
			'action' => 'query',
			'list' => 'allusers',
			'augroup' => implode( '|', $groups )
		];

		$req = new DerivativeRequest(
			$context->getRequest(),
			$row,
			true
		);

		try {
			$api = new ApiMain( $req, true );
			$api->execute();

		} catch ( \Exception $e ) {
			$errors[] = 'api error ' . $e->getMessage();
			self::$Logger->error( current( $errors ) );
			return false;
		}

		$res = $api->getResult()->getResultData();
		$ret = [];
		if ( !empty( $res['query']['allusers'] ) ) {
			foreach ( $res['query']['allusers'] as $value ) {
				if ( is_array( $value ) ) {
					$ret[] = $value['userid'];
				}
			}
		}
		return $ret;
	}

	/**
	 * @param int $notificationId
	 * @param array $groups
	 * @param int $page
	 * @param string $subject
	 * @param bool $must_differ
	 * @param array &$errors
	 * @return array|bool
	 */
	public static function sendNotification(
		$notificationId,
		$groups,
		$page,
		$subject,
		$must_differ,
		$skip_strategy,
		$skip_text,
		&$errors = []
	) {
		$users = self::usersInGroups( $groups );

		if ( !count( $users ) ) {
			$errors[] = 'no recipients';
			self::$Logger->warning( current( $errors ) );
			return false;
		}

		$title_ = Title::newFromId( $page );
		$wikiPage = self::getWikiPage( $title_ );

		if ( !$wikiPage ) {
			$errors[] = 'article not valid';
			self::$Logger->error( current( $errors ) );
			return false;
		}

		$wikiPage->doPurge();

		$options = [
			'allowTOC' => false,
			'injectTOC' => false,
			'enableSectionEditLinks' => false,
			'userLang' => null,
			'skin' => null,
			'unwrap' => true,
			// 'wrapperDivClass' => $this->getWrapperDivClass(),
			'deduplicateStyles' => true,
			'absoluteURLs' => true,
			'includeDebugInfo' => false,
			'bodyContentOnly' => true,
		];
		$context = RequestContext::getMain();
		$context->setTitle( $title_ );
		$parserOptions = ParserOptions::newFromContext( $context );
		$parserOutput = $wikiPage->getParserOutput( $parserOptions );

		$html = Parser::stripOuterParagraph( $parserOutput->getText( $options ) );

		$html2Text = new \Html2Text\Html2Text( $html );
		$text = $html2Text->getText();

		if ( !empty( $skip_text ) ) {
			switch ( $skip_strategy ) {
				case 'contains':
					if ( strpos( $text, $skip_text ) !== false ) {
						$errors[] = 'skip text contains';
						self::$Logger->warning( current( $errors ) );
						return false;
					}
					break;
				case 'does not contain':
					if ( strpos( $text, $skip_text ) === false ) {
						$errors[] = 'skip text does not contain';
						self::$Logger->warning( current( $errors ) );
						return false;
					}
					break;
				case 'regex':
					if ( preg_match( '/' . preg_quote( $skip_text, '/' ) . '/', $text ) ) {
						$errors[] = 'skip text regex';
						self::$Logger->warning( current( $errors ) );
						return false;
					}
			}
		}

		$date = date( 'Y-m-d H:i:s' );
		$dbr = self::getDb( DB_REPLICA );

		if ( $must_differ ) {
			$tablename = 'emailnotifications_sent';
			$previous_text = $dbr->selectField(
				$tablename,
				'text',
				[ 'notification_id' => $notificationId ],
				__METHOD__,
				[ 'LIMIT' => 1, 'ORDER BY' => 'created_at DESC' ],
			);
			if ( $previous_text && $previous_text === $text ) {
				$errors[] = 'text does not differ';
				self::$Logger->warning( current( $errors ) );
				return false;
			}
		}

		$services = MediaWikiServices::getInstance();
		$passwordSender = $services->getMainConfig()
			->get( MainConfigNames::PasswordSender );

		if ( empty( $passwordSender ) ) {
			$errors[] = '$wgPasswordSender not set';
			self::$Logger->warning( current( $errors ) );
			return false;
		}

		$sender = new MailAddress( $passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text() );

		$userFactory = $services->getUserFactory();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$languageFactory = $services->getLanguageFactory();

		$sent = [];
		foreach ( $users as $userid ) {
			$user = $userFactory->newFromId( $userid );
			if ( !$user ) {
				continue;
			}
			$language = $userOptionsLookup->getOption( $user, 'language' );
			$parserOptions = ParserOptions::newFromUserAndLang( $user, $languageFactory->getLanguage( $language ) );
			$parserOutput = $wikiPage->getParserOutput( $parserOptions );

			$html = Parser::stripOuterParagraph( $parserOutput->getText( $options ) );

			$email = $user->getEmail();
			if ( $email ) {
				$to = new MailAddress(
					$user->getEmail(),
					$user->getName(),
					$user->getRealName()
				);

				$tablename = 'emailnotifications_unsubscribe';
				$unsubscribed = $dbr->selectField(
					$tablename,
					'notification_id',
					[ 'notification_id' => $notificationId, 'user_id' => $userid ],
					__METHOD__,
					[ 'LIMIT' => 1 ],
				);

				if ( $unsubscribed ) {
					continue;
				}

				$body = $html;
				$url = SpecialPage::getTitleFor( 'EmailNotificationsEvents', $notificationId )
					->getFullURL( '', false, PROTO_CANONICAL );
				$listUnsubscribe = '<' . wfAppendQuery( $url, [ 'action' => 'unsubscribe' ] ) . '>';
				$trackingUrl = wfAppendQuery( $url, [ 'action' => 'tracking', 'msgId' =>
					str_replace( [ '<', '>' ], '', self::makeMsgId( $notificationId, $userid, $date ) ) ] );

				UserMailer::send( $to, $sender, $subject, $body, [
					 'headers' => [
					 	// 'List-Unsubscribe' will be overwritten
						'EmailNotifications-ListUnsubscribe' => $listUnsubscribe,
						'EmailNotifications-TrackingUrl' => $trackingUrl,
					],
				] );
				$sent[] = $userid;
			}
		}

		$tablename = 'emailnotifications_sent';
		$row = [
			'notification_id' => $notificationId,
			'text' => $text,
			'recipients' => count( $sent ),
		];
		$dbw = self::getDB( DB_PRIMARY );
		$options = [ 'IGNORE' ];
		$res = $dbw->insert(
			$tablename,
			$row + [ 'created_at' => $date ],
			__METHOD__,
			$options
		);

		return $sent;
	}

	/**
	 * @param string $messageId
	 * @return array
	 */
	public static function parseMessageId( $messageId ) {
		$messageId = substr( $messageId, 0, strpos( $messageId, '@' ) );
		return explode( '|', base64_decode( $messageId ) );
	}

	/**
	 * @see UserMailer::makeMsgId
	 * @param int $notificationId
	 * @param int $userid
	 * @param string $datetime
	 * @return string
	 */
	public static function makeMsgId( $notificationId, $userid, $datetime ) {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		$smtp = $mainConfig->get( MainConfigNames::SMTP );
		$server = $mainConfig->get( MainConfigNames::Server );
		// $domainId = WikiMap::getCurrentWikiDbDomain()->getId();
		// $msgid = uniqid( $domainId . ".", true /** for cygwin */ );
		$msgid = base64_encode( "$notificationId|$userid|$datetime" );
		if ( is_array( $smtp ) && isset( $smtp['IDHost'] ) && $smtp['IDHost'] ) {
			$domain = $smtp['IDHost'];
		} else {
			$url = wfParseUrl( $server );
			$domain = $url['host'];
		}
		return "<$msgid@$domain>";
	}

	/**
	 * @param array $conds
	 * @return void
	 */
	public static function deleteItem( $conds ) {
		$dbw = self::getDB( DB_PRIMARY );
		$tablename = 'emailnotifications_notifications';
		$dbw->delete( $tablename, $conds, __METHOD__ );

		$tablename = 'emailnotifications_unsubscribe';
		$conds = [ 'notification_id' => $conds['id'] ];
		$dbw->delete( $tablename, $conds, __METHOD__ );		
	}

	/**
	 * @param int $userId
	 * @param int $notificationId
	 * @return string|bool
	 */
	public static function unsubscribe( $userId, $notificationId ) {
		$dbw = self::getDB( DB_PRIMARY );
		$tablename = 'emailnotifications_unsubscribe';
		$row = [
			'notification_id' => $notificationId,
			'user_id' => $userId,
		];
		$date = date( 'Y-m-d H:i:s' );
		$options = [ 'IGNORE' ];
		$res = $dbw->insert(
			$tablename,
			$row + [ 'updated_at' => $date, 'created_at' => $date ],
			__METHOD__,
			$options
		);

		return self::getNotificationSubject( $notificationId );
	}

	/**
	 * @param int $user
	 * @return string
	 */
	public static function getNotificationSubject( $notificationId ) {
		if ( array_key_exists( $notificationId, self::$cacheIdSubject ) ) {
			return self::$cacheIdSubject[$notificationId];
		}
		$dbr = self::getDb( DB_REPLICA );
		$tablename = 'emailnotifications_notifications';
		$subject = $dbr->selectField(
			$tablename,
			'subject',
			[ 'id' => $notificationId ],
			__METHOD__,
			[ 'LIMIT' => 1 ],
		);
		self::$cacheIdSubject[$notificationId] = $subject;
		return $subject;
	}

	/**
	 * @param string $user
	 * @return int
	 */
	public static function notificationIdFromSubject( $subject ) {
		if ( array_key_exists( $subject, self::$cacheSubjectId ) ) {
			return self::$cacheSubjectId[$subject];
		}
		$dbr = \EmailNotifications::getDb( DB_REPLICA );
		$tablename = 'emailnotifications_notifications';
		$id = (int)$dbr->selectField(
			$tablename,
			'id',
			[ 'subject' => $subject ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);
		self::$cacheSubjectId[$subject] = $id;
		return $id;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public static function isAuthorizedGroup( $user ) {
		$cacheKey = $user->getName();
		if ( array_key_exists( $cacheKey, self::$UserAuthCache ) ) {
			return self::$UserAuthCache[$cacheKey];
		}
		$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
		$userGroups = $userGroupManager->getUserEffectiveGroups( $user );
		$authorizedGroups = [
			'sysop',
			'bureaucrat',
			'interface-admin',
			'autoconfirmed'
		];
		self::$UserAuthCache[$cacheKey] = count( array_intersect( $authorizedGroups, $userGroups ) );
		return self::$UserAuthCache[$cacheKey];
	}

	/**
	 * @param Title $title
	 * @return WikiPage|null
	 */
	public static function getWikiPage( $title ) {
		if ( !$title || !$title->canExist() ) {
			return null;
		}
		// MW 1.36+
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		}
		return WikiPage::factory( $title );
	}

	/**
	 * @param int $db
	 * @return \Wikimedia\Rdbms\DBConnRef
	 */
	public static function getDB( $db ) {
		if ( !method_exists( MediaWikiServices::class, 'getConnectionProvider' ) ) {
			// @see https://gerrit.wikimedia.org/r/c/mediawiki/extensions/PageEncryption/+/1038754/comment/4ccfc553_58a41db8/
			return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( $db );
		}
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		switch ( $db ) {
			case DB_PRIMARY:
			case DB_MASTER:
				return $connectionProvider->getPrimaryDatabase();
			case DB_REPLICA:
			default:
				return $connectionProvider->getReplicaDatabase();
		}
	}

}
