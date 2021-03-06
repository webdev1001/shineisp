<?php
/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class Version96 extends Doctrine_Migration_Base
{
    public function up()
    {
        $this->createForeignKey('products', 'products_isp_id_isp_isp_id', array(
             'name' => 'products_isp_id_isp_isp_id',
             'local' => 'isp_id',
             'foreign' => 'isp_id',
             'foreignTable' => 'isp',
             ));
        $this->addIndex('products', 'products_isp_id', array(
             'fields' => 
             array(
              0 => 'isp_id',
             ),
             ));
    }

    public function down()
    {
        $this->dropForeignKey('products', 'products_isp_id_isp_isp_id');
        $this->removeIndex('products', 'products_isp_id', array(
             'fields' => 
             array(
              0 => 'isp_id',
             ),
             ));
    }
}