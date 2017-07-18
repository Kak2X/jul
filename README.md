# jul
this is an experimental fork of jul.

## a word of warning
due to various internal changes, the code may be buggy (or not). you have been warned

## installing
you first need to rename the sample configuration file in the **/lib** directory (**config.sample.php**) to **config.php**. 
then you can then edit said file and import **install.sql** to your database.

a 1.92.08/boardc-like installer may be provided at some point.


the first user registered will be a root administrator. 
unless the 'deleted-user-id' setting is changed, a 'Deleted User' account will be automatically created as well to contain posts from deleted users.

after logging in, you may want to change board options from the Admin Control Panel (ie: Active filters, Forum list, Permissions...)

## license
exactly the same as the original branch
