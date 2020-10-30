# MacPrefer

MacPrefer is a binary tool written to ease the management preferences on macOS, and is primarily intended for people administering multiple macOS systems.  It can be easily used from a tool such as Jamf Pro or Puppet to make a few specific tasks involving user preferences a little less difficult.

This tool has two dependencies: [Duti](https://github.com/moretension/duti) and [DockUtil](https://github.com/kcrawford/dockutil).

## Dock Functionality

The dock commands can dump a user's current dock to a YAML or JSON file as well as set a user's dock from a YAML or JSON file.   To give credit where credit is due, most of the *heavy lifting* is done by [DockUtil](https://github.com/kcrawford/dockutil).  The only functionality added here is the ability to use a YAML or JSON configuration file for import and export.

When specifying dock items, convenience entries can be used to indicate system applications or Adobe Creative Cloud applications.

#### dock:dump 
This command will dump a dock to a config file, and is typically used to back up the current user's dock for later restoration.

|Flags  | Purpose |
|--|--|
| in | The path to the _plist_ file to dump. Not typically needed, as it defaults to the current user's dock. |
| out | The path to the YAML or JSON file to output to. Not typically needed, as it defaults to `/Users/XXX/Library/Prefer/dock.yml` |
| user | The user to process the dock for.  This defaults to the current user, which makes it important to include this flag when running this tool as root.|

#### dock:import
This command will import a properly formatted YAML or JSON file as the given user's dock, removing all existing dock entries.

|Flags  | Purpose |
|--|--|
| in | The path to the config file to import. |
| out | The path to save the config file to, after import. Defaults to `/Users/XXX/Library/Prefer/dock.yml` 
| user | The user to import a dock for.  This defaults to the current user, which makes it important to include this flag when running this tool as root.|

The format of the configuration is an array, with each primary key being separated by a spacer in the dock.  Elements within the primary keys are entries in the dock.  Each element is either an application's name, the an absolute path to an application, or an array describing a link to another resource.  An example in YAML is below:

    group1:
      - Messages
      - Postbox
     group2:
       - Safari
       - Firefox
       - 'Google Chrome'
       - 'Microsoft Edge'
     group5:
       - Hyper
       - { link: /Applications/Custom.terminal }
       - screen_sharing
     group6:
       - { link: /Applications/, section: others, display: folder, view: grid, sort: name }
 
When processing application names, the following locations are checked for the application:

 - /Applications
 - /Users/XXX/Applications

When processing links, the following keys and values can be used:

|Key  | Possible Values |
|--|--|
| link | A typical link with scheme (IE - file://) |
| section | _apps_, _others_ |
| display | _stack_, _folder_ |
| view | _grid_, _fan_, _list_, _auto_ |
| sort | _name_, _dateadded_, _datemodified_, _datecreated_, _kind_ |

## Adobe Applications Functionality

The Adobe commands can provide information about installed Adobe Creative Cloud applications, as well as backup and transfer preferences between different _years_ of the same Creative Cloud application.

For all of these commands, the application name should be provided in lowercase, using a dash as a separator, for example: _after-effects_.

#### adobe:info _app_ _year_
Provides information about an installed Adobe Creative Cloud application, output in JSON format.  This information includes:

 - Actual version installed
 - Path to application
 - SAP code
 - Base version
 - Uninstaller command string
 - Paths to known preferences

The year is optional for the applications that do not use it, such as XD, Dimension, and Lightroom.

#### adobe:backup _app_ _year_
Backs up the preferences of an Adobe Creative Cloud application.  While the application does not need to be installed, it can be helpful.  Preferences are backed up to the path below, and stored in time/date stamped zip files.

`/Users/<user>/Library/Preferences/Prefer/CC/<app>/<year>`

The year is optional for the applications that do not use it, such as XD, Dimension, and Lightroom.

#### adobe:transfer _app_
Copies preferences from one _year_ of a Creative Cloud application to another _year_.  This is intended for situations in which more than one _year_ of an application are installed side-by-side, such as both Photoshop 2020 and 2021.

_It should be noted that this is purely a  "copy" process; no changes are made to the files,  and no attempt is made to migrate changed or deprecated preferences.  This has worked well in testing, but may not work with all future preferences or applications.  As such, a backup of the existing preferences is always made during the copy process_.

|Flags  | Purpose |
|--|--|
| from | The source year, IE - 2019 |
| to | The destination year, IE - 2020 |
| user | This defaults to the current user, which makes it important to include this flag when running this tool as root. |

## Default Applications Functionality

A command is included that can set the default application for a _very limited_ number of file extensions, in cases where more than one application might be used to open a type of file such as a PDF or text file.  

The majority of the **heavy lifting** is done by [Duti](https://github.com/moretension/duti).  The main functions that are added here is the ability to work with an extension, rather than a Uniform Type Identifier as well as importing defaults from a JSON or YAML configuration file.

#### defaults:app
This command can set the default application to use for one specific extension, or can load defaults from a JSON or YAML file.

|Flags  | Purpose |
|--|--|
| in | A YAML or JSON file from which to set default apps for various extensions. | 
| out | The file in which to save default applications for various extensions.  Used when setting a default for a single extension.  Defaults to `/Users/XXX/Library/Preferences/Prefer/apps.yml` |
| extension | An extension for which to set the default application. |
| app | Path to the application to use when opening the given extension.  Convenience shortcuts can be used for system apps and the latest Adobe applications. |
| user | This defaults to the current user, which makes it important to include this flag when running this tool as root. |

##### Usable Extensions
The list of working extensions can be found in _DefaultApplicationCommand.php_.   Additional extensions may be added in the future, but as you can see it's a bit of a task to write a Uniform Type Identifier to extension map.

## Convenience App Name Shortcuts

In the **defaults:app** and **dock:import** commands, the following convenience shortcuts can be used.

#### System Applications
Any application included with macOS located in `/System/Library/CoreServices/Applications/` can be described in the configuration file with a lowercase, underscore separated string such as _dvd_player_ or _screen_sharing_.

#### Adobe Applications
Any Adobe Creative Cloud application can be described in the configuration file with a lowercase, underscore separated string such as _after_effects_ or _photoshop_.  The most recent version of the application will be used.

Additionally, the shortcut _acrobat_ can be specified, in which case the following apps would be utilized, in order of preference: 

 - Adobe Acrobat DC
 - Acrobat Pro (older)
 - Adobe Acrobat Reader DC
 - Adobe Acrobat Reader (older)
