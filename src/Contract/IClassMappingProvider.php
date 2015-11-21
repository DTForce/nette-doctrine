<?php

/**
 * This file is part of the DTForce Nette-Doctrine extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\DoctrineExtension\Contract;

/**
 * Interface IClassMappingProvider is used to create mapping between annotated entity classes and really instantiated
 * entity classes. Useful to be able to use interfaces in annotations instead of real implementations.
 *
 * {@see DTForce\DoctrineExtension\DI\DoctrineExtension}.
 *
 * @package DTForce\DoctrineExtension\Contract
 */
interface IClassMappingProvider
{

	/**
	 * Array mapping class used in annotations to class actually instantiated.
	 *
	 * @return array
	 */
	function getClassnameToClassnameMapping();
}
