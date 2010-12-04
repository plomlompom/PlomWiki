PlomWiki: @plomlompom tries to build his own wiki, optimized for his own needs.

CAVEAT

As @plomlompom is rather inexperienced, he starts with some very simple and
probably not very good PHP.

INSTALLATION

Copy PlomWiki/ and everything below it onto your server. You can now access your
wiki via http://[YourDomain/YourPath]/plomwiki.php?title=Start. The default page
editing password is "Password", change it in PlomWiki/password.txt.

USE

In your browser, click on "View" to view a page, on "Edit" to edit a page and on
"History" to examine a diff history of the pages edits. Here you can also revert
changes to the page text by clicking on the "Revert" link over a diff.

Per default, only little markup is possible on pages, though more could be added
as plugins (see Technical Details section). The standard markup includes:
* HTML code gets ignored: &, <, >, ' and " get escaped into their HTML entities.
* Single newlines become linebreaks, double newlines become paragraphs.
* Enclose wiki-internal links to other pagenames [[InDoubleSquareBrackets]].
* [*This*] becomes <strong>This</strong> and [/this/] becomes <em>this</em>.

To create a new page, either in the address bar replace the "Start" in the URL
part "plomwiki.php?title=Start" with the name for the new page, or create a link
to it with double square brackets ("[[NewPage]]") and click on that. This will
open the new page of said pagename ready to be edited and filled with content.

Two notes concerning pagenames: 
* Only ASCII-alphanumeric pagenames are allowed.
* Internal linking is case-sensitive: [[ThisPage]] is not equal to [[thispage]].

To delete a page, reduce page text to "delete". Empty page text won't be posted.

TECHNICAL DETAILS

Wiki pages are stored as text files of their current version in PlomWiki/pages/
with the pagename as the filename. In PlomWiki/pages/diffs diffs to previous
versions are stored. Page deletion does not actually remove the files but just
renames, timestamps and moves them into PlomWiki/pages/deleted.

The "action=" GET parameter in a page URL determines the action on the page. 
Values like "view" and "edit" correspond to functions like Action_view() and 
Action_edit() in plomwiki.php by prepending the value with "Action_" and, if a 
function "Action_[value]" is found, calling it, or else defaulting to "view".
This should give you an idea on how to add new page actions by just adding new
functions like Action_xyz().

To add code to plomwiki.php for purposes such as this, just put a file of your
new PHP code into the PlomWiki/plugins/ directory and refer to its relative
location on a line in the file PlomWiki/plugins.txt. All those files referenced
will be required by plomwiki.php every time it is run.

Standard markup is inserted as the plugin PlomWiki/plugins/standard_markup.php.
Any text manipulation function can be added as a markup plugin by activating its
code in plugins.txt and by calling said function in PlomWiki/markups.txt which 
contains the list of markups called by Markup() on a text in the order in which
they are to be applied.

To avoid unfinished DB manipulations / DB corruptions, any task writing to
PlomWiki/pages/ and the directories below it is not done directly but first
written into a todo file in PlomWiki/work/. Tasks in this file can be worked
through and finished independently from the user process that triggered them
(and which might be interrupted, for example, by a "server execution time
exceeded") with WorkToDo(). Tasks written into a todo file work/todo_urgent will
be worked through and have to be finished before any other page action (like 
viewing a page) can be performed. 

Any other todo file only gets worked through if the function WorkToDo($path) is
called on its path. Currently, a plugin PlomWiki/plugins/Action_work.php is
included but commented out in plugins.txt that implements the action=work which
works through a non-urgent todo file PlomWiki/work/todo.

You may find files called ".gitignore" files in several directories of PlomWiki.
Those can be safely deleted. They are included in the git repository version of
PlomWiki merely to ensure said directories are commited even if they're empty.
