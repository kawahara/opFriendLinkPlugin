<?php

class opFriendLinkTask extends sfDoctrineBaseTask
{
  protected
    $connectionOptions = null,
    $tables = array(),
    $tableNames = array();

  protected function configure()
  {
    parent::configure();
    $this->namespace        = 'openpne';
    $this->name             = 'friend-link';
    $this->briefDescription = '';
    $this->addOptions(array(
      new sfCommandOption('start-member-id', null, sfCommandOption::PARAMETER_OPTIONAL, 'Start member id', null),
      new sfCommandOption('end-member-id', null, sfCommandOption::PARAMETER_OPTIONAL, 'End member id', null),
    ));
    $this->detailedDescription = <<<EOF
The [opKawa:friend-link|INFO] task does things.
Call it with:

  [php symfony opKawa:friend-link|INFO]
EOF;
  }

  protected function getDbh()
  {
    $options = $this->connectionOptions;
    $dbh =  new PDO($options['dsn'], $options['username'],
      (!$options['password'] ? '':$options['password']), $options['other']);

    if (0 === strpos($options['dsn'], 'mysql:'))
    {
      $dbh->query('SET NAMES utf8');
    }

    return $dbh;
  }

  protected function getTable($modelName)
  {
    if (!isset($this->tables[$modelName]))
    {
      $this->tables[$modelName] = Doctrine::getTable($modelName);
    }

    return $this->tables[$modelName];
  }

  protected function getTableName($modelName)
  {
    if (!isset($this->tableNames[$modelName]))
    {
      $this->tableNames[$modelName] = $this->getTable($modelName)->getTableName();
    }

    return $this->tableNames[$modelName];
  }

  protected function executeQuery($query, $params = array())
  {
    if (!empty($params))
    {
      $stmt = $this->getDbh()->prepare($query);
      $stmt->execute($params);

      return $stmt;
    }

    return $this->getDbh()->query($query);
  }

  protected function fetchRow($query, $params = array())
  {
    return $this->executeQuery($query, $params)->fetch(Doctrine_Core::FETCH_ASSOC);
  }


  protected function checkAndFriendLink($memberId1, $memberId2)
  {
    if ($memberId1 == $memberId2)
    {
      return false;
    }

    $query = 'SELECT id, is_friend_pre FROM '.$this->getTableName('MemberRelationship')
      .' WHERE member_id_to = ? AND member_id_from = ?';

    $g1 = $this->fetchRow($query, array($memberId1, $memberId2));
    $g2 = $this->fetchRow($query, array($memberId2, $memberId1));

    if ($g1 && $g1[1])
    {
      $this->executeQuery('DELETE FROM '.$this->getTableName('MemberRelationship').' WHERE id = ?', array($g1[0]));
      $g1 = null;
    }

    if ($g2 && $g2[1])
    {
      $this->executeQuery('DELETE FROM '.$this->getTableName('MemberRelationship').' WHERE id = ?', array($g2[0]));
      $g2 = null;
    }

    if (!$g1 && !$g2)
    {
      return $this->friendLink($memberId1, $memberId2);
    }

    return false;
  }

  protected function friendLink($memberId1, $memberId2)
  {
    $query = 'INSERT INTO '.$this->getTableName('MemberRelationship').'(member_id_to, member_id_from, is_friend) VALUES (?, ?, 1)';
    $this->executeQuery($query, array($memberId1, $memberId2));
    $this->executeQuery($query, array($memberId2, $memberId1));
    return true;
  }

  protected function execute($arguments = array(), $options = array())
  {
    sfContext::createInstance($this->createConfiguration('pc_frontend', 'prod'), 'pc_frontend');
    $connection = Doctrine_Manager::connection();
    $this->connectionOptions = $connection->getOptions();

    $query1 = $query2 = 'SELECT id FROM '.$this->getTableName('Member').' WHERE (is_active = 1 OR is_active IS NULL)';
    /*
    $params = array();
    $start = 1;
    $end = null;
    if (null !== $options['start-member-id'] && is_numeric($options['start-member-id']))
    {
      $start    = $options['start-member-id'];
      $query1   = ' AND id >= ?';
      $params[] = $start;
    }
    if (null !== $options['end-member-id'] && is_numeric($options['end-member-id']))
    {
      $end      = $options['end-member-id'];
      $query1   = ' AND id <= ?';
      $params[] = $end;
    }*/

    $memberStmt1 = $this->executeQuery($query1);
    while ($member1 = $memberStmt1->fetch(PDO::FETCH_NUM))
    {
      $memberStmt2 = $this->executeQuery($query2.' AND id >= ?', array($member1[0]));
      while ($member2 = $memberStmt2->fetch(PDO::FETCH_NUM))
      {
        $this->checkAndFriendLink($member1[0], $member2[0]);
      }
    }
  }
}
