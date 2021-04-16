<?php

use sij\humhub\modules\rss\Events;
use humhub\commands\CronController;

return [
	'id' => 'rss',
	'class' => 'sij\humhub\modules\rss\Module',
	'namespace' => 'sij\humhub\modules\rss',
	'events' => [
        	[
			'class' => CronController::class,
			'event' => CronController::EVENT_ON_HOURLY_RUN,
			'callback' => [Events::class, 'onHourlyCron']
		],
	],
];
