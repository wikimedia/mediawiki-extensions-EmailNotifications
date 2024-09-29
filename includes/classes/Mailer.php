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

namespace MediaWiki\Extension\EmailNotifications;

if ( is_readable( __DIR__ . '/../../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../../vendor/autoload.php';
}

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer {

	/** @var Mail */
	public $mail;

	/** @var SymfonyMailer */
	private $mailer;

	/** @var array */
	private $errors = [];

	/**
	 * @param string $provider
	 * @param array $conf
	 */
	public function __construct( $provider, $conf ) {
		$dns = $this->getSymfonyDns( $provider, $conf );
		if ( $dns !== false ) {
			$transport = Transport::fromDsn( $dns );
			$this->mailer = new SymfonyMailer( $transport );
			$this->mail = new Email();
		}
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param string $provider
	 * @param array $conf
	 * @return string|false
	 */
	private function getSymfonyDns( $provider, $conf ) {
		$transport = null;
		$dns = null;
		$username = null;
		$password = null;
		$host = null;

/*
$wgEmailNotificationsMailer = 'sendgrid';	// 'native';
$wgEmailNotificationsMailerConf = [
	'transport' => 'api'			// smtp, http, api
	'key' => ''
];
*/
		$conf = array_change_key_case( $conf, CASE_LOWER );

		// @see https://symfony.com/doc/5.x/mailer.html
		switch ( $provider ) {
			case 'sendmail':
				return 'sendmail://default';
			case 'native':
				return 'native://default';

			case 'smtp':
				$transport = 'smtp';
				$username = $conf['username'];
				$password = $conf['password'];
				$host = $conf['server'] . ':' . $conf['port'];
				break;

			default:
				switch ( $conf['transport'] ) {
					case 'smtp':
						switch ( $provider ) {
							case 'amazon':
								// ses+smtp://USERNAME:PASSWORD@default
								$transport = 'ses+smtp';
								$username = $conf['username'];
								$password = $conf['password'];
								break;
							case 'gmail':
								$transport = 'gmail+smtp';
								$username = $conf['app-password'];
								break;
							case 'mandrill':
								$transport = 'mandrill+smtp';
								$username = $conf['username'];
								$password = $conf['password'];
								break;
							case 'mailgun':
								$transport = 'mailgun+smtp';
								$username = $conf['username'];
								$password = $conf['password'];
								break;
							case 'mailjet':
								$transport = 'mailjet+smtp';
								$username = $conf['access_key'];
								$password = $conf['secret_key'];
								break;
							case 'postmark':
								$transport = 'postmark+smtp';
								$username = $conf['id'];
								break;
							case 'sendgrid':
								$transport = 'sendgrid+smtp';
								$username = $conf['key'];
								break;
							case 'sendinblue':
								$transport = 'sendinblue+smtp';
								$username = $conf['username'];
								$password = $conf['password'];
								break;
							case 'ohmysmtp':
								$dns = 'ohmysmtp+smtp';
								$username = $conf['api_token'];
								break;
						}
						break;
					case 'http':
						switch ( $provider ) {
							case 'amazon':
								$transport = 'ses+htpps';
								$username = $conf['access_key'];
								$password = $conf['secret_key'];
								break;
							case 'gmail':
								break;
							case 'mandrill':
								$transport = 'mandrill+htpps';
								$username = $conf['key'];
								break;
							case 'mailgun':
								$transport = 'mailgun+htpps';
								$username = $conf['key'];
								$password = $conf['domain'];
								break;
							case 'mailjet':
							case 'postmark':
							case 'sendgrid':
							case 'sendinblue':
							case 'ohmysmtp':
						}
						break;
					case 'api':
						switch ( $provider ) {
							case 'amazon':
								$transport = 'ses+api';
								$username = $conf['access_key'];
								$password = $conf['secret_key'];
								break;
							case 'gmail':
								break;
							case 'mandrill':
								$transport = 'mandrill+api';
								$username = $conf['key'];
								break;
							case 'mailgun':
								$transport = 'mailgun+api';
								$username = $conf['key'];
								$password = $conf['domain'];
								break;
							case 'mailjet':
								$transport = 'mailjet+api';
								$username = $conf['access_key'];
								$password = $conf['secret_key'];
								break;
							case 'postmark':
								$transport = 'postmark+api';
								$username = $conf['key'];
								break;
							case 'sendgrid':
								$transport = 'sendgrid+api';
								$username = $conf['key'];
								break;
							case 'sendinblue':
								$transport = 'sendinblue+api';
								$username = $conf['key'];
								break;
							case 'ohmysmtp':
								$dns = 'ohmysmtp+api';
								$username = $conf['api_token'];
								break;
						}
						break;
				}
		}

		if ( !$dns ) {
			if ( !$username || !$transport ) {
				$this->errors[] = 'transport not supported';
				\EmailNotifications::$Logger->error( 'transport not supported' );
				return false;
			}

			// ses+smtp://USERNAME:PASSWORD@default
			$dns = $transport . '://' . urlencode( $username );

			if ( $password ) {
				$dns .= ':' . urlencode( $password );
			}

			$dns .= '@' . ( $host ?? 'default' );
		}

		return $dns;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	public function html2Text( $html ) {
		$html2Text = new \Html2Text\Html2Text( $html );
		return $html2Text->getText();
	}

	/**
	 * @param Email $email
	 * @return bool
	 */
	public function sendEmail( $email ) {
		try {
			$this->mailer->send( $email );
		} catch ( TransportExceptionInterface | TransportException | Exception $e ) {
			$this->errors[] = 'sendEmail ' . $e->getMessage();
			\EmailNotifications::$Logger->error( 'sendEmail ' . $e->getMessage() );
			return false;
		}

		return true;
	}
}
