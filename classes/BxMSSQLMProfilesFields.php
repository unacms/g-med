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
bx_import('BxDolStudioLanguagesUtils');

class BxMSSQLMProfilesFields extends BxMSSQLMData
{
	/**
	 *  @var  $_aProfileFields profile fields for transfer
	 */
	private $_aProfileFields = array(
	    /*'LangID' => array(
	         'name' => 'Language',
             'sql' => 'int(10) default NULL',
             'type' => 'select',
             'db_pass' => 'Xss',
             'keys' => '#!Languages'
            ),*/

        'DocotrTypeID' => array(
            'name' => 'Title',
            'type' => 'text',
            'sql' => "varchar(10) default ''",
            'db_pass' => 'Xss',
            'function' => 'getTitle'
        ),
        'CountryID' => array(
            'name' => 'Country',
            'type' => 'select',
            'sql' => "varchar(2) default ''",
            'db_pass' => 'Xss',
            'keys' => '#!Country',
            'function' => 'getCountry'
        ),
        'CityID' =>  array(
            'name' => 'City',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!Cities'
        ),
        'StateID' => array(
            'name' => 'State',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!State'
        ),
        'Expertises' =>  array(
            'name' => 'Specialties',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!Expertises',
            'function' => 'getExpertise'
        ),
		'SubExpertises' =>  array(
            'name' => 'SubSpecialty',
            'type' => 'checkbox_set',
            'sql' => "text default ''",
            'db_pass' => 'Multy',
            'keys' => '#!SubExpertises',
            'function' => 'getSubExpertise'
        ),
        /*'ExpertiseDate' =>  array(
            'name' => 'ExpertiseDate',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'function' => 'getExpertiseDate'
        ),
        'ZipCode' =>  array(
            'sql' => "varchar(20) default ''",
            'type' => 'text',
            'name' => 'ZipCode',
            'db_pass' => 'Xss',
             ),
        'Street' =>  array(
            'name' => 'Address',
            'type' => 'text',
            'sql' => "varchar(255) default ''",
            'db_pass' => 'Xss',
                    ),
        'LicenseNumber' =>  array(
            'name' => 'LicenseNumber',
            'type' => 'text',
            'sql' => "varchar(255) default ''",
            'db_pass' => 'Xss',
                    ),
        'LicenseDate' =>  array(
            'name' => 'LicenseDate',
            'type' => 'text',
            'db_pass' => 'Date',
            'sql' => 'date default NULL',
                    ),
        'YearsOfPractice' =>  array(
            'name' => 'YearsOfPractice',
            'type' => 'text',
            'db_pass' => 'Xss',
            'sql' => "varchar(10) default ''"
                    ),*/
        'PhoneNumber' =>  array(
            'name' => 'Phone',
            'type' => 'text',
            'db_pass' => 'Xss',
            'hidden' => 1,
            'sql' => "varchar(20) default ''",
        ),
        /*'PublicContact_WebSite' =>  array(
            'name' => 'WebSite',
            'type' => 'text',
            'db_pass' => 'Xss',
            'sql' => "varchar(255) default ''"
                    ),
        'PublicContact_Email' =>  array(
            'name' => 'Email2',
            'type' => 'text',
            'db_pass' => 'Xss',
            'sql' => "varchar(255) default ''"
                    ),
        'ProfessionalLevel' =>  array(
            'name' => 'ProfessionalLevel',
            'type' => 'text',
            'db_pass' => 'Xss',
            'sql' => "varchar(255) default ''"
        ),
        'Educations' =>  array(
            'name' => 'Educations',
            'type' => 'textarea',
            'db_pass' => 'XssMultiline',
            'sql' => "text default ''",
            'function' => 'getEducation'
        ),
        'Interests' =>  array(
            'name' => 'Interests',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!Interests',
            'function' => 'getInterests'
        ),
        'Degrees' =>  array(
            'name' => 'Degrees',
            'type' => 'select',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!Degrees',
            'function' => 'getDegrees'
        ),
        'Disciplines' =>  array(
            'name' => 'Discipline',
            'type' => 'checkbox_set',
            'sql' => 'int(10) default NULL',
            'db_pass' => 'Xss',
            'keys' => '#!Disciplines',
            'function' => 'getDisciplines'
        )*/
    );


