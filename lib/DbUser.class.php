<?php namespace OpenFuego\lib;

abstract class DbUser {

	protected $_dbh;

	private static function bind(&$sth, $params) {
		foreach($params as $name => $param)
		{
			if(array_key_exists('type', $param))
			{
				$sth->bindParam($name, $param['value'], $param['type']);
			}
			else
			{
				$sth->bindParam($name, $param['value']);
			}
		}
	}

	protected function getDbh() {
		if (!$this->_dbh) {
			$this->_dbh = new DbHandle();
		}

		return $this->_dbh;
	}

	protected function reconnectDbh() {
		$this->_dbh = new DbHandle();
		return $this->_dbh;
	}

	protected function execute($sql, $params) {
		$dbh = $this->getDbh();
		$sth = $dbh->prepare($sql);
		try
		{
			$this::bind($sth, $params);
			$sth->execute();
		}
		catch(\PDOException $e)
		{
			if(!$dbh->goneAway($e))
			{
				throw $e;
			}
			$dbh = $this->reconnectDbh();
			$sth = $dbh->prepare($sql);
			$this::bind($sth, $params);
			$sth->execute();
		}
		return $sth;
	}
}
