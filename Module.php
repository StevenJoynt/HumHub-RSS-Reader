<?php

namespace sij\humhub\modules\rss;

use Yii;
use yii\helpers\Url;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\space\models\Space;

class Module extends \humhub\modules\content\components\ContentContainerModule
{

    public function getContentContainerTypes()
    {
        return [
            Space::class,
        ];
    }

    public function getContentContainerConfigUrl(ContentContainerActiveRecord $container)
    {
        return $container->createUrl('/rss/rss/config');
    }

    public function getContentContainerName(ContentContainerActiveRecord $container)
    {
        return Yii::t('RssModule.base', 'RSS');
    }

    public function getContentContainerDescription(ContentContainerActiveRecord $container)
    {
        return Yii::t('RssModule.base', 'RSS News Reader');
    }

}
