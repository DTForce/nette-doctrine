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

	/**
	 * @var array
	 */
	public static $defaults = [
		'debug' => TRUE,
		'prefix' => 'doctrine.default',
		'proxyDir' => '%tempDir%/cache/proxies',
		'ownEventManager' => FALSE,
		'targetEntityMappings' => [],
		'metadata' => []
	];

	public function loadConfiguration()
	{
		$config = $this->getConfig(self::$defaults);

		$targetEntitiesMappings = $config['targetEntityMappings'];
		$metadatas = $config['metadata'];
		$builder = $this->getContainerBuilder();
		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof IEntitySourceProvider) {
				$metadata = $extension->getEntityFolderMappings();
				Validators::assert($metadata, 'array');
				$metadatas = array_merge($metadatas, $metadata);
			}

			if ($extension instanceof IClassMappingProvider) {
				$targetEntities = $extension->getClassnameToClassnameMapping();
				Validators::assert($targetEntities, 'array');
				$targetEntitiesMappings = array_merge($targetEntitiesMappings, $targetEntities);
			}

		}

		$name = $config['prefix'];

		$builder->addDefinition($name . ".resolver")
			->setClass('\Doctrine\ORM\Tools\ResolveTargetEntityListener');

		$builder->addDefinition($name . ".naming")
			->setClass('\Doctrine\ORM\Mapping\UnderscoreNamingStrategy');

		$cache = $this->getCache($name, $builder);

		$builder->addDefinition($name . ".config")
			->setClass('\Doctrine\ORM\Configuration')
			->setFactory('\Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration', [
				array_values($metadatas),
				$config['debug'],
				$config['proxyDir'],
				$cache,
				FALSE
			])
			->addSetup('setNamingStrategy', ['@' . $name . '.naming']);


		$builder->addDefinition($name . ".entityManager")
			->setClass('\Doctrine\ORM\EntityManager')
			->setFactory('\Doctrine\ORM\EntityManager::create', [
				$config['config'],
				'@' . $name . '.config',
				'@Doctrine\Common\EventManager'
			]);

		if ($this->hasIBarPanelInterface()) {
			$builder->addDefinition($this->prefix($name . '.diagnosticsPanel'))
				->setClass(self::DOCTRINE_SQL_PANEL_FQN);
		}

		$builder->addDefinition($name . ".connection")
			->setClass('\Doctrine\DBAL\Connection')
			->setFactory('@' . $name . '.entityManager::getConnection');

		foreach ($targetEntitiesMappings as $source => $target) {
			$builder->getDefinition($name . '.resolver')
				->addSetup('addResolveTargetEntity', [
					$source, $target, []
				]);
		}

	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig(self::$defaults);
		$name = $config['prefix'];

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
