<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class SPARQLQueryDispatcher
{
  private $endpointUrl;

  public function __construct($endpointUrl)
  {
    $this->endpointUrl = $endpointUrl;
  }

  public function query($sparqlQuery)
    {
      /*
        $opts = [
		 'http' => [
			    'method' => 'GET',
			    'header' => [
					 'Accept: application/sparql-results+json'
					 ],
			    ],
		 ];
        $context = stream_context_create($opts);
      */

        $url = $this->endpointUrl . '?query=' . urlencode($sparqlQuery) . '&format=json';
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36');
        $response = curl_exec($ch); 
        curl_close($ch);      
	//        $response = file_get_contents($url);//, false, $context);
        return json_decode($response, true);
    }
}
?>
