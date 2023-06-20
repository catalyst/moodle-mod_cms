<a href="https://github.com/catalyst/moodle-mod_cms/actions">
<img src="https://github.com/catalyst/moodle-mod_cms/workflows/ci/badge.svg">
</a>

# Custom activity content types for Moodle

An activity module for managing custom defined content types which are 'first class' concepts in Moodle. This is to enable course author to think in proper concepts that matter to them and not worry about the rendering of each content type which will be defined centrally.

## Installation

Step 1: Install the activity module
-----------------------------------

Using git submodule:
```
git submodule add git@github.com:catalyst/moodle-mod_cms.git mod/cms
```

OR you can download as a zip from github

https://github.com/catalyst/moodle-mod_cms/archive/refs/heads/main.zip

Extract this into /path/to/moodle/mod/cms

Then run the Moodle upgrade as normal.

https://docs.moodle.org/en/Installing_plugins

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
