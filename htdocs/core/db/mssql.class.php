<?php
/* Copyright (C) 2002-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2007 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2007      Simon Desee          <simon@dedisoft.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       	htdocs/core/db/mssql.class.php
 *	\brief			Fichier de la classe permettant de gerer une base mssql
 */

require_once DOL_DOCUMENT_ROOT .'/core/db/DoliDB.class.php';

/**
 *	Classe de gestion de la database de dolibarr
 */
class DoliDBMssql extends DoliDB
{
	//! Database type
	public $type='mssql';
	//! Database label
	const LABEL='MSSQL';
	//! Charset used to force charset when creating database
	var $forcecharset='latin1';      // Can't be static as it may be forced with a dynamic value
	//! Collate used to force collate when creating database
	var $forcecollate='latin1_swedish_ci';      // Can't be static as it may be forced with a dynamic value
	//! Version min database
	const VERSIONMIN='2000';
	//! Resultset of last query
	private $_results;

    /**
	 *	Constructor.
	 *	This create an opened connexion to a database server and eventually to a database
	 *
	 *	@param      string	$type		Type of database (mysql, pgsql...)
	 *	@param	    string	$host		Address of database server
	 *	@param	    string	$user		Nom de l'utilisateur autorise
	 *	@param	    string	$pass		Mot de passe
	 *	@param	    string	$name		Nom de la database
	 *	@param	    int		$port		Port of database server
     */
	function __construct($type, $host, $user, $pass, $name='', $port=0)
	{
		global $conf,$langs;

		$this->database_user=$user;
        $this->database_host=$host;
        $this->database_port=$port;
		$this->transaction_opened=0;

		if (! function_exists("mssql_connect"))
		{
			$this->connected = 0;
			$this->ok = 0;
			$this->error="Mssql PHP functions for using MSSql driver are not available in this version of PHP";
			dol_syslog(get_class($this)."::DoliDBMssql : MSsql PHP functions for using MSsql driver are not available in this version of PHP",LOG_ERR);
			return $this->ok;
		}

		if (! $host)
		{
			$this->connected = 0;
			$this->ok = 0;
			$this->error=$langs->trans("ErrorWrongHostParameter");
			dol_syslog(get_class($this)."::DoliDBMssql : Erreur Connect, wrong host parameters",LOG_ERR);
			return $this->ok;
		}

		// Essai connexion serveur
		$this->db = $this->connect($host, $user, $pass, $name, $port);
		if ($this->db)
		{
			// Si client connecte avec charset different de celui de la base Dolibarr
			// (La base Dolibarr a ete forcee en this->forcecharset a l'install)
			$this->connected = 1;
			$this->ok = 1;
		}
		else
		{
			// host, login ou password incorrect
			$this->connected = 0;
			$this->ok = 0;
			$this->error=mssql_get_last_message();
			dol_syslog(get_class($this)."::DoliDBMssql : Erreur Connect mssql_get_last_message=".$this->error,LOG_ERR);
		}

		// Si connexion serveur ok et si connexion base demandee, on essaie connexion base
		if ($this->connected && $name)
		{
			if ($this->select_db($name))
			{
				$this->database_selected = 1;
				$this->database_name = $name;
				$this->ok = 1;
			}
			else
			{
				$this->database_selected = 0;
				$this->database_name = '';
				$this->ok = 0;
				$this->error=$this->error();
				dol_syslog(get_class($this)."::DoliDBMssql : Erreur Select_db ".$this->error,LOG_ERR);
			}
		}
		else
		{
			// Pas de selection de base demandee, ok ou ko
			$this->database_selected = 0;
		}

		return $this->ok;
	}

    /**
     *  Convert a SQL request in Mysql syntax to native syntax
     *
     *  @param     string	$line   SQL request line to convert
     *  @param     string	$type	Type of SQL order ('ddl' for insert, update, select, delete or 'dml' for create, alter...)
     *  @return    string   		SQL request line converted
     */
	static function convertSQLFromMysql($line,$type='ddl')
	{
		return $line;
	}

