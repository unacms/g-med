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
			  $this -> setResultStatus(_t('_bx_dolphin_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}	
		
		$this -> setResultStatus(_t('_bx_dolphin_migration_started_migration_blogs'));		
			
		$this -> createMIdField();
        $sStart = '';
		$iPostId = $this -> getLastMIDField();
		if ($iPostId)
			$sStart = " AND [ID] >= {$iPostId}";
		
		$aResult = $this -> _mDb -> getAll("SELECT * FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] {$sStart} WHERE [MainParentID] IS NULL ORDER BY [ID]");
		
		$iCmts = 0;

		foreach($aResult as $iKey => $aValue)
		{
			$iProfileId = $this -> getProfileId((int)$aValue['UserID']);
			if (!$iProfileId)
				continue;

            $iPostId = $this -> isItemExisted($aValue['ID']);
			if (!$iPostId)
			{
				$iDate = isset($aValue['CreatedDate']) ? strtotime($aValue['CreatedDate']) : time();
			    $sQuery = $this -> _oDb -> prepare(
                     "
                     	INSERT INTO
                     		`{$this -> _sTableWithTransKey}`
                     	SET
                     		`author`   			= ?,
                     		`added`      		= ?,
                     		`changed`   		= ?,
							`thumb`				= 0,
							`title`				= ?,		
                     		`text`				= ?,							
							`cat`				= 26,
							`votes`				= ?,
							`status`			= ?
							
                     ", 
						$iProfileId,
                        $iDate,
                        $iDate,
						isset($aValue['Post_Title']) ? $aValue['Post_Title'] : time() + 1,
						isset($aValue['Post_Content']) ? $aValue['Post_Content'] : '',
						isset($aValue['LikesCount']) ? (int)$aValue['LikesCount'] : 0,
                        (int)$aValue['IsApprove'] ? 'active' : 'awaiting'
						);			
		
				$this -> _oDb -> query($sQuery);
				
				$iPostId = $this -> _oDb -> lastId();
				if (!$iPostId){
					$this -> setResultStatus(_t('_bx_dolphin_migration_started_migration_blogs_error', (int)$aValue['PostID']));
					return BX_MIG_FAILED;
				}	
				
				$this -> setMID($iPostId, $aValue['ID']);
			}
			
	    	$this -> exportFiles($iPostId, $aValue['ID'], $iProfileId);
						
			$iCmts = $this -> transferComments($iPostId, (int)$aValue['ID']);
			$this -> _iTransferred++;
						
			$this -> _oDb ->  query("UPDATE `{$this -> _sTableWithTransKey}` SET `comments` = :cmts WHERE `id` = :id", array('id' => $iPostId, 'cmts' => $iCmts));
			
        }

        $this -> setResultStatus(_t('_bx_dolphin_migration_started_migration_blogs_finished', $this -> _iTransferred));
        return BX_MIG_SUCCESSFUL;
    }

    protected function transferComments($iObject, $iEntryId)
    {

        $aComments = $this -> _mDb -> getAll("SELECT * FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] WHERE [MainParentID] = :id", array('id' => $iEntryId));

        $this -> createMIdField();

        $iCommnets = 0;
        foreach($aComments as $iKey => $aValue)
        {
            $iProfileId = $this -> getProfileId($aValue['UserID']);
            if ($iProfileId && $aValue['Post_Summary'])
            {
                $this -> _oDb -> query("INSERT INTO `bx_posts_cmts` (`cmt_id`, `cmt_parent_id`, `cmt_vparent_id`, `cmt_object_id`, `cmt_author_id`, `cmt_level`, `cmt_text`, `cmt_time`, `cmt_replies`, `cmt_rate`, `cmt_rate_count`)
									VALUES	(NULL, 0, 0, :object, :user, 0, :message, :time, 0, 0, 0)",
                    array(
                        'object' => $iObject,
                        'user' => $iProfileId,
                        'message' => $aValue['Post_Summary'],
                        'time' => strtotime($aValue['CreatedDate'])
                    ));

                $iCommnets++;
            }
        }

        return $iCommnets;
    }

	/**
	 * Export thumbnail
	 *
	 * @param integer $iEntryId una blog Id
	 * @param integer $iProfileId  profile id
	 * @return void
	 */
	private function exportFiles($iPostID, $iEntryId, $iProfileId)
    {
       $aFiles = $this -> _mDb -> getAll('SELECT * FROM [Posts_Files] WHERE [PostID] = :id', array('id' => $iEntryId));
       if (empty($aFiles))
		   return;

       $sFileUrl =  "http://gmed.imgix.net/postdata/";
       $imgExts = array("gif", "jpg", "jpeg", "png", "tiff", "tif");
       $oImageStorage = BxDolStorage::getObjectInstance('bx_posts_photos');
       $oFilesStorage = BxDolStorage::getObjectInstance('bx_posts_files');
       foreach($aFiles as &$aFile)
       {

           $sExt = pathinfo($aFile['FileName'], PATHINFO_EXTENSION);
           if (in_array($sExt, $imgExts))
           {

               $iImage = $oFilesStorage->storeFileFromUrl($sFileUrl . $aFile['FileName'], false, $iProfileId, (int)$iPostID);
               if ($iImage) {
                   $oFilesStorage->afterUploadCleanup($iImage, $iProfileId);
                   $sQuery = $this->_oDb->prepare("UPDATE `{$this -> _sTableWithTransKey}` SET `thumb` = ? WHERE `id` = ?", $iImage, $iPostID);
                   $this->_oDb->query($sQuery);
               }
           }
           else
           {
               $iFile = $oFilesStorage->storeFileFromUrl($sFileUrl . $aFile['FileName'], false, $iProfileId, (int)$iPostID);
               if ($iFile)
                   $oFilesStorage->afterUploadCleanup($iFile, $iProfileId);
           }
       }
    }
	
	public function removeContent()
	{
		if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;
		
		$aRecords = $this -> _oDb -> getAll("SELECT * FROM `{$this -> _sTableWithTransKey}` WHERE `{$this -> _sTransferFieldIdent}` !=0");
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
