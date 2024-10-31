<?php

require_once('./moodle-rest.php');

/**
 * Gère l'accès aux différents éléments des cours
 */
class archive_courses {

    /** @var moodle_rest le wrapper d'appel au WS Moodle */
    protected $moodle_rest;

    public function __construct($moodle_rest) {        
        $this->moodle_rest = $moodle_rest;
    }
    
    /**
     * Récupère tous les cours avec leurs caractéristiques
     * 
     * @return array les cours
     */
    public function get_all_courses() {
        // $courses = $this->moodle_rest->fetchJson('core_course_get_courses', ['options' => ['ids' => [4986]]]);
        $courses = $this->moodle_rest->fetchJson('core_course_get_courses');
        $courses = json_decode($courses, true);
        return $courses;
    }

    /**
     * Récupère les utilisateurs inscrits dans le cours
     * 
     * @return array les utilisateurs inscrits 
     */
    public function get_enrolled_users($courseid) {
        $users = $this->moodle_rest->fetchJson('core_enrol_get_enrolled_users', ['courseid' => $courseid, 'options' => [
            ['name' => 'userfields', 'value' => 'id,username']
        ]]);
        return json_decode($users, true);
    }

    /**
     * Récupère toutes les sections d'un cours et leurs modules/ressources
     * @param int $courseid l'id du cours
     * 
     * @return array $courses les sections des cours et leur contenu
     */
    public function get_course_contents($courseid) {
        $courseContents = $this->moodle_rest->fetchJson('core_course_get_contents', ['courseid' => $courseid]);
        return json_decode($courseContents, true);
    }

    /**
     * Récupère toutes les catégories
     * 
     * @return array toutes les catégories indexées par leur id
     */
    public function loadAllCategories($excludedCategories = array()) {
        $categories = $this->moodle_rest->fetchJson('core_course_get_categories');
        $categories = (array)json_decode($categories, true);
        $categoriesById = [];
        foreach ($categories as $category) {
            if (! in_array($category['id'], $excludedCategories)) {
                $categoriesById[$category['id']] = $category;
            }
        }
        return $categoriesById;
    }
}

?>