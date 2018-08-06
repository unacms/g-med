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

bx_import('BxDolProfile');
bx_import('BxDolTranscoderImage');
bx_import('BxDolStorage');
	
require_once('BxMSSQLMData.php');	
	
class BxMSSQLMProfiles extends BxMSSQLMData
{
    var $_sPwdFlagField = 'use_old_pwd';

    public function __construct(&$oMigrationModule, &$seDb)
    {
		parent::__construct($oMigrationModule, $seDb);
		$this -> _sModuleName = 'profiles';
		$this -> _sTableWithTransKey = 'sys_accounts';
    }

	public function getTotalRecords()
	{
		return (int)$this -> _mDb -> getOne("SELECT COUNT(*) FROM [" . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "]");
	}
	
	public function runMigration()
	{
		if (!$this -> getTotalRecords())
		{
			  $this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	          return BX_MIG_SUCCESSFUL;
		}	
		
		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profiles'));
        $sError = $this -> profilesMigrtion();
        if($sError) {
              $this -> setResultStatus($sError);
              return BX_MIG_FAILED;
        }
	
        $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profiles_finished', $this -> _iTransferred));
		return BX_MIG_SUCCESSFUL;
    }
	
	/**
	* Check if profile with the same nickname already exists
	* @param string $sNickName name of the user
	* @return string
         */ 	 
	private function isProfileExisted($sNickName, $sEmail)
	{
         $sQuery  = $this -> _oDb -> prepare("SELECT COUNT(*) FROM `sys_accounts` WHERE `name` = ? OR `email` = ?", $sNickName, $sEmail);
         return $this -> _oDb -> getOne($sQuery) ? true : false;
	}

    protected function createOldPwdFiledFlag()
    {
        if (!$this -> _sTableWithTransKey)
            return false;

        if ($this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sPwdFlagField))
            return true;

        return $this -> _oDb -> query("ALTER TABLE `{$this -> _sTableWithTransKey}` ADD `{$this -> _sPwdFlagField}` tinyint(1) unsigned NOT NULL default '0'");
    }

	function profilesMigrtion()
    {
			$this -> createMIdField();
		
			// get ID of the latest transferred profile from G-Med
			$iProfileID = $this -> getLastMIDField();

			$this-> createOldPwdFiledFlag();

			$sStart = '';
			if ($iProfileID) 				
				$sStart = " AND ID > {$iProfileID}";

			$aResult = $this -> _mDb -> getAll("SELECT TOP (10) * FROM ["  . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "] WHERE [Email] <> '' AND [ID] = '1146846' {$sStart} ORDER BY ID ASC");

			$oLanguage = BxDolLanguages::getInstance();
			foreach($aResult as $iKey => $aValue)
			{                  

			    $sDateReg  = strtotime($aValue['RegisterDate']);

				if($aValue['UserName'] && !$this -> isProfileExisted($aValue['UserName'], $aValue['Email']))
				  {					
					$sQuery = $this -> _oDb -> prepare( 
                     "
                     	INSERT INTO
                     		`sys_accounts`
                     	SET
                     		`name`   			= ?,
                     		`email`      		= ?,
                     		`password`   		= ?,
                     		`salt`		   		= '',
							`added`				= ?,
                     		`changed`	  		= ?,
                     		`logged` 			= ?,
							`email_confirmed`	= 1,
							`receive_updates`	= 1,	
							`receive_news`		= 1,
							`{$this -> _sPwdFlagField}` = 1
							
                     ",
                        /*`lang_id`			= ?,*/
						$aValue['UserName'],
						$aValue['Email'], 
						$aValue['Password'],
						/*$aValue['LangID'],*/
                        $sDateReg,
                        $sDateReg,
                        $sDateReg
						);
						
					$this -> _oDb -> query($sQuery);										
					$iAccountId = $this -> _oDb -> lastId();
					if (!$iAccountId) 
						continue;
					
					$this -> setMID($iAccountId, $aValue['ID']);
					
					$sFullName = isset($aValue['Native_FullName']) ? $aValue['Native_FullName'] : $aValue['FullName'];
					
					$sQuery = $this -> _oDb -> prepare( 
	                     "
	                     	INSERT INTO
	                     		`bx_persons_data`
	                     	SET
	                     		`author`   			= 0,
	                     		`added`      		= ?,
	                     		`changed`   		= ?,
								`picture`			= 0,		
	                     		`cover`				= 0,
								`fullname`			= ?,
								`birthday`			= ?,
								`gender`			= 1
	                     ",
                            $sDateReg,
                            $sDateReg,
							$sFullName,
							isset($aValue['BirthDay']) && $aValue['BirthDay']? strtotime($aValue['BirthDay']) : 'NULL'
							);
						
						$this -> _oDb -> query($sQuery);	
						$iContentId = $this -> _oDb -> lastId();
						
						$this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = {$iAccountId}, `type` = 'system', `content_id` = {$iContentId}, `status` = 'active'");
						$this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = {$iAccountId}, `type` = 'bx_persons', `content_id` = {$iContentId}, `status` = 'active'");
						$iProfile = $this -> _oDb -> lastId();

						if($iProfile)
							BxDolAccountQuery::getInstance() -> updateCurrentProfile($iAccountId, $iProfile);
						
						$sQuery = $this -> _oDb -> prepare("UPDATE `bx_persons_data` SET `author` = ? WHERE `id` = ?", $iProfile, $iContentId);						
						$this -> _oDb -> query($sQuery);

						$this -> exportAvatar($iContentId, $aValue);

				    	$this -> _iTransferred++;
                  }
             }

        }

	private function exportAvatar($iProfileId, $aProfileInfo)
    {
       $iProfileId = (int) $iProfileId;
       $sId = $this -> _mDb -> getOne("SELECT [FileName] FROM [UsersImages] WHERE [UserID] = :id", array('id' => $aProfileInfo['ID']));
       if (!$sId)
           return FALSE;
       $sAvatarPath = "http://gmed.imgix.net/userimages/$sId";

		$oStorage = BxDolStorage::getObjectInstance('bx_persons_pictures');
		$iId = $oStorage->storeFileFromUrl($sAvatarPath, false, $iProfileId);
		if ($iId)
		{
			$sQuery = $this -> _oDb -> prepare("UPDATE `bx_persons_data` SET `Picture` = ? WHERE `id` = ?", $iId, $iProfileId);
			$this -> _oDb -> query($sQuery);
		}
    }

	public function removeContent()
	{
		if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;
		
		$iNumber = 0;
		$aRecords = $this -> _oDb -> getAll("SELECT * FROM `{$this -> _sTableWithTransKey}` WHERE `{$this -> _sTransferFieldIdent}` !=0");
		if (!empty($aRecords))
		{
			foreach($aRecords as $iKey => $aValue)
			{
				$oAccount = BxDolAccount::getInstance($aValue['id']);
				if ($oAccount)
					$oAccount -> delete(true);			
				
				$iNumber++;
			}
					
			if ($iNumber)
			foreach ($this -> _oConfig -> _aMigrationModules as $sName => $aModule)
			{
				//Create transferring class object
				require_once($aModule['migration_class'] . '.php');			
				$oObject = new $aModule['migration_class']($this -> _oMainModule, $this -> _mDb);			
				$oObject -> removeContent();				
			}
		}	
		
		parent::removeContent();
		return $iNumber;
	}
	
}

	
/** @} */
