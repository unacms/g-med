<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    G-Med Migration  G-Med Migration
 * @ingroup     UnaModules
 *
 * @{
 */

require_once('BxMSSQLMData.php');
bx_import('BxDolStorage');

class BxMSSQLMGroups extends BxMSSQLMData
{
	public function __construct(&$oMigrationModule, &$oDb)
	{
        parent::__construct($oMigrationModule, $oDb);
		$this -> _sModuleName = 'groups';
		$this -> _sTableWithTransKey = 'bx_groups_data';
    }

	public function getTotalRecords()
	{
		return $this -> _mDb -> getOne("SELECT COUNT(*) FROM [Channels] WHERE [ChannelTypeID] IN (8,9)");
	}
    public function runMigration()
	{
		if (!$this -> getTotalRecords())
		{
			  $this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}

		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_groups'));

		$bFieldExists = $this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent);
        if (!$bFieldExists ||
           ($bFieldExists && !(int)$this -> _oDb -> getOne("SELECT COUNT(*) FROM `{$this -> _sTableWithTransKey}` WHERE `{$this -> _sTransferFieldIdent}` <> 0")))
            $this -> removeContent();

        $this -> createMIdField();

        $sStart = '';
		$iGroupId = $this -> getLastMIDField();
		if ($iGroupId)
			$sStart = " AND [ID] >= {$iGroupId}";

        $aAdmins = BxDolAclQuery::getInstance()->getProfilesByMembership(array(MEMBERSHIP_ID_MODERATOR, MEMBERSHIP_ID_ADMINISTRATOR));
        $iAuthor = !empty($aAdmins) && $aAdmins[0]['id'] ? $aAdmins[0]['id'] : 1;
        $iAccountId = !empty($aAdmins) && $aAdmins[0]['account_id'] ? $aAdmins[0]['account_id'] : 1;

        $aResult = $this -> _mDb -> getAll("SELECT [ID]
                                              ,[ChannelName]
                                              ,[ChannelTypeID]
                                              ,[ExpertiseID]
                                              ,[InwiseID]
                                              ,[PostsCount]
                                              ,[IsActive]
                                              ,[IsMenuShow]
                                              ,[SetOrder]
                                              ,[UsersCount]
                                              ,[IsShowInCountMenu]
                                              ,[GroupTypeId]
                                              ,[Description]
                                              ,[AllowedUserTypeIds]
                                              ,[ImageId]
                                              ,[CreatorUserId]
                                          FROM [Channels]
                                          WHERE [ChannelTypeID] IN (8,9) {$sStart}");

		foreach($aResult as $iKey => $aValue)
		{
            $iGroupId = $this -> isItemExisted($aValue['ID']);
			if (!$iGroupId && $aValue['ChannelName'])
			{
			    $sQuery = $this -> _oDb -> prepare(
                         "
                            INSERT INTO
                                `{$this -> _sTableWithTransKey}`
                            SET
                                `id`                = ?,
                                `author`   			= ?,
                                `added`      		= UNIX_TIMESTAMP(),
                                `changed`   		= UNIX_TIMESTAMP(),
                                `group_name`		= ?,
                                `allow_view_to`     = 3
                         ",
                        $aValue['ID'],
                        $iAuthor,
						$aValue['ChannelName']
						);

				$this -> _oDb -> query($sQuery);

				$iGroupId = $this -> _oDb -> lastId();
                $this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = :account, `type` = 'bx_groups', `content_id` = :group, `status` = 'active'",
                                            array('account' => $iAccountId, 'group' => $iGroupId));
                $this -> setMID($iGroupId, $aValue['ID']);
            }

            $iFollowers = $this -> transferFollowers($iGroupId, (int)$aValue['ID']);
			$this -> _iTransferred++;
        }

        $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_groups_finished', $this -> _iTransferred));
        return BX_MIG_SUCCESSFUL;
    }

    public function transferFollowers($iGroupID, $iOldGroupId){
        $aFollowers =  $aPosts = $this -> _mDb -> getAll("SELECT [UserID],[ChannelID],[IsAdmin]
                                                          FROM [Users_Channels]
                                                          WHERE ChannelID = :id ", array('id' => $iOldGroupId));

        $iGroupProfileID = $this -> _oDb -> getOne("SELECT `id` FROM `sys_profiles` WHERE `content_id` = :group AND  `type` =  'bx_groups' LIMIT 1", array('group' => $iGroupID));
        if (empty($aFollowers) || !$iGroupProfileID)
            return false;

        $iInc = 0;
        foreach($aFollowers as $aFollower){
            $iProfileID = $this -> getProfileId($aFollower['UserID']);
            if ($iProfileID) {
                $this->_oDb->query("INSERT INTO `sys_profiles_conn_subscriptions` SET `initiator` = :profile, `added` = UNIX_TIMESTAMP(), `content` = :group",
                    array('profile' => $iProfileID, 'group' => $iGroupProfileID));

                $this->_oDb->query("INSERT INTO `bx_groups_fans` SET `initiator` = :profile, `added` = UNIX_TIMESTAMP(), `content` = :group, `mutual` = 1",
                    array('profile' => $iProfileID, 'group' => $iGroupProfileID));
                $this->_oDb->query("INSERT INTO `bx_groups_fans` SET `content` = :profile, `added` = UNIX_TIMESTAMP(), `initiator` = :group, `mutual` = 1",
                    array('profile' => $iProfileID, 'group' => $iGroupProfileID));

                if ((int)$aFollower['IsAdmin']) {
                    $this->_oDb->query("INSERT INTO `bx_groups_admins` SET `fan_id` = :fan, `group_profile_id` = :group",
                        array('fan' => $iProfileID, 'group' => $iGroupProfileID));
                }
                $iInc++;
            }
        }

        return $iInc;
    }

	public function removeContent()
	{

		$aRecords = $this -> _oDb -> getAll("SELECT * FROM `{$this -> _sTableWithTransKey}` ");
		$iNumber = 0;
		if (!empty($aRecords))
		{
			foreach($aRecords as $iKey => $aValue)
			{
				BxDolService::call('bx_groups', 'delete_entity', array($aValue['id']));
				$iNumber++;
			}
		}
		parent::removeContent();
		return $iNumber;
	}
}

/** @} */
