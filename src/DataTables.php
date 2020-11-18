<?php
namespace Dash\Exporter;

/**
 * Provides structure of tables to be used in export
 */
class DataTables
{
    /**
     * @var tables to return
     */
    public static $tables = [

        "studio_projects"            => [
            'dependencies' => [
                "projects" => "project_id",
                "studios"  => "studio_id",
            ],
            "where"        => ["projects" => "project_id"],
        ],
        "versions"                   => [
            'dependencies' => [
                'projects' => 'project_id',
            ],
            'where'        => ['projects' => 'project_id'],
        ],
        "units"                      => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "setups"                     => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "scenes"                     => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "tapes"                      => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "record_type"                => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "records"                    => [
            'dependencies' => [
                'projects'    => 'project_id',
                'record_type' => 'record_type_id',
                'imports'     => 'import_id',
            ],
            'where'        => ['projects' => 'project_id'],
        ],
        "cont_note_pages"            => [
            'dependencies' => [
                'units'   => 'unit_id',
                'imports' => 'import_id',
            ],
            'where'        => ['units' => 'unit_id'],
        ],
        "line_script_pages"          => [
            'dependencies' => [
                'units'   => 'unit_id',
                'imports' => 'import_id'],
            'where'        => ['units' => 'unit_id'],
        ],
        "related_files"              => [
            'dependencies' => [
                'projects' => 'project_id',
                'records'  => 'record_id',
            ],
            'where'        => ['projects' => 'project_id'],
        ],
        "record_tags"                => [
            'dependencies' => ['records' => 'record_id'],
            'where'        => ['records' => 'record_id'],
        ],
        "stock_categories"           => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "Metadata_RecordType_assn"   => [
            'dependencies' => [
                'record_type' => 'record_type_id',
                'imports'     => 'import_id',
            ],
            'where'        => ['record_type' => 'record_type_id'],
        ],
        "notes"                      => [
            'dependencies' => [
                'projects' => 'project_id',
                'records'  => 'record_id',
            ],
            'where'        => ['projects' => 'project_id'],
        ],
        "Metadata_Field_Map"         => [
            'dependencies' => ['Metadata_RecordType_assn' => 'Metadata_Table_id'],
            'where'        => ['Metadata_RecordType_assn' => 'id'],
        ],
        "scene_cont_notes"           => [
            'dependencies' => [
                'scenes'          => 'scene_id',
                'imports'         => 'import_id',
                'cont_note_pages' => 'note_id',
            ],
            'where'        => ['notes' => 'note_id'],
        ],
        "version_scenes"             => [
            'dependencies' => [
                'versions' => 'version_id',
                'scenes'   => 'scene_id',
                'imports'  => 'import_id',
            ],
            'where'        => ['versions' => 'version_id'],
        ],
        "scene_setups"               => [
            'dependencies' => [
                'scenes'  => 'scene_id',
                'setups'  => 'setup_id',
                'imports' => 'import_id',
            ],
            'where'        => ['scenes' => 'scene_id'],
        ],
        "scene_lined_scripts"        => [
            'dependencies' => [
                'scenes'            => 'scene_id',
                'imports'           => 'import_id',
                'line_script_pages' => 'script_id',
            ],
            'where'        => ['scenes' => 'scene_id'],
        ],
        "stock_records"              => [
            'dependencies' => [
                'stock_categories' => 'cat_id',
                'records'          => 'record_id',
            ],
            'where'        => ['scenes' => 'record_id'],
        ],
        "tape_scenes"                => [
            'dependencies' => [
                'tapes' => 'tape_id',
                'scene' => 'scene_id',
            ],
            'where'        => ['tapes' => 'tape_id'],
        ],
        "tape_record"                => [
            'dependencies' => [
                'tapes'   => 'tape_id',
                'records' => 'record_id',
            ],
            'where'        => ['records' => 'record_id'],
        ],
        "scene_records"              => [
            'dependencies' => [
                'scenes'  => 'scene_id',
                'records' => 'record_id',
                'imports' => 'import_id',
            ],
            'where'        => ['records' => 'record_id'],
        ],
        "setup_records"              => [
            'dependencies' => [
                'setups'  => 'setup_id',
                'records' => 'record_id',
                'imports' => 'import_id',
            ],
            'where'        => ['records' => 'record_id'],
        ],
        "transcripts"                => [
            'dependencies' => [
                'versions' => 'version_id',
                'imports'  => 'import_id',
            ],
            'where'        => ['versions' => 'version_id'],
        ],
        "record_import_field_map"    => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "ignore_keywords"            => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "record_data_field_viewable" => [
            'dependencies' => ['projects' => 'project_id','record_type' => 'record_type_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "project_info"               => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],
        "ltsa"                       => [
            'dependencies' => ['projects' => 'project_id'],
            'where'        => ['projects' => 'project_id'],
        ],

    ];

}
