<?php
header('Content-type: text/plain');
$outputArr = array();

foreach (['wd1.json','wd2.json'] as $file) {
$wd = file_get_contents($file);
$obj = json_decode($wd);
//print_r ($obj);

$arr = $obj->results->bindings;

foreach ($arr as $k=>$item) {
  $pItem = '';
  $regex = '';
  $authLabel = '';
  $newObj = new stdClass;
  
  if (preg_match('/\/(P\d+)$/', $item->property->value, $m)) {
    $newObj->pItem = $m[1];
  }
  $newObj->regex = $item->format_as_a_regular_expression->value;
  $newObj->authLabel = $item->propertyLabel->value;
  $outputArr[$newObj->authLabel] = $newObj;
}

}
$output = new stdClass;
$output->contents = $outputArr;
print(json_encode($output, JSON_PRETTY_PRINT));

?>