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
			if (VERBOSE) echo sprintf("prod=%s clear parentCategory\n", $prod["name"]);
		}
	}
	$db->exec(sprintf("update cats set parentCategory=0 where parentCategory in (%s)", implode(",", $missing)));
}

// https://stackoverflow.com/questions/6372392/traverse-array-as-tree-in-php
class Tree
{
    protected $tree;
    protected $rootid;

    public function __construct($entries)
    {
        $this->tree = array();
        $this->rootid = PHP_INT_MAX;

        /* Build tree under each node */
        foreach($entries as $node)
            $this->buildTree($this->tree, $node);
    }


    /* Build tree */
    protected function buildTree(&$tree, $node) 
    {
        $i = $node['id'];
        $p = $node['parentCategory'];
        $this->rootid = min($this->rootid, $p);
        $tree[$i] = isset($tree[$i]) ? $node + $tree[$i] : $node;
        $tree[$p]['_children'][] = &$tree[$i];
    }


    /* Print tree */
    public function printTree() 
    {
        $this->printSubtree($this->tree[$this->rootid]);
    }


    /* Print subtree under given node */
    protected function printSubtree($node, $depth=0) 
    {
    	global $prodcount, $counter;
        /* Root node doesn't have id */
        if(isset($node['id']))
        {
	    	$n = $prodcount[$node['id']]["count"] ?? "0";
	    	$counter += $n;

            echo str_repeat('...', $depth-1) . " " . $node['name'];
	    	if ($n > 0) {
				echo  "(" . $n . ")";
	    	}
	    	echo "\n";
        }

        /* Explore children */
        if(isset($node['_children']))
        {
            foreach($node['_children'] as $child)
                $this->printSubtree($child, $depth + 1);
        }
    }


    /* Destroy instance data */
    public function __destruct() 
    {
        $this->tree = null;
    }
}

$counter = 0;
$entries = $db->getAll("select * from cats", []);
$prodcount = $db->getAllMap("category", "select category, count(*) as count from prods group by category");

$tree = new Tree($entries);
$tree->printTree();
var_dump($counter);
