<a href="https://github.com/catalyst/moodle-mod_cms/actions">
<img src="https://github.com/catalyst/moodle-mod_cms/workflows/ci/badge.svg">
</a>

# Custom activity content types for Moodle

An activity module for managing custom defined content types which are 'first class' concepts in Moodle. This is to enable course author to think in proper concepts that matter to them and not worry about the rendering of each content type which will be defined centrally.

## Branches ##
The following maps the plugin version to use depending on your Moodle version.

| Moodle version    | Branch            |
|-------------------| ------------------|
| Moodle 3.9 to 4.0 | MOODLE_39_STABLE  |
| Moodle 4.1 to 4.5 | MOODLE_401_STABLE |

## Installation

Step 1: Install the activity module
-----------------------------------

Using git submodule:
```
git submodule add -b MOODLE_401_STABLE git@github.com:catalyst/moodle-mod_cms.git mod/cms
```

Or you can download as a zip from github

https://github.com/catalyst/moodle-mod_cms/archive/refs/heads/main.zip

Extract this into /path/to/moodle/mod/cms

Then run the Moodle upgrade as normal.

https://docs.moodle.org/en/Installing_plugins

Step 2: Create a new content type
-----------------------------------

Visit Admin > Plugins > CMS > Manage content types and create a new type. Each type has data
sources which can add which provide various data to your template, for example the
'Custom fields' data type will let you define custom fields which are editable in module
settings page and those fields will be availanle for use in your template. The 'Roles list'
source will provide a dynamic list of anyone who has a specified role in the course, eg
you could display a list of all the course teachers and tutors, and what role they have.

Once you have defined the data source then you configure a mustache template to display that data
on the course page. To see the raw data the `{{{debug}}}` mustache variable shows you the complete
copy of the templates input context. You can see a basic preview however it is likely easier
to go and create a new instance of your content type in a course. Turn on editing mode, add a
new activty, and you should see your new content type in the chooser.

## Data sources

Extra detail can be added to CMSs and CMS types via data sources. Within your plugin, add classes that derive from
<code>mod_cms\local\datasource\base</code>, and define a function in lib.php called
<code>&lt;component>_modcms_datasources()</code>, which returns an array of fully qualified class names for these
classes.

# Contributing and Support

Issues and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-mod_cms/issues

If you would like commercial support or would like to sponsor additional improvements to this plugin please contact us:

https://www.catalyst-au.net/contact-us

# Crafted by Catalyst IT
This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/master/pix/catalyst-logo.svg" width="400">