	/**
	 *	Select a database
	 *
	 *	@param	    string	$database	Name of database
	 *	@return	    boolean  		    true if OK, false if KO
	 */
	function select_db($database)
	{
		return mssql_select_db($database, $this->db);
	}

	/**
	 *	Connexion to server
	 *
	 *	@param	    string	$host		database server host
	 *	@param	    string	$login		login
	 *	@param	    string	$passwd		password
	 *	@param		string	$name		name of database (not used for mysql, used for pgsql)
	 *	@param		string	$port		Port of database server
	 *	@return		resource			Database access handler
	 *	@see		close
	 */
	function connect($host, $login, $passwd, $name, $port=0)
	{
		dol_syslog(get_class($this)."::connect host=$host, port=$port, login=$login, passwd=--hidden--, name=$name");
		$newhost=$host;
		if ($port) $newhost.=':'.$port;
		$this->db  = @mssql_connect($newhost, $login, $passwd);
		//force les enregistrement en latin1 si la base est en utf8 par defaut
		// Supprime car plante sur mon PHP-Mysql. De plus, la base est forcement en latin1 avec
		// les nouvelles version de Dolibarr car force par l'install Dolibarr.
		//$this->query('SET NAMES '.$this->forcecharset);
		//print "Resultat fonction connect: ".$this->db;
		return $this->db;
	}

	/**
	 *	Return version of database server
	 *
	 *	@return	        string      Version string
	 */
	function getVersion()
	{
		$resql=$this->query("SELECT @@VERSION");
		$version=$this->fetch_array($resql);
		return $version['computed'];
	}

	/**
	 *	Return version of database client driver
	 *
	 *	@return	        string      Version string
	 */
	function getDriverInfo()
	{
		// FIXME: Dummy method
		// TODO: Implement

		return '';
	}

    /**
     *  Close database connexion
     *
     *  @return     boolean     True if disconnect successfull, false otherwise
     *  @see        connect
     */
    function close()
    {
        if ($this->db)
        {
          if ($this->transaction_opened > 0) dol_syslog(get_class($this)."::close Closing a connection with an opened transaction depth=".$this->transaction_opened,LOG_ERR);
          $this->connected=0;
          return mssql_close($this->db);
        }
        return false;
    }


	/**
	 * Start transaction
	 *
	 * @return	    int         1 if transaction successfuly opened or already opened, 0 if error
	 */
	function begin()
	{
		if (! $this->transaction_opened)
		{
			$ret=$this->query("BEGIN TRANSACTION");
			if ($ret)
			{
				$this->transaction_opened++;
				dol_syslog("BEGIN Transaction",LOG_DEBUG);
			}
			return $ret;
		}
		else
		{
			$this->transaction_opened++;
			return 1;
		}
	}

	/**
     * Validate a database transaction
     *
     * @param	string	$log        Add more log to default log line
     * @return  int         		1 if validation is OK or transaction level no started, 0 if ERROR
	 */
	function commit($log='')
	{
		if ($this->transaction_opened <= 1)
		{
			$ret=$this->query("COMMIT TRANSACTION");
			if ($ret)
			{
				$this->transaction_opened=0;
				dol_syslog("COMMIT Transaction",LOG_DEBUG);
				return 1;
			}
			else
			{
				return 0;
			}
		}
		else
		{
			$this->transaction_opened--;
			return 1;
		}
	}

	/**
	 * Annulation d'une transaction et retour aux anciennes valeurs
	 *
	 * @param	string	$log	Add more log to default log line
	 * @return	int             1 si annulation ok ou transaction non ouverte, 0 en cas d'erreur
	 */
	function rollback($log='')
	{
		if ($this->transaction_opened<=1)
		{
			$ret=$this->query("ROLLBACK TRANSACTION");
			$this->transaction_opened=0;
			dol_syslog("ROLLBACK Transaction".($log?' '.$log:''),LOG_DEBUG);
			return $ret;
		}
		else
		{
			$this->transaction_opened--;
			return 1;
		}
	}

