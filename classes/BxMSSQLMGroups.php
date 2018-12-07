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
		return $this -> _mDb -> getOne("SELECT COUNT(*) FROM [Channels] WHERE [ChannelTypeID] = 8");
	}
    public function runMigration()
	{        
		if (!$this -> getTotalRecords())
		{
			  $this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}

		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_groups'));

        if (!$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent) || (int)$this -> _oDb -> getOne("SELECT COUNT(*) FROM `{$this -> _sTableWithTransKey}`"))
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
                                          WHERE [ChannelTypeID] = 8 {$sStart}");

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

			/*$iRelations = $this -> transferRelations($iGroupId, (int)$aValue['ID']);*/
			$this -> _iTransferred++;
        }

        $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_groups_finished', $this -> _iTransferred));
        return BX_MIG_SUCCESSFUL;
    }

    /*private function getProfileIdByContentId($iId){
        $sQuery = $this -> _oDb -> prepare("SELECT `id` FROM `sys_profiles` WHERE `content_id`=? AND `type` = 'bx_groups' LIMIT 1", $iId);
        return $this -> _oDb -> getOne($sQuery);
    }

    protected function transferRelations($iNewChannelId, $iOldChannelId)
    {
        $iChannelProfileID = $this ->  getProfileIdByContentId($iNewChannelId);
        if (!$iChannelProfileID)
            return false;

        $aPosts = $this -> _mDb -> getAll("SELECT [PostID] FROM [Channels_Posts_Relations] WHERE [ChannelID] = :id", array('id' => $iOldChannelId));
        $iRelations = 0;
        foreach($aPosts as $iKey => $aValue)
        {
            $iPostAuthorProfile = $this -> _oDb -> getOne("SELECT `author` FROM `bx_posts_posts` WHERE `id` = :id", array('id' => $aValue['PostID']));
            if ($iPostAuthorProfile)
            {

                $this -> _oDb -> query("INSERT INTO `bx_cnl_content` SET 
                   `content_id`=:post_id,
                   `cnl_id`=:cnl_id,
                   `author_id`=:profile_id,
                   `module_name`='bx_posts'",
                    array(
                        'post_id' => (int)$aValue['PostID'],
                        'cnl_id' => $iNewChannelId,
                        'profile_id' => $iPostAuthorProfile
                    ));

                $iRelations++;
            }
        }


        return $iRelations;
    }*/

	public function removeContent()
	{
		if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;
		
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
