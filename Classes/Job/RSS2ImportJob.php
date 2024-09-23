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

namespace AMT\AmtFeedImporter\Job;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Extbase\Service\CacheService;
use AMT\AmtFeedImporter\Job\FeedImportJobInterface;
use AMT\AmtFeedImporter\Domain\Repository\FeedRepository;

class RSS2ImportJob implements FeedImportJobInterface {

	/**
	 * @var FeedRepository
	 */
	protected $feedRepository = NULL;

	/**
	 * @var CacheService
	 */
	protected $cacheService = NULL;

	/**
	 * @var \AMT\AmtFeedImporter\Domain\Model\Feed
	 */
	protected $feed = NULL;

	/**
	 * @param FeedRepository $feedRepository
	 * @param CacheService $cacheService
	 */
	public function __construct(FeedRepository $feedRepository, CacheService $cacheService) {
		$this->feedRepository = $feedRepository;
		$this->cacheService = $cacheService;
	}
	
	/**
	 * @see FeedImportJobInterface::run()
	 */
	public function run($feedUid) {

		if ((int) $feedUid <= 0) {
			return FALSE;
		}

		if ($this->feed === NULL && (int) $feedUid > 0) {
			$this->feed = $this->feedRepository->findByUid($feedUid);
		}

		$newsArray = $this->parseContent($this->feed->getFeedUrl());

		if ($newsArray === NULL) {
			return FALSE;
		} elseif (!is_array($newsArray)) {
			return FALSE;
		}

		foreach ($newsArray as $newsItem) {
            $queryBuilder = $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_news_domain_model_news');

            $importedNewsCollection = $queryBuilder
                ->select('*')
                ->from('tx_news_domain_model_news')
                ->where(
                    $queryBuilder->expr()->eq('amt_feedimporter_guid',  $queryBuilder->createNamedParameter($newsItem['amt_feedimporter_guid']))
                )
                ->execute()->fetch();

			if (!is_array($importedNewsCollection)) {
				$importedNews = array();
				$importedNews['amt_feedimporter_guid'] = $newsItem['amt_feedimporter_guid'];
				$importedNews['title'] = $newsItem['title'];
				$importedNews['teaser'] = $newsItem['teaser'];
				$importedNews['bodytext'] = $newsItem['bodytext'];
				$importedNews['author'] = $this->feed->getAuthor();
				$importedNews['datetime'] = $newsItem['datetime']->getTimestamp();
				$importedNews['sys_language_uid'] = $this->feed->getNewsLanguage();
				$importedNews['pid'] = $this->feed->getTargetFolder();

				if ($newsItem['author_email'] !== '') {
					$importedNews['author_email'] = $newsItem['author_email'];
				} else {
					$importedNews['author_email'] = $this->feed->getAuthorEmail();
				}

				if ($this->feed->getHideImportedNews()) {
					$importedNews['hidden'] = 1;
				}

				if ($this->feed->getNewsType() === '1') {
					$importedNews['type'] = 1;
					$importedNews['internalurl'] = $newsItem['amt_feedimporter_guid'];
				} elseif ($this->feed->getNewsType() === '2') {
					$importedNews['type'] = 2;
					$importedNews['externalurl'] = !empty($newsItem['link']) ?
						$newsItem['link'] : $newsItem['amt_feedimporter_guid'];
				}
			} else {
				$importedNews = $importedNewsCollection;

				if ($this->feed->getOverrideEditedNews() || (!$this->feed->getOverrideEditedNews() &&
						!((boolean) $importedNews['amt_feedimporter_was_edited']))) {
					$importedNews['title'] = $newsItem['title'];
					$importedNews['teaser'] = $newsItem['teaser'];
					$importedNews['bodytext'] = $newsItem['bodytext'];
					$importedNews['datetime'] = $newsItem['datetime']->getTimestamp();
                    $importedNews['externalurl'] = !empty($newsItem['link']) ?
                        $newsItem['link'] : $newsItem['amt_feedimporter_guid'];

					if ($newsItem['author_email'] !== '') {
						$importedNews['author_email'] = $newsItem['author'];
					} else {
						$importedNews['author_email'] = $this->feed->getAuthorEmail();
					}
				}
			}

			$categories = $this->feed->getCategories();

			$newsCategories = array();

			for ($categories->rewind(); $categories->valid(); $categories->next()) {
				$newsCategories[] = $categories->current()->getUid();
			}

			$importedNews['categories'] = implode(',', $newsCategories);

			if (is_array($newsItem['customMapping'])) {
				foreach ($newsItem['customMapping'] as $key => $val) {
					$importedNews[$key] = $val;
				}
			}

			/* @var $dataHandler DataHandler */
			$dataHandler = GeneralUtility::makeInstance(DataHandler::class);

			$data = array();

			if (!isset($importedNews['uid'])) {
				$data['tx_news_domain_model_news']['NEW'] = $importedNews;

				$dataHandler->start($data, array());
				$dataHandler->process_datamap();

				$importedNews['uid'] = $dataHandler->substNEWwithIDs['NEW'];
			} else {
				if ($this->feed->getOverrideEditedNews() || (!$this->feed->getOverrideEditedNews() &&
						!((boolean) $importedNews['amt_feedimporter_was_edited']))) {
					$data['tx_news_domain_model_news'][$importedNews['uid']] = $importedNews;

					$dataHandler->start($data, array());
					$dataHandler->process_datamap();
				}
			}

			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['amt_feed_importer']['Job/RSS2ImportJob.php']['processAfterSaved'])) {

				$params = array(
					'importedNews' => $newsItem,
					'newsId' => $importedNews['uid'],
				);

				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['amt_feed_importer']['Job/RSS2ImportJob.php']['processAfterSaved'] as $reference) {
					GeneralUtility::callUserFunction($reference, $params, $this);
				}
			}
		}

		if (count($newsArray) > 0) {

			$this->cacheService->clearPageCache($this->feed->getTargetFolder());
		}

		return TRUE;
	}

	/**
	 * @see FeedImportJobInterface::parseContent()
	 */
	public function parseContent($feedUrl) {
		$content = FALSE;

		if (function_exists('curl_version')) {
			$cUrl = curl_init();

			curl_setopt($cUrl, CURLOPT_URL, $feedUrl);
			curl_setopt($cUrl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($cUrl, CURLOPT_FOLLOWLOCATION, true);

            if ($this->feed->getSocialFeed()) {
                $this->socialAdditionalCurlConfiguration($cUrl);
            }

			$content = curl_exec($cUrl);

			curl_close($cUrl);
		} else if (file_get_contents(__FILE__) && ini_get('allow_url_fopen')) {
			$content = file_get_contents($feedUrl);
		}

		if ($content === FALSE) {
			return NULL;
		} else {
			$xmlObject = new \SimpleXMLElement($content);

			if ((int) $xmlObject->attributes()->version !== 2 || $xmlObject->getName() !== 'rss') {
				GeneralUtility::devLog('This is not RSS2 standard', 'amt_feed_importer', 3);

				return FALSE;
			}

			$parsedNews = array();

			foreach ($xmlObject->channel->item as $item) {
                $dateTime = \DateTime::createFromFormat(\DateTime::RSS, (string) $item->pubDate);

				$parsedNews[] = array(
					'amt_feedimporter_guid' => (string) $item->guid,
					'datetime' => $dateTime === FALSE ? new \DateTime() : $dateTime,
					'title' => (string) $item->title,
					'link' => (string) $item->link,
					'author_email' => (string) $item->author,
					'teaser' => (string) $item->description,
					'bodytext' => (string) $item->children('content', true)->encoded,
				);

				if ($this->feed->getCustomMapping() !== '') {
					$thisParsedNews = &$parsedNews[count($parsedNews) - 1];
					$thisParsedNews['customMapping'] = array();

					/* @var $typoScriptParser TypoScriptParser */
					$typoScriptParser = GeneralUtility::makeInstance(TypoScriptParser::class);
					$typoScriptParser->parse($this->feed->getCustomMapping());

					$customMappingConfiguration = $typoScriptParser->setup;

					if (is_array($customMappingConfiguration)) {
						foreach ($customMappingConfiguration as $key => $val) {
							if (!is_array($val)) {
                                if ($key === FeedImportJobInterface::CLEAR_FIELD) {
                                    $thisParsedNews['customMapping'][$val] = '';
                                } else {
                                    $thisParsedNews['customMapping'][$val] = (string) $item->{$key};
                                }
							} else {
								foreach ($val as $k => $v) {
									$joinedValues = array();

									$childrens = $item->children(str_replace('.', '', $key), TRUE)->{$k};

									foreach ($childrens as $children) {
										$joinedValues[] = (string) $children;
									}

									$thisParsedNews['customMapping'][$v] = implode(',', $joinedValues);
								}
							}
						}
					}
				}

				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['amt_feed_importer']['Job/RSS2ImportJob.php']['extraMapping'])) {
					$params = array(
						'parsedNews' => &$parsedNews[count($parsedNews) - 1],
						'item' => $item,
					);

					foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['amt_feed_importer']['Job/RSS2ImportJob.php']['extraMapping'] as $reference) {
						GeneralUtility::callUserFunction($reference, $params, $this);
					}
				}
			}

			return $parsedNews;
		}
	}

    /**
     * @param $cUrl
     * @return void
     */
    protected function socialAdditionalCurlConfiguration($cUrl) {
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: ";

        curl_setopt($cUrl, CURLOPT_USERAGENT, 'Mozilla');
        curl_setopt($cUrl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($cUrl, CURLOPT_REFERER, '');
        curl_setopt($cUrl, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($cUrl, CURLOPT_AUTOREFERER, true);
        curl_setopt($cUrl, CURLOPT_TIMEOUT, 10);
    }
}
