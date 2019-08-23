<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-type: text/plain');

$viaf = new Viaf2Wiki('5081794', ['use_local'=>true, 'q'=>'Q61859030'] );
print '<h1>Going for: '.$viaf->id.'</h1>'.PHP_EOL;
foreach ($viaf->pairs as $key => $arr) {
  $val = $viaf->prep($key);
  $viaf->validate($key,$val);
} 

class Viaf2Wiki {

  public function __construct ($id,$opts) {
    $this->base_url = 'https://viaf.org/viaf/';
    $this->format = '/viaf.json';
    $this->id = $id;
    $this->pairs = array();
    $this->setSites();
    if (array_key_exists('use_local',$opts) && ($opts['use_local'] == true)) {
      $this->local = true;
    }
    else {$this->local = false; }
    if (array_key_exists('q',$opts)) {
      $this->q = $opts['q'];
    }

    $this->getData(); //creates $this->obj
    $this->findPairs();
  }

  private function getData() {
    if ($this->local === true ) {
      $json =  file_get_contents($this->id.'.json');
    }
    else {
      // create curl resource 
      $ch = curl_init(); 
      
      $url = $this->base_url . $this->id . $this->format;
      // set url 
      curl_setopt($ch, CURLOPT_URL, $url); 
      
      //return the transfer as a string 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
      
      // $output contains the output string 
      $json = curl_exec($ch); 
      
      // close curl resource to free up system resources 
      curl_close($ch);      
    }
    $this->obj = json_decode($json);
  }
  
  public function findPairs() {
    foreach ($this->obj->sources->source as $src) {
      //      print_r ($src->{'@nsid'});
      list($key,$val) = preg_split('/\|/', $src->{'#text'});
      $this->pairs[$key]['val'] = $val;
      $this->pairs[$key]['nsid'] = $src->{'@nsid'};
    }
  }

  public function prep($key) {
    if ($key == 'LC') {
      return preg_replace('/ /','',$this->pairs[$key]['val']);
    }
    elseif ($key == 'ISNI') {
      if (preg_match('/(\d\d\d\d)(\d\d\d\d)(\d\d\d\d)(\d\d\d.)/',$this->pairs[$key]['val'],$m)) {
	return $m[1].' '.$m[2].' '.$m[3].' '.$m[4];
      }
    }
    elseif ($key == 'BNF') {
      if (preg_match('/cb(\d{8}.)/',$this->pairs[$key]['nsid'], $m)) {
	return $m[1];
      }
    }
    elseif (in_array($key, ['NTA','NII','SUDOC'])) {
      return $this->pairs[$key]['val'];
    }
  }

  public function  validate($key,$val) {
    if (array_key_exists($key, $this->sites)) {
      if (preg_match('/^'.$this->sites[$key]->regex.'$/', $val)) {
	print $this->q."\t".$this->sites[$key]->property."\t".$val.PHP_EOL;
      }
      else { 
	print '# FAILED format constraint: '.$key.' : '.$val.PHP_EOL;
      }
    }
    else { 
      print '#SKIPPED no formatting instructions: '.$key.' : '.$val.PHP_EOL;
    }
  }

  private function setSites() {
    $this->sites = array (
			  'LC' => new stdClass(),
			  'ISNI' => new stdClass(),
			  'BNF' => new stdClass(),
			  'NTA' => new stdClass(),
			  'NII' => new stdClass(),
			  'SUDOC' => new stdClass(),
			  );
    $this->sites['LC']->regex = '((n|nb|nr|no|ns|sh|gf)([4-9][0-9]|00|20[0-1][0-9])[0-9]{6})';
    $this->sites['LC']->property = 'P244';

    $this->sites['ISNI']->regex = '(0000 000[0-4] [0-9]{4} [0-9]{3}[0-9X]|)';
    $this->sites['ISNI']->property = 'P213';

    $this->sites['BNF']->regex = '(\d{8}[0-9bcdfghjkmnpqrstvwxz]|)';
    $this->sites['BNF']->property = 'P268';

    $this->sites['NTA']->regex = '\d{8}(\d|X)';
    $this->sites['NTA']->property = 'P1008';

    $this->sites['NII']->regex = 'DA\d{7}[\dX]';
    $this->sites['NII']->property = 'P271'; 

    $this->sites['SUDOC']->regex = '(\d{8}[\dX]|)';
    $this->sites['SUDOC']->property = 'P269';
  }

  }

?>