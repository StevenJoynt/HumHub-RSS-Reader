<?php

use sij\humhub\modules\rss\Events;
use humhub\components\console\Application;

return [
	'id' => 'rss',
	'class' => 'sij\humhub\modules\rss\Module',
	'namespace' => 'sij\humhub\modules\rss',
	'events' => [
		[ Application::class, Application::EVENT_AFTER_REQUEST, [Events::class, 'onCron'] ],
	],
];
