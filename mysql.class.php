<?php
/**
 * MySql数据库连接
 * 
 * FROM MOP(Mango Open Platform)
 * EXAMPLE:
 *   $db = MySql::getInstance();
 *   $result = $db->execute("SELECT * FROM `user_account` LIMIT 1");
 * 
 * @package     PAY-PHPClient 
 * @author      lihaho@gmail.com
 */
class MySql
{
    /** 
     * object of this class  
     *
     * @static object
     */
    static $db = NULL;

    /** 
     * handle of database connect   
     *
     * @private 
     */
    public $_handle;
    private function __clone(){}

    /**
     * Connect mysql 
     * @return void 
     */
    private function __construct($config, $silence = false)
    {
        $this->_handle = @mysql_connect($config['host'], $config['username'], $config['password']);
        if (!$this->_handle)
        {
            if (!$silence) die('数据库连接失败:' . mysql_error());
            return false;
        }
        if (!mysql_select_db($config['ns']))
        {
            if (!$silence) die('选定数据库失败:' . mysql_error());
            return false;
        }

        if (($this->_query("SET NAMES utf8")) === false)
        {
            if (!$silence) die('mysql set names utf8:' . mysql_error());
            return false;
        }

    }

    /**
     * Get a object of mysql via static function 
     * @return object
     */
    public static function getInstance($config, $silence = false)   
    {  
        if(!(self::$db instanceof self))  
        {  
            self::$db = new self($config, $silence);   
            if (self::$db->_handle == false) return false;
        }  
        return self::$db;
    }  

    /**
     * Execute a sql
     * @param string
     * @return mixed
     */
    public function execute($sql)
    {
        $sql = ltrim($sql);
        $type = strtoupper(substr($sql, 0, 6));
        $result = $this->_query($sql, $this->_handle);
        switch ($type) {
            case 'SELECT':
                $ret = $this->_fetch($result);
                break;
            case 'INSERT':
                $ret = $this->_insert_id($this->_handle);
                break;
            case 'UPDATE':
            case 'DELETE':
            default:
                $ret = $this->_affected_rows($this->_handle);
                break;
        }
        return $ret;
    }

    /**
     * Query a sql 
     * @param string
     * @return resource
     */
    private function _query($sql)
    { 
        $result = mysql_query($sql, $this->_handle);
        return $result;
    }

    /**
     * Fetch the data from query
     * @param resource 
     * @return array
     */
    private function _fetch($result)
    {
        if (!is_resource($result)) return false;
        $data = array();
        while ($rows = mysql_fetch_assoc($result)) 
        {
            $data[] = $rows;
        }
        return $data;
    }

    /**
     * Get id that insert
     * @return int 
     */
    private function _insert_id()
    {
        return mysql_insert_id();    
    }

    /**
     * Get the number of rows affected
     * @return int 
     */
    private function _affected_rows()
    {
        return mysql_affected_rows();    
    }
}
