@plomlompom tries to build his own wiki, optimized for his own needs.
As he is rather inexperienced, he starts with some very simple PHP.

Right now, the PlomWiki is extremely simple:
* Pages can be seen and edited; new pages can be created by appending an unused
  page name to "plomwiki.php?title=" in the URL field of your browser and
  editing the empty page that results.
* Apart from very sparse wiki markup, only raw text -- no HTML -- can be written
  by the user.
* Line breaks and paragraphs get detected and converted to HTML.
* Linking to other Wiki-internal pages is possible by putting "[[" "]]" around 
  page names. Works case-sensitively. Links to unused page names are possible,
  will open empty pages that can be edited to create new pages.
* Only ASCII-alphanumeric page names are possible.
* An empty page text doesn't get posted. To delete a page, reduce the page text
  to "delete". 