    public function __construct($oMigrationModule, &$seDb)
    {
        parent::__construct($oMigrationModule, $seDb);  
		$this -> _sModuleName = 'profile_fields';
		$this -> _sTableWithTransKey = 'sys_form_inputs';	
    }

	public function getTotalRecords()
	{

		return sizeof($this -> _aProfileFields);
	}
	
	private function transferTablesToPreValues(){
	    $this -> transferStates();
	    $this -> transferCities();
	    $this -> transferExpertises();
        $this -> transferSubExpertises();
	    /*$this -> transferInterests();
	    $this -> transferLanguages();*/
        //$this -> transferDisciplines();
        /*$this -> transferPositions();
        $this -> transferMedschools();*/
	}

    public function runMigration()
	{		 
		if (!$this -> getTotalRecords())
		{
			$this -> setResultStatus(_t('_bx_mssql_migration_no_data_to_transfer'));
	        return BX_MIG_SUCCESSFUL;			  
		}

		$this -> transferTablesToPreValues();
		$this -> createMIdField();
		$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profile_fields'));
		$i = 0;

		foreach($this -> _aProfileFields as $sKey => $aItem)
		{
			$sTransferName = $aItem['name'];
		    if (!$this -> _oDb -> isFieldExists('bx_persons_data', $sTransferName))
			{
				   if (!$this -> _oDb -> query("ALTER TABLE `bx_persons_data` ADD {$sTransferName} {$aItem['sql']}")){
						 $this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profile_field_can_not_be_transferred'));
						 return BX_MIG_FAILED;
				   }			   
			}
			  
			//continue;
			if ($this -> isFieldTransfered($sTransferName))
				continue;
			   
			$iKeyID = time() + $i++; // unique language keys postfix			
			$sQuery = $this -> _oDb -> prepare("
				INSERT INTO `{$this -> _sTableWithTransKey}` SET
					`object`	= 'bx_person', 
					`module`	= 'custom',
					`values`	= ?,
					`name`		= ?,
					`db_pass`	= ?,
					`type`		= ?,
					`caption_system` = ?,
					`caption`	= ?",
                    isset($aItem['keys']) ? $aItem['keys'] : '',
                    $sTransferName,
                    $aItem['db_pass'],
                    $aItem['type'],
					'_sys_form_txt_field_caption_system_' . $iKeyID,
					'_sys_form_txt_field_caption_' . $iKeyID
					);
			
			if (!$this -> _oDb -> query($sQuery))
			{
				$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profile_field_can_not_be_transferred'));
				return BX_MIG_FAILED;				
			}			   
			
			$iFieldId = $this -> _oDb -> lastId();
			$this -> setMID($iFieldId, $iFieldId);
			
			// create form fields	
			$this -> _oDb -> query($sQuery);
			
			$sQueryDisplay = "INSERT INTO `sys_form_display_inputs` (`id`, `display_name`, `input_name`, `visible_for_levels`, `active`, `order`) VALUES";			
			if (!isset($aItem['hidden'])) {
                $sQueryDisplay .= "(NULL, 'bx_person_view', '{$sTransferName}', 2147483647, 1, 0),";
            }

            $sQueryDisplay .= "(NULL, 'bx_person_add', '{$sTransferName}', 2147483647, 1, 0),";
            $sQueryDisplay .= "(NULL, 'bx_person_edit', '{$sTransferName}', 2147483647, 1, 0),";

			// add display for view and add forms
			$this -> _oDb -> query(trim($sQueryDisplay, ','));
			// add language keys with translations
			
			foreach($this -> _aLanguages as $sLangKey => $sValue)
			{
				$sTitle = $sTransferName;
				$this -> _oLanguage -> addLanguageString('_sys_form_txt_field_caption_system_' . $iKeyID, $sTitle, $this -> _aLanguages[$sLangKey], 0, false);
				$this -> _oLanguage -> addLanguageString('_sys_form_txt_field_caption_' . $iKeyID, $sTitle, $this -> _aLanguages[$sLangKey]);
			}
			
				$this -> _iTransferred++;
		}
			
		$this -> migrateProfileFieldsInfo();
			
		//$this -> setResultStatus(_t('_bx_mssql_migration_started_migration_profile_fields_finished', $this -> _iTransferred));
		return BX_MIG_SUCCESSFUL;
	}

