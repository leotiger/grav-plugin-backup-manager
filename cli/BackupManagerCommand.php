<?php
namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\JsonFile;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Grav\Plugin\BackupManager\BackupManager;
use Grav\Plugin\BackupManager\BackupManagerZip;

class BackupManagerCommand extends ConsoleCommand
{
    /** @var string $source */
    protected $source;

    /** @var ProgressBar $progress */
    protected $progress;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("backup")
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Where to store the backup (/backup is default)'
            )
            ->addArgument(
                'origins',
                InputArgument::IS_ARRAY|InputArgument::OPTIONAL,
                'Admin scope only: You can specify several origin paths for a backup (/backup is not allowed). Example: /user/config/plugins/'
            )
            ->addOption(
				'scope', 
				's', 
				InputOption::VALUE_OPTIONAL, 
				'You can specify working contexts like admin, config, pages, user, media, images, purge. Please see the documentation for a list of all options.'
			)
            ->addOption(
				'runastest', 
				'T', 
				InputOption::VALUE_NONE, 
				'Activate to run backup in test mode.'
			)
            ->addOption(
				'forceexec', 
				'F', 
				InputOption::VALUE_NONE, 
				'Activate to run backup in execution mode to bypass plugins.backup-manager.backup test mode setting if set.'
			)
            ->addOption(
				'ignorepaths', 
				'i', 
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
				'Specify a set of GRAV Instance root folders you want to ignore in this backup.'
			)
            ->addOption(
				'ignorefolders', 
				'I', 
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
				'Specify folder names you want to exclude whereever they show up in the GRAV instance tree.'
			)
            ->addOption(
				'ignoreempty', 
				'e', 
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
				'Specify folder names you want to add as empty folders with one level of empty subfolders below.'
			)
            ->addOption(
				'ignoretypes', 
				't', 
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
				'Specify file types you want to ignore during the backup process.'
			)
            ->addOption(
				'restricttypes', 
				'r', 
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'Specify file types to include. Also used to override default types for the media, images and audiovisual scope. Deactivates ignoretypes option.'
			)
            ->addOption(
				'intersectpaths', 
				'p', 
				InputOption::VALUE_NONE, 
				'Allows you to override default top level paths by not including them in your ignorepaths array.'
			)
            ->addOption(
				'intersectfolders', 
				'P', 
				InputOption::VALUE_NONE, 
				'Allows you to override default ignore folders by not including them in your ignorefolders array.'
			)
            ->addOption(
				'disableforceaddempty', 
				'f', 
				InputOption::VALUE_NONE, 
				'Allows you to disable adding specified folders as empty folders by force when this option is enabled in plugins.backup-manager.backup and subtitutes with your definition for empty folders. Only applies if a scope is defined.'
			)
            ->addOption(
				'caseinsensitive', 
				'X', 
				InputOption::VALUE_NONE, 
				'Runs backup in without case sensitivity for files and folders.'
			)
            ->addOption(
				'timeout', 
				'M', 
				InputOption::VALUE_OPTIONAL, 
				'Modify default timeout of 600 seconds. Integer value has to fall into the range from 60 to 1800.'
			)
            ->addOption(
				'capacity', 
				'C', 
				InputOption::VALUE_OPTIONAL, 
				'Set the capacity for the backup directory. Only applied for default GRAV /backup directory. Unspecified or 0 disables the handler.'
			)
            ->addOption(
				'keepdays', 
				'D', 
				InputOption::VALUE_OPTIONAL, 
				'Specify the number of days you want to keep backups. Only applied if backup storage uses the default GRAV /backup directory. Unspecified or 0 disables this option.'
			)
            ->addOption(
				'testratio', 
				'R', 
				InputOption::VALUE_OPTIONAL, 
				'A float value to set a compression rate for backups which operate in test mode. Defaults internally to 1.2 and accepts values from 1 to 5.'
			)
            ->addOption(
				'logstatus', 
				'L', 
				InputOption::VALUE_NONE, 
				'Allows you to place hints for folders you added as empty folders and to place a file with status information inside of the backup zip file.'
			)
            ->setDescription("Creates all kind of backups of the Grav instance")
            ->setHelp('<info>Backup</info> creates a zipped backup. Optionally it can be saved in a different destination. Scopes allow you to decide what to backup. The admin scope allows you to define up to 10 origin paths.');

        $this->source = getcwd();
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
		
        //spl_autoload_register(function ($class) {
            //if (Utils::startsWith($class, 'Grav\Plugin\BackupManager')) {
        //require_once __DIR__ .'/../classes/backupmanagerzip.php';
        //require_once __DIR__ . '/../vendor/autoload.php';
		//$autoload = Grav::instance()['locator']->findResource('plugins://backup-manager/classes/backupmanagerzip.php');
        //require_once($autoload);		
		
            //}
        //});
		
        $this->progress = new ProgressBar($this->output);
        $this->progress->setFormat('Archiving <cyan>%current%</cyan> files [<green>%bar%</green>] %elapsed:6s% %memory:6s%');

        Grav::instance()['config']->init();

        $destination = ($this->input->getArgument('destination')) ? $this->input->getArgument('destination') : null;

		$scope = ($this->input->getOption('scope')) ? $this->input->getOption('scope') : null;
		//$testmode = ($this->input->getOption('testmode')) ? $this->input->getOption('testmode') : null;
		
		$variables = [];
		
		$origins = ($this->input->getArgument('origins')) ? $this->input->getOption('origins') : null;
		if (!is_null($origins) && count($origins) > 0) {
			$origins = array_map('trim', $origins);
			$variables['origins'] = $origins;
		}
		
		
		if ($this->input->getOption('ignorepaths')) {
			$ignorepaths = $this->input->getOption('ignorepaths');
			if (!is_null($ignorepath) && count($ignorepaths) > 0) {
				$ignorepaths = array_filter(array_map('trim', $ignorepaths), 
					function($val) {
						$val = trim($val, DS);
						if (stripos($val, DS) !== false) {
							$val = basename($val);
						}
						return $val;
					}
				);
				$variables['excludeTopLevel'] = $ignorepaths;
			}
		}
				
		if ($this->input->getOption('ignorefolders')) {
			$ignorefolders = $this->input->getOption('ignorefolders');			
			if (!is_null($ignorefolders) && count($ignorefolders) > 0) {
				$ignorefolders = array_filter(array_map('trim', $ignorefolders), 
					function($val) {
						$val = trim($val, DS);
						if (stripos($val, DS) !== false) {
							$val = basename($val);
						}
						return $val;
					}
				);
				$variables['excludeFolders'] = $ignorefolders;			
			}
		}
		
		if ($this->input->getOption('ignoreempty')) {
			$ignoreempty = $this->input->getOption('ignoreempty');
			if (!is_null($ignoreempty) && count($ignoreempty) > 0) {
				$ignoreempty = array_filter(array_map('trim', $ignoreempty), 
					function($val) {
						$val = trim($val, DS);
						if (stripos($val, DS) !== false) {
							$val = basename($val);
						}
						return $val;
					}
				);
				$variables['excludeAsEmptyFolders'] = $ignoreempty;							
			}
		}
		
		if ($this->input->getOption('ignoretypes')) {
			$ignoretypes = $this->input->getOption('ignoretypes');
			if (count($ignoretypes) > 0) {
				$ignoretypes = array_filter(array_map('trim', $ignoretypes), 
					function($val) {
						$val = trim($val, '.');
						return $val;
					}
				);
				$variables['excludeFileTypes'] = $ignoretypes;			
			}
		}

		if ($this->input->getOption('restricttypes')) {
			$restricttypes = $this->input->getOption('restricttypes');
			if (count($restricttypes) > 0) {
				$restricttypes = array_filter(array_map('trim', $restricttypes), 
					function($val) {
						$val = trim($val, '.');
						return $val;
					}
				);
				$variables['restricttypes'] = $restricttypes;			
			}
		}
		
		if ($this->input->getOption('intersectpaths')) {
			$variables['intersectTopFolders'] = (bool)$this->input->getOption('intersectpaths');
		}
		if ($this->input->getOption('intersectfolders')) {
			$variables['intersectFolders'] = (bool)$this->input->getOption('intersectfolders');
		}

		if ($this->input->getOption('runastest')) {
			$variables['runastest'] = (bool)$this->input->getOption('runastest');
		}
		if ($this->input->getOption('forceexec')) {
			$variables['forceexec'] = (bool)$this->input->getOption('forceexec');
		}
		
		if ($this->input->getOption('disableforceaddempty')) {
			$variables['disableforceaddempty'] = (bool)$this->input->getOption('disableforceaddempty');
		}
						
		if ($this->input->getOption('caseinsensitive')) {
			$variables['caseinsensitive'] = (bool)$this->input->getOption('caseinsensitive');
		}
				
		$timeout = ($this->input->getOption('timeout')) ? intval($this->input->getOption('timeout')) : null;
		if ($timeout && ($timeout < 60 || $timeout > 1800)) {
			$timeout = null;			
		}
		elseif ($timeout) {
			$variables['timeout'] = $timeout;			
		}
		$capacity = ($this->input->getOption('capacity')) ? intval($this->input->getOption('capacity')) : null;
		if ($capacity && ($capacity > 2048 || $capacity < 0)) {
			// 2TB is the maximum we should handle. Force deactivate.
			$capacity = null;
		}
		elseif ($capacity) {
			$variables['maxSpace'] = $capacity;			
		}
		$keepdays = ($this->input->getOption('keepdays')) ? intval($this->input->getOption('keepdays')) : null;
		if ($keepdays && $keepdays < 0) {
			$keepdays = null;
		}	
		elseif ($keepdays) {
			$variables['keepDays'] = $keepdays;			
		}
		$testratio = ($this->input->getOption('testratio')) ? floatval($this->input->getOption('testratio')) : null;
		if ($testratio && ($testratio < 1 || $testratio > 5)) {
			$testratio = null;
		}
		elseif ($testratio) {
			$variables['testcompressionratio'] = $testratio;			
		}
		
		if ($this->input->getOption('logstatus')) {
			$variables['logstatus'] = (bool)$this->input->getOption('logstatus');
		}

		$forceaddasempty = (bool)Grav::instance()['config']->get('plugins.backup-manager.backup.ignore.forceaddasempty'); 
		
		require_once __DIR__ . '/../classes/BackupManagerZip.php';
		
		
        $backup = BackupManagerZip::backup($destination, [$this, 'output'], $scope, $variables);
		
		$log = JsonFile::instance(Grav::instance()['locator']->findResource("log://backup.log", true, true));
				
        $log->content([
            'time' => time(),
            'location' => $backup
        ]);
        $log->save();

        $this->output->writeln('');
        $this->output->writeln('');

    }

    /**
     * @param $args
     */
    public function output($args)
    {
        switch ($args['type']) {
            case 'message':
                $this->output->writeln($args['message']);
                break;
            case 'progress':
                if ($args['complete']) {
                    $this->progress->finish();
                } else {
                    $this->progress->advance();
                }
                break;
        }
    }

}

