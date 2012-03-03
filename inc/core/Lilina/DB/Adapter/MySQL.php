<?php
/**
 * MySQL DB adapter
 *
 * @package Lilina
 * @subpackage Database
 */

/**
 * MySQL DB adapter
 *
 * @package Lilina
 * @subpackage Database
 */
class Lilina_DB_Adapter_MySQL extends Lilina_DB_Adapter_Base implements Lilina_DB_Adapter {
	/**
	 * PDO handle
	 */
	protected $db;

	/**
	 * Table prefix
	 */
	protected $prefix;

	/**
	 * Create new MySQL DB adapter
	 *
	 * @param array $options Associative array, with keys 'host', 'db', 'user' and 'pass'
	 */
	public function __construct($options) {
		$defaults = array(
			'prefix' => 'lilina_'
		);
		$options = array_merge($defaults, $options);

		// This is probably unsafe
		$dsn = 'mysql:host=' . $options['host'] . ';dbname=' . $options['db'];
		$this->db = new PDO($dsn, $options['user'], $options['pass']);
		$this->prefix = $options['prefix'];

		// We need this so that `int`s are fetched as integers, etc
		// when using mysqlnd
		$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * Retrieve rows from the database
	 *
	 * @param array $options Options array, see Lilina_DB
	 * @return array Rows which match all the conditions
	 */
	public function retrieve($options) {
		$default = array(
			'table' => null,
			'fields' => null,
			'where' => array(),
			'limit' => null,
			'offset' => 0,
			'orderby' => array(),
			'fetchas' => 'array',
			'reindex' => null
		);
		$options = array_merge($default, $options);

		if (empty($options['table'])) {
			throw new Lilina_DB_Exception('Table must be specified', 'db.general.missingtable');
		}
		$options['table'] = $this->prefix . $options['table'];

		if ($options['fields'] === null) {
			$fields = '*';
		}
		else {
			$fields = $options['fields'];
			$fields = implode(', ', $fields);
		}
		$sql = 'SELECT ' . $fields . ' FROM ' . $options['table'];

		// Check conditions
		$values = array();
		if (!empty($options['where'])) {
			$where = self::build_where($options['where']);
			$sql .= $where[0];
			$values = $where[1];
		}

		// Order our data
		if ($options['orderby'] !== null && !empty($options['orderby']['key'])) {
			$sql .= ' ORDER BY `' . $options['orderby']['key'] .'`';
			if (!empty($options['orderby']['direction']) && $options['orderby']['direction'] === 'desc') {
				 $sql .= ' DESC';
			}
		}

		// Cut down to just what we need
		if ($options['limit'] !== null) {
			if ($options['offset'] !== 0) {
				$sql .= ' LIMIT ' . $options['limit'] . ' OFFSET ' . $options['offset'];
			}
			else {
				$sql .= ' LIMIT ' . $options['limit'];
			}
		}
		elseif ($options['offset'] !== 0) {
			// absurdly large number, since we can't use an offset otherwise
			$sql .= ' LIMIT 18446744073709551615 OFFSET ' . $options['offset'];
		}

		$sql .= ';';
		$stmt = $this->db->prepare($sql);

		if (!empty($values)) {
			foreach ($values as $key => $value) {
				$stmt->bindValue(':' . $key, $value);
			}
		}

		if (!$stmt->execute()) {
			$error = $stmt->errorInfo();
			throw new Lilina_DB_Exception($error[2]);
		}

		// We have to do this because PDO::FETCH_CLASS doesn't call __set()
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($options['reindex'])) {
			$new = array();
			$index = $options['reindex'];
			foreach ($data as $row) {
				$key = $row[$index];
				$new[$key] = $row;
			}

			$data = $new;
		}

		if ($options['fetchas'] !== 'array') {
			foreach ($data as $id => $row) {
				$data[$id] = new $options['fetchas']();
				foreach ($row as $k => $v) {
					$data[$id]->$k = $v;
				}
			}
		}

		return $data;
	}

