<?php

/**
 * This file is part of the DTForce Nette-Doctrine extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\DoctrineExtension\Contract;

/**
 * Interface IEntitySourceProvider is used to mark extensions extending entity look-up space. Used by
 * {@see DTForce\DoctrineExtension\DI\DoctrineExtension}.
 *
 * @package DTForce\DoctrineExtension\Contract
 */
interface IEntitySourceProvider
{

	/**
	 * Returns list of folders used to look for entities. Key of
	 * the returned array is ignored.
	 * @return array
	 */
	function getEntityFolderMappings();
}
