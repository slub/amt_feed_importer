<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Feed Importer',
	'description' => 'Extension to import news from feeds (e.g. RSS2, Atom, etc). Fast and simple configuration which is quite similar to configuration rss2_import',
	'category' => 'be',
	'author' => 'Krzysztof Kasprzyca',
	'author_email' => 'k.kasprzyca@amtsolution.pl',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'version' => '2.0.3',
	'constraints' => array(
		'depends' => array(
			'typo3' => '^11.5',
			'news' => '8.6.0-0.0.0'
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
