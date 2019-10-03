<?php
/**
 * Stats about prods in cats
 */
require __DIR__ . "/../core/init.php";
require __DIR__ . "/../core/error.php";
require __DIR__ . "/../core/db.php";
require __DIR__ . "/vendor/autoload.php";
use core\Db;

$db = new core\Db(sprintf("sqlite:%s/db.sqlite", __DIR__), "", "");
// Fix parentCategory reference to 0
// TODO 0 => null
{
	$missing = [];
	$prods = $db->getAllMap("id", "select * from cats");
	foreach ($prods as $id => &$prod) {
		if (! isset($prods[ $prod["parentCategory"] ])) {
			$missing[] = $prod["parentCategory"];
			$prod["parentCategory"] = 0;
			echo sprintf("prod=%s clear parentCategory\n", $prod["name"]);
		}
	}
	$db->exec(sprintf("update cats set parentCategory=0 where parentCategory in (%s)", implode(",", $missing)));
}

foreach ($missing as $parent) {
	// TODO
}