	/**
     *  Execute a SQL request and return the resultset
     *
     *  @param	string	$query          SQL query string
     *  @param  int		$usesavepoint	0=Default mode, 1=Run a savepoint before and a rollbock to savepoint if error (this allow to have some request with errors inside global transactions).
     *                   		 		Note that with Mysql, this parameter is not used as Myssql can already commit a transaction even if one request is in error, without using savepoints.
     *  @param  string	$type           Type of SQL order ('ddl' for insert, update, select, delete or 'dml' for create, alter...)
     *  @return resource        		Resultset of answer
	 */
	function query($query,$usesavepoint=0,$type='auto')
	{
		$query = trim($query);

		// Conversion syntaxe MySql vers MSDE.
		$query = str_ireplace("now()", "getdate()", $query);
		// Erreur SQL: cannot update timestamp field
		$query = str_ireplace(", tms = tms", "", $query);
		// Voir si l'on peut directement utiliser $query = str_ireplace("file", "[file]", $query);
		// au lieu des 3 lignes ci-dessous
		$query = str_ireplace(".file", ".[file]", $query);
		$query = str_ireplace(" file ", " [file] ", $query);
		$query = str_ireplace(" file,", " [file],", $query);
		// Idem file
		$query = str_ireplace(".percent", ".[percent]", $query);
		$query = str_ireplace(" percent ", " [percent] ", $query);
		$query = str_ireplace("percent,", "[percent],", $query);
		$query = str_ireplace("percent=", "[percent]=", $query);
		$query = str_ireplace("\'", "''", $query);


		$itemfound = stripos($query, " limit ");
		if ($itemfound !== false) {
			// Extraire le nombre limite
			$number = stristr($query, " limit ");
			$number = substr($number, 7);
			// Inserer l'instruction TOP et le nombre limite
			$query = str_ireplace("select ", "select top ".$number." ", $query);
			// Supprimer l'instruction MySql
			$query = str_ireplace(" limit ".$number, "", $query);
		}

		$itemfound = stripos($query, " week(");
		if ($itemfound !== false) {
			// Recreer une requete sans instruction Mysql
			$positionMySql = stripos($query, " week(");
			$newquery = substr($query, 0, $positionMySql);

			// Recuperer la date passee en parametre
			$extractvalue = stristr($query, " week(");
			$extractvalue = substr($extractvalue, 6);
			$positionMySql = stripos($extractvalue, ")");
			// Conserver la fin de la requete
			$endofquery = substr($extractvalue, $positionMySql);
			$extractvalue = substr($extractvalue, 0, $positionMySql);

			// Remplacer l'instruction MySql en Sql Server
			// Inserer la date en parametre et le reste de la requete
			$query = $newquery." DATEPART(week, ".$extractvalue.$endofquery;
		}

		//print "<!--".$query."-->";

		if (! in_array($query,array('BEGIN','COMMIT','ROLLBACK'))) dol_syslog('sql='.$query, LOG_DEBUG);

		if (! $this->database_name)
		{
			// Ordre SQL ne necessitant pas de connexion a une base (exemple: CREATE DATABASE)
			$ret = mssql_query($query, $this->db);
		}
		else
		{
			$ret = mssql_query($query, $this->db);
		}

		if (! preg_match("/^COMMIT/i",$query) && ! preg_match("/^ROLLBACK/i",$query))
		{
			// Si requete utilisateur, on la sauvegarde ainsi que son resultset
			if (! $ret)
			{
				$result = mssql_query("SELECT @@ERROR as code", $this->db);
				$row = mssql_fetch_array($result);

                $this->lastqueryerror = $query;
				$this->lasterror = $this->error();
				$this->lasterrno = $row["code"];

				dol_syslog(get_class($this)."::query SQL Error query: ".$query, LOG_ERR);
				dol_syslog(get_class($this)."::query SQL Error message: ".$this->lasterror." (".$this->lasterrno.")", LOG_ERR);
			}
			$this->lastquery=$query;
			$this->_results = $ret;
		}

		return $ret;
	}

