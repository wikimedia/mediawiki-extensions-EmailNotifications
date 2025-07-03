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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../vendor/autoload.php';
}

class SendNotifications extends Maintenance {

	/** @var \Wikimedia\Rdbms\DBConnRef|\Wikimedia\Rdbms\IDatabase|\Wikimedia\Rdbms\IReadableDatabase */
	private $db;

	/** @var User */
	private $user;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'send notifications' );
		$this->requireExtension( 'EmailNotifications' );

		// name,  description, required = false,
		//	withArg = false, shortName = false, multiOccurrence = false
		//	$this->addOption( 'format', 'import format (csv or json)', true, true );

		$this->addOption( 'remove-slot', 'remove pageproperties slot', false, false );
	}

	/**
	 * inheritDoc
	 */
	public function execute() {
		$this->user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		$this->db = \EmailNotifications::getDB( DB_REPLICA );
		$tablename = 'emailnotifications_notifications';
		$conds = [
			'enabled' => 1,
		];
		$res = $this->db->select(
			$tablename,
			'*',
			$conds,
			__METHOD__,
			[]
		);

		$sent = 0;
		foreach ( $res as $row ) {
			// if ( $row->enabled ) {
			try {
				$cron = new Cron\CronExpression( $row->frequency );
			} catch ( Exception $e ) {
				continue;
			}

			if ( $cron->isDue() ) {
				echo "sending notification {$row->subject}" . PHP_EOL;
				$errors = [];
				$ret = \EmailNotifications::sendNotification(
					$this->user,
					$row->id,
					explode( ',', $row->ugroups ),
					$row->page,
					$row->subject,
					$row->must_differ,
					$row->skip_strategy,
					$row->skip_text,
					$errors,
				);
				if ( is_array( $ret ) ) {
					$sent += count( $ret );
				}
				if ( count( $errors ) ) {
					foreach ( $errors as $msg ) {
						echo $msg . PHP_EOL;
					}
				}
				echo PHP_EOL;
			}
		}
		echo "$sent email sent" . PHP_EOL;
	}
}

$maintClass = SendNotifications::class;
require_once RUN_MAINTENANCE_IF_MAIN;
