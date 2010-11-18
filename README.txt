PlomWiki: @plomlompom tries to build his own wiki, optimized for his own needs.
As he is rather inexperienced, he starts with some very simple and probably not
very good PHP.

Right now, the PlomWiki doesn't do much:
* Pages can be seen and edited; new pages can be created by appending an unused
  page name to "plomwiki.php?title=" in the URL field of your browser and
  editing the empty page that results.
* Only ASCII-alphanumeric page names are possible.
* Apart from very sparse wiki markup, only raw text -- no HTML -- can be written
  by the user:
  * Line breaks and paragraphs get detected and converted to HTML.
  * Linking to other Wiki-internal pages is possible by putting "[[" "]]" around 
    page names. Works case-sensitively. Links to unused page names are possible,
    will open empty pages that can be edited to create new pages.
* An empty page text doesn't get posted. To delete a page, reduce the page text
  to "delete". 
* Editing a page is password-protected. The password can be changed in the file
  "password.txt" in the root directory.
* To avoid unfinished DB manipulations / DB corruptions, all such tasks are
  handed over to todo lists in work/. Those can be worked through and finished 
  independently from the user process that triggered them (and which might be 
  interrupted, for example, by a "server execution time exceeded"). Urgent tasks
  are handed over to work/todo_urgent which has to be finished before any other 
  action on the wiki (like viewing a page) can be performed. Tasks could also be
  handed over to work/todo which only gets worked on if ?action=work is called.
* The files work/.gitignore and work/temp/.gitignore can safely be deleted. They
  merely ensure that said directories are committed to git even if empty.
