# Magento® SUPEE-6788 Developer Toolbox

Magento's SUPEE-6788 patch is a mess for developers. There are a number of breaking changes, affecting 800+ of the most popular extensions and many customizations.

This script attempts to find and automatically resolve major problems from the patch. It does this in two stages: `analyze`, and `fix`.

The `analyze` step goes through all extensions looking for anything using custom admin routers (the major outdated change), and produces a list of every module affected, the bad XML and PHP code, and exactly what should be changed to resolve it. It also looks at every CMS page, static block, and email template for any blocks or configuration that are not known to be on the new whitelist. All of this is purely informational, to inform you of the state of the Magento installation and what will be involved in fixing it.

The `fix` step automatically applies as many of the identified changes as it can. Not every possible module and situation can be resolved automatically, but this should save a vast amount of time for the ones that can.

This is not the end-all/be-all solution to fixing conflicts from the patch. It is intended to minimize the time and risk involved in diagnosing and fix SUPEE-6788 patch conflicts for someone already well-versed in Magento development. The information produced will not be accessible to anyone unfamiliar with Magento routing.

If you need help, let us know. Contact details at the bottom.

**WARNING:** This script is destructive. If you apply the changes, it **WILL** overwrite existing files with the changes noted. Back up your site before applying any changes, and trial it first on a development copy if at all possible.

## Usage
* Backup your website.
* Upload fixSUPEE6788.php to {magento}/shell/fixSUPEE6788.php
* **To analyze:** Run from SSH: `php -f fixSUPEE6788.php -- analyze`
* **To apply changes:** Run from SSH: `php -f fixSUPEE6788.php -- fix`
* Additional option: `recordAffected` - If given, two files will be written after running: `var/log/fixSUPEE6788-modules.log` containing all modules affected by the patch, and `var/log/fixSUPEE6788-files.log` containing all files the script would/did modify. Use this to grab an archive of modified files (`tar czf modified.tar.gz -T var/log/fixSUPEE6788-files.log`), or weed out any files/modules for the fix whitelist.
* Additional option: `loadWhitelists` - If given, `shell/fixSUPEE6788-whitelist-modules.log` and `shell/fixSUPEE6788-whitelist-files.log` will be loaded, and any files/modules mentioned will be excluded. Format should be identical to the files produced by `recordAffected`.
* Command with options: `php -f fixSUPEE6788.php -- analyze recordAffected loadWhitelists`

All results are output to screen and to var/log/fixSUPEE6788.log.

## Caveats
* Script assumes admin controllers are all located within {module}/controllers/Adminhtml. This is convention, but not always true.
* Script will not handle multiple admin routes in a single module.
* The script may not catch all possible route formats. The automated changes may result in broken admin pages that must be corrected manually.

## Potential improvements
* Ability to flag extensions known to be affected by the SQL vulnerability or other changes, or somehow otherwise detect it.
* Load whitelist entries for analysis from the Magento config/block whitelist. *(Need details on how they're stored to do this.)*
* Add any missing cache/block whitelist entries to the whitelist during `fix`. *(Need details on how they're stored to do this.)*
* Documentation on how to resolve the various errors and edge cases that might occur.

## Who we are
This script is provided as a courtesy from ParadoxLabs. We created it to help with applying our own patches, and we're sharing it so you can benefit too. We are a Magento Silver Solution Partner, based out of Lancaster, Pennsylvania USA.

Contributions are welcome. If you have fixes or additional functionality you would like to add, we're happy to accept pull requests. But please realize support will be limited. This script is provided as-is, without warranty or liability. We make no guarantee as to its correctness or completeness, and by using it we assume you know what you're getting into.

The TemplateVars portion of this script was adapted from magerun-addons, courtesy of @peterjaap and @timvroom. Many thanks to their groundwork. https://github.com/peterjaap/magerun-addons

### [ParadoxLabs, Inc.](http://www.paradoxlabs.com)
* **Web:** http://www.paradoxlabs.com
* **Phone:**   [717-431-3330](tel:7174313330)
* **Email:**   sales@paradoxlabs.com
* **Support:** http://support.paradoxlabs.com
