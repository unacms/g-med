<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup   G-Med Migration  G-Med Migration
 * @ingroup     UnaModules
 *
 * @{
 */

if ( function_exists('ini_set'))
{
    ini_set('max_execution_time', 0);
	ini_set('display_errors', 0);
}
	
class BxMSSQLMModule extends BxBaseModGeneralModule
{
    protected $_oMDb = null;			
	public function __construct(&$aModule){
        parent::__construct($aModule);       
    }		
	
	public function actionPerformAction($sModule, $sAction = 'clean')
	{
		if (!$sModule || !isset($this -> _oConfig -> _aMigrationModules[$sModule]))
		{
			echo json_encode(array('code' => 1, 'message' => _t('_bx_mssql_migration_nothing_to_remove')));
			exit;
		}
		
		require_once($this -> _oConfig -> _aMigrationModules[$sModule]['migration_class'] . '.php');
        $oModule = new $this -> _oConfig -> _aMigrationModules[$sModule]['migration_class']($this, $this -> _oMDb);
		
		return $sAction == 'clean' ? $oModule -> dropMID() : $oModule -> removeContent();
	}	
	
	public function actionStartTransfer($aModules)
	{
		if (empty($aModules))
		{
			echo json_encode(array('code' => 1, 'message' => _t('_bx_mssql_migration_successfully_finished')));
			exit;
		}
		
		$this -> initDb();		
		header('Content-Type:text/javascript');			
		
		foreach($aModules as $iKey => $sModule){
            if( $sModule && !empty(($this -> _oConfig -> _aMigrationModules[$sModule])))
			{
				$sTransferred = $this -> _oDb -> getTransferStatus($sModule);	
				if ($sTransferred == 'finished') 
					continue;
		             
				if(isset($this -> _oConfig -> _aMigrationModules[$sModule]['dependencies']) && is_array($this -> _oConfig -> _aMigrationModules[$sModule]['dependencies']))
				{ 
						foreach($this -> _oConfig -> _aMigrationModules[$sModule]['dependencies'] as $iKey => $sDependenciesModule)
	                    {
	                        $sTransferred = $this -> _oDb -> getTransferStatus($sDependenciesModule);							
	                        if( $sTransferred != 'finished')
								return _t('_bx_mssql_migration_install_before', _t("_bx_mssql_migration_data_{$sDependenciesModule}"));
						}			   
				}
				 
				if(isset($this -> _oConfig -> _aMigrationModules[$sModule]['plugins']) && is_array($this -> _oConfig -> _aMigrationModules[$sModule]['plugins']))
				{
					$sPlugins = '';
					foreach($this -> _oConfig -> _aMigrationModules[$sModule]['plugins'] as $sKey => $sTitle)
					{
						if (!$this -> _oDb -> isPluginInstalled($sKey)) 	                                      								
							$sPlugins .= $sTitle . ', ';								
					}
						
					if ($sPlugins)
						return _t('_bx_mssql_migration_install_plugin', trim($sPlugins, ', '), $sModule);														
	            }		 
					
                // create new module's instance;
				require_once($this -> _oConfig -> _aMigrationModules[$sModule]['migration_class'] . '.php');
				// set as started;
                $this -> _oDb -> updateTransferStatus($sModule, 'started');
				
                 // create new migration instance;
                $oModule = new $this -> _oConfig -> _aMigrationModules[$sModule]['migration_class']($this, $this -> _oMDb);
                if($oModule -> runMigration()) 
	                $this -> _oDb -> updateTransferStatus($sModule, 'finished');                
                else {                    
                    $this -> _oDb -> updateTransferStatus($sModule, 'error');
					return _t('_bx_mssql_migration_successfully_failed');					
                }
            }		
	     }
		
		return _t('_bx_mssql_migration_successfully_finished');	
	}
	
	/** 
	* Creates date for migration
	* @param ref $oMDb G-Med database connect
	*/
	public function createMigration()
	{
		if (is_null($this -> _oMDb)) 
			$this -> initDb();

		/*$this -> _oDb -> cleanTrasnfersTable();*/
		foreach ($this -> _oConfig -> _aMigrationModules as $sName => $aModule)
		{			
			//if ($this -> _oMDb -> isTableExists($aModule['table_name']))
			{
				//Create transferring class object
				require_once($aModule['migration_class'] . '.php');			
				$oObject = new $aModule['migration_class']($this, $this -> _oMDb);			
				
				if ($mixedNumber = $oObject -> getTotalRecords()) 
						$this -> _oDb -> addToTransferList($sName, $mixedNumber);				
			}	
		}		
	}

    /**
	* Init G-Med database connect
	* @return mixed
	*/	
	public function initDb()
	{
		$aConfig = array(
                'host'    => 'localhost\SQLEXPRESS',
                'user'	  => '',
            	'pwd'     => '',
                'name' 	  => 'GMED2Production',
				'port'    => '',
				'sock'	  => ''
			);

        require_once('BxMDb.php');
        $this -> _oMDb = new BxMDb($aConfig);
		return $this -> _oMDb -> connect();
	}

	/**
     * Returns Password Ecnrypted using old algorithm. This function is used only for transferred Users and new ones will have new passwords with salt.
     * @param object
     */
    public function serviceEncryptPassword($oAlert){
        // if imported member tries to login set new hash for password
        if ($oAlert -> sAction == 'encrypt_password_after' && (int)$oAlert -> aExtras['info']['use_old_pwd']){
            $oAlert -> aExtras['password'] = $this -> encryptPassword($oAlert -> aExtras['pwd'], $oAlert -> aExtras['email']);
        }
        else if (isset($oAlert -> aExtras['action']) && $oAlert -> aExtras['action'] == 'forgot_password'){
            // set 0 for se_id - it means now member's password is encrypted using standard algorithm
            $this -> _oDb -> cleanOldID($oAlert -> iObject);
        }
    }

    private function encryptPassword($sPwd, $sEmail){
        $sPassword = strtolower(utf8_encode("{$sEmail}_{$sPwd}"));
        $sPassword = mb_convert_encoding($sPassword, 'UTF-16LE');

        return strtoupper(sha1($sPassword));
    }
}

/** @} */
