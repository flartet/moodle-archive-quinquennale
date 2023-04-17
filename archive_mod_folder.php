<?php

require_once('./moodle-rest.php');

/**
 * Gère toutes les manipulations liées à l'archivage des modules folder
 */
class archive_mod_folder {

    /** @var moodle_rest le wrapper d'appel au WS Moodle */
    protected $moodle_rest;

    public function __construct($moodle_rest) {        
        $this->moodle_rest = $moodle_rest;
    }
    
    /**
     * Récupère tous les folder présents dans une section de cours donnée
     * 
     * @param int $courseid identifiant du cours
     * @param int $sectionid identifiant de la section dans laquelle chercher
     * @return array représentation de la section avec ses modules folder s'il y en a, s'il n'y en a pas, la clé modules contient un tableau vide
     */
    public function get_folders_in_section($courseid, $sectionid) {
        $courseContents = $this->moodle_rest->fetchJson('core_course_get_contents', [
            'courseid' => $courseid,
            'options' => [
                ['name' => 'includestealthmodules', 'value' => true],
                ['name' => 'modname', 'value' => 'folder'],
                ['name' => 'sectionid', 'value' => $sectionid]
            ]
        ]);
        return json_decode($courseContents, true);
    }

}

?>