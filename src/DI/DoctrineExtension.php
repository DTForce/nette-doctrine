<?php

/**
 * This file is part of the DTForce Nette-Doctrine extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\DoctrineExtension\DI;

use Doctrine\ORM\Events;
use Kdyby\Doctrine\DI\IEntityProvider;
use Kdyby\Doctrine\DI\ITargetEntityProvider;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
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


	public function loadConfiguration()
	{
		$config = $this->getConfig();

		$targetEntitiesMappings = [];
		$metadatas = [];
		$builder = $this->getContainerBuilder();
		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof IEntityProvider) {
				$metadata = $extension->getEntityMappings();
				Validators::assert($metadata, 'array');
				$metadatas = array_merge($metadatas, $metadata);
			}

			if ($extension instanceof ITargetEntityProvider) {
				$targetEntities = $extension->getTargetEntityMappings();
				Validators::assert($targetEntities, 'array');
				$targetEntitiesMappings = array_merge($targetEntitiesMappings, $targetEntities);
			}

		}

		$name = $config['prefix'];

		$builder->addDefinition($name . ".resolver")
			->setClass('\Doctrine\ORM\Tools\ResolveTargetEntityListener');

		$builder->addDefinition($name . ".naming")
			->setClass('\Doctrine\ORM\Mapping\UnderscoreNamingStrategy');

		$builder->addDefinition($name . ".config")
			->setClass('\Doctrine\ORM\Configuration')
			->setFactory('\Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration', [
				array_values($metadatas),
				$config['debug'],
				$config['proxyDir'],
				'@' . $name . '.cache',
				FALSE
			])
			->addSetup('setNamingStrategy', ['@' . $name . '.naming']);

		if($this->hasEventManager($builder)) {
			$builder->getDefinition($builder->getByType('Doctrine\Common\EventManager'))
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		} else {
			$builder->addDefinition($name . ".eventManager")
				->setClass('@Doctrine\Common\EventManager')
				->addSetup('addEventListener', [Events::loadClassMetadata, '@' . $name . '.resolver']);
		}

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

}
