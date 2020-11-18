<?php
namespace Dash\Exporter;

use DateTime;
use DateTimeZone;
use Exception;
use PDO;
use PDOException;

/**
 * Utility class for data exporter
 */
class Util
{

/**
 * Insert multiple rows into a table
 * @param PDO    Obj     $pdo PDO to use for insertion
 * @param string $table  Table data is being inserted into
 * @param array  $fields List of fields in data
 * @param array  $data   Data to be inserted
 */
    public static function InsertMultiple($pdo, $table, $fields, $data)
    {
        $pdo->beginTransaction();
        $qmarks = [];

        foreach ($fields as $k => $f) {
            $qmarks[]   = '?';
            $fields[$k] = '`' . $f . '`';
        }

        $sql = "INSERT INTO {$table} (" . implode(",", $fields) . ") VALUES (" . implode(",", $qmarks) . ")";

        $stmt = $pdo->prepare($sql);

        try {
            $ret = false;

            foreach ($data as $d) {

                $stmt->execute($d);

                if (!$ret) {
                    $ret = $pdo->lastInsertId();
                }

            }

        } catch (PDOException $e) {
            die(s($e->getMessage()));
        }

        $pdo->commit();

        return $ret;
    }

/**
 * Add to data map
 * @param array  &$map     old key => new key map
 * @param string $table    table being documented
 * @param array  $data     rows updated in last insert
 * @param int    $startInt first new ID inserted on last insert
 */
    public static function UpdateDataMap(&$map, $table, $data, $startInt)
    {
        $newId = $startInt;

        foreach ($data as $d) {
            $map[$table][$d['id']] = intval($newId);
            $newId++;
        }

    }

/**
 * Create an array of fields for inserts
 * @param array $data Assoc array of data to be inserted
 */
    public static function DeriveFields($data)
    {
        $fields = [];

        foreach ($data as $f => $whatevs) {
            try {

                if ('id' === $f) {
                    continue;
                }

                $fields[] = $f;
            } catch (Exception $e) {
                die(s($e->getMessage()));
            }

        }

        return $fields;
    }

/**
 * @param  $rows
 * @param  array   $updates
 * @return mixed
 */
    public static function CreateInsert($rows, $dependencies, $key_map)
    {

        foreach ($rows as $k => $v) {
            try {

                if (isset($v['id'])) {
                    unset($v['id']);
                }

                foreach ($dependencies as $table => $column) {

                    if (!empty($v[$column]) && isset($key_map[$table][$v[$column]])) {
                        $v[$column] = $key_map[$table][$v[$column]];
                    }

                }

                $rows[$k] = array_values($v);
            } catch (Exception $e) {
                die(s($e->getMessage()));
            }

        }

        return $rows;
    }

/**
 * @param $exportDb
 * @param $og_proj
 * @param $key_map
 */
    public static function BuildRecordsDbTables($exportDb, $importDb, $og_proj, $key_map)
    {
        $tables = [];
        $sql    = "SELECT * FROM record_type WHERE project_id = ?";
        $stmnt  = $exportDb->prepare($sql);
        $stmnt->execute([$og_proj]);
        $arts = $stmnt->fetchAll(PDO::FETCH_ASSOC);

        // die(s($arts));

        $dsn           = 'mysql:dbname=' . getenv('EXPORT_RECORDS_DB') . ';host=' . getenv('EXPORT_DB_HOST');
        $user          = getenv('EXPORT_USER');
        $pass          = getenv('EXPORT_PASS');
        $ExportRecords = new PDO($dsn, $user, $pass) or die($ExportRecords->errorInfo());

        $dsn           = 'mysql:dbname=' . getenv('IMPORT_RECORDS_DB') . ';host=' . getenv('IMPORT_DB_HOST');
        $user          = getenv('IMPORT_USER');
        $pass          = getenv('IMPORT_PASS');
        $ImportRecords = new PDO($dsn, $user, $pass) or die($ImportRecords->errorInfo());

        foreach ($arts as $art) {
            $sql   = "SHOW CREATE TABLE {$art['record_data_table_name']}";
            $stmnt = $ExportRecords->prepare($sql);
            $stmnt->execute();
            $tblInfo = $stmnt->fetch(PDO::FETCH_ASSOC);

            $newTblName = substr($tblInfo['Table'], 0, strrpos($tblInfo['Table'], "_")) . "_" . $key_map['record_type'][$art['id']];
            $newCreate  = str_replace($tblInfo['Table'], $newTblName, $tblInfo['Create Table']);

            echo "Creating " . $newTblName . PHP_EOL;
            $ImportRecords->exec($newCreate);

            $sql  = "UPDATE record_type SET record_data_table_name = ? WHERE id = ?";
            $stmt = $importDb->prepare($sql);
            $stmt->execute([$newTblName, $key_map['record_type'][$art['id']]]);

            $og_rows = self::GetTableRows($ExportRecords, $art['record_data_table_name']);

            if (!empty($og_rows)) {
                $ng_rows = self::PrepareRecordsRows($og_rows, $key_map);
                $fields  = self::DeriveFields($og_rows[0]);

                echo "Inserting values into " . $newTblName . PHP_EOL;
                self::InsertMultiple($ImportRecords, $newTblName, $fields, $ng_rows);
            }

        }

    }

/**
 * @param  $rows
 * @param  $key_map
 * @return mixed
 */
    public static function PrepareRecordsRows($rows, $key_map)
    {

        foreach ($rows as $key => $row) {
            $row['__id'] = $key_map['records'][$row['__id']];

            $rows[$key] = array_values($row);
        }

        return $rows;
    }

/**
 * @param  $pdo
 * @param  $table
 * @return mixed
 */
    public static function GetTableRows($pdo, $table)
    {
        $sql  = "SELECT * FROM {$table}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

/**
 * @param $data
 */
    public static function ClearKeyMap(&$data)
    {
        $studios = $data['studios'];
        $data    = ['studios' => $studios];
    }

/**
 * @param $exportDb
 * @param $importDb
 * @param $metaProject
 * @param $key_map
 */
    public static function MoveFiles($exportDb, $importDb, $metaProject, $key_map)
    {

        if (!defined('FILE_PATH')) {
            define('FILE_PATH', 'files/');
        }

        if (!defined('FILE_PATH_ALE_IMPORT')) {
            define('FILE_PATH_ALE_IMPORT', FILE_PATH . 'ale_import_files/');
        }

        if (!defined('FILE_PATH_METADATA')) {
            define('FILE_PATH_METADATA', FILE_PATH . 'associated_metadata/');
        }

        if (!defined('FILE_PATH_IMPORT')) {
            define('FILE_PATH_IMPORT', FILE_PATH . 'import_files/');
        }

        $zipFiles = [];
        $sql      = "SELECT * FROM imports WHERE project_id = ?";
        $stmnt    = $exportDb->prepare($sql);
        $stmnt->execute([$metaProject]);

        $files = $stmnt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as $file) {
            $oldFile = self::CreateFileLink($file, false);

            echo "Checking " . $oldFile . PHP_EOL;

            if (!empty($file['zip_file']) && !in_array($file['zip_file'], $zipFiles)) {
                $zip = self::CreateFileLink($file, true);
                echo "Checking ZIP " . $zip . PHP_EOL;

                if (strlen($zip) > 0 && self::CheckFile(getenv('EXPORT_FILE_ROOT') . $zip)) {

                    self::DownloadFile(getenv('EXPORT_FILE_ROOT') . $zip, self::PreparePathForSave($zip));

                }

                $zipFiles[] = $file['zip_file'];
            }

            if (strlen($oldFile) > 0 && self::CheckFile(getenv('EXPORT_FILE_ROOT') . $oldFile)) {
                $newFile = str_replace(".{$metaProject}.", "." . $key_map['projects'][$metaProject] . ".", $oldFile);
                self::DownloadFile(getenv('EXPORT_FILE_ROOT') . $oldFile, self::PreparePathForSave($newFile));
                // file_put_contents(getenv('IMPORT_FILE_ROOT') . $newFile, $pull);
                echo "Importing " . getenv('IMPORT_FILE_ROOT') . $newFile . PHP_EOL;

            }

        }

    }

/**
 * @param $old_path
 */
    public static function PreparePathForSave($old_path)
    {
        $parts = explode("/", $old_path);

        if (!empty($parts[0]) && !empty($parts[1])) {

            if ($parts[0] . '/' . $parts[1] . '/' === FILE_PATH_ALE_IMPORT) {

                if (count($parts) === 4) {

                    if (!file_exists(getenv('IMPORT_FILE_ROOT') . $parts[1] . '/' . $parts[2])) {
                        mkdir(getenv('IMPORT_FILE_ROOT') . $parts[1] . '/' . $parts[2]);
                    }

                }

            }
        }

        array_shift($parts);
        return getenv('IMPORT_FILE_ROOT') . implode("/", $parts);
    }

/**
 * @param $import
 */
    public static function CreateFileLink($import, $getZip = false)
    {

        $dt        = new DateTime($import['timestamp'], new DateTimeZone('America/Los_Angeles'));
        $timestamp = $dt->getTimestamp();

        if ($getZip) {

            if (!empty($import['zip_file'])) {
                $strZipFile = substr($import['zip_file'], 0, -3) . $timestamp . substr($import['zip_file'], -4, 4);
                return FILE_PATH_ALE_IMPORT . $strZipFile;
            }

        } else {

            $arrLink = [];

            if ("ALE (ZIP)" == $import['import_type']) {
                $arrImportFile = explode("/", $import['import_file']);

                if (count($arrImportFile) > 1) {
                    $import['import_file'] = $arrImportFile[1];
                }

            }

            $arrAleImportTypes = ["ALE", "FLX", "CSV", "ALE (ZIP)"];

            if (in_array($import['import_type'], $arrAleImportTypes)) {

                $strFileName = substr($import['import_file'], 0, -3) . $timestamp . substr($import['import_file'], -4, 4);

                if (strlen($import['zip_file']) > 0) {
                    $strZipFile = substr($import['zip_file'], 0, -3) . $timestamp . substr($import['zip_file'], -4, 4);

                    if (self::CheckFile(getenv('EXPORT_FILE_ROOT') . FILE_PATH_ALE_IMPORT . $strZipFile . "." . $timestamp . "/" . $import['import_file'])) {
                        $arrLink[] = FILE_PATH_ALE_IMPORT . $strZipFile . "." . $timestamp . "/" . rawurlencode($import['import_file']);
                    } elseif (self::CheckFile(getenv('EXPORT_FILE_ROOT') . FILE_PATH_ALE_IMPORT . $strFileName)) {
                        $arrLink[] = FILE_PATH_ALE_IMPORT . $strFileName;
                    } elseif (self::CheckFile(getenv('EXPORT_FILE_ROOT') . FILE_PATH_ALE_IMPORT . rawurlencode($import['import_file']))) {
                        $arrLink[] = FILE_PATH_ALE_IMPORT . rawurlencode($import['import_file']);
                    }

                } else {
                    $arrLink[] = FILE_PATH_ALE_IMPORT . rawurlencode($strFileName);
                }

            } elseif ('Associated Metadata' == $import['import_type']) {
                $strFileName = self::CamelCase(substr($import['import_file'], 0, -4)) . '.' . $timestamp . substr($import['import_file'], -4, 4);
                $arrLink[]   = FILE_PATH_METADATA . rawurlencode($import['import_file']);
            } else {
                $strFileName = substr($import['import_file'], 0, -3) . str_replace(" ", "", $import['import_type']) . "." . $import['project_id'] . "." . $timestamp . substr($import['import_file'], -4, 4);
                $arrLink[]   = FILE_PATH_IMPORT . rawurlencode($strFileName);
            }

            return implode(" / ", $arrLink);
        }

    }

/**
 * @param $str
 */
    public static function CamelCase($str)
    {
        $arrSpace = explode(" ", trim($str));

        foreach ($arrSpace as $strSpace) {
            $arrUnderscore = explode("_", $strSpace);

            foreach ($arrUnderscore as $strUnderscore) {
                $arrPeriod = explode('.', $strUnderscore);

                foreach ($arrPeriod as $strPeriod) {
                    $arrDash = explode('-', $strPeriod);

                    foreach ($arrDash as $strDash) {
                        $strDash    = preg_replace('/[\W]/', '', $strDash);
                        $arrParts[] = ucfirst(strtolower($strDash));
                    }

                }

            }

        }

        return implode('', $arrParts);
    }

/**
 * @param $remote_file
 */
    public static function CheckFile($remote_file)
    {
        $ch = curl_init($remote_file);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return 200 == $responseCode;

    }

/**
 * @param $FileUrl
 * @param $save_path
 */
    public static function DownloadFile($fileUrl, $save_path)
    {

        // die(s($save_path));
        $fp = fopen(rawurldecode($save_path), 'w+');

        if (false === $fp) {
            // die(s($save_path));
            throw new Exception('Could not open: ' . $save_path);
        }

        $ch = curl_init($fileUrl);

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        fclose($fp);

        return 200 == $statusCode;
    }

}
