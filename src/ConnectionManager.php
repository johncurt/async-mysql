<?php
/**
 * Copyright 2018 John C. Fansler
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JohnCurt\AsyncMySQL;

class ConnectionManager {

	private $mysqliArgs = [];

	/** @var \AsyncMySQL\Query[] $queries */
	protected $queries = [];

	/** @var \mysqli[] $links */
	protected $links = [];

	/**
	 * ConnectionManager constructor. Takes the same parameters as mysqli which are used to create
	 * connections for all the async queries to run in.
	 *
	 * @param null $host
	 * @param null $username
	 * @param null $passwd
	 * @param null $dbname
	 * @param null $port
	 * @param null $socket
	 */
	public function __construct($host=null,
	                               $username=null,
	                               $passwd=null,
	                               $dbname=null,
	                               $port=null,
	                               $socket=null){
		//set defaults:
		$host     = $host     ?? ini_get("mysqli.default_host");
		$username = $username ?? ini_get("mysqli.default_user");
		$passwd   = $passwd   ?? ini_get("mysqli.default_pw");
		$dbname   = $dbname   ?? '';
		$port     = $port     ?? ini_get("mysqli.default_port");
		$socket   = $socket   ?? ini_get("mysqli.default_socket");

		//test mysqli connection
		try {
			$mysqli = new \mysqli($host,
				$username,
				$passwd,
				$dbname,
				$port,
				$socket);
		} catch (\Exception $e){
			throw new \InvalidArgumentException('Unable to connect to this MySQL endpoint.');
		}
		if ($mysqli->connect_error){
			throw new \InvalidArgumentException('Unable to connect to this MySQL endpoint.');
		}

		//save the data for use when creating a link as queries are added
		$this->mysqliArgs = [
			$host,
			$username,
			$passwd,
			$dbname,
			$port,
			$socket,
		];
	}

	/**
	 * Adds the query to the queue and starts running it.
	 *
	 * @param \AsyncMySQL\Query $query
	 * @return bool
	 */
	public function runQuery(Query &$query){
		$queryGuid = $query->getGuid();
		$this->queries[$queryGuid] = &$query;
		try {
			$this->links[ $queryGuid ] = new \mysqli(...$this->mysqliArgs); //turn array into args
			$this->links[ $queryGuid ]->query($query->getQuery(),MYSQLI_ASYNC);
		} catch (\Exception $e){
			//cleanup and return false for error.
			if (isset($this->links[$queryGuid])) unset($this->links[$queryGuid]);
			unset($this->queries[$queryGuid]);
			return false;
		}
		return true;
	}

	/**
	 * @return int
	 */
	public function getActiveQueryCount(){
		return count($this->queries);
	}

	/**
	 * Basic "tick" function to check for finished queries and call their functions.
	 */
	public function reapAny(){
		$read = $error = $reject = $this->links;
		$count = mysqli_poll($read, $error, $reject, 0, 0);
		if ($count>0){
			//have to reap everything regardless, so if anything is available - just try to reap any/all.
			foreach ($this->links as $guid=>$link){
				$this->reap($guid, $link);
			}
		}
	}

	/**
	 * Blocking call to wait (up to $timeoutInSeconds) to get all the current queries in the pool.
	 * Returns true if it succeeded in getting all queries, false on timeout.
	 *
	 * @param int $timeoutInSeconds
	 * @return bool timed out
	 */
	public function reapAll($timeoutInSeconds=0){
		$startTime = microtime(true);
		$curSecs = 0.0;
		while (count($this->links)>0 && ($timeoutInSeconds===0 || $timeoutInSeconds>$curSecs)) {
			$curSecs = microtime(true) - $startTime;
			$this->reapAny();
		}
		return (count($this->links)===0); // false if it timed out before reaping the last one!
	}

	/**
	 * @param string  $guid
	 * @param \mysqli $link
	 */
	private function reap(string $guid, \mysqli $link){
		$result = mysqli_reap_async_query($link);
		if ($result!==false){ //false when not ready - just ignore
			if (is_a($result, 'mysqli_result')){
				//we have a result - send it to the query success function
				$this->queries[$guid]->success($result);
			} else {
				$this->queries[$guid]->failure($link->error);
			}
			$this->removeByGuid($guid);
		} else if ($link->error<>''){
			$this->queries[$guid]->failure($link->error);
			$this->removeByGuid($guid);
		}
	}

	/**
	 * @param $guid
	 */
	private function removeByGuid($guid){
		$this->links[$guid]->close();
		unset($this->links[$guid]);
		unset($this->queries[$guid]);
	}

}