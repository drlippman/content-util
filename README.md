content-util
============

Various content processing utilities

OSepubcleanup.php
-----------------
Processes an EPUB, doing some light cleanup needed for importing into a
reader platform, and resizes super-large images to no more than 2 times the
specified width.

OSepubsplit.php
---------------
Splits OpenStax EPUB chapters into sections

OSepubQTI.php
-------------
Extracts multiple choice questions out of an OpenStax EPUB as QTI files

qti.php
-------
Takes an upload of a QTI quiz package, and outputs the questions as HTML.
Only works for multiple choice questions.

lbtoimathas.php
---------------
Takes a download from Lardbucket's book archive and imports it into IMathAS

OSziptoMOM.php
---------------
Reads an OpenStax/Connexions zip download and imports it into IMathAS.
This works better than the epub-based approach.

CK12toMOM.php
---------------
Reads a CK12 book into IMathAS.  Requires digging in to find the initial
revision ID for the book.

