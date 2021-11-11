<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Models;

use DAO;
use Exception;

class RefreshTokenDAO extends DAO {
    private static $instance;

    public static function newInstance() {
        if(!self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct();
        $this->setTableName('t_user_api_refresh');
        $this->setPrimaryKey('pk_i_id');
        $this->setFields(array('pk_i_id', 'fk_i_user_id', 's_token', 's_secret', 'dt_issued', 'dt_expires'));
    }

    private static function installSQL() {
        return 'CREATE TABLE IF NOT EXISTS ' . DB_TABLE_PREFIX . 't_user_api_refresh (
                    pk_i_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    fk_i_user_id INT(10) UNSIGNED NOT NULL,
                    s_token TEXT NOT NULL,
                    s_secret VARCHAR(40) NOT NULL,
                    dt_issued DATETIME DEFAULT CURRENT_TIMESTAMP,
                    dt_expires DATETIME,

                        PRIMARY KEY (pk_i_id),
                        FOREIGN KEY (fk_i_user_id) REFERENCES ' . DB_TABLE_PREFIX . 't_user (pk_i_id)
                );';
    }

    private static function uninstallSQL() {
        return 'DROP TABLE IF EXISTS ' . DB_TABLE_PREFIX . 't_user_api_refresh;';
    }

    public function install() {
        $this->alterTable($this->installSQL(), 'install');
    }

    public function uninstall() {
        $this->alterTable($this->uninstallSQL(), 'uninstall');
    }

    public function alterTable($sql, $id = 'alterTable', $error = true) {
        $this->dao->query('START TRANSACTION');
        if(!$this->dao->importSQL($sql) && $error) {
            $this->dao->query('ROLLBACK');
            throw new Exception('SQL error - DFTDAPI\Models\RefreshTokenDAO::' . $id . ' - ' . $this->dao->getErrorLevel() . ' - ' . $this->dao->getErrorDesc());
        }
        $this->dao->query('COMMIT');
    }

    public function findByUserSecret($user, $secret) {
        $this->dao->select();
        $this->dao->from($this->getTableName());
        $this->dao->where(['fk_i_user_id' => $user, 's_secret' => $secret]);
        $result = $this->dao->get();

        if(!$result) return false;
        
        return $result->row();
    }

    public function deleteByUserSecret($user, $secret) {
        return $this->delete(['fk_i_user_id' => $user, 's_secret' => $secret]);
    }

    public function deleteExceptSecret($user, $secret) {
        $this->dao->from($this->getTableName());
        $this->dao->where('fk_i_user_id', $user);
        $this->dao->where('s_secret != "' . $secret . '"');

        return $this->dao->delete();
    }

    public function cleanup() {
        $this->dao->from($this->getTableName());
        $this->dao->where('dt_expires < NOW()');

        return $this->dao->delete();
    }
}