<?php
/**
 * This script attempts to find and automatically resolve major conflicts resulting
 * from the SUPEE-6788 patch. It goes through all modules looking for old-style URLs,
 * and admin email templates, CMS pages, and static blocks looking for non-whitelisted
 * config and block references.
 * 
 * WARNING: This script is destructive. When you apply the changes, it WILL overwrite
 * your existing files with those changes. Back up your site first.
 * 
 * We make no guarantee as to the correctness or completeness of this script.
 * We are not liable for any problems that occur as a result of running it.
 * 
 * This is not intended to be the end-all/be-all solution to fixing patch conflicts.
 * It is meant to minimize the time necessary to diagnose and fix patch conflicts
 * for someone already well-versed in Magento development.
 * 
 * If you need help, give us a line.
 * 
 * Usage:
 * - Upload to shell/fixSUPEE6788.php
 * - Run from SSH: php -f fixSUPEE6788.php
 * 
 * README:  https://github.com/rhoerr/supee-6788-toolbox/blob/master/README.md
 * LICENSE: https://github.com/rhoerr/supee-6788-toolbox/blob/master/LICENSE
 * 
 * 
 * ParadoxLabs, Inc.
 * http://www.paradoxlabs.com
 * Phone:   717-431-3330
 * Email:   sales@paradoxlabs.com
 * Support: http://support.paradoxlabs.com
 */

require_once 'abstract.php';

class Mage_Shell_PatchClass extends Mage_Shell_Abstract
{
	protected $_modules;
	protected $_modifiedFiles = array();
	
	protected $_codePools = array(
		'local',
		'community',
		'core'
	);
	
	protected $_whitelist = array(
		'Mage_Adminhtml', // Don't try to fix Mage_Adminhtml
	);
	
	/**
	 * Initialize
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->_findModules();
	}
	
	/**
	 * Apply PHP settings to shell script
	 */
	protected function _applyPhpVariables()
	{
		parent::_applyPhpVariables();
		
		set_time_limit(0);
		error_reporting(E_ALL);
		ini_set( 'memory_limit', '2G' );
		ini_set( 'display_errors', 1 );
	}
	
	/**
	 * Run script: Search for SUPEE-6788 affected files, auto-patch if needed.
	 * 
	 * @return void
	 */
	public function run()
	{
		$dryRun = null;
		
		if( isset( $this->_args['analyze'] ) ) {
			$dryRun = true;
		}
		elseif( isset( $this->_args['fix'] ) ) {
			$dryRun = false;
		}
		
		if( !is_null( $dryRun ) ) {
			static::log('---- Searching config for bad routers -----------------------------');
			$configAffectedModules	= $this->_fixBadAdminhtmlRouter( $dryRun );
			
			static::log('---- Searching files for bad routes -------------------------------');
			$routesAffectedFiles	= $this->_fixBadAdminRoutes( $configAffectedModules );
			
			static::log('---- Searching for whitelist problems -----------------------------');
			$whitelist = new TemplateVars();
			$whitelist->execute();
			
			if( isset( $this->_args['summarize'] ) ) {
				static::log('---- Summary ------------------------------------------------------');
				static::log('Summary:');
				static::log( sprintf( "Affected Modules:\n%s", implode( "\n", $configAffectedModules ) ) );
			}
		}
		else {
            echo $this->usageHelp();
		}
	}

