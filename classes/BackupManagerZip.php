<?php
namespace Grav\Plugin\BackupManager;
/**
 * @package    Grav\Plugin\BackupManager
 *
 * @copyright  Copyright (C) 2017 Uli Hake. All rights reserved.
 *
 * @license    MIT License; see LICENSE file for details.
 * 
 */
use Grav\Common\Grav;
use Grav\Common\Inflector;
use RocketTheme\Toolbox\File\JsonFile;

class BackupManagerZip
{
    protected static $backupScopes = [
		'admin',
		'defaults',
		'config',
		'pages',
        'user',
		'media',
		'images',
		'audio',
		'video',
        'log',
		'plugins',
		'themes',
		'imagecache',
		'cache',
		'data',
		'system',
		'purge',		
		'purgeall',
		'purgepartials',
		'purgetests',
		'purgepages',
		'purgeimages',
		'purgemedia',
		'purgethemes',
		'purgedata',
		'purgeplugins',
		'purgeconfig',
		'purgesytem',
		'purgefailed'
    ];
	
    protected static $ignorePaths = [
        'backup',
        'cache',
        'images',
        'logs',
        'tmp',
    ];
	
    protected static $ignoreFolders = [
        '.git',
        '.svn',
        '.hg',
        '.idea',
        'node_modules',
    ];
	
	protected static $imageTypes = ['jpg','jpeg','png','gif','ico'];
	protected static $audioTypes = ['mp3','wav', 'flac'];
	protected static $videoTypes = ['mp4','mpg','mpeg','mov','webm','ogv','ogg','wmv'];

	// Additional variables for file and folder filtering
    protected static $ignoreFileTypes = [];	
    protected static $restrictFileTypes = [];	
    protected static $excludeTopLevel = [];	
    protected static $excludeFolders = [];	
    protected static $addAsEmptyFolders = [];
    protected static $adminMultiPaths = [];
	protected static $intersectTopLevels = false;
	protected static $intersectIgnoreFolders = false;

	// Backup process related	
    protected static $backupTimeout = 600;
	// site scope works as in former versions
    protected static $configScope = "site";
	protected static $ignoreFolderCase = false;
	protected static $runInTestMode = false;
	protected static $testModeCompressionRatio = 1.2;	
	protected static $forceaddasempty	= false;
	
	// Log related
	protected static $logBackup = false;
    protected static $not_included = [];
    protected static $not_included_json = [];
    protected static $hint_content = "";
    protected static $hint_content_dir = "";
    protected static $hint_content_log = "";
	// Default scope and config.site.backup based
	protected static $isFullBackup = false;
	
	// Backup status documentation inside zip
    protected static $backup_log_dir = "backup";
	protected static $bytesIncluded = 0;
	protected static $filesIncluded = 0;
	protected static $zipFilesIncluded = 0;
	protected static $bytesExcluded = 0;
	protected static $filesExcluded = 0;
	protected static $backupStarttime = 0;
	protected static $elapsedDurationBeforeSave = 0;

	
	// Backup store capacity and purging parameters
    protected static $keepDays = 0;
	protected static $backupDestination = "";	
	protected static $backupFilePath = "";	
	protected static $destinationMaxSpace = 0;
	protected static $destinationCurrentUsage = "0B";
	// Not always updated, we need defaults
	protected static $fifoStoreStatus = array(
				'store' => "",
				'maxSize' => 0,
				'maxSizeTranslated' => "0B",
				'keepDays' => 0,
				'time' => 0,
				'timeTranslated' => "Unknown",
				'cleanAll' => false,
				'purged' => 0,
				'purgedTranslated' => "0B",
				'backupCapacityUsed' => 0,
				'backupCapacityUsedTranslated' => "0B",
				'purgedFilesExceededMaxDay' => 0,
				'purgedFilesExceededMaxCapacity' => 0,
				'filesDeleted' => 0,
			);
	
	protected static $inflector = null;

    /**
     * Set Log Hint Text
     *
     * @param null $hint_content_log
     *
	 * return array
	 *
     */	
    public static function setLogHintText($hint_content_log = null)
    {			
		static::$hint_content_log = [
			"FYI: This file contains background information about this backup zip file.",
			"Backups with a suffix of -partial- are not suited to restore a GRAV site.",
			"If you need a backup that includes your GRAV, plugins, other essentials and your content, please run backup without a scope.",
		];
		if ($hint_content_log && (is_string($hing_content_log) || is_array($hint_content_log))) {
			static::$hint_content_log = (array)$hint_content_log;
		}		
		return static::$hint_content_log;
	}
	
    /**
     * Set Dir Hint Text
     *
     * @param null $hint_content_dir
     *
	 * return array
	 *
     */	
    public static function setDirHintText($hint_content_dir = null)
    {			
		static::$hint_content_dir = [
			"FYI: This folder has been included as an empty folder during backup.",
			"If your site does not operate as expected once you restore a backup you may have to recover subfolders and files from the original site or existing backups.",
			"This backup includes a backup log file in the backup directory which contains addional information.",
		];
		if ($hint_content_dir && (is_string($hing_content_dir) || is_array($hint_content_dir))) {
			static::$hint_content_dir = (array)$hint_content_dir;
		}		
		return static::$hint_content_dir;
	}
	
