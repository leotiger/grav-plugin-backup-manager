<?php
/**
 * BackupManager
 *
 * This file is part of the Grav BackupManager plugin. A something
 * released to the wild, wild world...
 * Minor parts, some literal portions can be found in GRAV.
 * 
 */
namespace Grav\Plugin\BackupManager;
use DateTime;
use Grav\Common\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\User\User;
use Grav\Common\GravTrait;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Language\LanguageCodes;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugins;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\JsonFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceIterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Session\Message;
use RocketTheme\Toolbox\Session\Session;
use Symfony\Component\Yaml\Yaml;
use Composer\Semver\Semver;
use PicoFeed\Reader\Reader;

class BackupManager
{
	/**
	* @var User
	*/
	public $user;

	/**
	* @var Grav
	*/
	public $grav;

	public $route;

	/**
	* @var Session
	*/
	protected $session;

	/**
	* @var array
	*/
	public $json_response;

	/**
	* @var admin
	*/
	public $backupmanager;

	/**
	* @var array
	*/
	protected $post;

	/**
	* @var string
	*/
	protected $task;
	
	/**
	* @var bool/null
	*/
	public $testmode;
	/** -------------
	* Public methods
	* --------------
	*/

	/**
	* Constructor
	*
	* @param [type] $config [description]
	*/
	public function __construct(Grav $grav, $base, $location, $route)
	{
		// Initialize Service class
		$this->grav = $grav;
		
		$this->config = $this->grav['config'];
		//$this->backupmanager = $this->grav['backupmanager'];
		
		$this->base = $base;
		$this->route = $route;
		$this->user = $this->grav['user'];
		$this->session     = $this->grav['session'];
		$this->uri         = $this->grav['uri'];
		$this->testmode = $this->grav['config']->get('plugins.backup-manager.backup.testmode.enabled') ? true : false;				
	}

    /**
     * Get current session.
     *
     * @return Session
     */
    public function session()
    {
        return $this->session;
    }
  
	/**
	* Add message into the session queue.
	*
	* @param string $msg
	* @param string $type
	*/
	public function setMessage($msg, $type = 'info')
	{
		/** @var Message $messages */
		$messages = $this->grav['messages'];
		$messages->add($msg, $type);
	}
	
	/**
	* Fetch and delete messages from the session queue.
	*
	* @param string $type
	*
	* @return array
	*/
	public function messages($type = null)
	{
		/** @var Message $messages */
		$messages = $this->grav['messages'];

		return $messages->fetch($type);
	}

	/**
	* Checks user authorisation to the action.
	*
	* @param  string $action
	*
	* @return bool
	*/
	public function authorize($action = 'admin.login')
	{
		$action = (array)$action;

		foreach ($action as $a) {
			if ($this->user->authorize($a)) {
				return true;
			}
		}
		return false;
	}
	
	
	/**
	* Translate a string to the user-defined language
	*
	* Another of those functions, copied over
	* 20% of the code basis is GRAV
	* Nothing to wonder about... it's GRAV
	*
	* @param array|mixed $args
	*
	* @param mixed       $languages
	*
	* @return string
	*/
	public function translate($args, $languages = null)
	{
		if (is_array($args)) {
			$lookup = array_shift($args);
		} else {
			$lookup = $args;
			$args = [];
		}

		if (!$languages) {
			$languages = [$this->grav['user']->authenticated ? $this->grav['user']->language : 'en'];
		} else {
			$languages = (array)$languages;
		}


		if ($lookup) {
			if (empty($languages) || reset($languages) == null) {
				if ($this->grav['config']->get('system.languages.translations_fallback', true)) {
					$languages = $this->grav['language']->getFallbackLanguages();
				} else {
					$languages = (array)$this->grav['language']->getDefault();
				}
			}
		}

		foreach ((array)$languages as $lang) {
			$translation = $this->grav['language']->getTranslation($lang, $lookup);

			if (!$translation) {
				$language = $this->grav['language']->getDefault() ?: 'en';
				$translation = $this->grav['language']->getTranslation($language, $lookup);
			}

			if (!$translation) {
				$language = 'en';
				$translation = $this->grav['language']->getTranslation($language, $lookup);
			}

			if ($translation) {
				if (count($args) >= 1) {
					return vsprintf($translation, $args);
				} else {
					return $translation;
				}
			}
		}
		return $lookup;
	}
	