    /**
     * Retrieve Usage Help Message
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f fixSUPEE6788.php -- [options]
  analyze       Analyze Magento install for SUPEE-6788 conflicts
  fix           Apply the automated fixes as found by analyze
  summarize     Include a summary of affected files and changes
  help          This help

USAGE;
    }
	
	/**
	 * Find modules/configuration affected by the admin controller issue.
	 *
	 * @param boolean $dryRun If true, find affected only; do not apply changes.
	 * @return array Affected module(s)
	 */
	protected function _fixBadAdminhtmlRouter( $dryRun=true )
	{
		$affected = array();
		
		foreach( $this->_modules as $name => $modulePath ) {
			$configPath = $modulePath . DS . 'etc' . DS . 'config.xml';
			
			if( is_file( $configPath ) ) {
				$config		= file_get_contents( $configPath );
				$match		= strpos( $config, '<use>admin</use>' );
				
				if( $match !== FALSE ) {
					static::log( sprintf( 'Found affected admin controller: %s', $configPath ) );
					
					/**
					 * Attempt to locate the complete route tag for replacement.
					 * String operations are messy, but it would be difficult to cover all possible cases otherwise.
					 */
					// Get route starting tag and position
					$routeStartingTag		= strrpos( substr( $config, 0, $match ), '<' );
					$routeStartingTagClose	= strpos( $config, '>', $routeStartingTag );
					
					$routeTag				= substr( $config, $routeStartingTag+1, ($routeStartingTagClose - $routeStartingTag - 1) );
					$affected[ $routeTag ]	= $modulePath;
					static::log( sprintf( 'Found route tag "%s" at %s.', $routeTag, $routeStartingTag ) );
					
					// Get route ending tag position and the full block
					$routeEndingTag			= strpos( $config, '</' . $routeTag .'>', $routeStartingTag );
					$routeLength			= $routeEndingTag - $routeStartingTag + strlen( $routeTag ) + 3;
					$originalXml			= substr( $config, $routeStartingTag, $routeLength );
					static::log( sprintf( "Found route tag-end at %s. Original route XML:\n%s", $routeEndingTag, $originalXml ) );
					
					// Get the module value
					$module					= null;
					preg_match( '/<module>(.*)<\/module>/', $originalXml, $module );
					$module					= isset( $module[1] ) ? $module[1] : $name;
					static::log( sprintf( 'Module is "%s".', $module ) );
					
					// Build the replacement XML
					$date					= date('Y-m-d H:i:s');
					$newRouteXml			= <<<XML
<!-- Route fixed by shell/fixSUPEE6788.php - {$date} -->
			<adminhtml>
				<args>
					 <modules>
						  <{$routeTag} before="Mage_Adminhtml">{$module}_Adminhtml</{$routeTag}>
					 </modules>
				</args>
			</adminhtml>
XML;
					static::log( sprintf( "XML to be replaced with:\n%s", $newRouteXml ) );
					
					/**
					 * If this is not a dry run, apply the changes and save config.xml.
					 */
					if( $dryRun === false ) {
						$config = substr_replace( $config, $newRouteXml, $routeStartingTag, $routeLength );
						
						if( file_put_contents( $configPath, $config ) !== false ) {
							$this->_modifiedFiles[] = $configPath;
							static::log('...Done.');
						}
						else {
							static::log( sprintf( 'ERROR: Unable to write new configuration to %s', $configPath ) );
						}
					}
				}
				else {
					// If not found, module is clean. Disregard.
				}
			}
			else {
				static::log( sprintf( 'Unable to load configuration: %s', $configPath ) );
			}
		}
		
		return $affected;
	}
	
	/**
	 * Attempt to find and fix any admin URLs (routes) affected by the router change.
	 *
	 * @param string[] $modulePaths Paths to modules to scan for routes.
	 * @param boolean $dryRun If true, find affected only; do not apply changes.
	 * @return array Affected files
	 */
	protected function _fixBadAdminRoutes( $modulePaths, $dryRun=true )
	{
		$affected = array();
		
		foreach( $modulePaths as $route => $modulePath ) {
			// Find/replace pairs
			$routePattern	= $route . '/adminhtml';
			$patterns		= array(
				'<action>' . $route . '/adminhtml_'		=> '<action>adminhtml/',
				'getUrl("' . $route . '/adminhtml_'		=> 'getUrl("adminhtml/',
				"getUrl('" . $route . '/adminhtml_'		=> "getUrl('adminhtml/",
				'getUrl( "' . $route . '/adminhtml_'	=> 'getUrl( "adminhtml/',
				"getUrl( '" . $route . '/adminhtml_'	=> "getUrl( 'adminhtml/",
			);
			
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $modulePath ) );
			foreach( $files as $file => $object ) {
				// Skip any non-PHP/XML files.
				if( strpos( $file, '.php' ) === false && strpos( $file, '.xml' ) === false ) {
					continue;
				}
				
				// Scan the file for our patterns, replace any found.
				$fileContents = file_get_contents( $file );
				if( strpos( $fileContents, $routePattern ) !== false ) {
					static::log( sprintf( 'Checking %s', $file ) );
					
					$lines		= explode( "\n", $fileContents );
					$changes	= false;
					
					foreach( $lines as $key => $line ) {
						if( strpos( $line, $routePattern ) !== false ) {
							foreach( $patterns as $pattern => $replacement ) {
								if( strpos( $line, $pattern ) !== false ) {
									$lines[ $key ]	= str_replace( $pattern, $replacement, $line );
									$changes		= true;
								}
							}
							
							if( $line != $lines[ $key ] ) {
								static::log( sprintf( '  WAS:%s', $line ) );
								static::log( sprintf( '  NOW:%s', $lines[ $key ] ) );
							}
						}
					}
					
					if( $dryRun === false && $changes === true ) {
						$fileContents = implode( "\n", $lines );
						
						if( file_put_contents( $file, $fileContents ) !== false ) {
							$this->_modifiedFiles[] = $file;
							// Silence!
						}
						else {
							static::log( sprintf( 'ERROR: Unable to write new configuration to %s', $configPath ) );
						}
					}
				}
			}
		}
		
		return $affected;
	}
	
	/**
	 * Locate all modules in the system.
	 *
	 * @return void
	 */
	protected function _findModules()
	{
		$this->_modules = array();
		
		$modules = Mage::getConfig()->getNode('modules')->children();
		foreach( $modules as $name => $settings ) {
			if( !in_array( $name, $this->_whitelist ) ) {
				$this->_modules[ $name ] = Mage::getModuleDir( '', $name );
			}
		}
	}
	
	/**
	 * Write the given message to a log file and to screen.
	 *
	 * @param  [type] $message [description]
	 * @return [type]          [description]
	 */
	public static function log( $message )
	{
		Mage::log( $message, null, 'fixSUPEE6788.log', true );
		
		if( !is_string( $message ) ) {
			$message = print_r( $message, 1 );
		}
		
		echo $message . "\n";
	}
}

