# jul
This is an experimental fork of jul.

## installing
You first need to rename the sample configuration file in the **/lib** directory (**config.sample.php**) to **config.php**. 
Then you can then edit said file and import **install.sql** to your database.

A 1.92.08/BoardC-like installer may be provided at some point.


The first user registered will be a root administrator. 
Unless the 'deleted-user-id' setting is changed, a 'Deleted User' account will be automatically created as well to contain posts from deleted users.

After logging in, you may want to change board options from the Admin Control Panel (ie: Active filters, Forum list, Permissions...)

## license
Exactly the same as the original branch.
