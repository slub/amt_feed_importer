<?php 

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Krzysztof Kasprzyca <k.kasprzyca@amtsolution.pl>, AMT Solution
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
namespace AMT\AmtFeedImporter\Task;

use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

abstract class AbstractFeedAdditionalFieldProvider implements AdditionalFieldProviderInterface {
	/**
	 * @param array $taskInfo reference to array with information about provided task
	 * @param object $task
	 * @param SchedulerModuleController $schedulerModule reference to the Scheduler BE module
	 * @return array Array containing information about additional fields
	 */
	public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule) {
		$this->checkIfInstanceOf($task);
		
		return $this->getFeedsField($task);
	}
	
	/** 
	 * @param array $submittedData reference to array with data provided by the user
	 * @param SchedulerModuleController $schedulerModule reference to the Scheduler BE module
	 * @return boolean validation result
	 */
	public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule) {
		return $this->fieldValidation($submittedData);
	}
	
	/**
	 * @param array $submittedData array with data provided by the user
	 * @param AbstractTask $task current task
	 * @return void
	 */
	public function saveAdditionalFields(array $submittedData, AbstractTask $task) {
		$this->checkIfInstanceOf($task);
	}

	/**
	 * Get feed configurations
	 * 
	 * @param mixed $task
	 */
	abstract protected function getFeedsField($task = NULL);
	
	/**
	 * Check if selected task is instance of needed class
	 * 
	 * @param object $task
	 */
	abstract protected function checkIfInstanceOf($task);
	
	/**
	 * Check if submitted data is valid
	 * 
	 * @param array $submittedData
	 * @return boolean
	 */
	abstract protected function fieldValidation($submittedData);
	
}