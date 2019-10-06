<?php
namespace core;
use core\Error;

/**
 * Small SQL abstractions.
 */
trait DbOrm {
	/**
	 * Insert entry into database.
	 * WARN: Please be careful to supply a $table from a safe source!
	 */
	public function insert($table, array $keyValue, $return_idx=true) {
		$values = [];
		$fields = [];
		foreach (array_keys($keyValue) as $key) {
			$fields[] = "`$key`";
			$values[] = "?";
		}

		$query = sprintf(
			"INSERT INTO `%s` (%s) VALUES(%s)",
			$table,
			implode(", ", $fields),
			implode(", ", $values)
		);
		$stmt = $this->query($query, array_values($keyValue));
		if ($stmt->rowCount() != 1) {
			user_error("Insert did not affect the DB?");
		}
		if ($return_idx === false) {
			return -1;
		}

		$idx = $this->db->lastInsertId();
		if (! is_numeric($idx) || $idx === "0") {
			user_error("Failed reading insert id");
		}
		return $idx;
	}

	/**
	 * Insert on new else update
	 */
	public function insertUpdate($table, array $keyValue, array $onUpdate) {
		$values = [];
		$fields = [];
		$update = [];
		foreach (array_keys($keyValue) as $key) {
			$fields[] = "`$key`";
			$values[] = "?";
		}
		foreach (array_keys($onUpdate) as $key) {
			$update[] = "`$key` = ?";
		}

		$query = sprintf(
			"INSERT INTO `%s` (%s) VALUES(%s) ON DUPLICATE KEY UPDATE %s",
			$table,
			implode(", ", $fields),
			implode(", ", $values),
			implode(",", $update)
		);
		$stmt = $this->query($query, array_merge(array_values($keyValue), array_values($onUpdate)));
		if ($stmt->rowCount() > 2) {
			user_error(sprintf("Invalid rowCount(%d) for query=%s", $stmt->rowCount(), $query));
		}
	}

	public function update($table, array $values, array $where, $row_count = 1) {
		$updates = [];
		$wheres = [];
		foreach (array_keys($values) as $key) {
			$updates[] = sprintf("`%s` = ?", $key);
		}
		foreach ($where as $key => $val) {
			if ($val === null) {
				$wheres[] = sprintf("`%s` IS ?", $key);
			} else {
				$wheres[] = sprintf("`%s` = ?", $key);
			}
		}
		$query = sprintf(
			"UPDATE `%s` SET %s WHERE %s %s",
			$table,
			implode(", ", $updates),
			implode("AND ", $wheres),
			$row_count !== null ? "LIMIT $row_count" : ""
		);

		$args = array_merge(
			array_values($values), array_values($where)
		);
		$stmt = $this->query(
			$query, $args
		);

		if ($row_count !== null) {
			if ($stmt->rowCount() != $row_count) {
				user_error(sprintf(
					"db.update.affected expect=%s,affect=%s for query=%s args=%s",
					$row_count, $stmt->rowCount(), $query, implode(", ", $args)
				));
			}
		}
		return $stmt->rowCount();
	}

	public function delete($table, array $where) {
		$depend = [];
		foreach (array_keys($where) as $key) {
			$depend[] = "`$key` = ?";
		}
		$sql = "DELETE FROM `$table` WHERE " . implode(" AND ", $depend);
		$stmt = $this->query($sql, array_values($where));
		return $stmt->rowCount();
	}
}

/**
 * Database result abstraction in array's.
 *
 * Why yet another DB class?
 * Simplicity! I hated how many LOC (Lines of Code)
 * all available libs introduced.
 *
 * Why instantiate?
 * Because multiple DBs is a realistic use-case.
 */
class Db {
	use DbOrm;
	/** \PDO */
	private $db;
	/** []\PDOStatement Re-use existing query's */
	private $cache = [];

