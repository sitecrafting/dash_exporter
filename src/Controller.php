<?php
namespace Dash\Exporter;

use PDO;

//
// require_once 'includes/functions.php';
// require_once 'includes/tables.php';

// $dotenv = Dotenv\Dotenv::createMutable(__DIR__);
// $dotenv->load();

/**
 * Class to clone projects from one instance of DASH to another
 */
class Controller
{
    /**
     * @var array
     */
    public $key_map = [];
    /**
     * @var mixed
     */
    public $importDb;
    /**
     * @var mixed
     */
    public $exportDb;
    /**
     * @var mixed
     */
    public $projects;

    public function __construct()
    {
        $this->key_map = ['studios' => []];

// Instantiat Export DB
        $dsn            = 'mysql:dbname=' . getenv('EXPORT_APP_DB') . ';host=' . getenv('EXPORT_DB_HOST');
        $user           = getenv('EXPORT_USER');
        $pass           = getenv('EXPORT_PASS');
        $this->exportDb = new PDO($dsn, $user, $pass);

// Instantate Import DB
        $dsn            = 'mysql:dbname=' . getenv('IMPORT_APP_DB') . ';host=' . getenv('IMPORT_DB_HOST');
        $user           = getenv('IMPORT_USER');
        $pass           = getenv('IMPORT_PASS');
        $this->importDb = new PDO($dsn, $user, $pass);

        $this->projects = explode(",", getenv("EXPORT_PROJECTS"));

    }

    public function init()
    {
        $this->processStudios();

        foreach ($this->projects as $metaProject) {
            // Update project
            $project_id = $this->processProject($metaProject);

            // Update imports
            $this->processImports($metaProject);

            $this->processTables();

            // Move Records DB tables
            Util::BuildRecordsDbTables($this->exportDb, $this->importDb, $metaProject, $this->key_map);
            Util::MoveFiles($this->exportDb, $this->importDb, $metaProject, $this->key_map);
            Util::ClearKeyMap($this->key_map);

            // die('Done' . PHP_EOL);
        }

    }

    public function processStudios()
    {
        // De-Duplicate Studios
        $eq = $this->exportDb->prepare("SELECT * FROM studios");
        $eq->execute();
        $export_studios = $eq->fetchAll(PDO::FETCH_ASSOC);

        $iq = $this->importDb->prepare("SELECT * FROM studios");
        $iq->execute();
        $import_studios = $iq->fetchAll(PDO::FETCH_ASSOC);

        foreach ($import_studios as $ik => $i) {

            foreach ($export_studios as $ek => $e) {

                if ($e['name'] === $i['name']) {
                    $this->key_map['studios'][$e['id']] = (int) $i['id'];
                    unset($export_studios[$ek]);
                    break;
                }

            }

        }

        if (!empty($export_studios)) {
            $data      = [];
            $fields    = [];
            $hasFields = false;

            foreach ($export_studios as $k => $s) {

                if (!$hasFields) {

                    foreach ($s as $field => $value) {

                        if ('id' !== $field) {
                            $fields[] = $field;
                        }

                    }

                    $hasFields = true;
                }

                unset($s['id']);
                $data[] = $s;
            }

            $startId = Util::InsertMultiple($this->importDb, 'studios', $fields, $data);

            if (is_numeric($startId)) {
                Util::UpdateDataMap($this->key_map, 'studios', $data, intval($startId));
            }

        }

    }

    /**
     * @param  $project_id
     * @return mixed
     */
    public function processProject($project_id)
    {
        $sql   = "SELECT * FROM projects WHERE id = ?";
        $stmnt = $this->exportDb->prepare($sql);
        $stmnt->execute([$project_id]);

        $org_project = $stmnt->fetch(PDO::FETCH_ASSOC);

        $insertData = Util::CreateInsert([$org_project], [], []);

        $insertFields = Util::DeriveFields($org_project);

        $newId = Util::InsertMultiple($this->importDb, 'projects', $insertFields, $insertData);
        Util::UpdateDataMap($this->key_map, 'projects', [$org_project], $newId);

        return $newId;
    }

    public function processTables()
    {

        foreach (DataTables::$tables as $tname => $table) {
            echo "Processing " . $tname . PHP_EOL;
            $dependencies = $table['dependencies'];
            $whereTable   = array_keys($table['where'])[0];

            if (!empty($this->key_map[$whereTable])) {

                $whereCol = $table['where'][$whereTable];
                $whereVal = implode(",", array_keys($this->key_map[$whereTable]));

                $sql   = "SELECT * FROM {$tname} WHERE {$whereCol} IN (?)";
                $stmnt = $this->exportDb->prepare($sql);
                $stmnt->execute([$whereVal]);
                // die(s($stmnt->debugDumpParams()));
                $tblRows = $stmnt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($tblRows)) {
                    echo "Inserting data from " . $tname . PHP_EOL;
                    $insertRows   = Util::CreateInsert($tblRows, $dependencies, $this->key_map);
                    $insertFields = Util::DeriveFields($tblRows[0]);

                    $newId = Util::InsertMultiple($this->importDb, $tname, $insertFields, $insertRows);
                    Util::UpdateDataMap($this->key_map, $tname, $tblRows, $newId);
                }

            }

        }

    }

    /**
     * @param $project_id
     */
    public function processImports($project_id)
    {
        $sql   = "SELECT * FROM imports WHERE project_id = ?";
        $stmnt = $this->exportDb->prepare($sql);
        $stmnt->execute([$project_id]);
        $org_rows     = $stmnt->fetchAll(PDO::FETCH_ASSOC);
        $insertRows   = Util::CreateInsert($org_rows, ['projects' => 'project_id'], $this->key_map);
        $insertFields = Util::DeriveFields($org_rows[0]);

        $newId = Util::InsertMultiple($this->importDb, 'imports', $insertFields, $insertRows);
        Util::UpdateDataMap($this->key_map, 'imports', $org_rows, $newId);

    }

}