    /**
     * Configure Ignores
     *
	 * @param null $scope
	 * @param null $runInTestMode
     * @param null $excludeTopLevel
     * @param null $excludeFolders
     * @param null $excludeAsEmptyFolders
     * @param null $excludeFileTypes
     * @param null $intersectTopLevels
     * @param null $intersectIgnoreFolders
	 * @param null $forceaddasempty
	 * @param null $ignoreFolderCase
	 * @param null $phptimeout
	 * @param null $maxSpace
	 * @param null $keepDays
	 * @param null testcompressionratio
	 * @param null $logBackup
     *
	 * return array
	 *
     */
    private static function prepareBackup(
		$scope = null,
		$runInTestMode = null,
		$excludeTopLevel = null, 
		$excludeFolders = null, 
		$excludeAsEmptyFolders = null, 
		$excludeFileTypes = null, 
		$intersectTopFolders = null, 
		$intersectFolders = null, 
		$forceaddasempty = null,
		$ignoreCase = null,
		$phptimeout = null,
		$maxSpace = null,
		$keepDays = null,		
		$testcompressionratio = null,
		$logBackup = null)		
    {		
		$excludeTopLevel = (array)$excludeTopLevel;
		$excludeFolders = (array)$excludeFolders;
		static::$excludeTopLevel = $excludeTopLevel;
		static::$excludeFolders = $excludeFolders;
		
		$excludeAsEmptyFolders = (array)$excludeAsEmptyFolders;
		if ($excludeFileTypes) {
			$excludeFileTypes = (array)$excludeFileTypes;
		}

		$ignoreCase = (bool)($ignoreCase);
		static::$ignoreFolderCase = $ignoreCase;
		static::$addAsEmptyFolders = array_merge(static::$addAsEmptyFolders, $excludeAsEmptyFolders);

		
		$intersectTopFolders = (bool)$intersectTopFolders;
		static::$intersectTopLevels = $intersectTopFolders;

		$intersectFolders = (bool)$intersectFolders;
		static::$intersectIgnoreFolders = $intersectFolders;
		
		if ($intersectTopFolders && count($excludeTopLevel)) {
			$toIgnoreFromDefined = array_values(array_intersect($excludeTopLevel, static::$ignorePaths));
			$toAddFromDefinitions = array_values(array_diff($excludeTopLevel, static::$ignorePaths));
			static::$ignorePaths = array_merge($toIgnoreFromDefined, $toAddFromDefinitions);
			// Important: never allow backup directory to be excluded from top level ignores
			if (!in_array('backup', static::$ignorePaths)) {
				static::$ignorePaths[] = 'backup';
			}
		}
		else {
			static::$ignorePaths = array_merge(static::$ignorePaths, $excludeTopLevel);			
		}
		if ($intersectFolders && count($excludeFolders)) {
			$toIgnoreFromDefined = array_values(array_intersect($excludeFolders, static::$ignoreFolders));
			$toAddFromDefinitions = array_values(array_diff($excludeFolders, static::$ignoreFolders));
			static::$ignoreFolders = array_merge($toIgnoreFromDefined, $toAddFromDefinitions);			
		}
		else {
			static::$ignoreFolders = array_merge(static::$ignoreFolders, $excludeFolders);
		}
		
		if (static::$ignoreFolderCase) {
			static::$ignorePaths = array_map('strtolower', static::$ignorePaths);			
			static::$ignoreFolders = array_map('strtolower', static::$ignoreFolders);			
			static::$addAsEmptyFolders = array_map('strtolower', static::$addAsEmptyFolders);			
		}
		
		// Make file types case insensitive by default		
		static::$ignoreFileTypes = array_filter(array_map('strtolower', array_merge(static::$ignoreFileTypes, $excludeFileTypes)), function($val) {return trim($val, ".");});
		
		// Clean folder definitions
		static::$ignorePaths = array_filter(array_map('trim', static::$ignorePaths), function($val) {return trim($val, "/");});
		static::$ignoreFolders = array_filter(array_map('trim', static::$ignoreFolders), function($val) {return trim($val, "/");});
		static::$addAsEmptyFolders = array_filter(array_map('trim', static::$addAsEmptyFolders), function($val) {return trim($val, "/");});
		
		// Timeout should never be less than 60 seconds for backups
		static::$backupTimeout = intval($phptimeout) > 59 ? intval($phptimeout) : static::$backupTimeout;		
		
		static::$logBackup = (bool)$logBackup;		
		static::$runInTestMode = (bool)$runInTestMode;
		if (intval($maxSpace)) {
			static::$destinationMaxSpace = intval($maxSpace)*pow(1024,3);			
		}			
		if (intval($keepDays)) {
			static::$keepDays = intval($keepDays);			
		}	
		if ($testcompressionratio && floatval($testcompressionratio)) {
			static::$testModeCompressionRatio = floatval($testcompressionratio);			
		}			
		$scope = (string)$scope;
		static::$configScope = ($scope && (in_array($scope, static::$backupScopes) || $scope === 'config.site.backup' || $scope === 'backup-manager.config')) ? $scope : '';
			
		return array(
			'ignoreTopLevel' => static::$ignorePaths,
			'ignoreFolders' => static::$ignoreFolders,
			'addAsEmptyFolders' => static::$addAsEmptyFolders,
			'ignoreFileTypes' => static::$ignoreFileTypes,
			'intersectTopLevels' => static::$intersectTopLevels,
			'intersectIgnoreFolders' => static::$intersectIgnoreFolders,
			'ignoreFolderCase' => static::$ignoreFolderCase,
			'backupTimeout' => static::$backupTimeout,
			'logBackup' => static::$logBackup,
			'runInTestMode' => static::$runInTestMode,
			'maxSpaceAllowed' => static::$destinationMaxSpace,
			'keepDays' => static::$keepDays,
			'scope' => static::$configScope,
			'forceAddAsEmpty' => static::$forceaddasempty,
			'compressionRatioForTests' => static::$testModeCompressionRatio,
		);		
	}
	
	
    /**
     * Backup
     *
     * @param null          $destination
     * @param callable|null $messager
	 * @param null 			$scope
	 * @param null			$configVars
     *
     * @return null|string
     */
    public static function backup($destination = null, callable $messager = null, $scope = null, $configVars = null)
    {

		$config = Grav::instance()['config'];

		// Configuration options
		$runInTestMode = null;
		$runastest = null;
		$forceexec = null;
		$excludeTopLevel = [];
		$excludeFolders = [];
		$excludeAsEmptyFolders = [];
		$excludeFileTypes = [];
		$intersectTopFolders = null;
		$intersectFolders = null;
		$forceaddasempty = null;
		$disableforceaddempty = null;
		$ignoreCase = null;
		$caseinsensitive = null;
		$phptimeout = null;
		$timeout = null;
		$maxSpace = null;
		$keepDays = null;		
		$testcompressionratio = null;
		$logBackup = null;
		$logstatus = null;
		$disabletestmode = null;
		$origins = null;
		$restricttypes = null;
		$current_config = null;
		
		if ($scope && $scope === "defaults") {
			
			if ($configVars && is_array((array)$configVars)) {
				$timeout = isset($config['timeout']) ? $config['timeout'] : null;
				$casesensitive = isset($config['casesensitive']) ? $config['casesensitive'] : null;
				$forceexec = isset($config['forceexec']) ? $config['forceexec'] : null;
			}
			
			$runInTestMode = (bool)$config->get('plugins.backup-manager.backup.testmode.enabled');

			// Check if we dispose of an override if test mode in site config is enabled
			if ($runInTestMode && $forceexec) {
				$runInTestMode = false;
			}			
			
			if ($runInTestMode !== true) {
				static::$isFullBackup = true;
			}
			
			$ignoreCase = (bool)$config->get('plugins.backup-manager.backup.ignore.foldercase');			
			// We allow to overide this as this may affect results of automated backups from cli
			if (!$ignoreCase && $casesensitive) {
				$ignoreCase = true;
			}	
			// Same for timeout
			$phptimeout = intval($config->get('plugins.backup-manager.backup.phptimeout'));
			if ($timeout && intval($timeout > 59) && intval($timeout) < 1801) {
				$phptimeout = intval($configVars['timeout']);
			}

			$maxSpace = intval($config->get('plugins.backup-manager.backup.storage.maxspace'));
			$keepDays = intval($config->get('plugins.backup-manager.backup.storage.keepdays'));
			//$testcompressionratio = (bool)($config->get('sitebackup.testmode.compressionratio'));
			$logBackup = (bool)$config->get('plugins.backup-manager.backup.log');
			
			$current_config = static::prepareBackup(
				$scope, 
				$runInTestMode, 
				$excludeTopLevel, 
				$excludeFolders, 
				$excludeAsEmptyFolders, 
				$excludeFileTypes, 
				$intersectTopFolders, 
				$intersectFolders, 
				$forceaddasempty, 
				$ignoreCase,
				$phptimeout,
				$maxSpace,
				$keepDays,
				$testcompressionratio,
				$logBackup
			);			
		}
		elseif ($scope && in_array((string)$scope, static::$backupScopes)) {
			
			if ($configVars && is_array((array)$configVars)) {
				if (isset($configVars['scope'])) {
					unset($configVars['scope']);
				}
				if (isset($configVars['forceaddasempty'])) {
					unset($configVars['forceaddasempty']);
				}
				extract($configVars, EXTR_IF_EXISTS);
			}
			
			$forceaddasempty = (bool)$config->get('plugins.backup-manager.backup.ignore.forceaddasempty'); 
			$ignoreCase = (bool)$config->get('plugins.backup-manager.backup.ignore.foldercase');
			$runInTestMode = (bool)$config->get('plugins.backup-manager.backup.testmode.enabled');			
			$phptimeout = (bool)$config->get('plugins.backup-manager.backup.phptimeout');			
			$logBackup = (bool)$config->get('plugins.backup-manager.backup.log');	
			
			if (is_null($maxSpace)) {
				$maxSpace = intval($config->get('plugins.backup-manager.backup.storage.maxspace'));
			}
			if (is_null($keepDays)) {
				$keepDays = intval($config->get('plugins.backup-manager.backup.storage.keepdays'));
			}
			if (is_null($testcompressionratio)) {
				$testcompressionratio = (bool)($config->get('sitebackup.testmode.compressionratio'));
			}
			
			// Allow to be changed from cli and plugins
			if ($disableforceaddempty) {
				$forceaddasempty = false;		
			}
			else {
				$excludeAsEmptyFolders = (array)$config->get('plugins.backup-manager.backup.ignore.addasemptyfolder');				
			}
			if ($caseinsensitive) {
				$ignoreCase = true;	
			}
			if ($runastest) {
				$runInTestMode = true;	
			}
			if ($forceexec && $runInTestMode) {
				$runInTestMode = false;
			}			
			if ($logstatus) {
				$logBackup = $logstatus;
			}
			
			if ($timeout && intval($timeout > 59) && intval($timeout) < 1801) {
				$phptimeout = intval($timeout);
			}			
						
			$current_config = static::prepareBackup(
				$scope, 
				$runInTestMode, 
				$excludeTopLevel, 
				$excludeFolders, 
				$excludeAsEmptyFolders, 
				$excludeFileTypes, 
				$intersectTopFolders, 
				$intersectFolders, 
				$forceaddasempty, 
				$ignoreCase,
				$phptimeout,
				$maxSpace,
				$keepDays,
				$testcompressionratio,
				$logBackup
			);
		} else {
			
			$scope = 'backup-manager.config';
			if ($configVars && is_array((array)$configVars)) {
				if (isset($configVars['scope'])) {
					unset($configVars['scope']);
				}
				if (isset($configVars['forceaddasempty'])) {
					unset($configVars['forceaddasempty']);
				}
				extract($configVars, EXTR_IF_EXISTS);
			}
			
			$runInTestMode = (bool)$config->get('plugins.backup-manager.backup.testmode.enabled');

			// Check if we dispose of an override if test mode in site config is enabled
			if ($runInTestMode && is_array($configVars) && isset($configVars['forceexec']) && $configVars['forceexec']) {
				$runInTestMode = false;
			}			
			
			if ($runInTestMode !== true) {
				static::$isFullBackup = true;
			}
			
			$excludeTopLevel = (array)$config->get('plugins.backup-manager.backup.ignore.toplevelfolders');
			$excludeFolders = (array)$config->get('plugins.backup-manager.backup.ignore.folders');
			$excludeAsEmptyFolders = (array)$config->get('plugins.backup-manager.backup.ignore.addasemptyfolder');
			$excludeFileTypes = (array)$config->get('plugins.backup-manager.backup.ignore.filetypes');
			$intersectTopFolders = (bool)$config->get('plugins.backup-manager.backup.ignore.toplevelintersect');
			$intersectFolders = (bool)$config->get('plugins.backup-manager.backup.ignore.foldersintersect');
			$forceaddasempty = (bool)$config->get('plugins.backup-manager.backup.ignore.forceaddasempty'); 

			$ignoreCase = (bool)$config->get('plugins.backup-manager.backup.ignore.foldercase');			
			// We allow to overide this as this may affect results of automated backups from cli
			if (!$ignoreCase && $casesensitive) {
				$ignoreCase = true;
			}			
			// Same for timeout
			$phptimeout = intval($config->get('plugins.backup-manager.backup.phptimeout'));
			if ($timeout && intval($timeout >= 59) && intval($timeout) <= 1801) {
				$phptimeout = intval($configVars['timeout']);
			}			
			
			$maxSpace = intval($config->get('plugins.backup-manager.backup.storage.maxspace'));
			$keepDays = intval($config->get('plugins.backup-manager.backup.storage.keepdays'));
			$testcompressionratio = (bool)($config->get('sitebackup.testmode.compressionratio'));
			$logBackup = (bool)$config->get('plugins.backup-manager.backup.log');
			$current_config = static::prepareBackup(
				$scope, 
				$runInTestMode, 
				$excludeTopLevel, 
				$excludeFolders, 
				$excludeAsEmptyFolders, 
				$excludeFileTypes, 
				$intersectTopFolders, 
				$intersectFolders, 
				$forceaddasempty, 
				$ignoreCase,
				$phptimeout,
				$maxSpace,
				$keepDays,
				$testcompressionratio,
				$logBackup
			);
		}
		
        if (!$destination) {
            $destination = Grav::instance()['locator']->findResource('backup://', true);

            if (!$destination) {
                throw new \RuntimeException('The backup folder is missing.');
            }
        }
		
		$log = JsonFile::instance(Grav::instance()['locator']->findResource("log://backup/lastrun_settings.log", true, true));
        $log->content($current_config);
        $log->save();		
		
		static::$bytesExcluded = 0;
		static::$filesExcluded = 0;					
		static::$bytesIncluded = 0;
		static::$filesIncluded = 0;
		static::setBackupStartTime();
		
		$exclusiveLength = strlen(rtrim(GRAV_ROOT, DS) . DS);
		
		static::$backupDestination = $destination;
		
        static::$inflector = new Inflector();

		$date = date('YmdHis', time());
		$site_id = static::getSiteID();
		
		$zipscope = null;
        if (is_dir($destination)) {
            $filename = $site_id . $date . '.zip';
            $destination = rtrim($destination, DS) . DS . $filename;			
			
			// Run fifo handler for backup files here and return if scope is clean
			// Only for default GRAV backup, double check
			if (stripos(static::$backupDestination, GRAV_ROOT) === 0) {
				static::backupFolderCleanFailed	(static::$backupDestination, $site_id);
				if (stripos(static::$configScope, 'purge') !== false) {
					
					switch (static::$configScope) {
						case 'purgeall':
							static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id, true);						
							break;
						case 'purge':
							static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id);
							break;
						case 'purgetests':
							static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id, true, 'testmode');
							break;
						case 'purgefailed':
							static::backupFolderCleanFailed	(static::$backupDestination, $site_id, true);
							//static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id);				
							break;
						default:
							static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id, str_replace('purge', '', static::$configScope));
					}
					