	/**
	 * Retrieve rows from the database
	 *
	 * @param array $options Options array, see Lilina_DB
	 * @return array Row count
	 */
	public function count($options) {
		$default = array(
			'table' => null,
			'where' => array(),
			'limit' => null,
			'offset' => 0
		);
		$options = array_merge($default, $options);

		if (empty($options['table'])) {
			throw new Lilina_DB_Exception('Table must be specified', 'db.general.missingtable');
		}
		$options['table'] = $this->prefix . $options['table'];

		$sql = 'SELECT COUNT(*) FROM ' . $options['table'];

		// Check conditions
		$values = array();
		if (!empty($options['where'])) {
			$where = self::build_where($options['where']);
			$sql .= $where[0];
			$values = $where[1];
		}

		// Cut down to just what we need
		if ($options['limit'] !== null) {
			if ($options['offset'] !== 0) {
				$sql .= ' LIMIT ' . $options['limit'] . ' OFFSET ' . $options['offset'];
			}
			else {
				$sql .= ' LIMIT ' . $options['limit'];
			}
		}
		elseif ($options['offset'] !== 0) {
			// absurdly large number, since we can't use an offset otherwise
			$sql .= ' LIMIT 18446744073709551615 OFFSET ' . $options['offset'];
		}

		$sql .= ';';
		$stmt = $this->db->prepare($sql);

		if (!empty($values)) {
			foreach ($values as $key => $value) {
				$stmt->bindValue(':' . $key, $value);
			}
		}

		if (!$stmt->execute()) {
			$error = $stmt->errorInfo();
			throw new Lilina_DB_Exception($error[2]);
		}

		return $stmt->fetchColumn(0);
	}

	/**
	 * Insert rows into the database
	 *
	 * @param array|object $data Data array, see source for reference
	 * @param array $options Options array, see source for reference
	 * @return boolean
	 */
	public function insert($data, $options) {
		$default = array(
			'table' => null,
			'primary' => null,
		);
		$options = array_merge($default, $options);

		if (empty($options['table'])) {
			throw new Lilina_DB_Exception('Table must be specified', 'db.general.missingtable');
		}
		$options['table'] = $this->prefix . $options['table'];
		if (empty($options['primary'])) {
			throw new Lilina_DB_Exception('Primary key must be specified for insert', 'db.insert.missingprimary');
		}

		if (is_object($data)) {
			$data = self::object_to_array($data, $options);
		}
		if (!is_array($data)) {
			throw new Lilina_DB_Exception('Data must be an object or array', 'db.general.datatypewrong');
		}

		$sql = 'INSERT INTO ' . $options['table'] . ' SET ';
		$fields = array();
		foreach ($data as $key => $value) {
			$fields[] = '`' . $key . '` = :' . $key;
		}
		$sql .= implode(', ', $fields);

		$stmt = $this->db->prepare($sql);
		foreach ($data as $key => $value) {
			$stmt->bindValue(':' . $key, $value);
		}

		try {
			if (!$stmt->execute()) {
				$error = $stmt->errorInfo();
				throw new Lilina_DB_Exception($error[2]);
			}
		}
		catch (PDOException $e) {
			switch ($e->getCode()) {
				case '23000':
					throw new Lilina_DB_Exception($e->getMessage(), 'db.insert.duplicate');
				default:
					throw $e;
			}
		}

		return true;
	}

