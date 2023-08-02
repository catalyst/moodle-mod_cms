A short explanation for these classes.

The restore system requires the infrastructure to be set up at the beginning, before anything is known about the data.
In order to do this, I needed to make a special case of creating all the datasource objects with a generic blank CMS object.

Data source objects are designed to work with a CMS object, or at least a CMS type. Using one with a blank CMS is problematic and
should not be done under ordinary circumstances.

To avoid confusion and potential land mines, restore functions are made static in the datasource classes, and separate classes have
been defined to handle the restore process, stored in this directory.
