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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use AMT\AmtFeedImporter\Task\AbstractFeedAdditionalFieldProvider;
use AMT\AmtFeedImporter\Task\AtomImportTask;
use AMT\AmtFeedImporter\Service\FeedService;

class AtomFeedAdditionalFieldProvider extends AbstractFeedAdditionalFieldProvider {
	private static $FEED_IMPORT_TYPE = 1;
	private static $ADDITIONAL_FIELD_NAME = 'amtfeedimporter_feed_atom_config';
	
	/**
	 * @var FeedService
	 */
	protected $feedService = NULL;
	
	/**
	 * @var \AMT\AmtFeedImporter\Domain\Repository\FeedRepository
	 */
	protected $feedRepository = NULL;
	
	/**
	 * Check if task is instance of AtomImportTask
	 * 
	 * @param AtomImportTask $task
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	protected function checkIfInstanceOf($task) {
		if ($task !== NULL && !$task instanceof AtomImportTask) {
			throw new \InvalidArgumentException('Task not of type ' . $task->__toString(), 1421739448);
		}
	}

	/**
	 * Get all feed configurations for Atom type
	 * 
	 * @param AtomImportTask $task
	 * @return array Array with configuration for additional field
	 */
	protected function getFeedsField($task = NULL) {
		
		if ($this->feedRepository === NULL) {
			$this->feedRepository = $this->getFeedService()->getFeedRepository();
		}
		
		$feeds = $this->feedRepository->findByType(self::$FEED_IMPORT_TYPE);
		
		$options = array();
		
		for ($feeds->rewind(); $feeds->valid(); $feeds->next()) {
			/* @var $feed \AMT\AmtFeedImporter\Domain\Model\Feed */
			$feed = $feeds->current();
			
			if ($task !== NULL) {
				$this->checkIfInstanceOf($task);
				
				if ($task->feedUid === $feed->getUid()) {
					$options[] = '<option value="' . $feed->getUid() . '" selected="selected">' . $feed->getName() . '</option>';
				} else {
					$options[] = '<option value="' . $feed->getUid() . '">' . $feed->getName() . '</option>';
				}
			} else {
				$options[] = '<option value="' . $feed->getUid() . '">' . $feed->getName() . '</option>';
			}
		}
		
		$fieldName = 'tx_scheduler[' . self::$ADDITIONAL_FIELD_NAME . ']';
		$fieldId = self::$ADDITIONAL_FIELD_NAME;
		$fieldHtml = '<select name="' . $fieldName . '" id="' . $fieldId . '">' . implode("\n", $options) . '</select>';
		
		$fieldConfiguration = array();
		$fieldConfiguration[self::$ADDITIONAL_FIELD_NAME] = array(
			'code' => $fieldHtml,
			'label' => 'LLL:EXT:amt_feed_importer/Resources/Private/Language/locallang.xlf:amt_feed_importer.atom_task.additional_fields.feed',
			'cshKey' => '',
			'cshLabel' => ''
		);
		
		return $fieldConfiguration;
	}
	
	/**
	 * @see AbstractFeedAdditionalFieldProvider::fieldValidation()
	 */
	protected function fieldValidation($submittedData) {
		if (MathUtility::canBeInterpretedAsInteger($submittedData[self::$ADDITIONAL_FIELD_NAME])) {
				
			if ($this->feedRepository === NULL) {
				$this->feedRepository = $this->getFeedService()->getFeedRepository();
			}
				
			$feed = $this->feedRepository->findByUid($submittedData[self::$ADDITIONAL_FIELD_NAME]);

            /** @var FlashMessageService $flashMessageService */
			$flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();

			if ($feed === NULL) {
                /** @var FlashMessage $flashMessage */
				$flashMessage = GeneralUtility::makeInstance(FlashMessage::class,
						'',
						$GLOBALS['LANG']->sL('LLL:EXT:amt_feed_importer/Resources/Private/Language/locallang.xlf:amt_feed_importer.errors.not_exist'),
						FlashMessage::ERROR,
						TRUE);

                $messageQueue->addMessage($flashMessage);
				
				return FALSE;
			} elseif ($feed->getType() !== self::$FEED_IMPORT_TYPE) {
                /** @var FlashMessage $flashMessage */
				$flashMessage = GeneralUtility::makeInstance(FlashMessage::class,
						'',
						$GLOBALS['LANG']->sL('LLL:EXT:amt_feed_importer/Resources/Private/Language/locallang.xlf:amt_feed_importer.errors.atom.wrong_type'),
						FlashMessage::ERROR,
						TRUE);

                $messageQueue->addMessage($flashMessage);
			
				return FALSE;
			}
			
			return TRUE;
		}
		
		return FALSE;
	}

	
	/**
	 * @see AbstractFeedAdditionalFieldProvider::saveAdditionalFields()
	 */
	public function saveAdditionalFields(array $submittedData, AbstractTask $task) {
		parent::saveAdditionalFields($submittedData, $task);
		
		$task->feedUid = (int) $submittedData[self::$ADDITIONAL_FIELD_NAME];
	}
	
	/**
	 * Get service with Dependency Injection
	 *
	 * @return FeedService
	 */
	protected function getFeedService() {
		if ($this->feedService === NULL) {
			$this->feedService = GeneralUtility::makeInstance(FeedService::class);
		}

		return $this->feedService;
	}
}