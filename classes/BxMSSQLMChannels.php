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
	
class BxMSSQLMChannels extends BxMSSQLMData
{	
	public function __construct(&$oMigrationModule, &$oDb)
	{
        parent::__construct($oMigrationModule, $oDb);
		$this -> _sModuleName = 'channels';
		$this -> _sTableWithTransKey = 'bx_cnl_data';
    }    
	
	public function getTotalRecords()
	{
		return $this -> _mDb -> getOne("SELECT COUNT(*) FROM [Channels] WHERE [ChannelName] <> '' AND [ChannelTypeID] <> 8");
	}
    protected function isChannelExists($iItemId, $sName)
    {
       return (int)$this -> _oDb -> getOne("SELECT `id` FROM `{$this -> _sTableWithTransKey}` WHERE `id` = :item OR `channel_name` = :name LIMIT 1", array('item' => $iItemId, 'name' => $sName));
    }
    public function runMigration()
	{        
		if (!$this -> getTotalRecords())
		{
			  $this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}

		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_channels'));

        if (!$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent) || (int)$this -> _oDb -> getOne("SELECT COUNT(*) FROM `bx_cnl_data`"))
            $this -> removeContent();

        $this -> createMIdField();

        $sStart = '';
		$iChannelId = $this -> getLastMIDField();
		if ($iChannelId)
			$sStart = " AND [ID] >= {$iChannelId}";

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
                                          WHERE [ChannelName] <> '' AND [ChannelTypeID] !=8 {$sStart}");

		foreach($aResult as $iKey => $aValue)
		{
            $iChannelId = $this -> isChannelExists($aValue['ID'], $aValue['ChannelName']);
			if (!$iChannelId && $aValue['ChannelName'])
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
                                `channel_name`		= ?
                         ",
                        $aValue['ID'],
                        $iAuthor,
						$aValue['ChannelName']
						);			
		
				$this -> _oDb -> query($sQuery);
				
				$iChannelId = $this -> _oDb -> lastId();
                $this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = :account, `type` = 'bx_channels', `content_id` = :channel, `status` = 'active'",
                                            array('account' => $iAccountId, 'channel' => $iChannelId));
                $this -> setMID($iChannelId, $aValue['ID']);
            }

			$iRelations = $this -> transferRelations($iChannelId, (int)$aValue['ID']);
			$this -> _iTransferred++;
        }

        $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_channels_finished', $this -> _iTransferred));
        return BX_MIG_SUCCESSFUL;
    }

    private function getProfileIdByContentId($iId){
        $sQuery = $this -> _oDb -> prepare("SELECT `id` FROM `sys_profiles` WHERE `content_id`=? AND `type` = 'bx_channels' LIMIT 1", $iId);
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

        /*if ($iRelations)
            $this -> _oDb -> query("UPDATE `bx_posts_posts` SET `speciality` = 1 WHERE `id` = :id", array('id' => $aValue['PostID']));*/

        return $iRelations;
    }

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
				BxDolService::call('bx_channels', 'delete_entity', array($aValue['id']));
				$iNumber++;
			}
		}		
		parent::removeContent();
		return $iNumber;
	}	
}

/** @} */
