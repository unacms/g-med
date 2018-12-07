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
    private $_sPwdFlagField = 'use_old_pwd';
    private $_iHealthyDayProfileId = 639152;
    private $_iHealthyDayAccountId = 0;

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
	private function isProfileExisted($sEmail)
	{
         $sQuery  = $this -> _oDb -> prepare("SELECT COUNT(*) FROM `sys_accounts` WHERE `email` = ?", $sEmail);
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
				$sStart = " AND [ID] > {$iProfileID}";

		    $aHealthyDay = $this -> _mDb -> getAll("SELECT [UserName],[Email],[Password],[RegisterDate],[ID],[FullName],[LastName],[MiddelName],[BirthDay],[FirstName] 
                                                FROM ["  . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "]
                                                WHERE [ID] = :id", array('id' => $this -> _iHealthyDayProfileId)); /* WHERE [ID] IN (75054, 4, 10396, 642945, 1142515, 1143586, 1144789, 1148522, 73231, 120003, 120332, 120142, 1148522, 84831, 80125, 85834, 77468, 74028, 84310, 84359, 84159, 1148522, 120021, 84047, 84118, 22184, 474661, 81226, 83461, 76859, 84742, 1148708, 81393,43845, 81983,119890,72620,82167,640807) AND  OR (StateID <> '' AND CityID <> '') */

            $aResult = $this -> _mDb -> getAll("SELECT [UserName],[Email],[Password],[RegisterDate],[ID],[FullName],[LastName],[MiddelName],[BirthDay],[FirstName],[UserTypeID] 
                                                FROM ["  . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "]
                                                WHERE [UserTypeID] IN (1,2,3,4,8) AND [ID] != :id 
                                                ORDER BY [ID] ASC", array('id' => $this -> _iHealthyDayProfileId)); /*AND [ID] IN (4,38717,16,640474,12,640036,39324,38666,39401,18,26,639970,17,40996,8,9,11,38688,43191,38695,39339,53087,640481,23,43897,39236,42685,5,42322,640482,19,39338,14,39298,41007,39293,43557,39363,42289,41775,39296,43892,13,39331,6,43930,38640,39220,39268,38667,39231,41769,15,39284,40978,38897,43940,39278,53074,635555,43946,39318,39344,21,39282,32,42,38633,35,38885,38899,639956,38669,39276,39310,39381,43963,38646,39235,40990,42562,41004,39264,39267,42554,42341,43941,647274,43962,53080,639976,39265,39332,639977,639989,39223,41000,3,644192)*/

            $aResult = array_merge($aHealthyDay, $aResult);
		    foreach($aResult as $iKey => $aValue)
			{                  

			    $sDateReg  = strtotime($aValue['RegisterDate']);
                $iAccountId = 0;
			    if(!(!$aValue['Email'] && $aValue['UserTypeID'] == 2) && !$this -> isProfileExisted($aValue['Email'])) {
                    $sSalt = '';
                    $sEmail = isset($aValue['Email']) && $aValue['Email'] ? $aValue['Email'] : "unknown_{$aValue['ID']}@dummy-domain.com";
                    if (!$aValue['Password']) {
                        $sSalt = genRndSalt();
                        $aValue['Password'] = encryptUserPwd($sEmail, $sSalt);
                    }

                    $sQuery = $this->_oDb->prepare(
                        "
                     	INSERT INTO
                     		`sys_accounts`
                     	SET
                     		`name`   			= ?,
                     		`email`      		= ?,
                     		`password`   		= ?,
                     		`salt`		   		= ?,
							`added`				= ?,
                     		`changed`	  		= ?,
                     		`logged` 			= ?,
							`email_confirmed`	= 1,
							`receive_updates`	= 1,	
							`receive_news`		= 1,
							`{$this -> _sPwdFlagField}` = ?
							
                     ",
                        $aValue['UserName'] ? $aValue['UserName'] : $aValue['FirstName'],
                        $sEmail,
                        $aValue['Password'],
                        $sSalt,
                        $sDateReg,
                        $sDateReg,
                        $sDateReg,
                        $sSalt ? 0 : 1
                    );
                        $this -> _oDb -> query($sQuery);
                        $iAccountId = $this -> _oDb -> lastId();

                        if (!$this -> _iHealthyDayAccountId && $aValue['ID'] == $this -> _iHealthyDayProfileId)
                            $this -> _iHealthyDayAccountId = $iAccountId;

			        }
                    else if (!$aValue['Email'] && $aValue['UserTypeID'] == 2) {
                        $iAccountId = $this-> getAccountIdByContentId();
                    }

                   $iAccountId = $iAccountId ? $iAccountId : $this -> _iHealthyDayAccountId;
                   $iAccountId = $iAccountId ? $iAccountId : $this-> getAccountIdByContentId($this -> _iHealthyDayProfileId);

                    $this -> setMID($iAccountId, $aValue['ID']);
					$sFirstName =  isset($aValue['FirstName']) && $aValue['FirstName'] ? $aValue['FirstName'] : '';
					$sMiddelName =  isset($aValue['MiddelName']) && $aValue['MiddelName'] ? $aValue['MiddelName'] : '';
					$sLastName =  isset($aValue['LastName']) && $aValue['LastName'] ? $aValue['LastName'] : '';

					$sFullName = "{$sFirstName} {$sMiddelName} {$sLastName}";
					$sFullName = $sFullName ? $sFullName : $aValue['UserName'];
					$sFullName = $sFullName ? $sFullName : substr(0, stripos($aValue['Email'], '@'));

					$sQuery = $this -> _oDb -> prepare(
	                     "
	                     	INSERT INTO
	                     		`bx_persons_data`
	                     	SET
	                     		`id`                = ?,
	                     	    `author`   			= 0,
	                     		`added`      		= ?,
	                     		`changed`   		= ?,
								`picture`			= 0,		
	                     		`cover`				= 0,
								`fullname`			= ?,
								`birthday`			= ?,
								`gender`			= 1
	                     ",
                            $aValue['ID'],
                            $sDateReg,
                            $sDateReg,
							$sFullName,
							isset($aValue['BirthDay']) && $aValue['BirthDay']? strtotime($aValue['BirthDay']) : 'NULL'
						 );
						
						$this -> _oDb -> query($sQuery);	
						$iContentId = $aValue['ID'];//$this -> _oDb -> lastId();
						
						$this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = {$iAccountId}, `type` = 'system', `content_id` = {$iContentId}, `status` = 'active'");
						$this -> _oDb -> query("INSERT INTO `sys_profiles` SET `account_id` = {$iAccountId}, `type` = 'bx_persons', `content_id` = {$iContentId}, `status` = 'active'");
						$iProfile = $this -> _oDb -> lastId();

						if($iProfile)
							BxDolAccountQuery::getInstance() -> updateCurrentProfile($iAccountId, $iProfile);
						
						$sQuery = $this -> _oDb -> prepare("UPDATE `bx_persons_data` SET `author` = ? WHERE `id` = ?", $iProfile, $iContentId);						
						$this -> _oDb -> query($sQuery);

						$this -> exportAvatar($aValue, $iContentId, $iProfile);

				    	$this -> _iTransferred++;

             }

    }

	private function exportAvatar($aProfileInfo, $iContentId, $iProfileId)
    {
       $sId = $this -> _mDb -> getOne("SELECT [FileName] FROM [UsersImages] WHERE [UserID] = :id", array('id' => $aProfileInfo['ID']));
       if (!$sId)
           return FALSE;
       $sAvatarPath = "http://gmed.imgix.net/userimages/$sId";

		$oStorage = BxDolStorage::getObjectInstance('bx_persons_pictures');
		$iId = $oStorage->storeFileFromUrl($sAvatarPath, false, $iProfileId);
		if ($iId)
		{
			$sQuery = $this -> _oDb -> prepare("UPDATE `bx_persons_data` SET `Picture` = ? WHERE `id` = ?", $iId, $iContentId);
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
