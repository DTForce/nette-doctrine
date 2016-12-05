<?php

/**
 * This file is part of the DTForce Nette-Doctrine extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\DoctrineExtension\DI;

use Doctrine\ORM\Events;
use DTForce\DoctrineExtension\Contract\IClassMappingProvider;
use DTForce\DoctrineExtension\Contract\IEntitySourceProvider;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;
use Nette\InvalidStateException;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\Validators;


/**
 * Doctrine Extension for Nette.
 *
 * Sample configuration:
 * <code>
 * doctrine:
 * 	config:
 * 		driver: pdo_pgsql
 * 		host: localhost
 * 		port: 5432
 * 		user: username
 * 		password: password
 * 		dbname: database
 *
 * 	debug: true
 * 	prefix: doctrine.default
 * 	proxyDir: %tempDir%/cache/proxies
 * </code>
 *
 * @author Jan Mareš
 * @package DTForce\DoctrineExtension\DI
 */
class DoctrineExtension extends CompilerExtension
{

	const DOCTRINE_SQL_PANEL_FQN = 'DTForce\DoctrineExtension\Debug\DoctrineSQLPanel';
	const DOCTRINE_DEFAULT_CACHE = 'Doctrine\Common\Cache\ArrayCache';
	const KDYBY_CONSOLE_EXTENSION = 'Kdyby\Console\DI\ConsoleExtension';

	/**
	 * @var array
	 */
	public static $defaults = [
		'debug' => TRUE,

		'dbal' => [
			'type_overrides' => [],
			'types' => [],
			'schema_filter' => NULL
		],

		'prefix' => 'doctrine.default',
		'proxyDir' => '%tempDir%/cache/proxies',
		'sourceDir' => NULL,
		'ownEventManager' => FALSE,
		'targetEntityMappings' => [],
		'metadata' => [],
		'functions' => []
	];

	private $entitySources = [];

	private $classMappings = [];


	public function loadConfiguration()
	{
		$config = $this->getConfig(self::$defaults);

		$this->classMappings = $config['targetEntityMappings'];
		$this->entitySources = $config['metadata'];
		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof IEntitySourceProvider) {
				$entitySource = $extension->getEntityFolderMappings();
				Validators::assert($entitySource, 'array');
				$this->entitySources = array_merge($this->entitySources, $entitySource);
			}