					// Only one for each socpe, no time stamp, we don't want to clutter up the backup folder with trash.
					$purgefile = Grav::instance()['locator']->findResource("log://backup/last-" . static::$configScope . "-formatted.log", true, true);
					$message = "Current state of the backup store:\n\n";
					$s = null; foreach (static::$fifoStoreStatus as $k=>$v) { if ($s !== null) { $s .= "\n"; } $s .= static::$inflector->titleize($k) . ": " . implode(', ', (array)$v); }
					$message .= $s;
					
					file_put_contents($purgefile, $message);
					
					$purgefile = Grav::instance()['locator']->findResource("log://backup/last-" . static::$configScope . "-raw.log", true, true);
					file_put_contents($purgefile, json_encode(static::$fifoStoreStatus));
					
					$external_log = Grav::instance()['locator']->findResource("log://backup/last-raw.log", true, true);
					$backupStats = static::processReflector();
					file_put_contents($external_log, json_encode($backupStats));
										
					return $purgefile;				
				}
				else {
					// Standard purge on every call
					static::$fifoStoreStatus = static::fifoBackupFolder(static::$backupDestination, $site_id);
				}
			}
			// Process zip in scope context
			$scopepath = null;
			$locator = Grav::instance()['locator'];
			switch(static::$configScope) {
				case "user":
					$scopepath = $locator->findResource('user://', true);
					$destination = rtrim($destination, ".zip") . '-partial-user.zip';
					break;
				case "log":
					$scopepath = $locator->findResource('log://', true);
					$destination = rtrim($destination, ".zip") . '-partial-log.zip';
					break;
				case "config":
					// Includes robots.txt and other configuration files in the instance root as well
					$scopepath = $locator->findResource('user://config', true);
					$destination = rtrim($destination, ".zip") . '-partial-config.zip';
					break;
				case "plugins":
					$scopepath = $locator->findResource('user://plugins', true);
					$destination = rtrim($destination, ".zip") . '-partial-plugins.zip';
					break;
				case "pages":
					$scopepath = $locator->findResource('user://pages', true);
					$destination = rtrim($destination, ".zip") . '-partial-pages.zip';
					break;
				case "themes":
					$scopepath = $locator->findResource('user://themes', true);
					$destination = rtrim($destination, ".zip") . '-partial-themes.zip';
					break;
				case "data":
					$scopepath = $locator->findResource('user://data', true);
					$destination = rtrim($destination, ".zip") . '-partial-data.zip';
					break;
				case "imagecache":
					$scopepath = $locator->findResource('images://', true);
					$destination = rtrim($destination, ".zip") . '-partial-imagecache.zip';
					break;
				case "system":
					// $scopepath = $locator->findResource('system://', true);
					$destination = rtrim($destination, ".zip") . '-partial-system.zip';
					$origins = [
						'user/config',
						'user/plugins',
						'system',
					];
					break;
				case "cache":
					$scopepath = $locator->findResource('cache://', true);
					$destination = rtrim($destination, ".zip") . '-partial-cache.zip';
					break;
				case "media":
					$scopepath = $locator->findResource('user://pages', true);
					static::$ignoreFileTypes = [];
					static::$restrictFileTypes = array_unique(array_merge(static::$imageTypes, static::$audioTypes, static::$videoTypes));
					$destination = rtrim($destination, ".zip") . '-partial-media.zip';
					break;
				case "images":
					$scopepath = $locator->findResource('user://pages', true);
					static::$ignoreFileTypes = [];
					static::$restrictFileTypes = static::$imageTypes;
					$destination = rtrim($destination, ".zip") . '-partial-images.zip';
					break;
				case "audio":
					$scopepath = $locator->findResource('user://pages', true);
					static::$ignoreFileTypes = [];
					static::$restrictFileTypes = static::$audioTypes;
					$destination = rtrim($destination, ".zip") . '-partial-audio.zip';
					break;
				case "video":
					$scopepath = $locator->findResource('user://pages', true);
					static::$ignoreFileTypes = [];
					static::$restrictFileTypes = static::$videoTypes;
					$destination = rtrim($destination, ".zip") . '-partial-video.zip';
					break;
				case "admin":
					$destination = rtrim($destination, ".zip") . '-partial-admin.zip';
					if ($origins && is_array($origins) && count($origins) > 0) {
						$origins = array_filter($origins, function($val, $key) use ($origins) {
							$val = str_replace(DS, DS, trim($val, DS));
							$filePath = rtrim(GRAV_ROOT, DS) . DS . $val;
							if (file_exists($filePath) && is_dir($filePath)) {
								if (static::findPathOverlaps($val, $key, $origins) === false) {
									return $val;
								}
							}
						}, ARRAY_FILTER_USE_BOTH);
						sort($origins);
					}					
					break;
			}
			if (file_exists($scopepath) && is_dir($scopepath)) {
				$zipscope = $scopepath;
			}
			if (static::$runInTestMode) {
				$destination = rtrim($destination, ".zip") . '-testmode.zip';			
			}
        }	
		
		// Last config restrict to filetypes if set for all
		if (is_array($restricttypes) && count($restricttypes) > 0) {
			// Make file types case insensitive by default		
			static::$restrictFileTypes = array_filter(array_map('strtolower', $restricttypes), function($val) {return trim($val, ".");});
			static::$ignoreFileTypes = [];
		}
		
        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => 'Creating new Backup "' . $destination . '"'
        ]);
        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => ''
        ]);
		
        $zip = new \ZipArchive();
		static::$backupFilePath = $destination;
        $zip->open($destination, \ZipArchive::CREATE);		
		
        $max_execution_time = ini_set('max_execution_time', static::$backupTimeout);
		
		static::$hint_content = static::setDirHintText();
		static::$hint_content = join("\n\n", static::$hint_content);		
		static::$not_included = [];
		static::$not_included_json = [];		
		
		if ((static::$configScope === 'admin' || static::$configScope === 'system') && is_array($origins) && count($origins) > 0) {
			static::$adminMultiPaths = $origins;			
			foreach($origins as $key => $zippath) {
				$zipscope = rtrim(GRAV_ROOT, DS) . '/' . $zippath;
				$backup = static::folderToZip(GRAV_ROOT, $zip, $exclusiveLength, $messager, null, $zipscope);
			}
		}
		else {
			static::folderToZip(GRAV_ROOT, $zip, $exclusiveLength, $messager, null, $zipscope);
		}

		if (static::$logBackup) {
			static::$hint_content = static::setLogHintText();
			static::$hint_content = join("\n", static::$hint_content);
			
			$backupStats = static::processReflector();
			static::$hint_content .= "\n\nBackup status information:\n\n";

			unset($backupStats['backupScopes']);
			unset($backupStats['logBackup']);
			$s = null; foreach ($backupStats as $k=>$v) { if ($s !== null) { $s .= "\n"; } $s .= static::$inflector->titleize($k) . ": " . implode(', ', (array)$v); }
			static::$hint_content .= $s;
			static::$hint_content .= "\n\n---Backup log end---\n\n";
			$localPath = substr(rtrim(GRAV_ROOT, DS) . '/backup', $exclusiveLength);
			$zip->addFromString($localPath . '/backup.log', static::$hint_content);
		}
		
		if (static::$configScope == 'config' || static::$configScope == 'system') {
			$phpinfo = static::phpinfo();
			$localPath = substr(rtrim(GRAV_ROOT, DS) . '/backup', $exclusiveLength);
			$zip->addFromString($localPath . '/phpinfo.html', $phpinfo);
		}
				
        $messager && $messager([
            'type' => 'progress',
            'percentage' => false,
            'complete' => true
        ]);

        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => ''
        ]);
        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => 'Saving and compressing archive...'
        ]);
		
		$catchDuration = static::getBackupDuration();
		static::$elapsedDurationBeforeSave = $catchDuration['elapsedbeforesave'];
		
        $zip->close();
		
        if ($max_execution_time !== false) {
            ini_set('max_execution_time', $max_execution_time);
        }

		
		$external_log = Grav::instance()['locator']->findResource("log://backup/last-formatted.log", true, true);
		$message = "Last backup process summary:\n\n";
		$backupStats = static::processReflector();
		$s = null; foreach ($backupStats as $k=>$v) { if ($s !== null) { $s .= "\n"; } $s .= static::$inflector->titleize($k) . ": " . implode(', ', (array)$v); }
		$message .= $s;		
		file_put_contents($external_log, $message);
		$external_log = Grav::instance()['locator']->findResource("log://backup/last-raw.log", true, true);
		$backupStats = static::processReflector();
		file_put_contents($external_log, json_encode($backupStats));
		
        return $destination;
    }

    /**
     * @param $folder
     * @param $zipFile
     * @param $exclusiveLength
     * @param $messager
	 * @param $currentFolder
     */
    private static function folderToZip($folder, \ZipArchive &$zipFile, $exclusiveLength, callable $messager = null, $currentParent = null, $scope = null)
    {
        $handle = opendir($folder);
		$parentFolder = !empty($currentParent) ? $currentParent : "";
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
				$filePath = "$folder/$f";					
				$localPath = substr($filePath, $exclusiveLength);
				if ($scope) {					
					$comparef = static::$ignoreFolderCase ? strtolower($f) : $f;
					// Remove prefix from file path before add to zip.
					$comparel = static::$ignoreFolderCase ? strtolower($localPath) : $localPath;				
					if (static::$logBackup && is_dir($filePath) && $folder === GRAV_ROOT && $comparef === 'backup') {
						$zipFile->addEmptyDir($localPath);
					}											
					elseif ($filePath === $scope && is_dir($filePath)) {
						$zipFile->addEmptyDir($localPath);
						if (static::$logBackup) {
							$zipFile->addFromString($localPath . '/backuphint.info', static::$hint_content);											
						}
						static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager, null, null);
					} 
					elseif (is_dir($filePath)) {						
						static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager, null, $scope);						
					}
					elseif (strtolower(basename($scope)) === 'config' && $filePath === rtrim(GRAV_ROOT, DS) . '/' . $f) {
						// Special case for config scope. 
						// Include files in site root as one of config scope's purposes is site support
						$extension = strtolower(pathinfo($f, PATHINFO_EXTENSION));
						if ($f == 'backuphint.info') {
							continue;
						}
						elseif (in_array($extension, static::$ignoreFileTypes)) {
							if (static::$logBackup) {					
								static::$not_included[] = $filePath;
								static::$not_included_json[] = $filePath;
							}
							static::$bytesExcluded += filesize($filePath);
							continue;
						}
						else {
							if (!static::$runInTestMode) {
								$zipFile->addFile($filePath, $localPath);
							}
							static::$bytesIncluded += filesize($filePath);
							static::$filesIncluded++;						
							$messager && $messager([
								'type' => 'progress',
								'percentage' => false,
								'complete' => false,
							]);
						}						
					}
					elseif ($folder === GRAV_ROOT && $f === 'index.php') {
						if (strtolower(basename($scope)) === 'themes') {
							$configDir = rtrim(GRAV_ROOT, DS) . '/user/config/themes';
							$configPath = substr($configDir, $exclusiveLength);							
							$zipFile->addEmptyDir($configPath);
							static::folderToZip($configDir, $zipFile, $exclusiveLength, $messager, null, null);							
						}
						elseif (strtolower(basename($scope)) === 'plugins') {
							$configDir = rtrim(GRAV_ROOT, DS) . '/user/config/plugins';
							$configPath = substr($configDir, $exclusiveLength);							
							$zipFile->addEmptyDir($configPath);
							static::folderToZip($configDir, $zipFile, $exclusiveLength, $messager, null, null);														
						}
					}
				}
				else {
					$comparef = static::$ignoreFolderCase ? strtolower($f) : $f;
					// Remove prefix from file path before add to zip.
					$comparel = static::$ignoreFolderCase ? strtolower($localPath) : $localPath;
					if (in_array($comparef, static::$addAsEmptyFolders)) {
						$zipFile->addEmptyDir($localPath);
						if (static::$logBackup) {
							$zipFile->addFromString($localPath . '/backuphint.info', static::$hint_content);						
							//add one level of subfolders as a hint on what's missing
						}
						static::$not_included[] = "---$f added as empty folder with subfolder references---";
						static::$not_included[] = $filePath;
						static::$not_included_json[] = $filePath;
						
						static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager, $f, null);						
						$fileData = static::calcExcludedFolderData($filePath);
						static::$bytesExcluded += $fileData[0];						
						static::$filesExcluded += $fileData[1];						
						continue;
					}
					elseif (!empty($parentFolder)) {
						$zipFile->addEmptyDir($localPath);
						static::$not_included[] = $filePath;						
						static::$not_included_json[] = $filePath;
						continue;
					}
					elseif (in_array($comparef, static::$ignoreFolders)) {					
						static::$not_included[] = "---Folder $f was completely ignored---";
						static::$not_included[] = $filePath;
						static::$not_included_json[] = $filePath;					
						$parentFolder = "";					
						$fileData = static::calcExcludedFolderData($filePath);
						static::$bytesExcluded += $fileData[0];						
						static::$filesExcluded += $fileData[1];						
						continue;
					} elseif (in_array($comparel, static::$ignorePaths)) {
						$zipFile->addEmptyDir($localPath);
						if (static::$logBackup) {					
							$zipFile->addFromString($localPath . '/backuphint.info', static::$hint_content);						
						}
						static::$not_included[] = "---$f added as empty top level folder---";
						static::$not_included[] = $filePath;
						static::$not_included_json[] = $filePath;					
						$parentFolder = "";					
						$fileData = static::calcExcludedFolderData($filePath);
						static::$bytesExcluded += $fileData[0];						
						static::$filesExcluded += $fileData[1];						
						continue;
					}

					if (is_file($filePath)) {
						$extension = strtolower(pathinfo($f, PATHINFO_EXTENSION));						
						if (count(static::$restrictFileTypes) > 0) {
							if (in_array($extension, static::$restrictFileTypes)) {
								if (!static::$runInTestMode) {
									$zipFile->addFile($filePath, $localPath);
								}
								static::$bytesIncluded += filesize($filePath);
								static::$filesIncluded++;						
								$messager && $messager([
									'type' => 'progress',
									'percentage' => false,
									'complete' => false,
								]);
							}
							else {
								static::$not_included[] = $filePath;
								static::$not_included_json[] = $filePath;
								static::$bytesExcluded += filesize($filePath);
							}
						}
						else {
							if ($f == 'backuphint.info') {
								continue;
							}
							elseif (in_array($extension, static::$ignoreFileTypes)) {
								static::$not_included[] = $filePath;
								static::$not_included_json[] = $filePath;
								static::$bytesExcluded += filesize($filePath);
								continue;
							}
							else {
								if (!static::$runInTestMode) {
									$zipFile->addFile($filePath, $localPath);
								}
								static::$bytesIncluded += filesize($filePath);
								static::$filesIncluded++;						
								$messager && $messager([
									'type' => 'progress',
									'percentage' => false,
									'complete' => false,
								]);
							}
						}
					} elseif (is_dir($filePath)) {
						// Add sub-directory.
						if (count(static::$restrictFileTypes) > 0) {
							if (static::hasFilesOfType($filePath, static::$restrictFileTypes)) {
								$zipFile->addEmptyDir($localPath);
								static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager, null, null);								
							}
						}
						else {
							$zipFile->addEmptyDir($localPath);
							static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager, null, null);
						}
					}
				}
            }
        }
        closedir($handle);
		return true;
    }	

    /**
	 * Cleanup the destination folder
	 * We only take care of GRAV Site backup files
	 *
     * @param string $path
     * @param string $site_prefix
     * @param null $cleanall	 
	 * 
     * @return array
	 *
     */	
	private static function fifoBackupFolder($path, $site_prefix, $clearall = null, $cleantests = null) {
		
		$site_prefix = trim($site_prefix);
		$test_mode = static::$runInTestMode;
		$files = null;
		if ($cleantests) {
			$files = glob("$path/{$site_prefix}*{$cleantests}.zip");
			$clearall = true;
			//$test_mode = false;
		}
		else {
			$files = glob("$path/{$site_prefix}*.zip");			
		}			

		$cleanPath = rtrim($path, '/'). '/';
		$now   = time();
		$accu_file_size = 0;
		$store_size = 0;
		$cleaned_bytes = 0;
		$files_exceeded_maxdays = 0;
		$files_exceeded_maxspace = 0;
		$test_files_purged = 0;
		$clearall_files_purged = 0;
		$files_deleted = 0;
		$days = intval(static::$keepDays);
		$timelimit = time() - (60 * 60 * 24 * $days);

		if ($files && (static::$keepDays > 0 || static::$destinationMaxSpace > 0 || $clearall === true || $clearall === 'partials')) {
			usort($files, function($a, $b) {
				// Sort latest to oldest
				return filemtime($a) < filemtime($b);
			});			
			foreach ($files as $file) {
				$filesize = filesize($file);
				if ($clearall && $clearall === true) {					
					$cleaned_bytes += $filesize;
					$files_deleted++;
					$clearall_files_purged++;
					if (!$test_mode) {
						unlink($file);
					}
				}
				elseif ($clearall && is_string($clearall)) {
					$accu_file_size += $filesize;
					if (stripos(strtolower(basename($file)), "-$clearall") !== false) {
						$cleaned_bytes += $filesize;
						$files_deleted++;
						$accu_file_size -= $filesize;
						if (!$test_mode) {
							unlink($file);
						}
					}
					else {
						$store_size += $filesize;
					}
				}
				else {
					if (filemtime($file) < $timelimit) {								
						$files_exceeded_maxdays++;
						$accu_file_size -= $filesize;
						$cleaned_bytes += $filesize;							
						$files_deleted++;
						if (!$test_mode) {
							unlink($file);
						}
					}					
					else {
						$accu_file_size += $filesize;								
						if ($accu_file_size > static::$destinationMaxSpace) {
							$files_exceeded_maxspace++;
							$cleaned_bytes += $filesize;
							$files_deleted++;
							if (!$test_mode) {							
								unlink($file);
							}
						}
						else {
							$store_size += $filesize;
						}
					}
				}
			}
		}
		
		$readableCleaned = static::formatBytes($cleaned_bytes);
		$backupStoreSize = static::formatBytes($store_size, 1);
		$maxSize = static::formatBytes(static::$destinationMaxSpace);
		$store = basename($path);
		$nowformat = date('d/m/Y H:i:s', $now);
		return array(
            'store' => $store,
            'maxSize' => static::$destinationMaxSpace,
            'maxSizeTranslated' => $maxSize,
            'keepDays' => static::$keepDays,
            'time' => $now,
            'timeTranslated' => $nowformat,
			'cleanAll' => (bool)$clearall,
            'purged' => $cleaned_bytes,
            'purgedTranslated' => $readableCleaned,
			'backupCapacityUsed' => $store_size,
			'backupCapacityUsedTranslated' => $backupStoreSize,
			'purgedFilesExceededMaxDay' => $files_exceeded_maxdays,
            'purgedFilesExceededMaxCapacity' => $files_exceeded_maxspace,
            'filesDeleted' => $files_deleted,
		);
	}	

    /**
	 * Cleanup the destination folder
	 * We only take care of GRAV Site backup files
	 *
     * @param string $path
     * @param string $site_prefix
     * @param null $cleanall	 
	 * 
     * @return array
	 *
     */	
	private static function backupFolderCleanFailed($path, $site_prefix, $now = null) {
		$site_prefix = trim($site_prefix);
		$files = glob("$path/$site_prefix*.zip.*");			

		$cleanPath = rtrim($path, '/'). '/';
		$timelimit = time() - (60 * 60 * 4);

		if ($files) {
			usort($files, function($a, $b) {
				// Sort latest to oldest
				return filemtime($a) < filemtime($b);
			});			
			foreach ($files as $file) {
				if ($now) {
					unlink($file);
				}				
				elseif (filemtime($file) < $timelimit) {								
					unlink($file);
				}					
			}
		}		
	}	
	
	
    /**
	 * Sum of filesize of all files in folder and subfolders and number of files found
	 *
     * @param string $path
	 * 
	 * return array
	 *
     */	
	private static function calcExcludedFolderData($path) {
		$filesdata = [0, 0];
		$files = scandir($path);
		$cleanPath = rtrim($path, '/'). '/';

		foreach($files as $t) {
			if ($t<>"." && $t<>"..") {
				$currentFile = $cleanPath . $t;
				if (is_dir($currentFile)) {
					$add = static::calcExcludedFolderData($currentFile);
					$filesdata[0] += $add[0];
					$filesdata[1] += $add[1];					
				}
				else {
					$size = filesize($currentFile);
					$filesdata[0] += $size;
					$filesdata[1]++;
				}
			}   
		}
		return $filesdata;
	}	

    /**
	 * hasFilesOfType
	 *
     * @param string $path
	 * @param array  $types
	 * 
	 * return bool
	 *
     */	
	private static function hasFilesOfType($path, $types) {
		$files = scandir($path);
		$cleanPath = rtrim($path, DS). DS;
		$hasTypes = false;
		foreach($files as $t) {
			if ($t<>"." && $t<>"..") {
				$currentPath = $cleanPath . $t;
				if (is_dir($currentPath)) {
					$hasTypes = static::hasFilesOfType($currentPath, $types);
				}
				else {
					$extension = strtolower(pathinfo($t, PATHINFO_EXTENSION));
					if (in_array($extension, $types)) {
						$hasTypes = true;
						break;
					}
				}
			}   
		}
		return $hasTypes;
	}	
	
    /**
	 * Set backup start time
	 * 
     */	
	public static function setBackupStartTime() {
		if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
			static::$backupStarttime = $_SERVER['REQUEST_TIME_FLOAT'];
		} else {
			static::$backupStarttime = microtime(true);
		}
	}	
	
    /**
     * @return array
     */
    public static function getBackupDuration()
    {
		$formatStart = date('Y-m-d H:i:s', static::$backupStarttime);
		
        $backupEndTime = microtime(true);
		$formatEnd = date('Y-m-d H:i:s', $backupEndTime);
		
		
		$backupDuration = $backupEndTime - static::$backupStarttime;
		$formatBackupDuration = static::formatTime($backupDuration);
		
		$elapsedBeforeSave = static::$elapsedDurationBeforeSave;
		$formatElapsedBeforeSave = static::formatTime($elapsedBeforeSave);

		$timeoutRemaining = static::$backupTimeout - $backupDuration;		
		$formatTimeoutRemaining = static::formatTime($timeoutRemaining);		
		
        return array(
            'start' => static::$backupStarttime,
            'end' => $backupEndTime,
            'duration' => $backupDuration,
			'remaining' => $timeoutRemaining,
			'elapsedbeforesave' => $elapsedBeforeSave,
			'startformat' => $formatStart,
			'endformat' => $formatEnd,
			'remainingformat' => $formatTimeoutRemaining,
            'durationformat' => $formatBackupDuration,
			'remainingformat' => $formatTimeoutRemaining,
			'elapsedbeforesaveformat' => $formatElapsedBeforeSave,
        );
    }
	
    /**
     * @param int $precision
     * @return array
     */
    public static function excludedFileStats($precision = 2)
    {
		$readableSize = "0B";
		$filesExcluded = static::$filesExcluded;
		$bytes = static::$bytesExcluded;
        if ($bytes === 0 || $bytes === null) {
			return array(
				'rawsize' => 0,
				'size' => $readableSize,
				'files' => $filesExcluded,
			);
        }

		$readableSize = static::formatBytes($bytes);
		
		return array(
			'rawsize' => static::$bytesExcluded,
			'size' => $readableSize,
			'files' => $filesExcluded,
		);
    }	
	
    /**
     * @param int $precision
     * @return array
     */
    public static function includedFileStats($precision = 2)
    {
		$readableSize = "0B";
		$bytes = 0;
		$filesIncluded = 0;
		if (static::$runInTestMode) {
			$bytes = static::$bytesIncluded;
			$filesIncluded = static::$filesIncluded;
		}
		elseif (file_exists(static::$backupFilePath)) {
			$bytes = filesize(static::$backupFilePath);
			$filesIncluded = static::$zipFilesIncluded;
		}
		if ($bytes === 0 || $bytes === null) {
			return array(
				'rawsize' => 0,
				'size' => $readableSize,
				'files' => $filesIncluded,
			);
		}
		
		$readableSize = static::formatBytes($bytes);
		
		return array(
			'rawsize' => static::$bytesIncluded,
			'size' => $readableSize,
			'files' => $filesIncluded,
		);
    }	

    /**
     * @param int $precision
     * @return array
     */
    public static function zipFileStats($precision = 2)
    {
		$inbytes = 0;
		$filesIncluded = static::$filesIncluded;
		$filesExcluded = static::$filesExcluded;
		$ratio = static::$testModeCompressionRatio;
		$zipbytes = 0;
		$saving = 0;
		$status = "test";
		if (static::$runInTestMode) {
			// Assume a low compression rate of 1.2 which is appropriate for sites with many images, pdfs, etc.
			// This could be enhanced by learning from previous backup processes but that's something for plugins, etc.
			// We provide enough data and configuration op
			$inbytes = static::$bytesIncluded;			
			if ($inbytes && $inbytes > 0) {
				$zipbytes = $inbytes / $ratio;
				$saving = round(((1 - ($zipbytes / $inbytes)) * 100), $precision);
				$status = "test";
				
			}
		}
		elseif (file_exists(static::$backupFilePath)) {			
			$zipbytes = filesize(static::$backupFilePath);
			$inbytes = static::$bytesIncluded;			
			if ($zipbytes && $inbytes && $zipbytes > 0 && $inbytes > 0) {
				$ratio = $inbytes / $zipbytes;
				$saving = round(((1 - ($zipbytes / $inbytes)) * 100), $precision);
				$filesIncluded = static::$zipFilesIncluded;
				$status = "success";
			}
		}
		if ($saving === 0 || $saving === null) {
			return array(
				'status' => 'unknown',
				'bytesReadyToZip' => 0,
				'bytesReadyToZipTranslated' => '0B',
				'ratio' => 0,
				'zippedBytes' => 0,
				'zippedBytesTranslated' => '0B',
				'saving' => $saving,
				'filestozip' => 0,
				'excludedfiles' => 0,
				'filesincludedinzip' => 0,
			);
		}
		
		$readableSizeZip = static::formatBytes($zipbytes);
		$readableSizeRaw = static::formatBytes($inbytes);
		
		return array(
			'status' => $status,
			'bytesReadyToZip' => $inbytes,
			'bytesReadyToZipTranslated' => $readableSizeRaw,
			'ratio' => $ratio,
			'zippedBytes' => $zipbytes,
			'zippedBytesTranslated' => $readableSizeZip,
			'saving' => $saving,
			'filestozip' => $filesIncluded,
			'excludedfiles' => $filesExcluded,
			'filesincludedinzip' => static::$zipFilesIncluded,
		);
    }	

    /**
     * @param string $path
	 * @param int 	 $pathkey
	 * @param array  $pathlist
     * @return bool/int
     */		
	private static function findPathOverlaps($findpath, $pathkey, $pathlist)
	{
		foreach ($pathlist as $key => $value) {			
			if (stripos($value, $findpath) !== false && $key !== $pathkey) {
				if (strlen($findpath) >= strlen($value)) {
					return $key;
				}
			}
		}
		return false;
	}	
	
    /**
     * @param int $bytes
     * @return string
     */	
	public static function formatBytes($bytes, $precision = 2) {
		$formatBytes = "0B";
		if ($bytes && $bytes > 0) {
			$units = array('B', 'KB', 'MB', 'GB', 'TB');
			$bytes = abs($bytes);
			$base = log($bytes) / log(1024);
			$formatBytes = round(pow(1024, $base - floor($base)), $precision) . $units[floor($base)];
		}
		return $formatBytes;
	}

    /**
     * @param float $time
     * @return string
     */	
	public static function formatTime($time) {
		$formatTime = "";
		if ($time && $time > 0) {
			if ($time < 0.001) {
				$formatTime = round($time * 1000000) . 'μs';
			} elseif ($time < 1) {
				$formatTime = round($time * 1000, 2) . 'ms';
			} else {
				$formatTime = round($time, 2) . 's';
			}				
		}
		return $formatTime;
	}
	
	
	private static function getInflector() {
		if (!static::$inflector) {
			static::$inflector = new Inflector();
		}
		return static::$inflector;
	}
	
    /**
	 * Get Site ID
	 *
     * @return string
	 *
     */	
	private static function getSiteID() {		
		$name = substr(strip_tags(Grav::instance()['config']->get('site.title', basename(GRAV_ROOT))), 0, 20);		
		$site_id = trim(static::getInflector()->hyphenize($name), '-') . '-';
		return $site_id;
	}
		
    /**
	 * Storage Stats
	 * We only take care of default GRAV backup folder
	 *
     * @return array
	 *
     */	
	public static function storageUsed($scope = null) {
		$backupstore = Grav::instance()['locator']->findResource('backup://', true);
		$bytes_used = 0;
        if (!$backupstore) {
			return 0;
		}
		else {
			$site_id = static::getSiteID();
			$files = glob("$backupstore/{$site_id}*.zip");
			foreach($files as $t) {
				// Just in case
				if ($t <> "." && $t <> ".." && is_file($t)) {
					$bytes_used += filesize($t);					
				}   
			}
		}
		return $bytes_used;
	}

	public static function storageFilesByContext($scope = null, $days = null, $tests = null) {
		$backupstore = Grav::instance()['locator']->findResource('backup://', true);
		$count = 0;
		$timelimit = null;
        if (!$backupstore) {
			return 0;
		}
		else {
			$site_id = static::getSiteID();
			$files = null;
			if ($scope && in_array((string)$scope, static::$backupScopes)) {
				$files = glob("$backupstore/{$site_id}*partial-{$scope}.[zZ][iI][pP]");
			}
			elseif ($scope && (string)$scope === 'partial') {
				$files = glob("$backupstore/{$site_id}*partial*.[zZ][iI][pP]");
			} 			
			elseif ($scope && (string)$scope === 'failed') {
				$files = glob("$backupstore/{$site_id}*.*[0123456789]");
			} 						
			else {
				$files = glob("$backupstore/{$site_id}*.[zZ][iI][pP]");
			}
			//$files = glob("$backupstore/{$site_id}*.[zZ][iI][pP]");
			
			if ($days && intval($days) > 0) {
				$timelimit = time() - (60 * 60 * 24 * $days);
			}
			if ($files && count($files) > 0) {
				usort($files, function($a, $b) {
					// Sort latest to oldest
					return filemtime($a) < filemtime($b);
				});	
				foreach($files as $t) {
					// Just in case
					if ($t <> "." && $t <> ".." && is_file($t)) {
						$filebase = basename(strtolower($t), '.zip');
						if ($tests === null && stripos($filebase, '-testmode') !== false) {
							continue;
						}
						if ($tests === true && stripos($filebase, '-testmode') === false) {
							continue;
						}
						
						if ($timelimit) {
							if (filemtime($t) > $timelimit) {
								$count++;
							}
						}
						else {
							$count++;
						}
					}
				}
			}
		}
		return $count;
	}
	
	public static function storageLatestByContext($limit = 20, $scope = null, $days = null, $tests = null) {
		$backupstore = Grav::instance()['locator']->findResource('backup://', true);
		$count = 0;
		$timelimit = null;
		$latest = array();
        if (!$backupstore) {
			return 0;
		}
		else {
			$site_id = static::getSiteID();
			$files = null;
			if ($scope && in_array((string)$scope, static::$backupScopes)) {
				$files = glob("$backupstore/{$site_id}*partial-{$scope}.[zZ][iI][pP]");
			}
			elseif ($scope && (string)$scope === 'partial') {
				$files = glob("$backupstore/{$site_id}*partial*.[zZ][iI][pP]");
			} 			
			elseif ($scope && (string)$scope === 'failed') {
				$files = glob("$backupstore/{$site_id}*.*[0123456789]");
			} 						
			else {
				$files = glob("$backupstore/{$site_id}*.[zZ][iI][pP]");
			}
			//$files = glob("$backupstore/{$site_id}*.[zZ][iI][pP]");
			
			if ($days && intval($days) > 0) {
				$timelimit = time() - (60 * 60 * 24 * $days);
			}
			if ($files && count($files) > 0) {
				usort($files, function($a, $b) {
					// Sort latest to oldest
					return filemtime($a) < filemtime($b);
				});	
				foreach($files as $t) {
					// Just in case
					if ($t <> "." && $t <> ".." && is_file($t)) {
						$filebase = basename(strtolower($t), '.zip');
						if ($tests === null && stripos($filebase, '-testmode') !== false) {
							continue;
						}
						if ($tests === true && stripos($filebase, '-testmode') === false) {
							continue;
						}
						
						if ($timelimit) {
							if (filemtime($t) > $timelimit) {
								if ($count <= $limit) {
									$latest[] = $t;
								}
								$count++;
							}
						}
						else {
							if ($count <= $limit) {
								$latest[] = $t;
							}
							$count++;
						}
					}
				}
			}
		}
		return $latest;

	}
	
    /**
     * Renders phpinfo
     *
     * @return string The phpinfo() output
     */
    private static function phpinfo()
    {
        if (function_exists('phpinfo')) {
            ob_start();
            phpinfo();
            $pinfo = ob_get_contents();
            ob_end_clean();
            return $pinfo;
        } else {
            return 'phpinfo() method is not available on this server.';
        }
    }
	
    /**
	 * @param bool $formatted
     * @param int $precision
     * @return array
     */	
	public static function processReflector($precision = 2) {
		
		$backupDuration = static::getBackupDuration();
		$fifoStoreStats = static::$fifoStoreStatus;
		
		$zipFileStats = static::zipFileStats($precision);
		return array(
			'runInTestMode' => static::$runInTestMode,
			'currentScope' => static::$configScope,
			'backupScopes' => static::$backupScopes,
			'ignoreTopLevel' => static::$ignorePaths,
			'ignoreFolders' => static::$ignoreFolders,				
			'ignoreFileTypes' => static::$ignoreFileTypes,
			'siteIgnoreTopLevel' => static::$excludeTopLevel,
			'siteIgnoreFolders' => static::$excludeFolders,
			'addAsEmptyFolders' => static::$addAsEmptyFolders,
			'restrictFileTypes' => static::$restrictFileTypes,
			'multiplePaths' => static::$adminMultiPaths,
			'intersectSiteTopLevels' => static::$intersectTopLevels,
			'intersectSiteIgnoreFolders' => static::$intersectIgnoreFolders,
			'forceAddAsEmpty' => static::$forceaddasempty,
			'logBackup' => static::$logBackup,
			'siteBackup' => static::$isFullBackup,
			'processTimeout' => static::$backupTimeout,
			'zipFileStatus' => $zipFileStats['status'],
			'assumedRatioForTests' => static::$testModeCompressionRatio,
			'bytesExcludedRaw' => static::$bytesExcluded,
			'bytesExcluded' => static::formatBytes(static::$bytesExcluded),
			'filesExcluded' => static::$filesExcluded,
			'filesToZip' => $zipFileStats['filestozip'],
			'bytesToZipRaw' => $zipFileStats['bytesReadyToZip'],
			'bytesToZip' => $zipFileStats['bytesReadyToZipTranslated'],
			'zippedBytesRaw' => $zipFileStats['zippedBytes'],
			'zippedBytes' => $zipFileStats['zippedBytesTranslated'],
			'compressionRatio' => $zipFileStats['ratio'],
			'zipSavings' => $zipFileStats['saving'],
			'excludedPathList' => static::$not_included_json,
			'backupStarttimeRaw' => $backupDuration['start'],
			'backupEndtimeRaw' => $backupDuration['end'],
			'backupDurationRaw' => $backupDuration['duration'],				
			'remainingTimeoutRaw' => $backupDuration['remaining'],
			'durationBeforeSaveRaw' => static::$elapsedDurationBeforeSave,
			'backupDuration' => $backupDuration['durationformat'],				
			'remainingTimeout' => $backupDuration['remainingformat'],
			'durationBeforeSave' => $backupDuration['elapsedbeforesaveformat'],
			'keepDays' => static::$keepDays,
			'backupPath' => static::$backupDestination,
			'storageCapacityRaw' => $fifoStoreStats['maxSize'],
			'capacityUsedRaw' => $fifoStoreStats['backupCapacityUsed'],
			'storageFilesDeleted' => $fifoStoreStats['filesDeleted'],
			'storagedPurgedBytesRaw' => $fifoStoreStats['purged'],
			'storageFilesExceededMaxDays' => $fifoStoreStats['purgedFilesExceededMaxDay'],
			'storageFilesExceededMaxCapacity' => $fifoStoreStats['purgedFilesExceededMaxCapacity'],
			'storageLastCheckRaw' => $fifoStoreStats['time'],
			'storageCapacity' => $fifoStoreStats['maxSizeTranslated'],
			'capacityUsed' => $fifoStoreStats['backupCapacityUsedTranslated'],
			'storagePurgedBytes' => $fifoStoreStats['purgedTranslated'],
			'storageLastCheck' => $fifoStoreStats['timeTranslated'],
		);
	}
}
