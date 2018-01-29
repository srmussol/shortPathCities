<?php
/**
 * Created by PhpStorm.
 * User: jaime
 * Date: 29/01/18
 * Time: 7:32
 */

function read($file = "cities.txt")
{
    $fh = fopen($file);
    $cities = [];
    if ($fh) {
        while ($line = fgetcsv($fh,0,"\t")) {
            $cities[$line[0]] = ['latitude' => (float)$line[1],'longitude' => (float)$line[2]];
        }
        fclose($fh);

        return $cities;
    } else {
        echo "file not found";
    }
}

function buildGraph ($nodes, $weightType = "geographical")
{
    $graph = [];
    foreach($nodes as $nodeName => $location) {
        $distances = [];
        foreach($nodes as $nodeName1 => $location1) {
            if($nodeName != $nodeName1)
                $distances[$nodeName1] = call_user_func_array("calculate" . ucfirst($weightType) . "Distance",[$location,$location1]);
        }
        $graph[$nodeName] = $distances;
    }
    return $graph;
}

function calculateDistancePath($graph, $path){
    $distance = 0;
    for ($i=0;$i < count($path) - 1; $i++) {
        $distance += $graph[$path[$i]][$path[$i + 1]];
    }
    return $distance;
}

function findShortestPaths($graph, $source, $exclude){
    $algorithm = new Dijkstra($graph);
    $min = INF;
    $path = [];
    foreach ($graph as $nodeName => $distances){
        if(!in_array($nodeName,$exclude) && $nodeName != $source) {
            $tempPath = $algorithm->shortestPaths($source, $nodeName, $exclude);
            if(!empty($tempPath)) {
                $distance = calculateDistancePath($graph, $tempPath[0]);
                if($min > $distance){
                    $min = $distance;
                    $path = $tempPath[0];
                }
            }
        }
    }
    return $path;
}

function printResults($path,$graph,$type = "console")
{
    if($type == "console") {
        print "We should go: \n";
        $totalNodes = count($path);
        foreach ($path as $key => $cityVisited) {
            echo ($key + 1) . "\t" . $cityVisited . "\n";
        }
        print sprintf("Toltal Distances: %f", calculateDistancePath($graph, $path));
    }

}

function calculateGeographicalDistance($coordinates,$coordinates1)
{
    $earthRadious = 6371.009;

    $degrees = acos((sin(deg2rad($coordinates["latitude"]))*sin(deg2rad($coordinates1["latitude"]))) +
            (cos(deg2rad($coordinates["latitude"]))*cos(deg2rad($coordinates1["latitude"]))*
                cos(deg2rad($coordinates["longitude"]-$coordinates1["longitude"]))));
    return $earthRadious * $degrees;
}

/**
 * @package   fisharebest/algorithm
 * @author    Greg Roach <greg@subaqua.co.uk>
 * @copyright (c) 2015 Greg Roach <greg@subaqua.co.uk>
 * @license   GPL-3.0+
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Class Dijkstra - Use Dijkstra's algorithm to calculate the shortest path
 * through a weighted, directed graph.
 */
class Dijkstra {
    /** @var integer[][] The graph, where $graph[node1][node2]=cost */
    protected $graph;
    /** @var integer[] Distances from the source node to each other node */
    protected $distance;
    /** @var string[][] The previous node(s) in the path to the current node */
    protected $previous;
    /** @var integer[] Nodes which have yet to be processed */
    protected $queue;
    /**
     * @param integer[][] $graph
     */
    public function __construct($graph) {
        $this->graph = $graph;
    }
    /**
     * Process the next (i.e. closest) entry in the queue
     *
     * @param string[] $exclude A list of nodes to exclude - for calculating next-shortest paths.
     *
     * @return void
     */
    protected function processNextNodeInQueue(array $exclude) {
        // Process the closest vertex
        $closest = array_search(min($this->queue), $this->queue);
        if (!empty($this->graph[$closest]) && !in_array($closest, $exclude)) {
            foreach ($this->graph[$closest] as $neighbor => $cost) {
                if (isset($this->distance[$neighbor])) {
                    if ($this->distance[$closest] + $cost < $this->distance[$neighbor]) {
                        // A shorter path was found
                        $this->distance[$neighbor] = $this->distance[$closest] + $cost;
                        $this->previous[$neighbor] = array($closest);
                        $this->queue[$neighbor]    = $this->distance[$neighbor];
                    } elseif ($this->distance[$closest] + $cost === $this->distance[$neighbor]) {
                        // An equally short path was found
                        $this->previous[$neighbor][] = $closest;
                        $this->queue[$neighbor]      = $this->distance[$neighbor];
                    }
                }
            }
        }
        unset($this->queue[$closest]);
    }
    /**
     * Extract all the paths from $source to $target as arrays of nodes.
     *
     * @param string $target The starting node (working backwards)
     *
     * @return string[][] One or more shortest paths, each represented by a list of nodes
     */
    protected function extractPaths($target) {
        $paths = array(array($target));
        while (list($key, $path) = each($paths)) {
            if ($this->previous[$path[0]]) {
                foreach ($this->previous[$path[0]] as $previous) {
                    $copy = $path;
                    array_unshift($copy, $previous);
                    $paths[] = $copy;
                }
                unset($paths[$key]);
            }
        }
        return array_values($paths);
    }
    /**
     * Calculate the shortest path through a a graph, from $source to $target.
     *
     * @param string   $source  The starting node
     * @param string   $target  The ending node
     * @param string[] $exclude A list of nodes to exclude - for calculating next-shortest paths.
     *
     * @return string[][] Zero or more shortest paths, each represented by a list of nodes
     */
    public function shortestPaths($source, $target, array $exclude = array()) {
        // The shortest distance to all nodes starts with infinity...
        $this->distance = array_fill_keys(array_keys($this->graph), INF);
        // ...except the start node
        $this->distance[$source] = 0;
        // The previously visited nodes
        $this->previous = array_fill_keys(array_keys($this->graph), array());
        // Process all nodes in order
        $this->queue = array($source => 0);
        while (!empty($this->queue)) {
            $this->processNextNodeInQueue($exclude);
        }
        if ($source === $target) {
            // A null path
            return array(array($source));
        } elseif (empty($this->previous[$target])) {
            // No path between $source and $target
            return array();
        } else {
            // One or more paths were found between $source and $target
            return $this->extractPaths($target);
        }
    }
}


$cities = read();
$graph = buildGraph($cities);

$cityNames = array_keys($cities);
$totalDistance = INF;
foreach ($cityNames as $startPoint) {
    $useGraph = $graph;
    $source = $startPoint; // start point
    $ways = [$startPoint]; // array save the path
    // handle find the best ways
    while (count($ways) < count($graph)){
        $exclude = $ways;

        unset($exclude[count($exclude) - 1]);
        $path = findShortestPaths($graph, $source, $exclude);
        for($i = 1; $i < count($path);$i++){
            array_push($ways, $path[$i]);
        }
        $source = $ways[count($ways) - 1];
        foreach ($exclude as $c) {
            unset($useGraph[$c]);
        }

    }
    $distance = calculateDistancePath($graph, $ways);
    if ($distance < $totalDistance) {
        $totalDistance = $distance;
        $waydef = $ways;
    }
}

printResults($waydef,$graph);