$shell = new Mage_Shell_PatchClass();
$shell->run();



/**
 * TemplateVars adapted from magerun-addons
 * Courtesy of @peterjaap and @timvroom
 * https://github.com/peterjaap/magerun-addons
 */
class TemplateVars
{
	protected static $varsWhitelist = array(
		'web/unsecure/base_url',
		'web/secure/base_url',
		'trans_email/ident_general/name',
		'trans_email/ident_general/email',
		'trans_email/ident_sales/name',
		'trans_email/ident_sales/email',
		'trans_email/ident_support/name',
		'trans_email/ident_support/email',
		'trans_email/ident_custom1/name',
		'trans_email/ident_custom1/email',
		'trans_email/ident_custom2/name',
		'trans_email/ident_custom2/email',
		'general/store_information/name',
		'general/store_information/phone',
		'general/store_information/address',
	);
	
	protected static $blocksWhitelist = array(
		'core/template',
		'catalog/product_new',
	);
	
	/**
	 * @return void
	 */
	public function execute()
	{
		$resource			= Mage::getSingleton('core/resource');
		$db					= $resource->getConnection('core_read');
		$cmsBlockTable		= $resource->getTableName('cms/block');
		$cmsPageTable		= $resource->getTableName('cms/page');
		$emailTemplate		= $resource->getTableName('core/email_template');
		
		$sql				= "SELECT %s FROM %s WHERE %s LIKE '%%{{config %%' OR  %s LIKE '%%{{block %%'";
		$list				= array('block' => array(), 'variable' => array());
		$cmsCheck			= sprintf($sql, 'content, concat("cms_block=",identifier) as id', $cmsBlockTable, 'content', 'content');
		$result				= $db->fetchAll($cmsCheck);
		$this->check($result, 'content', $list);
		
		$cmsCheck			= sprintf($sql, 'content, concat("cms_page=",identifier) as id', $cmsPageTable, 'content', 'content');
		$result				= $db->fetchAll($cmsCheck);
		$this->check($result, 'content', $list);
		
		$emailCheck			= sprintf($sql, 'template_text, concat("core_email_template=",template_code) as id', $emailTemplate, 'template_text', 'template_text');
		$result				= $db->fetchAll($emailCheck);
		$this->check($result, 'template_text', $list);
		
		$localeDir			= Mage::getBaseDir('locale');
		$scan				= scandir($localeDir);
		$this->walkDir($scan, $localeDir, $list);
		
		if(count($list['block']) > 0) {
			Mage_Shell_PatchClass::log('Found blocks that are not whitelisted:');
			foreach ($list['block'] as $key => $blockName) {
				Mage_Shell_PatchClass::log( sprintf( '  %s in %s', $blockName, substr( $key, 0, -1 * strlen($blockName) ) ) );
			}
		}
		
		if(count($list['variable']) > 0) {
			Mage_Shell_PatchClass::log('Found template/block variables that are not whitelisted:');
			foreach ($list['variable'] as $key => $varName) {
				Mage_Shell_PatchClass::log( sprintf( '  %s in %s', $varName, substr( $key, 0, -1 * strlen($varName) ) ) );
			}
		}
	}
	
	protected function walkDir(array $dir, $path = '', &$list) {
		foreach ($dir as $subdir) {
			if (strpos($subdir, '.') !== 0) {
				if(is_dir($path . DS . $subdir)) {
					$this->walkDir(scandir($path . DS . $subdir), $path . DS . $subdir, $list);
				} elseif (is_file($path . DS . $subdir) && pathinfo($subdir, PATHINFO_EXTENSION) !== 'csv') {
					$file = array( array(
						'id'		=> $path . DS . $subdir,
						'content'	=> file_get_contents($path . DS . $subdir),
					) );
					$this->check($file, 'content', $list);
				}
			}
		}
	}
	
	protected function check($result, $field = 'content', &$list) {
		if ($result) {
			$blockMatch = '/{{block[^}]*?type=["\'](.*?)["\']/i';
			$varMatch = '/{{config[^}]*?path=["\'](.*?)["\']/i';
			foreach ($result as $res) {
				$target = ($field === null) ? $res: $res[$field];
				if (preg_match_all($blockMatch, $target, $matches)) {
					foreach ($matches[1] as $match) {
						if( !in_array( $match, self::$blocksWhitelist ) ) {
							$list['block'][ $res['id'] . $match ] = $match;
						}
					}
				}
				if (preg_match_all($varMatch, $target, $matches)) {
					foreach ($matches[1] as $match) {
						if( !in_array( $match, self::$varsWhitelist ) ) {
							$list['variable'][ $res['id'] . $match ] = $match;
						}
					}
				}
			}
		}
	}
}