    /**
     * Search in the logs when was the latest backup made
     *
     * @return array Array containing the latest backup information
     */
    public function lastBackup()
    {
		$files = BackupManagerZip::storageFilesByContext('site');
		if ($files === 0) {
			$log = $this->grav['locator']->findResource("log://backup.log");
			if (file_exists($log)) {
				unlink($log);
			}
		}
		
        $file    = JsonFile::instance($this->grav['locator']->findResource("log://backup.log"));
        $content = $file->content();
		$label = $this->translate("PLUGIN_BACKUP_MANAGER.PLEASE_BACKUP");
		//$this->translate("PLUGIN_BACKUP_MANAGER.LAST_BACKUP_UNKOWN"),
        if (empty($content)) {
            return [
                'days'        => "&#x221e;",
                'chart_fill'  => 0,
                'chart_empty' => 100,
				'dayslabel' => $label
            ];
        }
        $backup = new \DateTime();
        $backup->setTimestamp($content['time']);
        $diff = $backup->diff(new \DateTime());

        $days       = $diff->days;
		if ($days > 6) {
			
            return [
                'days'        => $this->translate("PLUGIN_BACKUP_MANAGER.SEVENPLUSDAYS"),
                'chart_fill'  => 0,
                'chart_empty' => 100,
				'dayslabel' => $label
            ];			
		}
		else {
			$chart_empty = round((((($days + 7) / 7) - 1) * 100), 2);
			if ($days == 0) {
				$days = $this->translate("PLUGIN_BACKUP_MANAGER.LAST_24H");
				$label = $this->translate("PLUGIN_BACKUP_MANAGER.TODAY");
			}
			elseif ($days == 1) {
				$label = $this->translate("PLUGIN_BACKUP_MANAGER.DAY");
			}
			else {
				$label = $this->translate("PLUGIN_BACKUP_MANAGER.DAYS");				
			}
			return [
				'days'        => $days,
				'chart_fill'  => 100 - $chart_empty,
				'chart_empty' => $chart_empty,
				'dayslabel' => $label
			];			
		}
    }

    /**
     * Obtain information about the backup store
     *
     * @return array Array containing the latest backup storage information
     */
    public function storeStatus()
    {
		$capacity = $this->config->get('plugins.backup-manager.backup.storage.maxspace', 0);
		if ($capacity) {
			$capacity = intval($capacity)*pow(1024,3);
			$used = BackupManagerZip::storageUsed();
			$filled = round($used / $capacity * 100, 2);
			$empty = 100;
			$battery = 0;
			if ($filled < 100) {
				$empty = 100 - $filled;
				$battery = (floor($filled * 4) / 4) - 1;
			}
			else {
				$empty = 0;
				$battery = 4;
			}
			$battery = 0;
			$capacityfriendly = BackupManagerZip::formatBytes($capacity, 1);
			$usedfriendly = BackupManagerZip::formatBytes($used, 1);
			return [
				'used'        => $usedfriendly,
				'chart_fill'  => $filled,
				'chart_empty' => $empty,
				'capacity' => $capacityfriendly,
				'battery' => $battery
				
			];					
		}
		else {
			$capacity = $this->admin->translate("PLUGIN_BACKUP_MANAGER.STORAGE_UNMANAGED");
			return [
				'days'        => 0,
				'chart_fill'  => 0,
				'chart_empty' => 100,
				'capacity' => $capacity,
				'battery' => 0,
			];		
			
		}		
    }
	
	/**
	* Next functions essentially the same calling for optimization :-)
	*
	*/
	public function getPeriod($days = 7) {
		$files = BackupManagerZip::storageFilesByContext('site', $days);
		return $files;
	}

	
	public function getPartials() {
		$files = BackupManagerZip::storageFilesByContext('partial');
		return $files;
	}
	
	public function getInstance() {
		$files = BackupManagerZip::storageFilesByContext('site');
		if ($files == 0) {
			$log = $this->grav['locator']->findResource("log://backup.log");
			if (file_exists($log)) {
				unlink($log);
			}
		}		
		return $files;
	}

	public function getTests() {
		$files = BackupManagerZip::storageFilesByContext(null, null, true);
		return $files;
	}
	
	public function getAll() {
		$files = BackupManagerZip::storageFilesByContext(null, null, 'all');
		return $files;
	}
	
	public function getFailed() {
		$files = BackupManagerZip::storageFilesByContext('failed');
		return $files;
	}
	
