<?php

namespace sij\humhub\modules\rss\models;

use Yii;
use humhub\components\ActiveRecord;

/**
 * This is the model class for table "rss_posts".
 *
 * The followings are the available columns in table 'rss_posts':
 * @property integer $id
 * @property string $rss_link
 * @property integer $post_id
 */
class RssPosts extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'rss_posts';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            [ ['rss_link', 'post_id'], 'required' ],
            [ ['rss_link'], 'string' ],
            [ ['post_id'], 'integer' ],
        ];
    }

}
