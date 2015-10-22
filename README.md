# Magento SUPEE-6788 Developer Toolbox

Magento's SUPEE-6788 patch is a complicated beast for developers. There are a number of not-badkwards-compatible breaking changes introduced that affect 800+ of the most popular extensions, plus a wide variety of customizations.

This script attempts to find and automatically resolve major conflicts resulting from the SUPEE-6788 patch. It goes through all modules looking for old-style URLs, and admin email templates, CMS pages, and static blocks looking for non-whitelisted config and block references.

**WARNING:** This script is destructive. When you apply the changes, it **WILL** overwrite your existing files. Back your site up first, and run it on a development copy if at all possible.

This is not the end-all/be-all solution to fixing patch conflicts. It is intended to minimize the time necessary to diagnose and fix patch conflicts for someone already well-versed in Magento development.

If you need help, give us a line.

## Usage
* Backup your website.
* Upload fixSUPEE6788.php to {magento}/shell/fixSUPEE6788.php
* **To analyze:** Run from SSH: php -f fixSUPEE6788.php -- analyze
* **To apply changes:** Run from SSH: php -f fixSUPEE6788.php -- fix

Additional options recordAffected and loadWhitelists are detailed in the script help.

All results are output to screen and to var/log/fixSUPEE6788.log.

## Caveats
* Assumes admin controllers are all located within {module}/controllers/Adminhtml. Convention, but not always true.
* Will not handle multiple admin routes in a single module.
* May not catch all possible route formats.

## Potential improvements
* Whitelist for files or modules the extension should not touch.
* Ability to flag extensions known to be affected by the SQL vulnerability or other changes, or otherwise detect it.
* Load whitelist entries for analysis from the actual whitelist. (Need details on how they're stored to do this.)
* Add any missing cache/block whitelist entries to the whitelist. (Need details on how they're stored to do this.)

## Who we are
This script is provided as a courtesy from ParadoxLabs. We created it to save time and risk when applying our own patches, and we're sharing it so you can benefit too. We are a Magento Silver Solution Partner, based out of Lancaster, Pennsylvania USA.

If you have fixes or additional functionality you would like to add, we are happy to accept pull requests. But please be aware that support will be limited. Per the license, this script is provided as-is, without warranty or liability. We make no guarantee as to its correctness or completeness, and we assume you know what you're getting into by using it.

The TemplateVars portion of this script was adapted from magerun-addons, courtesy of @peterjaap and @timvroom. https://github.com/peterjaap/magerun-addons

### [ParadoxLabs, Inc.](http://www.paradoxlabs.com)
    http://www.paradoxlabs.com
    Phone:   717-431-3330
    Email:   sales@paradoxlabs.com
    Support: http://support.paradoxlabs.com