			if ($extension instanceof IClassMappingProvider) {
				$entityMapping = $extension->getClassnameToClassnameMapping();
				Validators::assert($entityMapping, 'array');
				$this->classMappings = array_merge($this->classMappings, $entityMapping);
			}

		}

		if ($config['sourceDir'] !== NULL) {
			$this->entitySources[] = $config['sourceDir'];
		}

		$builder = $this->getContainerBuilder();

		$name = $config['prefix'];


		$builder->addDefinition($name . ".resolver")
				->setClass('\Doctrine\ORM\Tools\ResolveTargetEntityListener');

		$builder->addDefinition($name . ".naming")
				->setClass('\Doctrine\ORM\Mapping\UnderscoreNamingStrategy');

		$builder->addDefinition($name . ".config")
				->setClass('\Doctrine\ORM\Configuration')
				->addSetup(new Statement('setFilterSchemaAssetsExpression', [$config['dbal']['schema_filter']]));


		$builder->addDefinition($name . ".connection")
				->setClass('\Doctrine\DBAL\Connection')
				->setFactory('@' . $name . '.entityManager::getConnection');


		$builder->addDefinition($name . ".entityManager")
				->setClass('\Doctrine\ORM\EntityManager')
				->setFactory('\Doctrine\ORM\EntityManager::create', [
						$config['connection'],
						'@' . $name . '.config',
						'@Doctrine\Common\EventManager'
				]);

		if ($this->hasIBarPanelInterface()) {
			$builder->addDefinition($this->prefix($name . '.diagnosticsPanel'))
					->setClass(self::DOCTRINE_SQL_PANEL_FQN);
		}

		$this->addHelpersToKdybyConsole($builder);
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig(self::$defaults);
		$name = $config['prefix'];

		$cache = $this->getCache($name, $builder);

		$configService = $builder->getDefinition($name . ".config")
				->setFactory('\Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration', [
						array_values($this->entitySources),
						$config['debug'],
						$config['proxyDir'],
						$cache,
						FALSE
				])
				->addSetup('setNamingStrategy', ['@' . $name . '.naming']);


		foreach ($config['functions'] as $functionName => $function) {
			$configService->addSetup(
				'addCustomStringFunction',
				[
					$functionName,
					$function
				]
			);
		}


		foreach ($this->classMappings as $source => $target) {
			$builder->getDefinition($name . '.resolver')
					->addSetup('addResolveTargetEntity', [
							$source, $target, []
					]);
		}

		if($this->hasEventManager($builder)) {
			$builder->getDefinition($builder->getByType('Doctrine\Common\EventManager'))
					->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		} else {
			if($config['ownEventManager']){
				throw new InvalidStateException("Where is your own EventManager?");
			}
			$builder->addDefinition($name . ".eventManager")
					->setClass('Doctrine\Common\EventManager')
					->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		}

		$this->processDbalTypes($name, $config['dbal']['types']);
		$this->processDbalTypeOverrides($name, $config['dbal']['type_overrides']);
	}


	/**
	 * @param string $name
	 * @param array $types
	 */
	private function processDbalTypes($name, array $types)
	{
		$builder = $this->getContainerBuilder();
		$connection = $builder->getDefinition($name . '.entityManager');

		foreach ($types as $type => $className) {
			$connection->addSetup(
				'if ( ! Doctrine\DBAL\Types\Type::hasType(?)) { Doctrine\DBAL\Types\Type::addType(?, ?); }',
				[$type, $type, $className]
			);
		}
	}


	/**
	 * @param string $name
	 * @param array $types
	 */
	private function processDbalTypeOverrides($name, array $types)
	{
		$builder = $this->getContainerBuilder();
		$entityManagerDefinition = $builder->getDefinition($name . '.entityManager');

		foreach ($types as $type => $className) {
			$entityManagerDefinition->addSetup('Doctrine\DBAL\Types\Type::overrideType(?, ?);', [$type, $className]);
		}
	}


	public function afterCompile(ClassType $class)
	{
		$initialize = $class->methods['initialize'];
		if ($this->hasIBarPanelInterface()) {
			$initialize->addBody('$this->getByType(\'' . self::DOCTRINE_SQL_PANEL_FQN . '\')->bindToBar();');
		}
	}


	/**
	 * @return bool
	 */
	private function hasIBarPanelInterface()
	{
		return interface_exists('Tracy\IBarPanel');
	}


	private function addHelpersToKdybyConsole(ContainerBuilder $builder)
	{
		if(class_exists(self::KDYBY_CONSOLE_EXTENSION)){
			$helperTag = constant(self::KDYBY_CONSOLE_EXTENSION . '::HELPER_TAG');
			$builder->addDefinition($this->prefix('helper.entityManager'))
					->setClass('Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper')
					->addTag($helperTag, 'em');
		}
	}

	/**
	 * Tests whether EventManager is defined.
	 *
	 * @param ContainerBuilder $builder
	 * @return bool
	 */
	private function hasEventManager(ContainerBuilder $builder)
	{
		return strlen($builder->getByType('Doctrine\Common\EventManager')) > 0;
	}

	private function getCache($prefix, ContainerBuilder $builder){
		if(strlen($builder->getByType('Doctrine\Common\Cache\Cache')) > 0){
			return '@' . $builder->getByType('Doctrine\Common\Cache\Cache');
		} else {
			$builder->addDefinition($prefix . ".cache")
				->setClass(self::DOCTRINE_DEFAULT_CACHE);
			return '@' . $prefix . ".cache";
		}
	}
}
