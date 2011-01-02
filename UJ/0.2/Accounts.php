<?php
require_once BASE_DIR . 'Security.php';

class Accounts {
	private static function genRegistrationToken($email) {
		return md5('Repelsteeltke'.$email);
	}
	public static function register($emailUser, $emailDomain, $storageNode, $app, $pubPass, $subPass, $fromNode = null) {
		if($fromNode === null) {
			$accountState = Security::STATE_PENDING;
		} else {
			$accountState = Security::STATE_PENDINGIMMIGRANT;
		}
		$email = $emailUser.'@'.$emailDomain;
		$registrationToken = self::genRegistrationToken($email);
		Security::create($emailUser, $emailDomain, $storageNode, $app, $pubPass, $subPass, $accountState, $registrationToken);
		$emailSenderClass = EMAIL_SENDER;
		$emailSender = new $emailSenderClass();
		$emailSender->sendRegistrationToken($email, $registrationToken, $fromNode);
		return 'ok';
	}
	public static function confirm($accountId, $partition, $registrationToken) {
		return Security::confirmAccount($accountId, $partition, $registrationToken);
	}
	public static function disappear($accountId, $partition) {
		switch(Security::getState($accountId, $partition)) {
		case Security::STATE_PENDING:
		case Security::STATE_PENDINGIMMIGRANT:
			Security::deleteAccount($accountId, $partition);
			break;
		default:
			Security::setState($accountId, $partition, Security::STATE_GONE);
		}
		return 'ok';
	}
	public static function emigrate($accountId, $partition, $toNode, $migrationToken) {
		return Security::createEmigrant($accountId, $partition, $toNode, $migrationToken);
	}
	public static function immigrate($emailUser, $emailDomain, $storageNode, $app, $pubPass, $subPass, $migrationToken, $fromNode) {
		self::register($emailUser, $emailDomain, $storageNode, $app, $pubPass, $subPass, $fromNode);
		list($accountId, $partition) = Security::getAccountId($emailUser, $emailDomain, $storageNode, $app, $pubPass, true);
		return Security::createImmigrant($accountId, $partition, $migrationToken, $fromNode);
	}
	public static function migrate($accountId, $partition, $migrationToken, $group, $keyPath, $needValue, $delete, $limit) {
		Security::checkEmigrant($accountId, $partition, $migrationToken);
		switch($group) {
		case 'KV':
			$entries = array();//KeyValue::export($accountId, $partition, $needValue, $delete, $limit);
			return array('KV'=>$entries);
		case 'MSG':
			$messages = array();//Messages::export($accountId, $partition, $needValue, $delete, $limit);
			return array('MSG'=>$messages);
		default:
			$messages = array();//Messages::export($accountId, $partition, $needValue, $delete, $limit);
			if(count($messages) < $limit) {
				$entries = array();//KeyValue::export($accountId, $partition, $needValue, $delete, $limit - count($messages));
			}
			return array('KV'=>$entries, 'MSG'=>$messages);
		}
	}
	public static function doMigration($emailUser, $emailDomain, $storageNode, $app, $group, $keyPath) {
		list($accountId, $partition) = Security::getAccountId($emailUser, $emailDomain, $storageNode, $app);
		list($migrationToken, $fromUser, $fromNode) = self::getImmigrantDetails($accountId, $partition);
		//get a few objects (entries or messages, depending on $group):
		$objects = Http::call($fromNode, array(
			'method'=>'MIGR.MIGRATE',
			'app'=>$app,
			'migrationToken'=>$migrationToken, 
			'toNode'=>$storageNode, 
			'group'=>$group,
			'keyPath'=>$keyPath,
			'delete'=>'false',
			'limit'=>10,
			'needValue'=>'true'
			));
		switch($group) {
		case 'KV':
			$success = KeyValue::import($accountId, $partition, $objects);
			break;
		case 'MSG':
			$success = Messages::import($accountId, $partition, $objects);
			break;
		default:
			throw new HttpBadRequest();
		}
		if($success) {//let fromNode know that they we received them correctly (TODO: check this with e.g. CRC!) and they can now delete those objects on their side
			foreach($objects as $thisKeyPath=>$object) {
				Http::call($fromNode, array(
					'method'=>'MIGR.MIGRATE',
					'app'=>$app,
					'migrationToken'=>$migrationToken, 
					'toNode'=>$storageNode, 
					'group'=>$group,
					'keyPath'=>$thisKeyPath,
					'delete'=>'true',
					'limit'=>1,
					'needValue'=>'false'
					));
			}
		}
	}
}
