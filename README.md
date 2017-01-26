# [Grav](http://getgrav.org) Enhanced Backup Manager

**If you encounter any issues, please don't hesitate
to [report
them](https://github.com/leotiger/grav-plugin-backup-manager/issues).**

> Enhanced backup for your Grav instance

## Why is user driven backup important?

Users of emerging projects like Grav need freedom. Backup and archive facilities that work
hazzle-free, offer confidence. Pages, content, media: users want to be free, easy...
But it's not only about opportunities and freedom, this backup manager offers a lot to
admins as well. And there's more to come if this plugin receives some positive feedback!

And yeah: invited to contribute!

## Introduction

Grav provides backup out of the box but offers no control over the process.
This plugin offers a Grav backup compatible solution that offers you some 
nice extra features:

* backup scopes
* test mode
* additional folder and file type ignores
* file type restrictions (at the moment only via cli)
* storage administration
* latest backups 
* purging
* clean up of failed backups
* enhanced cli
* access to a reduced set of options for non super users
* etc., etc.

## Configuration

You can customize the backup process for your instance in the settings of the 
plugin. Backup Manager facilitates a test mode that allows you to adapt "THE MOTHER OF
ALL BACKUPS FOR GRAV" (a joke) to your environment, low resources, you control, you
restrict. With scopes you dispose of predefined backup sets. If you need support, 
just stuff all into a partial "config backup" by using the "Config" scope. Backup
Manager packs now all your configurations, the content of the root folder and a file
with the phpinfo into a backup archive.

Existing backup scopes:

* admin
* defaults
* config
* pages
* user
* media
* images
* audio
* video
* log
* plugins
* themes
* imagecache
* cache
* data
* system

Defined purging scopes:

* purge		
* purgeall
* purgepartial
* purgetests
* purgepages
* purgeimages
* purgemedia
* purgethemes
* purgedata
* purgeplugins
* purgeconfig
* purgesystem
* purgefailed

The default purge context runs during every backup process and works on the basis of your settings
for the Backup Manager for capacity and the days you want to keep backups.

## Installation

Download the [ZIP
archive](https://github.com/leotiger/grav-plugin-backup-manager/archive/master.zip)
from GitHub and extract it to the `user/plugins` directory in your Grav
installation. And if suited it may appear on the GRAV plugin site with some installation
support out of the box in the future...

## CLI

This plugin inludes support for cli and thus for automization. CLI allows for additional 
options. Investigate. Not all is fail-safe, but a lot of the stuff works well.

One of the nice CLI features: you can specify "free" folders and a lot of them, all to
be included in a backup, doesn't matter where they are in the Grav instance... 
(Hope all this works good enough to maintain your interest, I didn't had the time to test
all of the features thoroughly: one man out on a mother sea today...

## Credits

You will find some known code. This is due to the fact that the first goal was to offer
a core enhancement for Grav itself. But this approach needs a lot of coordination with 
the Grav people. Difficult. Finally the decision to make a plugin out of this may have 
more pros than cons. Nevertheless, I obliged myself to work hard on core compatible code
that allows for an easy integration of backup functionality.

## Known Issues

This is still a baby, a zero version. For this reason issues are expected. But you can be
sure that Backup Manager is already doing a good job! Before spending more time on this, 
I would appreciate feedback. If a something like this is needed... The communication of
issues helps as a strong indicator for that... If you have an issue, let us know. Same
thing goes for proposals and ideas.