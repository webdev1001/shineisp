<?php
// Connection Component Binding
Doctrine_Manager::getInstance()->bindComponent('Banks', 'doctrine');

/**
 * BaseBanks
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $bank_id
 * @property string $name
 * @property string $classname
 * @property string $account
 * @property string $url_test
 * @property string $url_official
 * @property integer $test_mode
 * @property integer $enabled
 * @property string $description
 * @property integer $method_id
 * @property PaymentsMethods $PaymentsMethods
 * @property Doctrine_Collection $Payments
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class BaseBanks extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('banks');
        $this->hasColumn('bank_id', 'integer', 4, array(
             'type' => 'integer',
             'fixed' => 0,
             'unsigned' => false,
             'primary' => true,
             'autoincrement' => true,
             'length' => '4',
             ));
        $this->hasColumn('name', 'string', 50, array(
             'type' => 'string',
             'notnull' => true,
             'length' => '50',
             ));
        $this->hasColumn('classname', 'string', 100, array(
             'type' => 'string',
             'notnull' => true,
             'length' => '100',
             ));
        $this->hasColumn('account', 'string', 50, array(
             'type' => 'string',
             'notnull' => false,
             'length' => '50',
             ));
        $this->hasColumn('url_test', 'string', null, array(
             'type' => 'string',
             'notnull' => false,
             'length' => '',
             ));
        $this->hasColumn('url_official', 'string', null, array(
             'type' => 'string',
             'notnull' => false,
             'length' => '',
             ));
        $this->hasColumn('test_mode', 'integer', 1, array(
             'type' => 'integer',
             'default' => '1',
             'notnull' => true,
             'length' => '1',
             ));
        $this->hasColumn('enabled', 'integer', 1, array(
             'type' => 'integer',
             'default' => '0',
             'notnull' => false,
             'length' => '1',
             ));
        $this->hasColumn('description', 'string', null, array(
             'type' => 'string',
             'length' => '',
             ));
        $this->hasColumn('method_id', 'integer', 4, array(
             'type' => 'integer',
             'length' => '4',
             ));
    }

    public function setUp()
    {
        parent::setUp();
        $this->hasOne('PaymentsMethods', array(
             'local' => 'method_id',
             'foreign' => 'method_id'));

        $this->hasMany('Payments', array(
             'local' => 'bank_id',
             'foreign' => 'bank_id'));
    }
}