	/**
	 * Create a new persistant conn to the DB.
	 */
	public function __construct($dsn, $user, $pass) {
		$this->db = new \PDO($dsn, $user, $pass, array());
		$this->db->setAttribute(
			\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION
		);
		$db = explode(":", $dsn)[0];
		if ($db === "mysql") {
			$this->db->query("SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES'");
		} else if ($db === "sqlite") {
			// sqlite
			$this->db->query("PRAGMA strict=ON");
		} else {
			user_error("Unsupported DB: $db");
		}
	}

	/**
	 * Close the DB-conn (supressing any errors like timeouts)
	 */
	public function close() {
                // Supress broken pipe error on destroy
                Error::mute();
		$this->cache = [];
                $this->db = null;
                Error::unmute();
        }
	
	/**
	 * Run query.
	 */
	private function query($query, array $args) {
		try {
			$h = md5($query);
			if (! isset($this->cache[$h])) {
				$this->cache[$h] = $this->db->prepare($query);
			}
			$stmt = $this->cache[$h];
			$ok = $stmt->execute($args);
			if (! $ok) {
				user_error("SQL failed query=$query");
			}
		} catch (\PDOException $e) {
			$msg = str_replace("\n", "", $e->getMessage());
			$args = str_replace("\n", "", print_r($args, true));
			user_error(sprintf("SQL reason=[%s] query=[%s] and args=[%s]",
				$msg, $query, $args
			));
		}
		return $stmt;
	}

	/**
	 * Run query.
	 */
	public function exec($query, array $args = []) {
		return $this->query($query, $args);
	}

	/**
	 * Run query and get ALL data in associative array.
	 * @param bool $unique Force key-entries to be unique (else error)
	 */
	public function getAllMap($key, $query, array $args = [], $unique=true) {
		$output = [];

		$stmt = $this->query($query, $args);
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT)) {
			if ($unique && isset($output[ $row[$key] ])) {
				user_error("getAllMap(duplicate key=$key) for sql=$sql");
			}
			$output[ $row[$key] ] = $row;
		}
		$stmt->closeCursor();

		return $output;
	}

	/**
	 * Run query and get ALL data in associative array.
	 * @return array|bool FALSE on failure
	 */
	public function getAll($query, array $args = []) {
		$stmt = $this->query($query, $args);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Run query and get first row in associative array.
	 * @return array|bool FALSE on failure
	 */
	public function getRow($query, array $args = []) {
		$stmt = $this->query($query, $args);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if (! is_array($row) || count($row) === 0) {
			return false;
		}
		return $row;
	}

	/**
	 * Run query and get single value.
	 * @return mixed|bool FALSE on failure
	 */
	public function getCell($query, array $args = []) {
		$stmt = $this->query($query, $args);
		return $stmt->fetchColumn();
	}	

	/**
	 * Get columns as 1d array
	 * @return array Empty array on failure
	 */
	public function getCol($query, array $args = []) {
		$out = [];
		foreach ($this->getAll($query, $args) as $row) {
			$row = array_values($row);
			$out[] = $row[0];
		}
		return $out;
	}

	/**
	 * Begin new transaction.
	 * @return DbTxn
	 */
	public function txn() {
		if ($this->db->beginTransaction() === false) {
			user_error("db: Failed starting txn");
		}
		return new DbTxn($this->db);
	}
}

/**
 * Transaction abstraction.
 */
class DbTxn {
	private $db;
	private $done;

	public function __construct($db) {
		$this->db = $db;
	}
	public function __destruct() {
		if (! $this->done) {
			error_log("WARN: Transaction never finished!");
			$this->rollback();
		}
	}

	/**
	 * Save (Commit) changes in transaction to DB.
	 */
	public function commit() {
		$this->db->commit();
		$this->done = true;
	}

	/**
	 * Cancel (rollback) changes in transaction.
	 */
	public function rollback() {
		$this->db->rollback();
		$this->done = true;
	}
}

