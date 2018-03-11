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

class Query {
	protected $guid;
	protected $query;
	protected $success;
	protected $failure;
	protected $result = null;
	protected $resultExists = false;
	protected $done = false;

	/**
	 * Query constructor.
	 *
	 * @param string        $query
	 * @param callable|null $success
	 * @param callable|null $failure
	 */
	public function __construct(string $query, $success=null, $failure=null) {
		$this->success = &$success;
		$this->failure = &$failure;
		$this->query = $query;
		$this->guid = $this->makeGUIDv4();
		$this->resultExists = false;
	}

	/**
	 * Generates a GUID for tracking links/queries together.
	 * @return string
	 */
	private function makeGUIDv4(){
		//this function always exists in php7.
		$data = openssl_random_pseudo_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 * @return bool
	 */
	public function isDone(){
		return $this->done;
	}

	/**
	 * @return string
	 */
	public function getQuery(): string {
		return $this->query;
	}

	/**
	 * @return string
	 */
	public function getGuid(): string {
		return $this->guid;
	}

	/**
	 * @param $result
	 */
	public function success($result){
		$this->resultExists = true;
		$this->result = $result;
		$this->done = true;
		if ($this->success !== null && is_callable($this->success)){
			/** @var callable $function */
			$function = &$this->success;
			$function($result);
		}
	}

	/**
	 * @param $error
	 */
	public function failure($error){
		$this->resultExists = false;
		$this->error = $error;
		$this->done = true;
		if ($this->failure !== null && is_callable($this->failure)){
			/** @var callable $function */
			$function = &$this->failure;
			$function($error);
		}
	}

	/**
	 * @return bool
	 */
	public function hasResult(): bool {
		return $this->resultExists;
	}

	/**
	 * @return \mysqli_result
	 */
	public function getResult() {
		return $this->result;
	}

}