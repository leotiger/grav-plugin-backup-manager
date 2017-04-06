# v0.1.5
## 04/06/2017

1. [](#fixed)
    * undefined variable casesensitive changed to caseinsensitive which runs in via CLI config (Issue #8)

# v0.1.4
## 02/13/2017

1. [](#fixed)
    * Make setting for number of backups to show operative (Issue 5)
    * Fix internal function call inside storeStatus function (Issue 6)

# v0.1.3
## 01/26/2017

1. [](#fixed)
    * Remove unused setting admin.pages from blueprint (Issue 2)
	* Display for backups older than today works now

2. [](#new)
    * Add setting and support for number of backups to show
	  in the latest backups list

3. [](#improved)
    * Make last backup graph display work like proposed in issue 910
	  for grav-plugin-admin
	* Delete backup.log file when no related site backup exists
	* Update last backup graph when purging or adding related site backups
	  
# v0.1.2
## 01/25/2017

1. [](#fixed)
    * Issues with some purge scopes not working for naming problems
	* Issue with statistics showing partial backups under the site counter too
	* Showing "Download test" instead of "Download" after a live run in the 
	  Details

# v0.1.1
## 01/25/2017

1. [](#new)
    * Added phpinfo output to config and system scope
	  to offer insights into system if used for support

2. [](#fixed)
    * Strict error for Google Chrome and others (Issue 1 by @iusvar)
	* Permissions to allow the plugin to show up correctly in the sidebar menu
	  for users without admin.super rights 
	* A false error showing up when not admin.super

3. [](#improved)
    * Allow supression of messages for task authorize function
	  (Something admin plugin should offer as well)

# v0.1.0
## 01/21/2017

1. [](#new)
    * Initial release...