    /**
     * Performs a task.
     *
     * @return bool True if the action was performed successfully.
     */
    public function execute($task, $post)
    {	
		$this->task = $task;
		$this->post = $post;
        if (!$this->validateNonce()) {
            return false;
        }
		
        $method = 'task' . ucfirst($this->task);

        if (method_exists($this, $method)) {
            try {
                $success = call_user_func([$this, $method]);
            } catch (\RuntimeException $e) {
                $success = true;
                $this->setMessage($e->getMessage(), 'error');
            }
        }
        return $success;
    }
	
    protected function validateNonce()
    {
        if (method_exists('Grav\Common\Utils', 'getNonce')) {
            if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
                if (isset($this->post['backup-nonce'])) {
                    $nonce = $this->post['backup-nonce'];
                } else {
                    $nonce = $this->grav['uri']->param('backup-nonce');
                }

                if (!$nonce || !Utils::verifyNonce($nonce, 'backup-form')) {
                    if ($this->task == 'addmedia') {

                        $message = sprintf($this->translate('PLUGIN_ADMIN.FILE_TOO_LARGE', null),
                            ini_get('post_max_size'));

                        //In this case it's more likely that the image is too big than POST can handle. Show message
                        $this->json_response = [
                            'status'  => 'error',
                            'message' => $message
                        ];

                        return false;
                    }

                    $this->setMessage($this->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN'), 'error');
                    $this->json_response = [
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN')
                    ];

                    return false;
                }
                unset($this->post['backup-nonce']);
            } else {
				$nonce = $this->grav['uri']->param('backup-nonce');
				if (!isset($nonce) || !Utils::verifyNonce($nonce, 'backup-form')) {
					$this->setMessage($this->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN'),
						'error');
					$this->json_response = [
						'status'  => 'error',
						'message' => $this->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN')
					];

					return false;
				}
            }
        }
        return true;
    }

    /**
     * Sets the page redirect.
     *
     * @param string $path The path to redirect to
     * @param int    $code The HTTP redirect code
     */
    public function setRedirect($path, $code = 303)
    {
        $this->redirect     = $path;
        $this->redirectCode = $code;
    }

    /**
     * Checks if the user is allowed to perform the given task with its associated permissions
     *
     * @param string $task        The task to execute
     * @param array  $permissions The permissions given
	 * @param bool   $nomessage   Supress the message delivery to allow permission testing from the inside
     *
     * @return bool True if authorized. False if not.
     */
    protected function authorizeTask($task = '', $permissions = [], $nomessage = false)
    {
        if (!$this->authorize($permissions)) {
			if (!$nomessage) {
				if ($this->grav['uri']->extension() === 'json') {
					$this->json_response = [
						'status'  => 'unauthorized',
						'message' => $this->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' ' . $task . '.'
					];
				} else {
					$this->setMessage($this->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' ' . $task . '.',
						'error');
				}
			}
            return false;
        }

        return true;
    }

    /**
     * Gets the permissions needed to access a given view
	 * Yeah, that's something we could need from time to time
     *
     * @return array An array of permissions
     */
    protected function dataPermissions()
    {
        $type        = $this->view;
        $permissions = ['admin.super'];

        switch ($type) {
            case 'configuration':
            case 'system':
                $permissions[] = 'admin.configuration';
                break;
            case 'settings':
            case 'site':
                $permissions[] = 'admin.settings';
                break;
            case 'plugins':
                $permissions[] = 'admin.plugins';
                break;
            case 'themes':
                $permissions[] = 'admin.themes';
                break;
            case 'users':
                $permissions[] = 'admin.users';
                break;
            case 'pages':
                $permissions[] = 'admin.pages';
                break;
        }

        return $permissions;
    }

