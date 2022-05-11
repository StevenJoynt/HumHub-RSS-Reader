<?php

use yii\db\Migration;

class m220202_180401_initial extends Migration
{

    public function up()
    {
        $this->createTable('rss_posts', [
            'id' => 'pk',
            'rss_link' => 'varchar(1024) NOT NULL',
            'post_id' => 'int(11) NOT NULL',
        ], '');

        // creates index for column `rss_link`
        // hopefully mysql is smart enaugh to handle varchar(1024) decently
        $this->createIndex(
            'idx-rss_posts-rss_link',
            'rss_posts',
            'rss_link',
        );
    }

    public function down()
    {
        echo "m220202_180401_initial does not support migration down.\n";
        return false;
    }

}
