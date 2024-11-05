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
    public function get_all_courses($excluded_courses, $categories) {
        // $courses = $this->moodle_rest->fetchJson('core_course_get_courses', ['options' => ['ids' => [4986]]]);
        $courses = $this->moodle_rest->fetchJson('core_course_get_courses');
        $courses = json_decode($courses, true);
        foreach ($courses as $key => $course) {
            if ((in_array($course['id'], $excluded_courses)) || (!array_key_exists($course['categoryid'], $categories))) {
                unset($courses[$key]);
                echo "Écartement de ".$course['fullname']." sans catégorie chargée (".$course['categoryid'].").\n";
            }
        }
        die;
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
            if (in_array($category['id'], $excludedCategories)) {
                $excludedCategories = array_merge($excludedCategories, $this->getSonsRecursive($categories, $category['id']));
            }
            $categoriesById[$category['id']] = $category;
        }
        foreach ($excludedCategories as $excludedCategory) {
            echo "Écartement de la catégorie ".$categoriesById[$excludedCategory]['name']."\n";
            unset($categoriesById[$excludedCategory]);
        }
        return $categoriesById;
    }

    public function getSonsRecursive($categories, $catId, $sons = []) {
        foreach ($categories as $category) {
            if ($category['parent'] == $catId) {
                array_push($sons, $category['id']);
                $sons = array_merge($this->getSonsRecursive($categories, $category['id'], $sons));
            }
        }
        return $sons;

    }

}

?>