	private function isFieldTransfered($sName)
	{
		$sQuery = $this -> _oDb -> prepare("SELECT COUNT(*) FROM `sys_form_inputs` WHERE `object` = 'bx_person' AND `module` = 'custom' AND `name` = ? LIMIT 1", $sName);
		return (int)$this -> _oDb -> getOne($sQuery) > 0;
	}        
			
	/**
	* Returns Una profile fields list
	* @param int $iProfileId profile ID
	* @return array
	*/ 
	private function getPersonsFieldValues($iProfileId)
	{
		return $this -> _oDb -> getRow("SELECT * FROM `bx_persons_data` WHERE `id` = {$iProfileId} LIMIT 1");			
	}		
	
	private function getExpertise($iMigProfileId){
		$aResult = $this -> _mDb -> getPairs("SELECT
                                            [Expertises].[ID]
                                            FROM [USERS] 
                                            LEFT JOIN [Doctors_Expertises] ON [USERS].[DoctorID] = [Doctors_Expertises].[DoctorID]
                                            LEFT JOIN [Expertises] ON [Expertises].[ID] = [Doctors_Expertises].[ExpertiseID]                                            
                                            WHERE [USERS].[ID] = :id
                                            GROUP BY [Expertises].[ID]", 'ID', 'ID', array('id' => $iMigProfileId));
	    return empty($aResult) ? '' : implode(',', $aResult);
	}

	private function getDisciplines($iMigProfileId){
		$aResult = $this -> _mDb -> getAll("SELECT
                                            [Expertises].[ID]
                                            FROM [USERS] 
                                            LEFT JOIN [Doctors_Expertises] ON [USERS].[DoctorID] = [Doctors_Expertises].[DoctorID]
                                            LEFT JOIN [Expertises] ON [Expertises].[ID] = [Doctors_Expertises].[ExpertiseID]                                            
                                            WHERE [USERS].[ID] = :id", array('id' => $iMigProfileId));
	    return empty($aResult) ? '' : implode(',', $aResult);
	}

	private function getCountry($iMigProfileId, $aUser){
		$sCountry = $this -> _mDb -> getOne("SELECT
                                            [CountryName]
                                            FROM [Countries] 
                                            WHERE [ID] = :id", array('id' => $aUser['CountryID']));
		if (!$sCountry)
		    return '';

        $sCountry = strtolower($sCountry);
        $sCountry = $this -> _oDb -> getOne("SELECT `Value` FROM `sys_form_pre_values` WHERE LKey LIKE '%{$sCountry}%'");
	    return $sCountry ? $sCountry : '';
	}

	private function getTitle($iMigProfileId, $aUser){
		$iType = $this -> _mDb -> getOne("SELECT
                                              [DocotrTypeID]
                                            FROM [Doctors] 
                                            WHERE [ID] = :id", array('id' => $aUser['DoctorID']));
		if (!(int)$iType)
		    return '';

	    return (int)$iType == 1 ? 'Dr' : 'Prof';
	}

    private function getSubExpertise($iMigProfileId){
        $aResult = $this -> _mDb -> getPairs("SELECT
                                            [SubExpertises].[ID] 
                                            FROM [USERS]
                                            LEFT JOIN [Doctors_Expertises] ON [USERS].[DoctorID] = [Doctors_Expertises].[DoctorID]
                                            LEFT JOIN [Expertises] ON [Expertises].[ID] = [Doctors_Expertises].[ExpertiseID]
                                            LEFT JOIN [Expertises_SubExpertises] ON [Expertises_SubExpertises].[ExpertiseID] = [Expertises].[ID]
                                            LEFT JOIN [SubExpertises] ON [SubExpertises].[ID] = [Expertises_SubExpertises].[SubExpertiseID]
                                            WHERE [USERS].[ID] = :id
                                            GROUP BY [SubExpertises].[ID]", 'ID', 'ID', array('id' => $iMigProfileId));

        return empty($aResult) ? '' : implode(',', $aResult);
    }


	/*
	 * SELECT TOP(10)
[USERS].Email,
[Expertises].*,
[SubExpertises].* FROM [USERS]
LEFT JOIN [Doctors_Expertises] ON [USERS].[DoctorID] = [Doctors_Expertises].[DoctorID]
LEFT JOIN [Expertises] ON [Expertises].[ID] = [Doctors_Expertises].[ExpertiseID]
LEFT JOIN [Expertises_SubExpertises] ON [Expertises_SubExpertises].[ExpertiseID] = [Expertises].[ID]
LEFT JOIN [SubExpertises] ON [SubExpertises].[ID] = [Expertises_SubExpertises].[SubExpertiseID]
WHERE [USERS].[ID] = 2770
	 */

	private function convertValues($sFieldName, $aFiled, $iMigId)
	{
        $aUser = $this -> _mDb -> getRow("SELECT * FROM [Users] WHERE [ID] = {$iMigId}");
        if (empty($aUser))
            return false;

        if (($aFiled['type'] == 'text' || $aFiled['type'] == 'select') && !isset($aFiled['function']))
			return $aUser[$sFieldName];

		if (isset($aFiled['function']))
			return call_user_func_array(array($this, $aFiled['function']), array($iMigId, $aUser));
		
		/*if (is_array($mixedFiledValue))
			$aItems = explode(',', $mixedFiledValue);
		else
			$mixedFiledValue
		$aPairs = $this -> getPreValuesBy(substr($aOriginalFields[$aFiled['name']]['values'], 2), 'Value', 'Order');
		$iResult = 0;
		foreach($aItems as $iKey => $sValue)
			$iResult += pow(2, (int)$aPairs[$sValue] - 1);*/
		
		return '';
	}

	/**
	* Migrate profile fields values for transferred profiles
	 *  
	 *  @return void
	 */
	private function migrateProfileFieldsInfo()
    {
		$sQuery = "SELECT * FROM `sys_accounts` WHERE `{$this -> _sTransferFieldIdent}` <> ''";
        $aAccounts = $this -> _oDb -> getAll($sQuery);

		foreach($aAccounts as $iKey => $aProfile)
		{
  		    if (!($iProfileId = $this -> getContentId($aProfile[$this -> _sTransferFieldIdent])))
			   continue;

			$aValues = array();
			$sNewField = '';
			$aCurrentMembersValues = $this -> getPersonsFieldValues($iProfileId);
	        foreach($this -> _aProfileFields as $sFieldName => $aItem)
			{
				if (!empty($aCurrentMembersValues[$aItem['name']]))
					continue;
				
				$sNewField .= "`{$aItem['name']}` = :{$aItem['name']},";
				$aValues[$aItem['name']] = $this -> convertValues($sFieldName, $aItem, $aProfile[$this -> _sTransferFieldIdent]);
			}

			if ($sNewField)
			{
				$sNewField = trim($sNewField, ',');

			    $sQuery = 
				"
					UPDATE
						`bx_persons_data`
					SET
						{$sNewField}
					WHERE
						`id` = {$iProfileId}
				";
				
				$this -> _oDb -> query($sQuery, $aValues);
			}
							
		}	
	}		

	public function removeContent()
	{
		if (!$this -> _oDb -> isTableExists($this -> _sTableWithTransKey) || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;
	
		$aRecords = $this -> _oDb -> getAll("SELECT `ti` . * , `tdi`.`id` AS  `di_id` , `tdi`.`display_name` AS  `display_name` , `tdi`.`visible_for_levels` AS `visible_for_levels` 
											FROM  `sys_form_display_inputs` AS  `tdi` 
											LEFT JOIN  `sys_form_inputs` AS  `ti` ON  `tdi`.`input_name` =  `ti`.`name` 
											WHERE `ti`.`object` =  'bx_person' AND `ti`.`{$this -> _sTransferFieldIdent}` !=0");
		if (!empty($aRecords))
		{
			$iNumber = 0;		
			foreach($aRecords as $iKey => $aValue)
			{
				$sSql = $this -> _oDb -> prepare("DELETE `td`, `tdi` FROM `sys_form_display_inputs` AS `tdi` LEFT JOIN `sys_form_inputs` AS `td` ON `tdi`.`input_name`=`td`.`name` WHERE `td`.`object`='bx_person' AND `td`.`name` = ?", $aValue['name']);
				if ($this-> _oDb -> query($sSql))
				{
					$oLanguage = BxDolStudioLanguagesUtils::getInstance();
					if(!empty($aValue['caption']))
						$oLanguage->deleteLanguageString($aValue['caption']);
					
					if(!empty($aValue['caption_system']))
						$oLanguage->deleteLanguageString($aValue['caption_system']);
					
					if(!empty($aValue['info']))
						$oLanguage->deleteLanguageString($aValue['info']);
					
					if(!empty($aValue['checker_error']))
						$oLanguage->deleteLanguageString($aValue['checker_error']);
				
					(int)$this -> _oDb -> query("ALTER TABLE `bx_persons_data` DROP `{$aValue['name']}`");
					
					$iNumber++;
				}
			}
		}				

		parent::removeContent();
		return $iNumber;
	}

	function transferStates()
    {
        $aStates = $this -> _mDb -> getPairs("SELECT * FROM [States] ORDER BY [ID]", 'ID', 'StateName');
        If (empty($aStates))
            return FALSE;

        return $this -> transferPreValues('State', 'State', $aStates);
    }

    function transferInterests()
    {
        $aInterests = $this -> _mDb -> getPairs("SELECT * FROM [Interests] ORDER BY [ID]", 'ID', 'InterestName');
        If (empty($aInterests))
            return FALSE;

        return $this -> transferPreValues('Interests', 'Interests', $aInterests);
    }

    function transferCities()
    {
        $aCities = $this -> _mDb -> getPairs("SELECT * FROM [Cities] ORDER BY [ID]", 'ID', 'CityName');
		If (empty($aCities))
            return FALSE;

        return $this -> transferPreValues('Cities', 'Cities', $aCities);
    }


    function transferExpertises()
    {
        $aExpertises = $this -> _mDb -> getPairs("SELECT * FROM [Expertises] ORDER BY [ID]", 'ID', 'ExpertiseName');
        If (empty($aExpertises))
            return FALSE;

        return $this -> transferPreValues('Expertises', 'Expertises', $aExpertises);
    }

    function transferSubExpertises()
    {
        $aSubExpertises = $this -> _mDb -> getPairs("SELECT * FROM [SubExpertises] ORDER BY [ID]", 'ID', 'SubExpertiseName');
        If (empty($aSubExpertises))
            return FALSE;

        return $this -> transferPreValues('SubExpertises', 'SubExpertises', $aSubExpertises);
    }

    function transferLanguages()
    {
        $aLanguages = $this -> _mDb -> getPairs("SELECT * FROM [Languages] ORDER BY [ID]", 'ID', 'LanguageName');
        If (empty($aLanguages))
            return FALSE;

        return $this -> transferPreValues('Languages', 'Languages', $aLanguages);
    }
    function transferDegrees()
    {
        $aDegrees = $this->_mDb->getPairs("SELECT * FROM [Degrees] ORDER BY [ID]", 'ID', 'DegreesName');
        If (empty($aDegrees))
            return FALSE;

        return $this->transferPreValues('Degrees', 'Degrees', $aDegrees);
    }

    function transferDisciplines()
     {
            $aDisciplines = $this -> _mDb -> getPairs("SELECT * FROM [Disciplines] ORDER BY [ID]", 'ID', 'DisciplineName');
            If (empty($aDisciplines))
                return FALSE;

            return $this -> transferPreValues('Disciplines', 'Disciplines', $aDisciplines);
     }

     function transferPositions()
     {
            $aPositions = $this -> _mDb -> getPairs("SELECT * FROM [Positions] ORDER BY [ID]", 'ID', 'PositionName');
            If (empty($aPositions))
                return FALSE;

            return $this -> transferPreValues('Positions', 'Positions', $aPositions);
     }

     function transferMedschools()
     {
            $aMedschools = $this -> _mDb -> getPairs("SELECT * FROM [Medschools] ORDER BY [ID]", 'ID', 'MedSchoolName');
            If (empty($aMedschools))
                return FALSE;

            return $this -> transferPreValues('Medschools', 'Medschools', $aMedschools);
     }

     function transferInsurances()
     {
            $aInsurances = $this -> _mDb -> getPairs("SELECT * FROM [Insurances] ORDER BY [ID]", 'ID', 'InsuranceName');
            If (empty($aInsurances))
                return FALSE;

            return $this -> transferPreValues('Insurances', 'Insurances', $aInsurances);
     }
}

/** @} */
