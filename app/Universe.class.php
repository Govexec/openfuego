<?php namespace OpenFuego\app;

use OpenFuego\lib\TwitterHandle as TwitterHandle;
use OpenFuego\lib\DbHandle as DbHandle;
use OpenFuego\lib\DbUser as DbUser;
use OpenFuego\lib\Logger as Logger;

class Universe extends DbUser {

	public $citizens;

	protected function array_most_common($input) {
		$counted = array_count_values($input);
		arsort($counted);
		return($counted);
	}


	public function getCitizens($min_influence = 1) {
		try {
			$sql = "
				SELECT user_id
				FROM openfuego_citizens
				WHERE influence >= :min_influence;
			";

			$params = array('min_influence' => array('value' => $min_influence));
			$sth = $this->execute($sql, $params);
			$user_ids = $sth->fetchAll(\PDO::FETCH_COLUMN);

			return $user_ids;

		} catch (\PDOException $e) {
			Logger::error($e);
			return FALSE;
		}
	}


	public function populate($authorities, $min_influence = 1) {

		if (count($authorities) > 15) {
			$error_message = __METHOD__ . " failed. The maximum number of authorities is 15. Dying.";
			Logger::fatal($error_message);
			die($error_message);
		}

		$owner_screen_name = \OpenFuego\TWITTER_SCREEN_NAME;

		$twitter = new TwitterHandle();

		$authorities = implode(',', $authorities);
		$authorities = str_replace('@', '', $authorities);
		$authorities = $twitter->get('users/lookup', array('screen_name' => $authorities));

		foreach ($authorities as $authority) {
			$authorities_ids[] = $authority['id_str'];
		}

		$universe_ids = $authorities_ids;

		foreach ($authorities as $authority) {
			$authority_friends_ids = $twitter->get('friends/ids', array('screen_name' => $authority['screen_name']));

			if ($twitter->http_code != 200) {
				$error_message = __METHOD__ . " failed, Twitter error {$twitter->http_code}. Dying.";
				Logger::fatal($error_message);
				die();
			}

			$authority_friends_ids = $authority_friends_ids['ids'];
			$universe_ids = array_merge($universe_ids, $authority_friends_ids); // append more ids to universe
		}

		$universe_ids_sorted = $this->array_most_common($universe_ids);

		unset($authority_friends_ids, $owner_screen_name, $twitter, $universe_ids);

		$sql = "TRUNCATE TABLE openfuego_citizens";
		$params = array();
		$this->execute($sql, $params);

		$sql = "INSERT INTO openfuego_citizens (user_id, influence) VALUES (:user_id, :influence);";

		foreach ($universe_ids_sorted as $key=>$value) {
			try {
				$params = array(
					'user_id' => array('value' => $key, 'type' => \PDO::PARAM_INT),
					'influence' => array('value' => $value, 'type' => \PDO::PARAM_INT)
				);
				$this->execute($sql, $params);
			}

			catch (\PDOException $e) {
				Logger::fatal($e);
				die();
			}
		}

	 return TRUE;
	}


	public function isCitizen($user_id_str) {

		try {
			$sql = "SELECT user_id FROM openfuego_citizens WHERE user_id = :user_id LIMIT 1;";
			$params = array('user_id' =>  array( 'value' => $user_id_str));

			$sth = $this->execute($sql, $params);

			if ($sth->fetchColumn(0)) {
				return TRUE;
			}
			return FALSE;
		}
		catch (\PDOException $e) {
			Logger::error($e);
			return FALSE;
		}
	}


	public function getInfluence($user_id_str) {

		try {
			$sql = "SELECT influence FROM openfuego_citizens WHERE user_id = :user_id LIMIT 1;";
			$params = array('user_id' => array( 'value' => $user_id_str));
			$sth = $this->execute($sql, $params);

			$influence = $sth->fetchColumn(0);

			return $influence;

		} catch (\PDOException $e) {
			Logger::error($e);
			return FALSE;
		}
	}

	public function __destruct() {

	}
}