	/**
	 * Update rows in the database
	 *
	 * @param array|object $data Data array, see source for reference
	 * @param array $options Options array, see source for reference
	 * @return boolean
	 */
	public function update($data, $options) {
		$default = array(
			'table' => null,
			'where' => array(),
			'orderby' => array(),
			'limit' => null,
		);
		$options = array_merge($default, $options);

		if (empty($options['table'])) {
			throw new Lilina_DB_Exception('Table must be specified', 'db.general.missingtable');
		}
		$options['table'] = $this->prefix . $options['table'];
		if (empty($options['where'])) {
			throw new Lilina_DB_Exception('Condition must be specified for update', 'db.update.missingwhere');
		}

		if (is_object($data)) {
			$data = self::object_to_array($data, $options);
		}
		if (!is_array($data)) {
			throw new Lilina_DB_Exception('Data must be an object or array', 'db.general.datatypewrong');
		}

		$sql = 'UPDATE ' . $options['table'] . ' SET ';
		$fields = array();
		foreach ($data as $key => $value) {
			$fields[] = '`' . $key . '` = :' . $key;
		}
		$sql .= implode(', ', $fields);

		if (!empty($options['where'])) {
			$where = self::build_where($options['where'], $data);
			$sql .= $where[0];
			$data = array_merge($data, $where[1]);
		}

		// Order our data
		if ($options['orderby'] !== null && !empty($options['orderby']['key'])) {
			$sql .= ' ORDER BY `' . $options['orderby']['key'] .'`';
			if (!empty($options['orderby']['direction']) && $options['orderby']['direction'] === 'desc') {
				 $sql .= ' DESC';
			}
		}

		if ($options['limit'] !== null) {
			$sql .= ' LIMIT ' . $options['limit'];
		}

		$stmt = $this->db->prepare($sql);

		foreach ($data as $key => $value) {
			$stmt->bindValue(':' . $key, $value);
		}

		if (!$stmt->execute()) {
			$error = $stmt->errorInfo();
			throw new Lilina_DB_Exception($error[2]);
		}

		return true;
	}

	/**
	 * Delete rows from the database
	 *
	 * @param array $options Options array, see source for reference
	 * @return boolean
	 */
	public function delete($options) {
		$default = array(
			'table' => null,
			'where' => array(),
			'orderby' => array(),
			'limit' => null,
		);
		$options = array_merge($default, $options);

		if (empty($options['table'])) {
			throw new Lilina_DB_Exception('Table must be specified', 'db.general.missingtable');
		}
		$options['table'] = $this->prefix . $options['table'];
		if (empty($options['where'])) {
			throw new Lilina_DB_Exception('Condition must be specified for update', 'db.update.missingwhere');
		}

		$sql = 'DELETE FROM ' . $options['table'];

		$where = self::build_where($options['where']);
		$sql .= $where[0];
		$data = $where[1];

		// Order our data
		if ($options['orderby'] !== null && !empty($options['orderby']['key'])) {
			$sql .= ' ORDER BY `' . $options['orderby']['key'] .'`';
			if (!empty($options['orderby']['direction']) && $options['orderby']['direction'] === 'desc') {
				 $sql .= ' DESC';
			}
		}

		if ($options['limit'] !== null) {
			$sql .= ' LIMIT ' . $options['limit'];
		}

		$stmt = $this->db->prepare($sql);

		foreach ($data as $key => $value) {
			$stmt->bindValue(':' . $key, $value);
		}

		if (!$stmt->execute()) {
			$error = $stmt->errorInfo();
			throw new Lilina_DB_Exception($error[2]);
		}

		return true;
	}

	protected function build_where($where, $data = array()) {
		$sql = ' WHERE (';
		$conditions = array();
		foreach ($where as $condition) {
			if (!is_array($condition)) {
				throw new Lilina_DB_Exception('WHERE conditions must be arrays of arrays', 'db.general.invalidwhere');
			}
			switch ($condition[1]) {
				case '==':
				case '===':
					$condition[1] = '=';
					break;
				case '!=':
				case '!==':
					$condition[1] = '!=';
					break;
			}
			$key = $condition[0];
			if (isset($data[$key])) {
				$key = '__noconflict_' . $key;
			}

			$conditions[] = '`' . $condition[0] . '` ' . $condition[1] . ' :' . $key;
			$values[$key] = $condition[2];
		}
		$sql .= implode(' AND ', $conditions) . ')';

		return array($sql, $values);
	}
}