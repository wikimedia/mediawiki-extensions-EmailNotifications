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
 * along with EmailNotifications.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2024, https://wikisphere.org
 */

use MediaWiki\Extension\EmailNotifications\BodyPostProcess;
use MediaWiki\Extension\EmailNotifications\Mailer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
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
		$dbw = self::getDB( DB_PRIMARY );

		if ( !count( $row['ugroups'] ) ) {
			return false;
		}

		$row['ugroups'] = implode( ',', $row['ugroups'] );
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
	 * @param array $attachments
	 * @param array &$errors
	 * @return bool
	 */
	public static function sendEmail( $headers, $to, $from, $subject, $text, $html, $attachments = [], &$errors = [] ) {
		$mailer = $GLOBALS['wgEmailNotificationsMailer'];
		$conf = $GLOBALS['wgEmailNotificationsMailerConf'];

		if ( empty( $mailer ) ) {
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

				// automatically assigned
				if ( in_array( $key, $ignore ) ) {
					continue;
				}

				// @see vendor/symfony/mime/Header/Headers.php
				switch ( strtolower( $key ) ) {
					case 'date':
						$headersEmail->addDateHeader( $key, new \DateTimeImmutable() );
						break;
					case 'from':
					case 'to':
					case 'cc':
					case 'bcc':
					// @see https://www.mediawiki.org/w/index.php?title=Topic:Yh239sott8bbkc0e&topic_showPostId=yh4ksi74qd0vhlf4#flow-post-yh4ksi74qd0vhlf4
					case 'reply-to':
						$headersEmail->addMailboxListHeader( $key,
							( is_array( $value ) ? $value : [ $value ] ) );
						break;
					case 'sender':
						$headersEmail->addMailboxHeader( $key, $value );
						break;
					case 'message-id':
						$headersEmail->addIdHeader( $key, $value );
						break;
					case 'return-path':
						$headersEmail->addPathHeader( $key, $value );
						break;
					default:
						$headersEmail->addTextHeader( $key, $value );
				}
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
			// *** keep commented ! otherwise a multipart MIME message
			// won't work
			// $headersEmail->addTextHeader( 'Content-type', 'text/html; charset=UTF-8' );

			$bodyPostProcess = new BodyPostProcess( $GLOBALS['wgServer'], $html );
			$bodyPostProcess->updateImageUrls();
			$html = $bodyPostProcess->getHtml();

			if ( empty( $text ) ) {
				$text = $mailer->html2Text( $html );
			}

			$email->text( $text );
			$email->html( $html );

		} else {
			$email->text( $text );
		}

		$email->to( ...$to );

		// @see https://phpenterprisesystems.com/symfony-framework/93-how-to-send-emails-with-attachments-in-symfony-6
		foreach ( $attachments as $value ) {
			$email->attach( $value['body'], $value['name'], $value['contentType'] );
		}

		$mailer->sendEmail( $email );

		return true;
	}

	/**
	 * @param User $user
	 * @param array $groups
	 * @param array &$errors
	 * @return array|bool
	 */
	public static function usersInGroups( $user, $groups, &$errors = [] ) {
		$context = RequestContext::getMain();
		$context->setUser( $user );

		// @see https://www.mediawiki.org/wiki/API:Allusers
		$row = [
			'action' => 'query',
			'list' => 'allusers',
			'augroup' => implode( '|', $groups ),
			'aulimit' => 500,
		];

		$req = new DerivativeRequest(
			$context->getRequest(),
			$row,
			true
		);

		try {
			$api = new ApiMain( $req, true );
			$api->getContext()->setUser( $user );
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
	 * @param User $user
	 * @param int $notificationId
	 * @param array $groups
	 * @param int $page
	 * @param string $subject
	 * @param bool $must_differ
	 * @param string $skip_strategy
	 * @param string $skip_text
	 * @param array &$errors
	 * @return array|bool
	 */
	public static function sendNotification(
		$user,
		$notificationId,
		$groups,
		$page,
		$subject,
		$must_differ,
		$skip_strategy,
		$skip_text,
		&$errors = []
	) {
		$users = self::usersInGroups( $user, $groups, $errors );

		if ( $users === false ) {
			self::$Logger->warning( current( $errors ) );
			return false;
		}

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
					if ( preg_match( '/' . str_replace( '/', '\/', $skip_text ) . '/', $text ) ) {
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

		$parser = $services->getParserFactory()->getInstance();
		$parser->setTitle( $title_ );

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

			// parse subject as wikitext
			$parser->setOptions( $parserOptions );
			$parser->setOutputType( Parser::OT_PLAIN );
			$parser->clearState();
			$subject_ = $parser->recursiveTagParseFully( $subject );
			$html2Text_ = new \Html2Text\Html2Text( $subject_ );
			$subject_ = $html2Text_->getText();

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

				UserMailer::send( $to, $sender, $subject_, $body, [
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
	 * @param int $notificationId
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
	 * @param string $subject
	 * @return int
	 */
	public static function notificationIdFromSubject( $subject ) {
		if ( array_key_exists( $subject, self::$cacheSubjectId ) ) {
			return self::$cacheSubjectId[$subject];
		}
		$dbr = self::getDb( DB_REPLICA );
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
				return $connectionProvider->getPrimaryDatabase();
			case DB_REPLICA:
			default:
				return $connectionProvider->getReplicaDatabase();
		}
	}

}
