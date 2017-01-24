<?php
namespace Grav\Plugin;

use Grav\Common\GPM\GPM;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Common\Uri;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Session;
use Grav\Plugin\Admin;
use Grav\Plugin\BackupManager\BackupManager;
use Grav\Plugin\Admin\Themes;

class BackupManagerPlugin extends Plugin
{

    protected $route = "backup-manager";

	/**
	* @var Uri
	*/
	protected $uri;

	/**
	* @var Base
	*/
	protected $base;

	/**
	* @var Admin_Route
	*/
	protected $admin_route;

	/**
	* @var backup
	*/
	protected $backupmanager;

	/**
	* @var admincontroller
	*/
	protected $taskcontroller;
	
	protected $json_response = [];

	
    /**
     * @return array
     */
	public static function getSubscribedEvents() {
		return [
			'onPluginsInitialized' => [['setup', 1000],['onPluginsInitialized', 0]],
		];
	}

	/**
	* If the admin path matches, initialize the Login plugin configuration and set the admin
	* as active.
	*/
	public function setup()
	{
		require_once __DIR__ . '/classes/backupmanager.php';
		require_once __DIR__ . '/classes/BackupManagerZip.php';

		$route = $this->config->get('plugins.admin.route');
		if (!$route) {
			return;
		}

		$this->base = '/' . trim($route, '/');
		$this->admin_route = rtrim($this->grav['pages']->base(), '/') . $this->base;
		$this->uri = $this->grav['uri'];

		// Only activate admin if we're inside the admin path.
		if ($this->isAdminPath()) {
			$this->active = true;
		}
		
	}
	
    /**
     * Initialize configuration
     */
    public function onPluginsInitialized()
    {
        if (!$this->isAdmin()) {
            $this->active = false;
            return;
        }

        $this->enable([
			'onPagesInitialized' => ['onAdminPagesInitialized', 1001],          			
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],			
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onAdminMenu' => ['onAdminMenu', 0],
			
        ]);
        if (empty($this->template)) {
            $this->template = 'backup-manager';
        }
		
		$this->backupmanager = new BackupManager($this->grav, $this->admin_route, $this->template, $this->route);
		$this->backupmanager->json_response = [];
		$this->grav['backupmanager'] = $this->backupmanager;
    }

    /**
     * Add plugin templates path
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }
			
	public function onAdminPagesInitialized()
	{

		// Make local copy of POST.
		$post = !empty($_POST) ? $_POST : [];

		// Handle tasks.
		$task = !empty($post['backuptask']) ? $post['backuptask'] : $this->uri->param('backuptask');
		//if ($task && $task == "backup") {
		if ($task) {			
			// Set original route for the home page.
			$home = '/' . trim($this->config->get('system.home.alias'), '/');
		  
			/** @var Pages $pages */
			$pages = $this->grav['pages'];

			$this->grav['backupmanager']->routes = $pages->routes();

			// Remove default route from routes.
			if (isset($this->grav['backupmanager']->routes['/'])) {
				unset($this->grav['backupmanager']->routes['/']);
			}
			$page = $pages->dispatch('/', true);

			// If page is null, the default page does not exist, and we cannot route to it
			if ($page) {
				$page->route($home);
			}
			
			$this->backupmanager->execute($task, $post);
			
			if ( $this->backupmanager->json_response ) {
				echo json_encode($this->backupmanager->json_response);
				exit();
			}
		} 
		else {    
		}	
	}	
	
	public function onTwigSiteVariables() {
		$this->grav['assets']
			->add('plugin://backup-manager/assets/css/backup-manager.css');				
		$this->grav['assets']
			->add('plugin://backup-manager/assets/js/chartist.min.js');				
		$this->grav['assets']
			->add('plugin://backup-manager/assets/js/toastr.min.js');				
		$this->grav['assets']
			->add('plugin://backup-manager/assets/js/backup-manager.js');							
		$twig = $this->grav['twig'];

		$twig->twig_vars['backupmanager'] = $this->backupmanager;			
	}
	
    /**
     * Add navigation item to the admin plugin
     */
    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['Backup'] = ['route' => $this->route, 'icon' => ' fa-battery-3'];
    }
	
    /**
     * Check if the current route is under the admin path
     *
     * @return bool
     */
    public function isAdminPath()
    {
        if ($this->uri->route() == $this->base || substr($this->uri->route(), 0,
                strlen($this->base) + 1) == $this->base . '/'
        ) {
            return true;
        }

        return false;
    }
	
	
}
