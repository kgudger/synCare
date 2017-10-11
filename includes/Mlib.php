<?php
require_once "Mail.php";

//error_reporting(E_ALL | E_STRICT);

class DB
{
    private $db;
	function __construct()
	{
    		$db = $this->connect();
	}

	function connect()
	{
	    if ($this->db == 0)
	    {
	        require_once("db2convars.php");
		try {
	        /* Establish database connection */
	        	$this->db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpwd);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
			echo "Unable to connect: " . $e->getMessage() ."<p>";
			die();
		}


	    }
	    return $this->db ;
	}

	function sendEmails($name,$Id) {
		
		$sql = "SELECT amount, date, currency.currency AS cur,
					category.category AS cat,realname, 
					complete, currency.convert AS conv
				FROM requests, users, currency, category
				WHERE who=users.uid AND
					category.catid = requests.catid AND
					currency.cid=currencyid
				ORDER BY date DESC
				LIMIT 1";
		$result = $this->db->query($sql);
		$row = $result->fetch(PDO::FETCH_ASSOC);
		
		$sql = "SELECT uname, email, percent, realname,
					currency.convert AS conv, currency.currency AS cur
				FROM users, contribution, currency
				WHERE contribution.uid = users.uid AND
					contribution.percent > 0 AND
					currency.cid = users.currency";
		$result = $this->db->query($sql);
		while ($row1 = $result->fetch(PDO::FETCH_ASSOC)) {
			$pamount = number_format($row[amount]*$row1[percent]*$row1[conv]/(100*$row[conv]),2, '.', '') ;
			$from = '<kgudger@gmail.com>';
			$to = '<' . $row1[email] . '>';
			$subject = 'Support Request';
			$total = number_format($row[amount]*$row1[conv]/($row[conv]),2, '.', '');
			$body = "Dear $row1[realname]\n\n$row[realname] asks that you please visit\nhttp://home.loosescre.ws/~keith/synCare/synCare/www/?username=$row1[uname]&amount=$pamount&currency=$row1[cur]&category=$row[cat]&total=$total";

			$headers = array(
				'From' => $from,
				'To' => $to,
				'Subject' => $subject
			);

			$smtp = Mail::factory('smtp', array(
				'host' => 'ssl://smtp.gmail.com',
				'port' => '465',
				'auth' => true,
				'username' => $username,
				'password' => $password
			));
			$mail = $smtp->send($to, $headers, $body);

			if (PEAR::isError($mail)) {
				echo('<p>' . $mail->getMessage() . '</p>');
			} else {
				echo('<p>Message successfully sent!</p>');
			}
		}
	}

	function getCon($uid) {

		$output = array();
		$sql = "SELECT amount, date, currency.currency AS cur,
					category.category AS cat,realname, 
					complete, currency.convert AS conv
				FROM requests, users, currency, category
				WHERE who=users.uid AND
					category.catid = requests.catid AND
					currency.cid=currencyid AND
					complete = 0
				ORDER BY date DESC
				LIMIT 1";
		$result = $this->db->query($sql);
		$row = $result->fetch(PDO::FETCH_ASSOC);
		
		$sql = "SELECT uname, email, percent, realname,
					currency.convert AS conv, currency.currency AS cur
				FROM users, contribution, currency
				WHERE contribution.uid = users.uid AND
					users.uid = ? AND
					contribution.percent > 0 AND
					currency.cid = users.currency";
		$result = $this->db->prepare($sql);
		$result->execute(array($uid));
		while ($row1 = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp = array();
			$total    = $row[amount];
			$pamount  = $row[amount]*$row1[percent]*$row1[conv]/(100*$row[conv]) ;
			$temp[total] = $total;
			$temp[requestor] = $row[realname];
			$temp[donor] = $row1[uname];
			$temp[currency] = $row[cur];
			$temp[category] = $row[cat];
			$temp[donor_amount] = number_format($pamount,2, '.', '');
			$temp[donor_currency] = $row1[cur];
			array_push($output,$temp);
		}
		echo json_encode($output) ;
    }
	
	function getUsers()
	{
	  $output = array();
	  $sql = "SELECT users.uid AS uuid, uname, realname, email, rolename, 
			currency.currency AS money, permission.permission AS perm,
			percent
			FROM users, role, permission, currency, contribution
			WHERE role = rid AND
				users.permission = permission.pid AND
				users.currency = currency.cid AND
				contribution.uid = users.uid" ;
		$result = $this->db->query($sql);
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp = array("uid"=>$row[uuid], "username"=>$row[uname],
						"fullname"=>$row[realname],
						"email"=>$row[email], "role"=>$row[rolename],
						"permission"=>$row[perm], "currency"=>$row[money],
						"percent"=>$row[percent]);
			array_push($output,$temp);
		}
	  echo json_encode($output) ;
    }

	function getXactions($uid)
	{
		$output = array();
		$sql = "SELECT amount, date, currency.currency AS cur,
					category.category AS cat,realname,
					currency.convert AS conv
				FROM requests, users, currency, category
				WHERE who=users.uid AND
					category.catid = requests.catid AND
					currency.cid=currencyid AND
					complete = 0 AND
					currency.cid=currencyid
				ORDER BY date DESC
				LIMIT 1";
		$result = $this->db->query($sql);
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp=array();
			$sql = "SELECT currency.currency AS cur, currency.convert AS conv
					FROM users, currency
					WHERE users.uid = ? AND
					users.currency = currency.cid";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($uid));
			$row1 = $stmt->fetch(PDO::FETCH_ASSOC);
			$temp[type] = "request";
			$temp[realname] = $row[realname];
			$temp[amount] = number_format($row[amount] * $row1[conv] / $row[conv],2, '.', '');
			$date = new DateTime($row[date]);
			$temp[date] = $date->format('F j, Y');
			$temp[currency] = $row1[cur];
			$temp[category] = $row[cat];
			$sql = "SELECT percent 
					FROM contribution
					WHERE contribution.uid = ?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($uid));
			$rows = $stmt->fetch(PDO::FETCH_ASSOC);
			$per = $rows[percent];
			$pamount = number_format($row[amount]*$per/100 * $row1[conv] / $row[conv],2, '.', '') ;
			$tot = $row['amount'];
			$temp[contribution] = $pamount;
			array_push($output,$temp);
		}
		$sql = "SELECT amount, date, currency.currency AS cur,
					category.category AS cat,realname,
					currency.convert AS conv
				FROM transactions, users, currency, category
				WHERE who=users.uid AND
					category.catid = transactions.catid AND
					currency.cid=currencyid AND
					currency.cid=currencyid
				ORDER BY date DESC";
		$result = $this->db->query($sql);
		$conv = 0 ;
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp=array();
			$temp[type] = "transaction";
			$temp[realname] = $row[realname];
			$conv = $row[conv] ;
			$temp[amount] = number_format($row[amount] * $row1[conv] / $conv,2, '.', '');
			$date = new DateTime($row[date]);
			$temp[date] = $date->format('F j, Y');
			$temp[currency] = $row1[cur];
			$temp[category] = $row[cat];
			array_push($output,$temp);
		}
		$sql = "SELECT SUM(amount) AS Total 
				FROM transactions
				WHERE who = ?";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($uid));
		$row2 = $stmt->fetch(PDO::FETCH_ASSOC);
		if (empty($row2[Total]))
			$tot = 0 ; //$row1[Total];
		else $tot = number_format($row2[Total]* $row1[conv] / $conv,2, '.', '');
		$temp = array();
		$temp[type]="total";
		$temp[amount]=$tot;
		array_push($output,$temp);
		echo json_encode($output) ;
        }

	function getRequests()
	{
		$sql = "SELECT amount, date, currency.currency AS cur,
					category.category AS cat,realname, complete
				FROM requests, users, currency, category
				WHERE who=users.uid AND
					category.catid = requests.catid AND
					currency.cid=currencyid AND
					complete = 0 
				ORDER BY date DESC";
		$result = $this->db->query($sql);
		$output = array();
		$cname = "" ;
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp[realname] = $row[realname];
			$temp[amount] = number_format($row[amount],2, '.', '');
			$temp[date] = $row[date];
			$temp[currency] = $row[cur];
			$temp[category] = $row[cat];
			array_push($output,$temp);
		}
		echo json_encode($output) ;
        }

	function putRequest($name,$amt,$cat,$cur)
	{
		$sql = "INSERT INTO `requests` 
			(`who`, `catid`, `currencyid`)
				SELECT users.uid, category.catid, currency.cid 
				FROM users, category, currency
				WHERE users.uname = ? AND 
					category.category = ? AND
					currency.currency=?";
					
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($name,$cat,$cur));
		$lastId = $this->db->lastInsertId();
		$sql = "UPDATE `requests` 
				SET amount = ?
				WHERE reqid = $lastId";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($amt));
		$this->sendEmails($name,$lastId);
		echo "Success, entered $amt for $name";
	}
	
	function getStatus()
	{
		$output = array();
		$sql = "SELECT reqid
				FROM requests
				WHERE complete = 0 
				ORDER BY date DESC
				LIMIT 1";
		$result = $this->db->query($sql);
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$reqid = $row[reqid] ;
		}
		$sql = "SELECT realname, users.uid AS uid, percent, 
					currency.currency AS cur, currency.convert AS conv
				FROM users, contribution, currency
				WHERE users.uid = contribution.uid AND
					percent > 0 AND
					currency.cid = users.currency";
		$result = $this->db->query($sql);
		while ($row1 = $result->fetch(PDO::FETCH_ASSOC)) {
			$temp = array();
			$nuid = $row1[uid];
			$sql = "SELECT who, reqid, amount, currency.currency AS cur
					FROM transactions, currency
					WHERE (reqid = $reqid) AND
					who = $nuid AND
					currency.cid=transactions.currencyid";
			$result1 = $this->db->query($sql);
			$temp[name] = $row1[realname];
			$row2 = $result1->fetch(PDO::FETCH_ASSOC);
			if (!empty($row2)) {
				$temp[contributed] = number_format($row2[amount],2, '.', '');;
			} else {
				$temp[contributed] = 0;
			}
			$temp[currency] = $row1[cur];
			array_push($output,$temp);
		}
		echo json_encode($output) ;
    }

	function putX($name,$amt,$cat,$cur)
	{
		$sql = "INSERT INTO `transactions` 
			(`who`, `catid`, `currencyid`)
				SELECT users.uid, category.catid, currency.cid 
				FROM users, category, currency
				WHERE users.uname = ? AND 
					category.category = ? AND
					currency.currency=?";
					
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($name,$cat,$cur));
		$lastId = $this->db->lastInsertId();
//		echo "lastID is $lastId, cat is $cat, cur is $cur";
		$sql = "SELECT reqid
				FROM requests
				WHERE complete = 0
				ORDER BY date DESC
				LIMIT 1";
		$result1 = $this->db->query($sql);
		$row1 = $result1->fetch(PDO::FETCH_ASSOC);
		$reqid = $row1[reqid];
		$sql = "UPDATE `transactions` 
				SET amount = ?, reqid = $reqid
				WHERE tid = $lastId";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array($amt));
		echo "Success, entered $amt for $name";
	}
	
}
