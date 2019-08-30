# Installation

* run: fetch-auth-formats.sh 
  * this runs a pair of Wikidata queries to harvest relevant P-item numbers and properties for Wikidata authority IDs
  * those data are stored as wd1.json and wd2.json
  * (developers should re-run this shell script occassionally to update the list of possible authority formats)

# Usage 
 
* submit a Wikidata Q-item number and its associated VIAF ID on the index.php page. 
  * index.php calls `auth-format-to-json.php` to convert wd1.json and wd2.json into an array of objects that contains the regex and property number fore each ID type, keyed to WD's english name for the authority type. 
  * it queries VIAF for known related IDs and formats those results and validates the format against the regex defined in Wikidata
  * it eliminates references to IDs already contained in Wikidata
  * it formats the relevant IDs and values for ingestion into Wikidata's QuickStatements: https://tools.wmflabs.org/quickstatements/#/batch