GitHub Multihook Enabler
========================

Motivation
----------
I wrote this quick script NOT because I'm lazy, but because adding/enabling a service hook for 30+ repos (or even just a few) is really quite tedious.  
My original motivation sprung from enabling Pushover notifications on all of my repos. As such, I wrote this just for Pushover. However, if I'm sufficiently motivated, I'll rewrite this in Python and add support for other (maybe all?) service hooks.


Pushover
--------
Syntax: `php pushover.php github_username github_password pushover_apikey [pushover_device]`  
It's pretty self-explanatory... Even has useful output. And help!


Email
-----
Syntax: `php email.php github_username github_password email_address [email_secret]`  
It's pretty self-explanatory... Even has useful output. And help!


TODO
----
 * Rewrite in Python
 * Make this more flexible, to support any and all GitHub service hooks (maybe with a GUI, with Q&A)
 * Add the ability to overwrite (GitHub PATCH method) existing service hook configuration.
 * Send an optional test notification after adding the notification.


Thanks and References
---------------------
Thanks to [Pushover.net](http://pushover.net)  
This makes great use of [GitHub's v3 API](http://developer.github.com/v3/)