    /**
     * Redirect, not necessary as well
     */
    public function redirect()
    {
        if (!$this->redirect) {
            return;
        }

        $base           = $this->base;
        $this->redirect = '/' . ltrim($this->redirect, '/');
        $multilang      = $this->isMultilang();

        $redirect = '';
        if ($multilang) {
            // if base path does not already contain the lang code, add it
            $langPrefix = '/' . $this->grav['session']->admin_lang;
            if (!Utils::startsWith($base, $langPrefix . '/')) {
                $base = $langPrefix . $base;
            }

            // now the first 4 chars of base contain the lang code.
            // if redirect path already contains the lang code, and is != than the base lang code, then use redirect path as-is
            if (Utils::pathPrefixedByLangCode($base) && Utils::pathPrefixedByLangCode($this->redirect)
                && substr($base,
                    0, 4) != substr($this->redirect, 0, 4)
            ) {
                $redirect = $this->redirect;
            } else {
                if (!Utils::startsWith($this->redirect, $base)) {
                    $this->redirect = $base . $this->redirect;
                }
            }

        } else {
            if (!Utils::startsWith($this->redirect, $base)) {
                $this->redirect = $base . $this->redirect;
            }
        }

        if (!$redirect) {
            $redirect = $this->redirect;
        }

        $this->grav->redirect($redirect, $this->redirectCode);
    }

    /**
     * POST data, needed? No.
     *
     * @param array $post
     *
     * @return array
     */
    protected function getPost($post)
    {
        unset($post['task']);

        // Decode JSON encoded fields and merge them to data.
        if (isset($post['_json'])) {
            $post = array_replace_recursive($post, $this->jsonDecode($post['_json']));
            unset($post['_json']);
        }

        $post = $this->cleanDataKeys($post);

        return $post;
    }

    /**
     * Get scoreboards
     *
     * @return array
     */
    protected function getFileStats()
    {
        $filestats = array();
		$files = BackupManagerZip::storageFilesByContext('site');
		if ($files === 0) {
			$log = $this->grav['locator']->findResource("log://backup.log");
			if (file_exists($log)) {
				unlink($log);
			}
		}
		$filestats['instance'] = $files;		
		
		$filestats['period'] = BackupManagerZip::storageFilesByContext('site', 7);	
		$filestats['partials'] = BackupManagerZip::storageFilesByContext('partial');
		$filestats['tests'] = BackupManagerZip::storageFilesByContext(null, null, true);
		$filestats['all'] = BackupManagerZip::storageFilesByContext(null, null, 'all');
		$filestats['failed'] = BackupManagerZip::storageFilesByContext('failed');
		return $filestats;
	}
	
