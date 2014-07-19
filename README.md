MediaWiki Delete History extension
==================================

Made by Pierre Mavro <pierre@mavro.fr>
Licenced under GPL v2

Minimum requirements
--------------------
* MediaWiki 1.14+
* php5-cli

Installation instructions
-------------------------
1. Be sure you've got php5-cli installed. If you're under Debian like OS :
```
aptitude install php5-cli
```

2. Download the latest version of DeleteHistory in your extensions directory (ex. /var/www/mediawiki/extensions):
```
git clone git://git.deimos.fr/git/mediawiki_extensions.git
```

3. Edit your LocalSettings.conf and add those lines :
```
# DeleteHistory
$wgGroupPermissions['sysop']['DeleteHistory'] = true;
include('extensions/DeleteHistory/DeleteHistory.php');
```
4. That's all, now you can go in Special Pages and try the DeleteHistory extension. If you don't see anything new, it's because you don't have the admin privileges.

5. Do not hesitate to contact me if you have some questions

Changelog
---------
* v0.8
+ Adding Brazilian Portuguese language
+ Fix minor bug on reporting array

- v0.7
* Compatible with MediaWiki 1.21

- v0.6
* Adding German language

- v0.5
* Adding Engine and Collation tables informations when requesting optimization. It helps to understand the result of it.

- v0.4.1
* Fix some vulnerability

- v0.4
* Compatible with MediaWiki 1.18

- v0.3
* Show in KB or MB in the database space won when an optimize runs
* Changed shown order (Result,Optimisation Status,Logs)
* Added arrays style to results

- v0.2
* Compatible with MediaWiki 1.16

- v0.1
* First release

