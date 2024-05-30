<?php
declare(strict_types=1);

echo 'Hello, World — with equal rights for everyone!';

$environment = environment();
echo '<pre>'.json_encode($environment, JSON_PRETTY_PRINT).'</pre>';

$records = database($environment);
echo '<pre>'.json_encode($records, JSON_PRETTY_PRINT).'</pre>';

$contents = files();
echo '<pre>'.json_encode($contents, JSON_PRETTY_PRINT).'</pre>';

function environment() {
	return parse_ini_file('../.env');
}

function database($environment) {
	$query = "CREATE TABLE IF NOT EXISTS `table` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`title` varchar(255) NOT NULL,
			`created` datetime NOT NULL,
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	;";
	$binds = [];
	query($environment, $query, $binds);
	
	$query = "INSERT INTO `table` SET `title` = :title, `created` = NOW();";
	$binds = ['title' => 'Hello, World!'];
	query($environment, $query, $binds);
	
	$query = "SELECT * FROM `table`;";
	$binds = [];
	$records = query($environment, $query, $binds);
	
	return $records;
}

function query($environment, $query, $binds) {
	$pdo = new \PDO(
		'mysql:host='.$environment['SQL_HOSTNAME'].';port='.$environment['SQL_PORT'].';dbname='.$environment['SQL_DATABASE'].';charset=utf8mb4',
		$environment['SQL_USERNAME'],
		$environment['SQL_PASSWORD'],
	);
	$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	$statement = $pdo->prepare($query);
	
	foreach ($binds as $key => $value) {
		$parameter = $key;
		
		// the parameter for `bindValue()` should be 1-based
		// whereas a numeric array defaults to 0-based
		if (is_int($parameter)) {
			$parameter++;
		}
		
		$statement->bindValue($parameter, $value);
	}
	
	$statement->execute();
	$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
	$statement->closeCursor();
	
	return $results;
}

function files() {
	file_put_contents('../data/test.txt', 'foobarbaz');
	return file_get_contents('../data/test.txt');
}
