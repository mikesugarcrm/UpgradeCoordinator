# The Upgrade Coordinator
A system for co-ordinating multiple upgrades of a sugar instance

The upgrade coordinator allows you to perform multiple upgrades of a sugar instance in one command.

The upgrade coordinator can delete files, deploy patches, execute custom upgrade scripts, and will take care of clearing the cache directory safely and running QRR when appropriate. You can run it with one command, walk away, and when it's done, your sugar instance is upgraded.

**note:** all version numbers shown here are only examples.

## Setting things up
You can install and run the coordinator from any directory on the machine where you're running your sugar instance.

The upgrades to be run are determined by the contents of the upgrades/ directory.
To add an additional upgrade, create a directory in `upgrades/` named for the target version number of that upgrade, i.e.:
`upgrades/9.2.0`

Repeat this process for every upgrade you plan to run.

Then you'll need to create directories for:

### The Upgrade Package
The upgrade package will be named something like `SugarEnt-Upgrade-9.0.2-to-9.2.0.zip`. 

Create a directory for the upgrade package:
`upgrades/<version>/upgrade`

Copy the SugarEnt-Upgrade-9.0.2-to-9.2.0.zip file (or whatever upgrade package file you're working with) into this directory and unzip it there.

Make sure you check these unzipped files into your git repo. Don't worry, the coordinator will re-zip them when the time comes.

For now, leave them un-zipped and checked into git. If you discover that you need to change a file in the upgrade package, git will track that change for you. Trust me, this will save you many headaches if you're unfortunate enough to need to change a file in the upgrade package!


### The Silent Upgrader
The silent upgrader package will be named something like `silentUpgrade-PRO-9.0.2.zip`. 

Create a directory for the silent upgrader:
`upgrades/<version>/silent_upgrader`

Copy the silent upgrader zip file into that directory and unzip it there. Like the upgrade package, make sure you check the unzipped contents into git in case you need to track a change to them.

The coordinator will re-zip these files when the time comes.


### Patch Files:
Patch files are just sugar application files. Patch files go in `upgrades/<version>/files/[pre|post]`, and then they should mirror the sugar application directory structure, i.e.

```bash
upgrades/9.2.0/files/post/custom/Extension/modules/Cases/Ext/LogicHooks/logic_hooks.php
```

Patch files in a `pre` directory will be deployed before the upgrade package is run.

Patch files in a `post` directory will be deployed after the upgrade package is finished.

Generally, version-specific patches should be used to get through healthcheck. For bugs that are present after all upgrades are complete, put your patch files in `upgrades/final/files/post`.

### Custom Upgrade Scripts
Custom upgrade scripts are similar to the upgrade scripts the coordinator runs. They are kept in `upgrades/<version>/scripts/[pre|post]`.

Custom upgrade scripts will be copied into your sugar instance directory and will include sugar global variables in their scope, so you should be able to do pretty much anything with them.

The coordinator will run the `pre` scripts after deploying the `pre` patch files, and the  `post` scripts after deploying the `post` patch files.

The upgrade scripts must extend the class `\Sugarcrm\Sugarcrm\UpgradeCoordinator\lib\UpgradeScript`. See the file `lib/UpgradeScript.php` and `lib/UpgradeScriptManager.php` for more details.


## The Final Upgrade Directory
In `upgrades`, you will find `upgrades/final`. This is the directory where you will put most of your fixes.

Any patches that should be applied after all upgrades are complete go in `upgrades/final/files/post`

Any upgrade scripts that should be run after all upgrades are complete go in `upgrades/final/scripts/post`

The purpose of the `final` directory is to allow you to add another upgrade step without having to move patch files you want to run after all your upgrades are complete.

## Running the Upgrade Coordinator
It's this simple:
```bash
php UpgradeCoordinator.php </path/to/sugar/instance/> [/path/to/php/binary]
```
The <path/to/sugar/instance/ should be an absolute path.

The path to the php binary is *optional* - don't include it if you don't need to.

## What Happens Next?

### Passing Healthcheck
The first time you run the coordinator on a highly customized instance, you'll probably fail healthcheck. To understand why, look in `logs`. You will find the coordinator's log file as well as the log files that the Silent Upgrader produces and from there you'll have to work out what healthcheck is complaining about.
When you understand what you need to change to pass healthcheck, you will probably need to add patch files in `upgrades/<version>/files/pre/sugar/application/file/path.ext`
If you need to revert such changes afterwards, you can do that in `upgrades/<version>/files/post`.

You'll probably have to repeat that process for each upgrade you want to run. 

### Post upgrade bug fixing.
After the upgrades are complete, you'll need to test the whole application, especially your customizations, to make sure they all still work.

It's likely some of your customizations will have some issues, and you'll need to update files to address those issues.

Those fixes go in `upgrades/final/files/post`, which works just like the other `files` directories.

