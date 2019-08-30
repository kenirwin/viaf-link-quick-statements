<?php
$q = $_REQUEST['q'];
include('SPARQLQueryDispatcher.class.php');

$endpointUrl = 'https://query.wikidata.org/sparql';
$sparqlQueryString = 'SELECT ?predicate ?object WHERE { wd:'. $q.' ?predicate ?object }';

$queryDispatcher = new SPARQLQueryDispatcher($endpointUrl);
$queryResult = $queryDispatcher->query($sparqlQueryString);

//var_dump ($queryResult);
$props = array();

$arr = $queryResult['results']['bindings'];

foreach ($arr as $key=>$item) {
  $prop = $item['predicate']['value'];
  if (preg_match('/\/(P\d+)$/', $prop, $m)) {
    array_push($props,$m[1]);
  }
}

print_r($props);

?>