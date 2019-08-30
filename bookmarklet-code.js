/*
Compile using Ben Alman's Bookmarklet Generator:
http://benalman.com/projects/run-jquery-code-bookmarklet/
 */

var viaf = $('a[href^="https://viaf.org/viaf/"]').first().text();
var q = window.location.pathname.substring(6);
var url = 'http://www.kenirwin.net/wikidata/viaf-link-importer/?q='+q+'&viaf='+viaf;
console.log(url);

var win = window.open(url, 'viaf-lookup');
if (win) {
    win.focus();
} else {
    alert('Please allow popups for this website');
}