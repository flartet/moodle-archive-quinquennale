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
     * @param array $excluded_courses le tableau des cours à exclure (en config)
     * @param array $categories un tableau de catégories tel que renvoyé par getAllCategories, indexé par id
     * @return array les cours
     */
    public function get_all_courses($excluded_courses, $categories) {
        // si on veut faire un test sur un seul cours, on peut forcer ici
        // $courses = $this->moodle_rest->fetchJson('core_course_get_courses', ['options' => ['ids' => [5968]]]);
        $courses = $this->moodle_rest->fetchJson('core_course_get_courses');
        $courses = json_decode($courses, true);
        foreach ($courses as $key => $course) {
            if ((in_array($course['id'], $excluded_courses)) || (!array_key_exists($course['categoryid'], $categories))) {
                unset($courses[$key]);
                echo "Écartement de ".$course['fullname']." sans catégorie chargée (".$course['categoryid'].").\n";
            }
        }
        return $courses;
    }

    /**
     * Récupère les utilisateurs inscrits dans le cours
     * 
     * @param int $courseid l'id du cours
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
     * @param array $excludedCategories les ids des catégories à supprimer
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

    /**
     * Récupération récursive des catégories enfant d'une catégorie
     *
     * @param array $categories le tableau des catégories indexé par id avec au moins id, parent
     * @param int $catId l'id de la catégorie
     * @param array $sons tableau utile à la récursion
     * @return array tous les id des descendants de la catégorie
     */
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