	/**
	 *	Renvoie la ligne courante (comme un objet) pour le curseur resultset
	 *
	 *	@param	Resultset	$resultset  Curseur de la requete voulue
	 *	@return	Object					Object result line or false if KO or end of cursor
	 */
	function fetch_object($resultset)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		return mssql_fetch_object($resultset);
	}

	/**
     *	Return datas as an array
     *
     *	@param	Resultset	$resultset  Resultset of request
     *	@return	array					Array
	 */
	function fetch_array($resultset)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		return mssql_fetch_array($resultset);
	}


	/**
     *	Return datas as an array
     *
     *	@param	Resultset	$resultset  Resultset of request
     *	@return	array					Array
	 */
	function fetch_row($resultset)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		return @mssql_fetch_row($resultset);
	}

	/**
     *	Return number of lines for result of a SELECT
     *
     *	@param	Resultset	$resultset  Resulset of requests
     *	@return int		    			Nb of lines
     *	@see    affected_rows
	 */
	function num_rows($resultset)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		return mssql_num_rows($resultset);
	}

	/**
	 *	Renvoie le nombre de lignes dans le resultat d'une requete INSERT, DELETE ou UPDATE
	 *
	 *	@param	resultset	$resultset   Curseur de la requete voulue
	 *	@return int		    Nombre de lignes
	 *	@see    num_rows
	 */
	function affected_rows($resultset)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		// mssql necessite un link de base pour cette fonction contrairement
		// a pqsql qui prend un resultset
		$rsRows = mssql_query("select @@rowcount as rows", $this->db);
		return mssql_result($rsRows, 0, "rows");
		//return mssql_affected_rows($this->db);
	}


	/**
	 *	Free last resultset used.
	 *
	 *	@param  resultset	$resultset   Curseur de la requete voulue
	 *	@return	void
	 */
	function free($resultset=0)
	{
		// Si le resultset n'est pas fourni, on prend le dernier utilise sur cette connexion
		if (! is_resource($resultset)) { $resultset=$this->_results; }
		// Si resultset en est un, on libere la memoire
		if (is_resource($resultset)) mssql_free_result($resultset);
	}

	/**
	 *	Escape a string to insert data
	 *
	 *  @param	string	$stringtoencode		String to escape
	 *  @return	string						String escaped
	 */
	function escape($stringtoencode)
	{
		return addslashes($stringtoencode);
	}


	/**
	 *   Convert (by PHP) a GM Timestamp date into a PHP server TZ to insert into a date field.
	 *   Function to use to build INSERT, UPDATE or WHERE predica
	 *
	 *   @param	    string	$param      Date TMS to convert
	 *   @return	string      		Date in a string YYYYMMDDHHMMSS
	 */
	function idate($param)
	{
		return dol_print_date($param,"%Y-%m-%d %H:%M:%S");
	}

	/**
     *	Return generic error code of last operation.
     *
     *	@return	string		Error code (Exemples: DB_ERROR_TABLE_ALREADY_EXISTS, DB_ERROR_RECORD_ALREADY_EXISTS...)
	 */
	function errno()
	{
		if (! $this->connected)
		{
			// Si il y a eu echec de connexion, $this->db n'est pas valide.
			return 'DB_ERROR_FAILED_TO_CONNECT';
		}
		else
		{
			// Constants to convert a MSSql error code to a generic Dolibarr error code
			$errorcode_map = array(
			1004 => 'DB_ERROR_CANNOT_CREATE',
			1005 => 'DB_ERROR_CANNOT_CREATE',
			1006 => 'DB_ERROR_CANNOT_CREATE',
			1007 => 'DB_ERROR_ALREADY_EXISTS',
			1008 => 'DB_ERROR_CANNOT_DROP',
			1025 => 'DB_ERROR_NO_FOREIGN_KEY_TO_DROP',
			1046 => 'DB_ERROR_NODBSELECTED',
			1048 => 'DB_ERROR_CONSTRAINT',
			2714 => 'DB_ERROR_TABLE_ALREADY_EXISTS',
			1051 => 'DB_ERROR_NOSUCHTABLE',
			1054 => 'DB_ERROR_NOSUCHFIELD',
			1060 => 'DB_ERROR_COLUMN_ALREADY_EXISTS',
			1061 => 'DB_ERROR_KEY_NAME_ALREADY_EXISTS',
			2627 => 'DB_ERROR_RECORD_ALREADY_EXISTS',
			102  => 'DB_ERROR_SYNTAX',
			8120 => 'DB_ERROR_GROUP_BY_SYNTAX',
			1068 => 'DB_ERROR_PRIMARY_KEY_ALREADY_EXISTS',
			1075 => 'DB_ERROR_CANT_DROP_PRIMARY_KEY',
			1091 => 'DB_ERROR_NOSUCHFIELD',
			1100 => 'DB_ERROR_NOT_LOCKED',
			1136 => 'DB_ERROR_VALUE_COUNT_ON_ROW',
			1146 => 'DB_ERROR_NOSUCHTABLE',
			1216 => 'DB_ERROR_NO_PARENT',
			1217 => 'DB_ERROR_CHILD_EXISTS',
			1451 => 'DB_ERROR_CHILD_EXISTS'
			);

			if (isset($errorcode_map[$this->lasterrno]))
			{
				return $errorcode_map[$this->lasterrno];
			}
			$errno=$this->lasterrno;
			return ($errno?'DB_ERROR_'.$errno:'0');
		}
	}

	/**
	 *	Return description of last error
	 *
	 *	@return	string		Error text
	 */
	function error()
	{
		if (! $this->connected) {
			// Si il y a eu echec de connexion, $this->db n'est pas valide pour mssql_get_last_message.
			return 'Not connected. Check setup parameters in conf/conf.php file and your mssql client and server versions';
		}
		else {
			return mssql_get_last_message($this->db);
		}
	}

	/**
	 * Get last ID after an insert INSERT
	 *
	 * @param   string	$tab    	Table name concerned by insert. Ne sert pas sous MySql mais requis pour compatibilite avec Postgresql
	 * @param	string	$fieldid	Field name
	 * @return  int     			Id of row
	 */
	function last_insert_id($tab,$fieldid='rowid')
	{
		$res = $this->query("SELECT @@IDENTITY as id");
		if ($data = $this->fetch_array($res))
		{
			return $data["id"];
		}
		else
		{
			return -1;
		}
	}

	/**
     *  Encrypt sensitive data in database
     *  Warning: This function includes the escape, so it must use direct value
     *
     *  @param  string  $fieldorvalue   Field name or value to encrypt
     *  @param	int		$withQuotes     Return string with quotes
     *  @return return          		XXX(field) or XXX('value') or field or 'value'
	 */
	function encrypt($fieldorvalue, $withQuotes=0)
	{
		global $conf;

		// Type of encryption (2: AES (recommended), 1: DES , 0: no encryption)
		$cryptType = ($conf->db->dolibarr_main_db_encryption?$conf->db->dolibarr_main_db_encryption:0);

		//Encryption key
		$cryptKey = (!empty($conf->db->dolibarr_main_db_cryptkey)?$conf->db->dolibarr_main_db_cryptkey:'');

		$return = $fieldorvalue;
		return ($withQuotes?"'":"").$this->escape($return).($withQuotes?"'":"");
	}

	/**
     *	Decrypt sensitive data in database
     *
     *	@param	string	$value			Value to decrypt
     * 	@return	string					Decrypted value if used
	 */
	function decrypt($value)
	{
		global $conf;

		// Type of encryption (2: AES (recommended), 1: DES , 0: no encryption)
		$cryptType = ($conf->db->dolibarr_main_db_encryption?$conf->db->dolibarr_main_db_encryption:0);

		//Encryption key
		$cryptKey = (!empty($conf->db->dolibarr_main_db_cryptkey)?$conf->db->dolibarr_main_db_cryptkey:'');

		$return = $value;
		return $return;
	}


	/**
	 * Return connexion ID
	 *
	 * @return	        string      Id connexion
	 */
	function DDLGetConnectId()
	{
		$resql=$this->query('SELECT CONNECTION_ID()');
		$row=$this->fetch_row($resql);
		return $row[0];
	}

	/**
	 *	Create a new database
	 *	Do not use function xxx_create_db (xxx=mysql, ...) as they are deprecated
	 *	We force to create database with charset this->forcecharset and collate this->forcecollate
	 *
	 *	@param	string	$database		Database name to create
	 * 	@param	string	$charset		Charset used to store data
	 * 	@param	string	$collation		Charset used to sort data
	 * 	@param	string	$owner			Username of database owner
	 * 	@return	resource				resource defined if OK, null if KO
	 */
	function DDLCreateDb($database,$charset='',$collation='',$owner='')
	{
        if (empty($charset))   $charset=$this->forcecharset;
        if (empty($collation)) $collation=$this->forcecollate;

        // ALTER DATABASE dolibarr_db DEFAULT CHARACTER SET latin DEFAULT COLLATE latin1_swedish_ci
		$sql = 'CREATE DATABASE '.$database;
		$sql.= ' DEFAULT CHARACTER SET '.$charset.' DEFAULT COLLATE '.$collation;
		$ret=$this->query($sql);
		if (! $ret)
		{
			// On reessaie pour compatibilite avec mssql < 5.0
			$sql = 'CREATE DATABASE '.$database;
			$ret=$this->query($sql);
		}

		return $ret;
	}

	/**
	 *  List tables into a database
	 *
	 *  @param	string		$database	Name of database
	 *  @param	string		$table		Nmae of table filter ('xxx%')
     *  @return	array					List of tables in an array
	 */
	function DDLListTables($database,$table='')
	{
		$this->_results = mssql_list_tables($database, $this->db);
		return $this->_results;
	}

	/**
	 *	List information of columns into a table.
	 *
	 *	@param	string	$table		Name of table
	 *	@return	array				Tableau des informations des champs de la table
	 */
	function DDLInfoTable($table)
	{

		// FIXME: Dummy method
		// TODO: Implement
		// May help: https://stackoverflow.com/questions/600446/sql-server-how-do-you-return-the-column-names-from-a-table

		$infotables=array();
		return $infotables;
	}

	/**
	 *	Create a table into database
	 *
	 *	@param	    string	$table 			Nom de la table
	 *	@param	    array	$fields 		Tableau associatif [nom champ][tableau des descriptions]
	 *	@param	    string	$primary_key 	Nom du champ qui sera la clef primaire
	 *	@param	    string	$type 			Type de la table
	 *	@param	    array	$unique_keys 	Tableau associatifs Nom de champs qui seront clef unique => valeur
	 *	@param	    array	$fulltext_keys	Tableau des Nom de champs qui seront indexes en fulltext
	 *	@param	    string	$keys 			Tableau des champs cles noms => valeur
	 *	@return	    int						<0 if KO, >=0 if OK
	 */
	function DDLCreateTable($table,$fields,$primary_key,$type,$unique_keys="",$fulltext_keys="",$keys="")
	{
		// cles recherchees dans le tableau des descriptions (fields) : type,value,attribute,null,default,extra
		// ex. : $fields['rowid'] = array('type'=>'int','value'=>'11','null'=>'not null','extra'=> 'auto_increment');
		$sql = "create table ".$table."(";
		$i=0;
		foreach($fields as $field_name => $field_desc)
		{
			$sqlfields[$i] = $field_name." ";
			$sqlfields[$i]  .= $field_desc['type'];
			if( preg_match("/^[^\s]/i",$field_desc['value']))
			$sqlfields[$i]  .= "(".$field_desc['value'].")";
			else if( preg_match("/^[^\s]/i",$field_desc['attribute']))
			$sqlfields[$i]  .= " ".$field_desc['attribute'];
			else if( preg_match("/^[^\s]/i",$field_desc['default']))
			{
				if(preg_match("/null/i",$field_desc['default']))
				$sqlfields[$i]  .= " default ".$field_desc['default'];
				else
				$sqlfields[$i]  .= " default '".$field_desc['default']."'";
			}
			else if( preg_match("/^[^\s]/i",$field_desc['null']))
			$sqlfields[$i]  .= " ".$field_desc['null'];

			else if( preg_match("/^[^\s]/i",$field_desc['extra']))
			$sqlfields[$i]  .= " ".$field_desc['extra'];
			$i++;
		}
		if($primary_key != "")
		$pk = "primary key(".$primary_key.")";

		if($unique_keys != "")
		{
			$i = 0;
			foreach($unique_keys as $key => $value)
			{
				$sqluq[$i] = "UNIQUE KEY '".$key."' ('".$value."')";
				$i++;
			}
		}
		if($keys != "")
		{
			$i = 0;
			foreach($keys as $key => $value)
			{
				$sqlk[$i] = "KEY ".$key." (".$value.")";
				$i++;
			}
		}
		$sql .= implode(',',$sqlfields);
		if($primary_key != "")
		$sql .= ",".$pk;
		if($unique_keys != "")
		$sql .= ",".implode(',',$sqluq);
		if($keys != "")
		$sql .= ",".implode(',',$sqlk);
		$sql .=") type=".$type;

		dol_syslog($sql);
		if(! $this -> query($sql))
		return -1;
		else
		return 1;
	}

	/**
	 *	Return a pointer of line with description of a table or field
	 *
	 *	@param	string		$table	Name of table
	 *	@param	string		$field	Optionnel : Name of field if we want description of field
	 *	@return	resource			Resource
	 */
	function DDLDescTable($table,$field="")
	{
		$sql="DESC ".$table." ".$field;

		dol_syslog($sql);
		$this->_results = $this->query($sql);
		return $this->_results;
	}

	/**
	 *	Create a new field into table
	 *
	 *	@param	string	$table 				Name of table
	 *	@param	string	$field_name 		Name of field to add
	 *	@param	string	$field_desc 		Tableau associatif de description du champ a inserer[nom du parametre][valeur du parametre]
	 *	@param	string	$field_position 	Optionnel ex.: "after champtruc"
	 *	@return	int							<0 if KO, >0 if OK
	 */
	function DDLAddField($table,$field_name,$field_desc,$field_position="")
	{
		// cles recherchees dans le tableau des descriptions (field_desc) : type,value,attribute,null,default,extra
		// ex. : $field_desc = array('type'=>'int','value'=>'11','null'=>'not null','extra'=> 'auto_increment');
		$sql= "ALTER TABLE ".$table." ADD ".$field_name." ";
		$sql .= $field_desc['type'];
		if( preg_match("/^[^\s]/i",$field_desc['value']))
		$sql  .= "(".$field_desc['value'].")";
		if( preg_match("/^[^\s]/i",$field_desc['attribute']))
		$sql  .= " ".$field_desc['attribute'];
		if( preg_match("/^[^\s]/i",$field_desc['null']))
		$sql  .= " ".$field_desc['null'];
		if( preg_match("/^[^\s]/i",$field_desc['default']))
		if(preg_match("/null/i",$field_desc['default']))
		$sql  .= " default ".$field_desc['default'];
		else
		$sql  .= " default '".$field_desc['default']."'";
		if( preg_match("/^[^\s]/i",$field_desc['extra']))
		$sql  .= " ".$field_desc['extra'];
		$sql .= " ".$field_position;

		if(! $this -> query($sql))
		return -1;
		else
		return 1;
	}

	/**
	 *	Update format of a field into a table
	 *
	 *	@param	string	$table 				Name of table
	 *	@param	string	$field_name 		Name of field to modify
	 *	@param	string	$field_desc 		Array with description of field format
	 *	@return	int							<0 if KO, >0 if OK
	 */
	function DDLUpdateField($table,$field_name,$field_desc)
	{
		$sql = "ALTER TABLE ".$table;
		$sql .= " MODIFY COLUMN ".$field_name." ".$field_desc['type'];
		if ($field_desc['type'] == 'tinyint' || $field_desc['type'] == 'int' || $field_desc['type'] == 'varchar') {
			$sql.="(".$field_desc['value'].")";
		}

		dol_syslog($sql,LOG_DEBUG);
		if (! $this->query($sql))
		return -1;
		else
		return 1;
	}

	/**
	 *	Drop a field from table
	 *
	 *	@param	string	$table 			Name of table
	 *	@param	string	$field_name 	Name of field to drop
	 *	@return	int						<0 if KO, >0 if OK
	 */
	function DDLDropField($table,$field_name)
	{
		$sql= "ALTER TABLE ".$table." DROP COLUMN `".$field_name."`";
		dol_syslog($sql,LOG_DEBUG);
		if (! $this->query($sql))
		{
			$this->error=$this->lasterror();
			return -1;
		}
		else return 1;
	}

	/**
	 * 	Create a user and privileges to connect to database (even if database does not exists yet)
	 *
	 *	@param	string	$dolibarr_main_db_host 		Ip serveur
	 *	@param	string	$dolibarr_main_db_user 		Nom user a creer
	 *	@param	string	$dolibarr_main_db_pass 		Mot de passe user a creer
	 *	@param	string	$dolibarr_main_db_name		Database name where user must be granted
	 *	@return	int									<0 if KO, >=0 if OK
	 */
	function DDLCreateUser($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,$dolibarr_main_db_name)
	{
		// FIXME: Dummy method
		// TODO: Implement
		// May help: http://msdn.microsoft.com/fr-fr/library/ms173463.aspx

		// Always fail for now
		return -1;
	}

    /**
     *	Return charset used to store data in database
     *
     *	@return		string		Charset
     */
    function getDefaultCharacterSetDatabase()
	{
		// FIXME: Dummy method
		// TODO: Implement

		return '';
	}

	/**
	 *	Return list of available charset that can be used to store data in database
	 *
	 *	@return		array		List of Charset
	 */
	function getListOfCharacterSet()
	{
		// FIXME: Dummy method
		// TODO: Implement

		return '';
	}

	/**
	 *	Return collation used in database
	 *
	 *	@return		string		Collation value
	 */
	function getDefaultCollationDatabase()
	{
		$resql=$this->query("SELECT SERVERPROPERTY('collation')");
		if (!$resql)
		{
			return $this->forcecollate;
		}
		$liste=$this->fetch_array($resql);
		return $liste['computed'];
	}

	/**
	 *	Return list of available collation that can be used for database
	 *
	 *	@return		array		Liste of Collation
	 */
	function getListOfCollation()
	{
		// FIXME: Dummy method
		// TODO: Implement

		return '';
	}

	/**
	 *	Return full path of dump program
	 *
	 *	@return		string		Full path of dump program
	 */
	function getPathOfDump()
	{
		// FIXME: Dummy method
		// TODO: Implement

	    return '';
	}

	/**
	 *	Return full path of restore program
	 *
	 *	@return		string		Full path of restore program
	 */
	function getPathOfRestore()
	{
		// FIXME: Dummy method
		// TODO: Implement

	    return '';
	}

	/**
	 * Return value of server parameters
	 *
	 * @param	string	$filter		Filter list on a particular value
	 * @return	array				Array of key-values (key=>value)
	 */
	function getServerParametersValues($filter='')
	{
		// FIXME: Dummy method
		// TODO: Implement
		// May help: SELECT SERVERPROPERTY

		$result=array();
		return $result;
	}

	/**
	 * Return value of server status
	 *
	 * @param	string	$filter		Filter list on a particular value
	 * @return  array				Array of key-values (key=>value)
	 */
	function getServerStatusValues($filter='')
	{
		// FIXME: Dummy method
		// TODO: Implement
		// May help: http://www.experts-exchange.com/Database/MS-SQL-Server/Q_20971756.html

		return array();
	}
}

