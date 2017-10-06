<?php 

namespace KISSMVC;

//===============================================================
// Model/ORM
// Requires a function getdbh() which will return a PDO handler
/*
function getdbh() {
	if (!isset($GLOBALS['dbh']))
		try {
			//$GLOBALS['dbh'] = new PDO('sqlite:'.APP_PATH.'db/dbname.sqlite');
			$GLOBALS['dbh'] = new PDO('mysql:host=localhost;dbname=dbname', 'username', 'password');
		} catch (PDOException $e) {
			die('Connection failed: '.$e->getMessage());
		}
	return $GLOBALS['dbh'];
}
*/
//===============================================================
class KISS_Model  {

	public $pkname;
	public $tablename;
	public $dbhfnname;
	public $QUOTE_STYLE='MYSQL'; // valid types are MYSQL,MSSQL,ANSI
	public $COMPRESS_ARRAY=true;
	public $rs = array(); // for holding all object property variables

	function __construct($pkname='',$tablename='',$dbhfnname='getdbh',$quote_style='MYSQL',$compress_array=true) {
		$this->pkname=$pkname; //Name of auto-incremented Primary Key
		$this->tablename=$tablename; //Corresponding table in database
		$this->dbhfnname=$dbhfnname; //dbh function name
		$this->QUOTE_STYLE=$quote_style;
		$this->COMPRESS_ARRAY=$compress_array;
	}

	function get($key) {
		return $this->rs[$key];
	}

	function set($key, $val) {
		if (isset($this->rs[$key]))
			$this->rs[$key] = $val;
		return $this;
	}

	function __get($key) {
		return $this->get($key);
	}

	function __set($key, $val) {
		return $this->set($key,$val);
	}

	protected function getdbh() {
		return call_user_func($this->dbhfnname);
	}

	protected function enquote($name) {
		if ($this->QUOTE_STYLE=='MYSQL')
			return '`'.$name.'`';
		elseif ($this->QUOTE_STYLE=='MSSQL')
			return '['.$name.']';
		else
			return '"'.$name.'"';
	}

	//Inserts record into database with a new auto-incremented primary key
	//If the primary key is empty, then the PK column should have been set to auto increment
	function create() {
		$dbh=$this->getdbh();
		$pkname=$this->pkname;
		$s1=$s2='';
		foreach ($this->rs as $k => $v)
			if ($k!=$pkname || $v) {
				$s1 .= ','.$this->enquote($k);
				$s2 .= ',?';
			}
		$sql = 'INSERT INTO '.$this->enquote($this->tablename).' ('.substr($s1,1).') VALUES ('.substr($s2,1).')';
		$stmt = $dbh->prepare($sql);
		$i=0;
		foreach ($this->rs as $k => $v)
			if ($k!=$pkname || $v)
				$stmt->bindValue(++$i,is_scalar($v) ? $v : ($this->COMPRESS_ARRAY ? gzdeflate(serialize($v)) : serialize($v)) );
		$stmt->execute();
		if (!$stmt->rowCount())
			return false;
		$this->set($pkname,$dbh->lastInsertId());
		return $this;
	}

	function retrieve($pkvalue) {
		$dbh=$this->getdbh();
		$sql = 'SELECT * FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname).'=?';
		$stmt = $dbh->prepare($sql);
		$stmt->bindValue(1,(int)$pkvalue);
		$stmt->execute();
		$rs = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($rs)
			foreach ($rs as $key => $val)
				if (isset($this->rs[$key]))
					$this->rs[$key] = is_scalar($this->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
		return $this;
	}

	function update() {
		$dbh=$this->getdbh();
		$s='';
		foreach ($this->rs as $k => $v)
			$s .= ','.$this->enquote($k).'=?';
		$s = substr($s,1);
		$sql = 'UPDATE '.$this->enquote($this->tablename).' SET '.$s.' WHERE '.$this->enquote($this->pkname).'=?';
		$stmt = $dbh->prepare($sql);
		$i=0;
		foreach ($this->rs as $k => $v)
			$stmt->bindValue(++$i,is_scalar($v) ? $v : ($this->COMPRESS_ARRAY ? gzdeflate(serialize($v)) : serialize($v)) );
		$stmt->bindValue(++$i,$this->rs[$this->pkname]);
		return $stmt->execute();
	}

	function delete() {
		$dbh=$this->getdbh();
		$sql = 'DELETE FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname).'=?';
		$stmt = $dbh->prepare($sql);
		$stmt->bindValue(1,$this->rs[$this->pkname]);
		return $stmt->execute();
	}

	//returns true if primary key is a positive integer
	//if checkdb is set to true, this function will return true if there exists such a record in the database
	function exists($checkdb=false) {
		if ((int)$this->rs[$this->pkname] < 1)
			return false;
		if (!$checkdb)
			return true;
		$dbh=$this->getdbh();
		$sql = 'SELECT 1 FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname)."='".$this->rs[$this->pkname]."'";
		$result = $dbh->query($sql)->fetchAll();
		return count($result);
	}

	function merge($arr) {
		if (!is_array($arr))
			return false;
		foreach ($arr as $key => $val)
			if (isset($this->rs[$key]))
				$this->rs[$key] = $val;
		return $this;
	}

	function retrieve_one($wherewhat,$bindings) {
		$dbh=$this->getdbh();
		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT * FROM '.$this->enquote($this->tablename);
		if (isset($wherewhat) && isset($bindings))
			$sql .= ' WHERE '.$wherewhat;
		$sql .= ' LIMIT 1';
		$stmt = $dbh->prepare($sql);
		$stmt->execute($bindings);
		$rs = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$rs)
			return false;
		foreach ($rs as $key => $val)
			if (isset($this->rs[$key]))
				$this->rs[$key] = is_scalar($this->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
		return $this;
	}

	function retrieve_many($wherewhat='',$bindings='') {
		$dbh=$this->getdbh();
		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT * FROM '.$this->tablename;
		if ($wherewhat)
			$sql .= ' WHERE '.$wherewhat;
		$stmt = $dbh->prepare($sql);
		$stmt->execute($bindings);
		$arr=array();
		$class=get_class($this);
		while ($rs = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$myclass = new $class();
			foreach ($rs as $key => $val)
				if (isset($myclass->rs[$key]))
					$myclass->rs[$key] = is_scalar($myclass->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
			$arr[]=$myclass;
		}
		return $arr;
	}

	function select($selectwhat='*',$wherewhat='',$bindings='',$pdo_fetch_mode=PDO::FETCH_ASSOC) {
		$dbh=$this->getdbh();
		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT '.$selectwhat.' FROM '.$this->tablename;
		if ($wherewhat)
			$sql .= ' WHERE '.$wherewhat;
		$stmt = $dbh->prepare($sql);
		$stmt->execute($bindings);
		return $stmt->fetchAll($pdo_fetch_mode);
	}
}