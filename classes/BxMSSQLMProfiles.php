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
    private $_iMagazinMembersId = 10;//10;
    private $_iHealthyDayAccountId = 0;

    public function __construct(&$oMigrationModule, &$seDb)
    {
		parent::__construct($oMigrationModule, $seDb);
		$this -> _sModuleName = 'profiles';
		$this -> _sTableWithTransKey = 'sys_accounts';
        $this -> _iHealthyDayProfileId = $this -> _oConfig -> getHealthyProfileId();
        $this -> _iMagazinMembersId = $this -> _oConfig -> getMagazineMembershipId();
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

    protected function getLastPersonsID()
    {
        if (!$this -> _sTableWithTransKey)
            return false;

        if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
            return false;

        return (int)$this -> _oDb -> getOne("SELECT `id` FROM `bx_persons_data` WHERE `id` <> :id ORDER BY `id` DESC LIMIT 1", array('id' => $this -> _iHealthyDayProfileId));
    }

	function profilesMigrtion()
    {
			$this -> createMIdField();

			// get ID of the latest transferred profile from G-Med
			$iProfileID = $this -> getLastMIDField($this -> _iHealthyDayProfileId);

			$this-> createOldPwdFiledFlag();

			$sStart = '';
            $aHealthyDay = array();
			if ($iProfileID)
				$sStart = " AND [u].[ID] > {$iProfileID}";
			else
		        $aHealthyDay = $this -> _mDb -> getAll("SELECT [Users].[ID],[UserName],[Email],[Password],[RegisterDate],[FullName],[LastName],[MiddelName],[BirthDay],[FirstName],[UserTypeID]
                                                        ,[StateName],[CityName],[Countries].[CountryName],[ZipCode]  
                                                        ,[Users].[CountryID]
                                                        ,[Users].[StateID]
                                                        ,[CityID]
                                                FROM ["  . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "]
                                                LEFT JOIN [States] ON [States].[ID] = [Users].[StateID]
                                                LEFT JOIN [Cities] ON [Cities].[ID] = [Users].[CityID]
                                                LEFT JOIN [Countries] ON [Countries].[ID] = [Users].[CountryID]
                                                WHERE [Users].[ID] = :id", array('id' => $this -> _iHealthyDayProfileId));

            /*$aResult = $this -> _mDb -> getAll("SELECT [UserName],[Email],[Password],[RegisterDate],[ID],[FullName],[LastName],[MiddelName],[BirthDay],[FirstName],[UserTypeID]
                                                FROM ["  . $this -> _oConfig -> _aMigrationModules[$this -> _sModuleName]['table_name'] . "]
                                                WHERE [UserTypeID] IN (0,1,2,3,4,8) AND [ID] != :id
                                                ORDER BY [ID] ASC", array('id' => $this -> _iHealthyDayProfileId)); */

			$aResult = $this -> _mDb -> getAll("SELECT TOP 2000
													[u].[ID],[p].[posts],[UserName],[Email],[Password],[RegisterDate],[FullName],[LastName],[MiddelName],[BirthDay],[FirstName],[UserTypeID]
													,[StateName],[CityName],[Countries].[CountryName],[ZipCode]  
                                                    ,[u].[CountryID]
                                                    ,[u].[StateID]
                                                    ,[CityID]            
													FROM [Users] as [u]
													LEFT JOIN (
														SELECT 
															   
																								   [UserID],
																								   COUNT(*) as [posts]
																								FROM [Channels_Posts] as [p]
																								LEFT JOIN (SELECT COUNT(*) as [count], [PostID] FROM [Posts_Files] WHERE [FileType] != 1 AND [FileType] IS NOT NULL GROUP BY [PostID]) as [f] ON [f].[PostID] = [p].[ID]
																								LEFT JOIN [Users] as [u] ON [u].[ID] = [p].[UserID]
																								LEFT JOIN [HealthdayPosts] as [h] ON [h].[PostID] = [p].[ID] 
																								WHERE [MainParentID] IS NULL AND NOT (([Post_Title] = '' OR [Post_Title] IS NULL) AND ([Post_Content] = '' OR [Post_Content] IS NULL))																								
																								GROUP BY [UserID]
													) as [p] ON [p].[UserID] = [u].[ID]
													 LEFT JOIN [States] ON [States].[ID] = [u].[StateID]
                                                     LEFT JOIN [Cities] ON [Cities].[ID] = [u].[CityID]
                                                     LEFT JOIN [Countries] ON [Countries].[ID] = [u].[CountryID]
													WHERE [p].[posts] <> 0 AND [UserTypeID] IN (0,1,2,3,4,8) AND [u].[ID] > 192 AND [u].[ID] != :id {$sStart}
													ORDER BY [u].[ID], [p].[posts] DESC", array('id' => $this -> _iHealthyDayProfileId));

			$aResult = array_merge($aHealthyDay, $aResult);
			foreach($aResult as $iKey => $aValue)
			{
			    $sDateReg  = strtotime($aValue['RegisterDate']);
                $iAccountId = 0;
			    if($aValue['UserTypeID'] != 2)
			    {
                    $sSalt = '';
                    $sEmail = isset($aValue['Email']) && $aValue['Email'] ? $aValue['Email'] : "unknown_{$aValue['ID']}@dummy-domain.com";

                    if ($this -> isProfileExisted($aValue['Email']))
                        continue;

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
                    else {
                        if (!$this -> _iHealthyDayAccountId)
                             $this->_iHealthyDayAccountId = $this->getAccountIdByContentId($this->_iHealthyDayProfileId);

                        $iAccountId = $this->_iHealthyDayAccountId;
                    }

                   //$iAccountId = $iAccountId ? $iAccountId : $this -> _iHealthyDayAccountId;
                   //$iAccountId = $iAccountId ? $iAccountId : $this-> getAccountIdByContentId($this -> _iHealthyDayProfileId);

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

						if($iProfile) {
                            BxDolAccountQuery::getInstance()->updateCurrentProfile($iAccountId, $iProfile);

                            if ((int)$aValue['UserTypeID'] == 2)
                            $this -> _oDb -> query(
                                "INSERT INTO
                                        `sys_acl_levels_members`
                                    SET
                                        `IDMember` = :profile,
                                        `IDLevel` = :level,
                                        `DateStarts` = NOW()                                        
                                 ",
                                array('profile' => $iProfile, 'level' => $this -> _iMagazinMembersId)
                            );
                        }

						$sQuery = $this -> _oDb -> prepare("UPDATE `bx_persons_data` SET `author` = ? WHERE `id` = ?", $iProfile, $iContentId);
						$this -> _oDb -> query($sQuery);

						$this -> exportAvatar($aValue, $iContentId, $iProfile);

                        $this -> transferSpecialities($aValue['ID']);

                        $sCountryCode = '';
						if ($aValue['CountryName'])
                            $sCountryCode = $this -> getCountryIOS($aValue['CountryName']);

						if ($sCountryCode || $aValue['CityName'] || $aValue['CountryName'] || $aValue['StateName'] || $aValue['ZipCode'])
						$this -> _oDb -> query("INSERT INTO `bx_persons_meta_locations` 
                            SET 
                                `object_id`=:object, 
                                `country`=:country, 
                                `state`=:state, 
                                `city`=:city, 
                                `zip`= :zip",
                            array('object' => $aValue['ID'],
                                  'country' => $sCountryCode,
                                  'state' => isset($aValue['StateName']) ? $aValue['StateName'] : '',
                                  'city' => isset($aValue['CityName']) ? $aValue['CityName'] : '',
                                  'zip' => isset($aValue['ZipCode']) ? $aValue['ZipCode'] : ''));

				    	$this -> _iTransferred++;
             }
    }

    private function transferSpecialities($iProfileId){
        $aResult = $this -> _mDb -> getAll("SELECT   [Users].[ID], 
                            [Doctors].[ID], 
                            [e].ID as [ex_id], 
                            [e].ExpertiseName,
                            [se].[ID] as [sex_id], 
                            [se].[SubExpertiseName],
                            [d].[ID] as[di_id],
                            [d].[DisciplineName]
                            FROM [Users] 
                            LEFT JOIN [Doctors] ON [Users].[DoctorID] = [Doctors].[ID] AND [DoctorID] IS NOT NULL
                            LEFT JOIN [Doctors_Expertises] as [de] ON [de].[DoctorID] = [Doctors].[ID]
                            LEFT JOIN [Expertises] as [e] ON [e].[ID] = [de].[ExpertiseID]
                            LEFT JOIN [SubExpertises] as [se] ON [se].[ID] = [de].[SubExpertiseID]
                            LEFT JOIN [Doctors_Expertises_Disciplines] as [ded] ON [ded].[DoctorsExpertisesID] = [de].[ID]
                            LEFT JOIN [Disciplines] as [d] ON [d].[ID] = [ded].[DisciplineID]
                            WHERE [Users].[ID] = :profile
                            ORDER BY [Users].[ID]", array('profile' => $iProfileId));

        if (empty($aResult))
            return false;

        $aResults = array(array(), array(), array());
        foreach($aResult as &$aSpec) {
            if (isset($aSpec['ex_id']) && (int)$aSpec['ex_id'] && !isset($aResults[0][$aSpec['ex_id']]))
                $aResults[0][$aSpec['ex_id']] = $aSpec['ExpertiseName'];

            if (isset($aSpec['sex_id']) && (int)$aSpec['sex_id'] && !isset($aResults[1]["{$aSpec['ex_id']}-{$aSpec['sex_id']}"]))
                $aResults[1]["{$aSpec['ex_id']}-{$aSpec['sex_id']}"] = $aSpec['SubExpertiseName'];

            if (isset($aSpec['di_id']) && (int)$aSpec['di_id'] && !isset($aResults[2]["{$aSpec['sex_id']}-{$aSpec['di_id']}"]))
                $aResults[2]["{$aSpec['sex_id']}-{$aSpec['di_id']}"] = $aSpec['DisciplineName'];

        }

        if (!empty($aResults[0])) {
            $sResult = '["' . implode(',', array_keys($aResults[0])) . '","' . implode(',', array_keys($aResults[1])) . '","' . implode(',', array_keys($aResults[2])) . '",""]';

            $sSp1 = implode(',', array_values($aResults[0]));
            $sSp2 = implode(',', array_values($aResults[1]));
            $sSp3 = implode(',', array_values($aResults[2]));

            $sQuery = $this->_oDb->prepare("UPDATE `bx_persons_data` 
            SET 
              `speciality` = ?,
              `specialityL1` = ?,
              `specialityL2` = ?,
              `specialityL3` = ? 
            WHERE `id` = ?", $sResult, $sSp1, $sSp2, $sSp3, $iProfileId);

            return $this->_oDb->query($sQuery);
        }

        return false;
	}

    private function getCountryIOS($sCountry){
	    $aCountry = preg_split('[\s,]', $sCountry, -1, PREG_SPLIT_NO_EMPTY);

	    return $this -> _oDb -> getOne("SELECT `Value` FROM `sys_form_pre_values` WHERE `LKey` LIKE '%" . bx_process_input($aCountry[0]) . "%'");
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
