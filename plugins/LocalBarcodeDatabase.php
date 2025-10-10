<?php

namespace Grocy\Plugins;

use \PDO;
use \Exception;
use Grocy\Services\DatabaseService;

// Database connection
class LocalBarcodeDatabase
{
	private $pdo;
	private $dbPath;
	//private $dbPath = "sqlite:grocery.db";
	private static $instance = null;

	public function __construct()
	{
		$this->dbPath = $this->GetDbFilePath();
		$this->pdo = new PDO("sqlite:" . $this->dbPath);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if ($this->getDatabase()->shopping_locations()->where('name = :1', 'Woolworths')->fetch() == null) {
			$this->getDatabase()->shopping_locations()->insert(['name' => 'Woolworths', 'description' => 'Woolworths Supermarket', 'active' => 1]);
		}

		if ($this->getDatabase()->shopping_locations()->where('name = :1', 'Coles')->fetch() == null) {
			$this->getDatabase()->shopping_locations()->insert(['name' => 'Coles', 'description' => 'Coles Supermarket', 'active' => 1]);
		}

		if ($this->getDatabase()->shopping_locations()->where('name = :1', 'Aldi')->fetch() == null) {
			$this->getDatabase()->shopping_locations()->insert(['name' => 'Aldi', 'description' => 'Aldi Supermarket', 'active' => 1]);
		}

		$locations = [
			['name' => 'Baby',          'description' => 'Baby products location'],
			['name' => 'Bar',           'description' => 'Alcohol and bar items'],
			['name' => 'Bathroom',      'description' => 'Health, beauty, and bathroom items'],
			['name' => 'BreadBox',      'description' => 'Bakery and bread storage'],
			['name' => 'Freezer',       'description' => 'Frozen foods storage'],
			['name' => 'Fridge',        'description' => 'Chilled and fresh foods'],
			['name' => 'Garage',        'description' => 'Pet supplies and bulk items'],
			['name' => 'Laundry',       'description' => 'Cleaning and household products'],
			['name' => 'Pantry',        'description' => 'Shelf-stable pantry goods'],
			['name' => 'Snack Cuppord', 'description' => 'Snacks and treats cupboard'],
		];

		foreach ($locations as $loc) {
			$exists = $this->getDatabase()->locations()
				->where('name = :1', $loc['name'])
				->fetch();

			if ($exists == null) {
				$this->getDatabase()->locations()->insert([
					'name' => $loc['name'],
					'description' => $loc['description'],
					'active' => 1
				]);
			}
		}

		//echo $this->pdo->query("PRAGMA database_list")->fetchColumn(2);
	}

	public static function getInstance()
	{
		if (self::$instance == null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function getDatabase()
	{
		return $this->getDatabaseService()->GetDbConnection();
	}

	private function GetDbFilePath()
	{
		return GROCY_DATAPATH . '/shared/' . (defined('GROCY_STOCK_BARCODE_LOOKUP_DATABASE') ? GROCY_STOCK_BARCODE_LOOKUP_DATABASE : '');
	}

	protected function getDatabaseService()
	{
		return DatabaseService::getInstance();
	}
	/**
	 * Generic UPSERT function
	 */
	function upsert_item(string $table, string $lookup_field, array $data)
	{
		$lookup_value = $data[$lookup_field] ?? null;
		if ($lookup_value === null) {
			throw new Exception("Missing lookup field '$lookup_field'");
		}

		// Check if exists
		$stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $lookup_field = :lookup LIMIT 1");
		$stmt->execute(['lookup' => $lookup_value]);
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($existing) {
			// Update
			$sets = [];
			foreach ($data as $key => $value) {
				$sets[] = "$key = :$key";
			}
			$sql = "UPDATE $table SET " . implode(", ", $sets) . " WHERE $lookup_field = :lookup";
			$stmt = $this->pdo->prepare($sql);
			$data['lookup'] = $lookup_value;
			$stmt->execute($data);
		} else {
			// Insert
			$columns = implode(", ", array_keys($data));
			$placeholders = ":" . implode(", :", array_keys($data));
			$sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($data);
		}
	}

	/**
	 * Simple CRUD helpers
	 */
	function create_item(string $table, array $data)
	{
		$columns = implode(", ", array_keys($data));
		$placeholders = ":" . implode(", :", array_keys($data));
		$sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($data);
		return $this->pdo->lastInsertId();
	}

	function get_item(string $table, string $id_field, $id)
	{
		$stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $id_field = :id LIMIT 1");
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	function get_item_and(string $table, array $conditions)
	{
		// Build WHERE clause dynamically
		$whereParts = [];
		$params = [];

		foreach ($conditions as $field => $value) {
			$whereParts[] = "$field = :$field";
			$params[$field] = $value;
		}

		$where = implode(' AND ', $whereParts);

		$stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $where LIMIT 1");
		$stmt->execute($params);

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	function get_item_Like(string $table, array $conditions)
	{
		// Build WHERE clause dynamically
		$whereParts = [];
		$params = [];

		foreach ($conditions as $field => $value) {
			$whereParts[] = "$field LIKE :$field";
			$params[$field] = "'%$value%'"; // Add wildcards here
		}

		$where = implode(' AND ', $whereParts);

		$stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $where");
		$stmt->execute($params);

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	function get_item_Like_MultiWord(string $table, string $field, string $searchString)
	{
		// Split the search string into words by spaces
		$words = preg_split('/\s+/', trim($searchString));

		// Build WHERE clause dynamically
		$whereParts = [];
		$params = [];

		foreach ($words as $i => $word) {
			// Create unique placeholder for each word
			$placeholder = ":word$i";
			$whereParts[] = "$field LIKE $placeholder";
			$params["word$i"] = "%$word%";  // add wildcards here
		}

		// Combine with AND (all words must match) or OR (any word matches)
		$where = implode(' AND ', $whereParts);  // use 'OR' if desired

		$stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $where");
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	function update_item(string $table, string $id_field, $id, array $data)
	{
		$sets = [];
		foreach ($data as $key => $value) {
			$sets[] = "$key = :$key";
		}
		$sql = "UPDATE $table SET " . implode(", ", $sets) . " WHERE $id_field = :id";
		$stmt = $this->pdo->prepare($sql);
		$data['id'] = $id;
		$stmt->execute($data);
	}

	function delete_item(string $table, string $id_field, $id)
	{
		$stmt = $this->pdo->prepare("DELETE FROM $table WHERE $id_field = :id");
		$stmt->execute(['id' => $id]);
	}

	function get_shopping_location_by_name(string $name)
	{
		return $this->getDatabase()->shopping_locations()->where('name = :1', $name)->fetch();
	}

	function get_location_by_name(string $store, string $category)
	{
		$item = $this->get_item_and('Category_Mapping', [
			'store'    => $store,
			'category' => $category
		]);

		if ($item) {
			$name = $item['location'];
			return $this->getDatabase()->locations()->where('name = :1', $name)->fetch();
		}
		return null;
	}
}
