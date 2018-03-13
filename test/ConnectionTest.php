<?php

use JohnCurt\AsyncMySQL\ConnectionManager;
use JohnCurt\AsyncMySQL\Query;

class ConnectionTest extends \PHPUnit\Framework\TestCase {
	public function testConnectionFailsOnNoConnection() {
		$this->expectException(InvalidArgumentException::class);
		$conn = new ConnectionManager(null);
		$this->assertInstanceOf('ConnectionManager', $conn);
	}
	public function testAddQueryAdds(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query = new Query("SELECT 1 as num", $success, $failure);
		$conn->runQuery($query);
		$this->assertEquals(1, $conn->getActiveQueryCount());
	}
	public function testReapingAQueryCausesItToChangeStatus(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query = new Query("SELECT 'testReapingAQueryCausesItToChangeStatus' AS num", $success, $failure);
		$conn->runQuery($query);
		$this->assertEquals(1, $conn->getActiveQueryCount());
		$this->assertFalse($query->isDone());
		sleep(1);
		$conn->reapAny();
		$this->assertTrue($query->isDone());
		$this->assertEquals(0, $conn->getActiveQueryCount());
	}
	public function testQueriesCanHappenOutOfOrderBasedOnTime(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query2 = new Query("SELECT SLEEP(2) as wait, 1 AS num", $success, $failure);
		$query1 = new Query("SELECT 2 AS num", $success, $failure);
		$conn->runQuery($query1); //sleeps 2 secs!
		$conn->runQuery($query2); //should come back immediately;
		$this->assertEquals(2, $conn->getActiveQueryCount());
		$check = $conn->reapAll(5000);
		$this->assertTrue($check);
		$this->assertEquals(0, $conn->getActiveQueryCount());
	}
	public function testReapAllTimeoutStopsLoopEarly(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query = new Query("SELECT SLEEP(2) as wait, 1 AS num", $success, $failure);
		$conn->runQuery($query); //sleeps 2 secs!
		$check = $conn->reapAll(1);
		$this->assertFalse($check);
		$check = $conn->reapAll(1.5);
		$this->assertTrue($check);
	}
	public function testReapAllTimeoutAsZero(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query = new Query("SELECT SLEEP(2) as wait, 1 AS num", $success, $failure);
		$conn->runQuery($query); //sleeps 2 secs!
		$check = $conn->reapAll(0);
		$this->assertTrue($check);
		$this->assertEquals(0, $conn->getActiveQueryCount());
	}
	public function testQueriesAreHappeningSimultaneously(){
		$start = microtime(true);
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$success = null;
		$failure = null;
		$query1 = new Query("SELECT SLEEP(3) as wait, 1 AS num", $success, $failure);
		$query2 = new Query("SELECT SLEEP(3) as wait, 1 AS num", $success, $failure);
		$query3 = new Query("SELECT SLEEP(3) as wait, 1 AS num", $success, $failure);
		$conn->runQuery($query1); //sleeps 10 secs!
		$conn->runQuery($query2); //sleeps 10 secs!
		$conn->runQuery($query3); //sleeps 10 secs!

		$this->assertEquals(3, $conn->getActiveQueryCount());
		$check = $conn->reapAll(0);
		$this->assertTrue($check);
		$this->assertEquals(0, $conn->getActiveQueryCount());
		$elapsed = microtime(true) - $start;
		$this->assertLessThan(4, $elapsed);
	}
	public function testCallsSuccessOnSuccess(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$successVar = false;
		$success = function($result) use (&$successVar){
			$successVar = true;
		};
		$failure = null;
		$query = new Query("SELECT 1 AS num", $success, $failure);
		$conn->runQuery($query); //sleeps 2 secs!
		$check = $conn->reapAll(3);
		$this->assertTrue($check);
		$this->assertTrue($successVar);
		$this->assertEquals(0, $conn->getActiveQueryCount());
	}
	public function testCallsFailureOnSuccess(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		$successVar = false;
		$failure = function($result) use (&$successVar){
			$successVar = true;
		};
		$success = null;
		$query = new Query("SELECT * from table_does_not_exist AS num", $success, $failure);
		$conn->runQuery($query); //sleeps 2 secs!
		$check = $conn->reapAll(3);
		$this->assertTrue($check);
		$this->assertTrue($successVar);
		$this->assertEquals(0, $conn->getActiveQueryCount());
	}
	public function testResultsCanBeReceivedAndTraversed(){
		$conn = new ConnectionManager('127.0.0.1','root','root','test',33060);
		/** @var mysqli_result $result1 */
		$result1 = null;
		/** @var mysqli_result $result2 */
		$result2 = null;
		$success1 = function($result) use (&$result1) {$result1=$result;};
		$success2 = function($result) use (&$result2) {$result2=$result;};
		$query1 = new Query('SELECT 1 as num;',$success1);
		$query2 = new Query('SELECT 2 as num;',$success2);
		$conn->runQuery($query1);
		$conn->runQuery($query2);
		$conn->reapAll();
		//results 1 and 2 should now have the respective resources!
		sleep(1);
		$data = $result1->fetch_assoc();
		$this->assertEquals('1', $data['num']);
		$data = $result2->fetch_assoc();
		$this->assertEquals('2', $data['num']);

	}
}
