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

class BxMSSQLMPosts extends BxMSSQLMData
{
	public function __construct(&$oMigrationModule, &$oDb)
	{
        parent::__construct($oMigrationModule, $oDb);
		$this -> _sModuleName = 'posts';
		$this -> _sTableWithTransKey = 'bx_posts_posts';
    }

	public function getTotalRecords()
	{
		return array(
		                $this -> _mDb -> getOne("SELECT COUNT(*) FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] WHERE [MainParentID] IS NULL"),
                        $this -> _mDb -> getOne("SELECT COUNT(*) FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] WHERE [MainParentID] IS NOT NULL"),
                        $this -> _mDb -> getOne("SELECT COUNT(*) FROM [Posts_Files]"),
                     );
	}

	public function runMigration()
	{
		if (!$this -> getTotalRecords())
		{
			  $this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}

		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_blogs'));

		$this -> createMIdField();
        $sStart = '';
		$iPostId = $this -> getLastMIDField();
		if ($iPostId)
			$sStart = " AND [p].[ID] >= {$iPostId}";

        $aResult = $this -> _mDb -> getAll("SELECT
                                               [p].[ID],
                                               [UserID],
                                               [CreatedDate],
                                               [Post_Title],
                                               [Post_Content],
                                               [LikesCount],
                                               [IsApprove],
                                               [MainParentID],
                                               [Post_Summary],
                                               [CreatedDate],
                                               [h].[HealthdayPostID]
                                            FROM [Channels_Posts] as [p]
                                            LEFT JOIN [Users] as [u] ON [u].[ID] = [p].[UserID]
                                            LEFT JOIN [HealthdayPosts] as [h] ON [h].[PostID] = [p].[ID] 
                                            WHERE [MainParentID] IS NULL AND NOT (([Post_Title] = '' OR [Post_Title] IS NULL) AND ([Post_Content] = '' OR [Post_Content] IS NULL)) {$sStart}
                                            ORDER BY [p].[ID]"); /*  LEFT JOIN (SELECT COUNT(*) as [count], [PostID] FROM [Posts_Files] GROUP BY [PostID]) as [f] ON [f].[PostID] = [p].[ID]  AND [count] = 0 */
        /* [p].[ID] IN (8654,7317,5418,9111) */
		foreach($aResult as $iKey => $aValue)
		{
			$iProfileId = $this -> getProfileId((int)$aValue['UserID']);
			if (!$iProfileId)
				continue;

            $iPostId = $this -> isItemExisted($aValue['ID']);
			if (!$iPostId)
			{
				$iDate = isset($aValue['CreatedDate']) && $aValue['CreatedDate'] ? strtotime($aValue['CreatedDate']) : time();
                $iDate = $iDate !== FALSE ? $iDate : time();

			    $sQuery = $this -> _oDb -> query(
                     "
                     	INSERT INTO
                     		`{$this -> _sTableWithTransKey}`
                     	SET
                     		`id`                = :id,
                     		`author`   			= :author,
                     		`added`      		= :date,
                     		`changed`   		= :date,
                     		`published`         = :date,						
							`title`				= :title,		
                     		`text`				= :text,
							`votes`				= :votes,
							`status`			= :status,
                     	    `magazine_id`       = :magazine
							
                     ",
                        array('id' => $aValue['ID'],
                            'author' => $iProfileId,
                            'date' => $iDate,
                            'title' => isset($aValue['Post_Title']) && $aValue['Post_Title'] ? $aValue['Post_Title'] : $aValue['CreatedDate'],
                            'text' => isset($aValue['Post_Content']) ? $aValue['Post_Content'] : '',
                            'votes' => isset($aValue['LikesCount']) ? (int)$aValue['LikesCount'] : 0,
                            'status' => (int)$aValue['IsApprove'] ? 'active' : 'hidden',
                            'magazine' => (int)$aValue['HealthdayPostID']
						));

				$iPostId = $aValue['ID'];//$this -> _oDb -> lastId();
				if (!$this -> _oDb -> lastId()){
					$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_blogs_error', (int)$aValue['ID']));
					return BX_MIG_FAILED;
				}

				$this -> setMID($iPostId, $aValue['ID']);
			}

            $this -> addToTheGroup($aValue['ID']);
	    	$this -> exportFiles($aValue['ID'], $iProfileId);
            //$this -> transferMeta($aValue['ID']);
			$iCmts = $this -> transferComments((int)$aValue['ID']);
            $iViews = $this -> transferViews((int)$aValue['ID']);
			$this -> _iTransferred++;

			$this -> _oDb ->  query("UPDATE `{$this -> _sTableWithTransKey}` SET `comments` = :cmts, `views` = :views WHERE `id` = :id", array('id' => $iPostId, 'cmts' => $iCmts, 'views' => $iViews));
        }

        $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_blogs_finished', $this -> _iTransferred));
        return BX_MIG_SUCCESSFUL;
    }

    private function addToTheGroup($iPostID){
        $aGroupIDs = $this -> _mDb -> getPairs("SELECT [ChannelID] as [ID]                                                  
                                              FROM [Channels_Posts_Relations] as [r]
                                              LEFT JOIN [Channels] as [c] ON [c].[ID] = [r].[ChannelID]
                                              WHERE [PostID] = :post AND [c].[ChannelTypeID] IN (8,9)", 'ID', 'ID', array('post' => $iPostID));

        if (empty($aGroupIDs))
            return false;

        foreach($aGroupIDs as $iKey){
            $iGroupID = $this -> _oDb -> getOne("SELECT `id` FROM `sys_profiles` WHERE `type` = 'bx_groups' AND `content_id` = :group", array('group' => $iKey));
            if ($iGroupID)
            {
                $sQuery = $this->_oDb->prepare("UPDATE `{$this -> _sTableWithTransKey}` SET `allow_view_to` = ? WHERE `id` = ?", -$iGroupID, $iPostID);
                $this->_oDb->query($sQuery);
                return;
            }
        }

        return false;
	}

    protected function transferMeta($iPostID)
    {
        $aChannels = $this -> _mDb -> getAll("SELECT [ChannelName] 
                                              FROM [Channels_Posts_Relations] 
                                              LEFT JOIN [Channels] as [c] ON [ChannelID] = [c].[ID]
                                              WHERE [PostID] = :id", array('id' => $iPostID));
        $iCount = 0;
        if (empty($aChannels))
            return false;

        foreach($aChannels as $iKey => $aValue)
        {
            $this -> _oDb -> query("REPLACE INTO `bx_posts_meta_keywords` SET 
                   `object_id`=:post_id,
                   `keyword`=:name",
                    array(
                        'post_id' => (int)$iPostID,
                        'name' => $aValue['ChannelName']
                    ));

            $iCount++;
        }

       $this -> _oDb -> query("UPDATE `bx_posts_posts` SET `speciality` = 3 WHERE `id` = :id", array('id' => $iPostID));

        return $iCount;
    }

    protected function transferComments($iEntryId)
    {

        $aComments = $this -> _mDb -> getAll("SELECT [UserID],[MainParentID],[Post_Summary],[CreatedDate] FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] WHERE [MainParentID] = :id", array('id' => $iEntryId));

        $this -> createMIdField();

        $iComments = 0;
        foreach($aComments as $iKey => $aValue)
        {
            $iProfileId = $this -> getProfileId($aValue['UserID']);
            if ($iProfileId && $aValue['Post_Summary'])
            {
                $this -> _oDb -> query("INSERT INTO `bx_posts_cmts` (`cmt_id`, `cmt_parent_id`, `cmt_vparent_id`, `cmt_object_id`, `cmt_author_id`, `cmt_level`, `cmt_text`, `cmt_time`, `cmt_replies`, `cmt_rate`, `cmt_rate_count`)
									VALUES	(NULL, 0, 0, :object, :user, 0, :message, :time, 0, 0, 0)",
                    array(
                        'object' => $iEntryId,
                        'user' => $iProfileId,
                        'message' => $aValue['Post_Summary'],
                        'time' => strtotime($aValue['CreatedDate'])
                    ));

                $iComments++;
            }
        }

        return $iComments;
    }

    private function transferViews($iEntryId)
    {

        $aViews = $this -> _mDb -> getAll("SELECT 
                                                   [UserID]
                                                  ,[PostID]
                                                  ,[CreatedDate]
                                                  ,[IsExpose]
                                                  ,[WebDomain]  
                                                  FROM [Statistic_UsersReadPost]
                                                  WHERE PostID = :id AND IsExpose = 1", array('id' => $iEntryId));
        if (empty($aViews))
            return 0;

        $iViews = 0;
        foreach($aViews as $iKey => $aValue)
        {
            $iProfileId = $this -> getProfileId($aValue['UserID']);
            if ($iProfileId)
            {
                $iDate = strtotime($aValue['CreatedDate']);
                $iDate = $iDate !== FALSE ? $iDate: time();

                $this -> _oDb -> query("INSERT INTO `bx_posts_views_track` (`object_id`, `viewer_id`, `viewer_nip`, `date`)
									VALUES	(:object, :user, :ip, :time)",
                    array(
                        'object' => $iEntryId,
                        'user' => $iProfileId,
                        'ip' => $iDate + $iViews,
                        'time' => $iDate
                    ));

                $iViews++;
            }
        }

        return $iViews;
    }

	/**
	 * @param integer $iEntryId una blog Id
	 * @param integer $iProfileId  profile id
	 * @return void
	 */
	private function exportFiles($iEntryId, $iProfileId)
    {
       $aFiles = $this -> _mDb -> getAll('SELECT [FileName] FROM [Posts_Files] WHERE [PostID] = :id', array('id' => $iEntryId));
       if (empty($aFiles))
		   return;

       $bIsCoverSet = false;
       $sFileUrl =  "http://gmed.imgix.net/postdata/";
       $imgExts = array("gif", "jpg", "jpeg", "png", "tiff", "tif");
       $oImageStorage = BxDolStorage::getObjectInstance('bx_posts_photos');
       $oFilesStorage = BxDolStorage::getObjectInstance('bx_posts_files');
       foreach($aFiles as &$aFile)
       {

           $sExt = pathinfo($aFile['FileName'], PATHINFO_EXTENSION);
           if (in_array($sExt, $imgExts) && !$bIsCoverSet)
           {
               $iImage = $oFilesStorage->storeFileFromUrl($sFileUrl . $aFile['FileName'], false, $iProfileId, (int)$iEntryId);
               if ($iImage) {
                   $sQuery = $this->_oDb->prepare("UPDATE `{$this -> _sTableWithTransKey}` SET `thumb` = ? WHERE `id` = ?", $iImage, $iEntryId);
                   $this->_oDb->query($sQuery);
                   $bIsCoverSet = true;
               }
           }

           $oImageStorage->storeFileFromUrl($sFileUrl . $aFile['FileName'], false, $iProfileId, (int)$iEntryId);
       }
    }

	public function removeContent()
	{
		/*if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;*/

		$aRecords = $this -> _oDb -> getAll("SELECT * FROM `{$this -> _sTableWithTransKey}`"); /*WHERE `{$this -> _sTransferFieldIdent}` !=0*/
		$iNumber = 0;
		if (!empty($aRecords))
		{
			foreach($aRecords as $iKey => $aValue)
			{
				BxDolService::call('bx_posts', 'delete_entity', array($aValue['id']));
				$iNumber++;
			}
		}
		parent::removeContent();
		return $iNumber;
	}
}

/** @} */
