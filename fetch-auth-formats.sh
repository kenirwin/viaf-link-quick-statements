#!/bin/sh

#https://query.wikidata.org/sparql?query=SELECT%20%3Fproperty%20%3FpropertyLabel%20%3Fformat_as_a_regular_expression%20WHERE%20%7B%0A%20%20%3Fproperty%20wikibase%3ApropertyType%20%3FpropertyType%3B%0A%20%20%20%20wdt%3AP31%20wd%3AQ55586529.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22%5BAUTO_LANGUAGE%5D%2Cen%22.%20%7D%0A%20%20OPTIONAL%20%7B%20%3Fproperty%20wdt%3AP1793%20%3Fformat_as_a_regular_expression.%20%7D%0A%7D%0AORDER%20BY%20(xsd%3Ainteger(STRAFTER(STR(%3Fproperty)%2C%20%22P%22)))&format=json

curl 'https://query.wikidata.org/sparql?query=SELECT%20%3Fproperty%20%3FpropertyLabel%20%3Fformat_as_a_regular_expression%20WHERE%20%7B%0A%20%20%3Fproperty%20wikibase%3ApropertyType%20%3FpropertyType%3B%0A%20%20%20%20wdt%3AP31%20wd%3AQ55586529.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22%5BAUTO_LANGUAGE%5D%2Cen%22.%20%7D%0A%20%20OPTIONAL%20%7B%20%3Fproperty%20wdt%3AP1793%20%3Fformat_as_a_regular_expression.%20%7D%0A%7D%0AORDER%20BY%20(xsd%3Ainteger(STRAFTER(STR(%3Fproperty)%2C%20%22P%22)))&format=json'> wd1.json

curl 'https://query.wikidata.org/sparql?query=SELECT%20%3Fproperty%20%3FpropertyLabel%20%3Fformat_as_a_regular_expression%20WHERE%20%7B%0A%20%20%3Fproperty%20wikibase%3ApropertyType%20%3FpropertyType%3B%0A%20%20%20%20wdt%3AP31%20wd%3AQ19595382.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22%5BAUTO_LANGUAGE%5D%2Cen%22.%20%7D%0A%20%20OPTIONAL%20%7B%20%3Fproperty%20wdt%3AP1793%20%3Fformat_as_a_regular_expression.%20%7D%0A%7D%0AORDER%20BY%20(xsd%3Ainteger(STRAFTER(STR(%3Fproperty)%2C%20%22P%22)))&format=json'> wd2.json


