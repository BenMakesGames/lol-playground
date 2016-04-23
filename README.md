Allows you to dynamically filter League of Legends champions on various stats, using Angular 1 and jQuery.

This program has two parts:

1. A crudely-written PHP script - scrapechampions.php - which scrapes champion data from the League of Legends wiki, and outputs the results as a JSON object.
2. The application itself, consisting of index.html and js/main.js.

To update the application's champion data, copy the JSON object output by scrapechampions.php into js/main.js (line 3).
