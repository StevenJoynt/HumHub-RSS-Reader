<?php

namespace sij\humhub\modules\rss\models;

use Yii;

class ConfigureForm extends \yii\base\Model
{

    public $url;

    public function rules()
    {
        return [
            [
                'url',
                'trim'
            ],[
                'url',
                'required'
            ],[
                'url',
                'string',
                'max' => 255,
            ]
        ];
    }

    public function attributeLabels()
    {
        return [
            'url' => 'URL of the RSS Feed',
        ];
    }

}
