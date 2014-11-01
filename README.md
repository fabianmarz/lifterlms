lifterLMS
==========

LIFTER LMS

###Shortcodes
* [lifterlms_my_account]
  *adds entire account page
  *Accepts no arguments
* [courses]
**Accepts arguments: order, orderby and per_page


###Debug: lms_log($message)
*Logs message to wp-contents/debug.log

####Examples
*log_me(array('This is a message' => 'for debugging purposes'));
*log_me('This is a message for debugging purposes');





CHANGELOG
=========

v1.0.2 - (UNRELEASED)
---------------------

+ Added lesson short description to previous lesson preview links -- it was rendering on "Next" but not "Previous"
+ Added a class to course shop links wrapper to signify the course has been completed
+ Removed an uncessary CSS rule related to the progress bar


v1.0.2 - 2014-10-30
-------------------

+ Fixed SSL certificate issues when retreiving data from https://lifterlms.com
+ Added rocket settings icon back into repo


v1.0.1 - 2014-10-30
-------------------

+ Updated activation endpoint url to point towards live server rather than dev