    /**
     * Handle purge actions
     *
     * @return bool
     */
    protected function taskPurge()
    {
        $param_sep = $this->grav['config']->get('system.param_sep', ':');
        if (!$this->authorizeTask('backup', ['admin.backup-manager', 'admin.maintenance', 'admin.super'])) {
            return false;
        }
        // Get optional backup scope param
        $backup_scope = $this->grav['uri']->param('scope');
		$force_exec = $this->grav['uri']->param('force');
        try {
			$variables = null;
			if ($force_exec) {
				$variables = array('forceexec' => true);
			}
			if ($backup_scope) {
				$backup = BackupManagerZip::backup(null, null, $backup_scope, $variables);
			}
			else {
				$backup = BackupManagerZip::backup(null, null, 'purge', $variables);
			}
			
        } catch (\Exception $e) {
            $this->json_response = [
                'status'  => 'error',
                'message' => $this->translate('PLUGIN_ADMIN.AN_ERROR_OCCURRED') . '. ' . $e->getMessage()
            ];
            return true;
        }
		
		$forceUrl = "";
		$runInTestMode = $this->grav['config']->get('plugins.backup-manager.backup.testmode.enabled');				
		
		$backuptype = "";
		
		if (!$backup_scope) {
			if ($runInTestMode) { // && $this->authorizeTask('backup', ['admin.maintenance', 'admin.super']) ) {
				$forceUrl = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
					'/') . '/backup-manager.json/backuptask' . $param_sep . 'purge/force' . $param_sep . 'force/backup-nonce' . $param_sep . Utils::getNonce('backup-form');			
			}						
			$backuptype = $this->translate('PLUGIN_BACKUP_MANAGER.TASK_PURGE');
			$backup_scope = 'purge';
		}
		else {
			if ($runInTestMode) { // && $this->authorizeTask('backup', ['admin.maintenance', 'admin.super']) ) {
				$forceUrl = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
					'/') . '/backup-manager.json/backuptask' . $param_sep . 'purge/scope' . $param_sep . $backup_scope . '/force' . $param_sep . 'force/backup-nonce' . $param_sep . Utils::getNonce('backup-form');			
			}			
			$backuptype = $this->translate('PLUGIN_BACKUP_MANAGER.TASK_' . strtoupper($backup_scope));			
		}
		
		$message = "";
		if (!$runInTestMode || $force_exec) {
			$message = $this->translate('PLUGIN_BACKUP_MANAGER.PURGE_SUCCESS');
		}
		else {
			$message = $this->translate('PLUGIN_BACKUP_MANAGER.PURGE_SUCCESS_TESTMODE');
		}

		$log = JsonFile::instance($this->grav['locator']->findResource("log://backup/last-{$backup_scope}-raw.log", true));
		
		$last = $log->content();
		$storestatus = $this->storeStatus();

		
		$filestats = $this->getFileStats();
		$lastBackup = $this->lastBackup();
		
        $this->json_response = [
            'status'  => 'success',
            'message' => $message,
			'backuptype' => $backuptype,
			'last'	  => $last,
			'storestatus' => $storestatus,
			'urlzip'  => '',
			'forcepurge'  => $forceUrl,
			'filestats' => $filestats,
			'lastbackup' => $lastBackup,
            'toastr'  => [
                'timeOut'           => "0",
                'extendedTimeOut'   => "0",
                'closeButton'       => true,
				'newestOnTop'	    => true,
				'preventDuplicates' => true
            ]
        ];

        return true;
    }		
	
	
    /**
     * Handle backup actions
     *
     * @return bool
     */
    protected function taskBackup()
    {
        $param_sep = $this->grav['config']->get('system.param_sep', ':');
        if (!$this->authorizeTask('backup', ['admin.backup-manager', 'admin.maintenance', 'admin.super'])) {
            return false;
        }
        // Get optional backup scope param
        $backup_scope = $this->grav['uri']->param('scope');
        $force_exec = $this->grav['uri']->param('force');
		
        $download = $this->grav['uri']->param('download');

        if ($download) {
            $file             = base64_decode(urldecode($download));
            $backups_root_dir = $this->grav['locator']->findResource('backup://', true);

            if (substr($file, 0, strlen($backups_root_dir)) !== $backups_root_dir) {
                header('HTTP/1.1 401 Unauthorized');
                exit();
            }

            Utils::download($file, true);
        }
		
        try {
			$variables = null;
			if ($force_exec) {
				$variables = array('forceexec' => true);
			}
			if ($backup_scope) {
				$backup = BackupManagerZip::backup(null, null, $backup_scope, $variables);
			}
			else {
				$backup = BackupManagerZip::backup(null, null, null, $variables);
			}
			
        } catch (\Exception $e) {
            $this->json_response = [
                'status'  => 'error',
                'message' => $this->translate('PLUGIN_ADMIN.AN_ERROR_OCCURRED') . '. ' . $e->getMessage()
            ];
            return true;
        }
		
		// We have to handle downloads as well if we want to offer partial backups for other user contexts
        $download = urlencode(base64_encode($backup));
        $url      = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
                '/') . '/backup-manager.json/backuptask' . $param_sep . 'backup/download' . $param_sep . $download . '/backup-nonce' . $param_sep . Utils::getNonce('backup-form');

		
		$forceUrl = "";
		$runInTestMode = $this->grav['config']->get('plugins.backup-manager.backup.testmode.enabled');
		$backuptype = "";
		if (!$backup_scope) {
			if ($runInTestMode) { // && $this->authorizeTask('backup', ['admin.maintenance', 'admin.super']) ) {
				$forceUrl = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
					'/') . '/backup-manager.json/backuptask' . $param_sep . 'backup/force' . $param_sep . 'force/backup-nonce' . $param_sep . Utils::getNonce('backup-form');			
			}			
			$backuptype = $this->translate('PLUGIN_BACKUP_MANAGER.TASK_SITE');
			$log = JsonFile::instance($this->grav['locator']->findResource("log://backup.log", true, true));
			$log->content([
				'time'     => time(),
				'location' => $backup,
			]);
			$log->save();		
		}
		else {
			if ($runInTestMode) { // && $this->authorizeTask('backup', ['admin.maintenance', 'admin.super']) ) {
				$forceUrl = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
					'/') . '/backup-manager.json/backuptask' . $param_sep . 'backup/scope' . $param_sep . $backup_scope . '/force' . $param_sep . 'force/backup-nonce' . $param_sep . Utils::getNonce('backup-form');			
			}
			$backuptype = $this->translate('PLUGIN_BACKUP_MANAGER.TASK_' . strtoupper($backup_scope));
		}
		$message = "";
		$buttondownload = $this->translate('PLUGIN_BACKUP_MANAGER.DOWNLOAD_TEST');
		if (!$runInTestMode || $force_exec) {
			$buttondownload = $this->translate('PLUGIN_BACKUP_MANAGER.BACKUP_DOWNLOAD');
			$message = $this->translate('PLUGIN_ADMIN.YOUR_BACKUP_IS_READY_FOR_DOWNLOAD') . '. <a href="' . $url . '" class="button">'
				. $this->translate('PLUGIN_ADMIN.DOWNLOAD_BACKUP') . '</a>';			
		}
		else {
			$message = $this->translate('PLUGIN_BACKUP_MANAGER.BACKUP_SUCCESS_TESTMODE');
			$message .= $this->translate('PLUGIN_BACKUP_MANAGER.DOWNLOAD_TESTMODE_HINT') . '. <a href="' . $url . '" class="button">'
				. $this->translate('PLUGIN_BACKUP_MANAGER.DOWNLOAD_BACKUP_TESTMODE') . '</a>';			
		}
		

		
		$log = JsonFile::instance($this->grav['locator']->findResource("log://backup/last-raw.log", true));
		$last = $log->content();
		$storestatus = $this->storeStatus();
		$filestats = $this->getFileStats();
		$lastBackup = $this->lastBackup();		
        $this->json_response = [
            'status'  => 'success',
            'message' => $message,
			'backuptype' => $backuptype,
			'last'	  => $last,
			'storestatus' => $storestatus,
			'urlzip'  => $url,
			'forcebackup'  => $forceUrl,
			'filestats' => $filestats,
			'downbtnlabel' => $buttondownload,
			'lastbackup' => $lastBackup,
            'toastr'  => [
                'timeOut'           => "0",
                'extendedTimeOut'   => "0",
                'closeButton'       => true,
				'newestOnTop'	    => true,
				'preventDuplicates' => true
            ]
        ];

        return true;
    }
	
    /**
	 * Latest Backups
	 * 
     * Get our latest backups and classify them a bit
	 * Real simple right now, with a limit of 50 there's a lot on the track
	 * At least limit should show up soon in the settings
	 * 
	 * return array
     */
    public function latestBackups() {
		
        $param_sep = $this->grav['config']->get('system.param_sep', ':');
		$restrict = false;
		if (!$this->authorizeTask('backup', ['admin.maintenance', 'admin.super'], true)) {
			// Is our third credential holder a admin.backup user
			$restrict = true;
		}
		
		$backupFilesToShow = $this->grav['config']->get('backup.storage.showbackups') ? $this->grav['config']->get('backup.storage.showbackups') : 50;
		$files = BackupManagerZip::storageLatestByContext($backupFilesToShow);
		$pack = array();
		foreach($files as $filekey => $file) {
			if (file_exists($file)) {
				$zipname = basename(strtolower($file), '.zip');
				$download = urlencode(base64_encode($file));
				// Uuuurgs
				$url      = rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->base,
					'/') . '/backup-manager.json/backuptask' . $param_sep . 'backup/download' . $param_sep . $download . '/backup-nonce' . $param_sep . Utils::getNonce('backup-form');				
				if ($restrict)
				{
					if (stripos($zipname, 'partial-pages') !== false || stripos($zipname, 'partial-media') !== false || stripos($zipname, 'partial-images') !== false) {
						$pack[] = [
							'zipurl' => $url,
							'zipname' => $zipname,
							'zipdate' => date('Y-m-d H:i:s', filemtime($file)),
							'ziptype' => stripos($file, '-partial') !== false ? $this->translate('PLUGIN_BACKUP_MANAGER.PARTIAL_BACKUPS') : $this->translate('PLUGIN_BACKUP_MANAGER.SITE_BACKUPS'),
							'zipsize' => BackupManagerZip::formatBytes(filesize($file), 0),					
						];						
					}
				}
				else {
					$pack[] = [
						'zipurl' => $url,
						'zipname' => $zipname,
						'zipdate' => date('Y-m-d H:i:s', filemtime($file)),
						'ziptype' => stripos($file, '-partial') !== false ? $this->translate('PLUGIN_BACKUP_MANAGER.PARTIAL_BACKUPS') : $this->translate('PLUGIN_BACKUP_MANAGER.SITE_BACKUPS'),
						'zipsize' => BackupManagerZip::formatBytes(filesize($file), 0),					
					];
				}
			}
		}
		return $pack;
    }
	
}
