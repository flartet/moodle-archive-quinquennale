<?php

require_once('./archive-config.php');
require_once('./moodle-rest.php');
require_once('./archive_fileutils.php');
require_once('./archive_courses.php');
require_once('./archive_mod_quiz.php');
require_once('./archive_mod_assign.php');
require_once('./archive_mod_folder.php');

$archiveExams = new ArchiveExams();
$archiveExams->run();

class ArchiveExams {
    // pour iris exams 2023
    public static $EXCLUDED_CATEGORIES = [889,882];
    // pour iris exams 2020
    // public static $EXCLUDED_CATEGORIES = [1, 3, 4, 12];

    public static $EXCLUDED_COURSES = [1];
    public static $EXCLUDED_MODULES = ['label', 'forum', 'resource'];

    public static $BULK_LOAD = 2;

    public static $GRADES_HEAD = 0.25;
    public static $LIMIT_POSITIVE_GRADES = 10;
    public static $GRADE_MINIMUM = 10;
    public static $GRADE_FILENAME = 'note.html';

    public static $DESCRIPTION_FILENAME = 'description.html';
    public static $QUIZ_ATTEMPT_FILENAME = 'devoir.html';

    public static $ASSIGN_INTRO_FILENAME = 'intro.html';
    public static $ASSIGN_SUBMISSION_FILENAME = 'devoir';

    public static $MODULE_DEFAULT_NAME = 'module-default';

    public static $MODULES_EXAMEN = ['quiz', 'assign'];

    /** @var moodle_rest $moodle_rest */
    protected $moodle_rest;

    protected $allCategories = null;

    /** @var archive_courses $ac */
    protected $ac;
    /** @var archive_mod_folder $amf */
    protected $amf;
    /** @var archive_mod_assign $ama */
    protected $ama;
    /** @var archive_mod_quiz $amq */
    protected $amq;

    public function run() {

        $this->moodle_rest = new moodle_rest(URL, WSTOKEN, false);

        $this->ac = new archive_courses($this->moodle_rest);
        $this->amf = new archive_mod_folder($this->moodle_rest);
        $this->ama = new archive_mod_assign($this->moodle_rest);
        $this->amq = new archive_mod_quiz($this->moodle_rest);

        $this->allCategories = $this->ac->loadAllCategories();
        $courses = $this->ac->get_all_courses();
        if (isset($courses['errorcode'])) {
            die($courses['errorcode'].': '.$courses['message']);
        }
        foreach ($courses as $course) {
            echo 'Parcours de '.$course['fullname'].' ('.$course['id'].") ...\n";
            $coursePath = $this->getCoursePath($course);
            $users = $this->ac->get_enrolled_users($course['id']);
            echo "\tUtilisateurs inscrits : ".count($users)."\n";
            $courseContents = $this->ac->get_course_contents($course['id']);
            if (! $this->courseHasExam($courseContents)) {
                echo "\tPas de contenu pour ce cours.\n";
                continue;
            }
            // dans le cas où un cours possède du contenu, on sauvegarde tout car tous les cas de figure sont possible 
            // quant à la fourniture des consignes
            $sectionNumber = 0;
            foreach ($courseContents as $section) {
                $sectionPath = $coursePath.str_pad($sectionNumber, 2, '0', STR_PAD_LEFT).'_'.$section['name'].DIRECTORY_SEPARATOR;
                echo "\tParcours de la section (".$section['name'].")\n";
                $moduleNumber = 0;
                foreach ($section['modules'] as $module) {
                    $modulePath = $sectionPath.str_pad($moduleNumber, 2, '0', STR_PAD_LEFT).'_'.($module['name'] ?? self::$MODULE_DEFAULT_NAME);
                    if ($module['modname'] == 'quiz') {
                        $htmlQuizAttempt = $this->getRandomQuizAttempt($module, $users);
                        echo "\t\tArchivage du (".$module['modname'].'-'.$module['instance'].') du nom ('.$module['name'].")\n";
                        $this->saveModuleDescription($module, $modulePath);
                        archive_fileutils::writeFile($modulePath, self::$QUIZ_ATTEMPT_FILENAME, $htmlQuizAttempt, true);
                    }
                    else if ($module['modname'] == 'assign') {
                        echo "\t\tArchivage du (".$module['modname'].'-'.$module['instance'].') du nom ('.$module['name'].")\n";
                        $submissions = $this->ama->get_submissions($module['instance']);
                        if (isset($submissions['errorcode'])) {
                            echo "\t\t".$submissions['errorcode'].': '.$submissions['message']."\n";
                        }
                        else {
                            $submissions = (array_pop($submissions['assignments']))['submissions'];
                            echo "\t\t".'Devoirs rendus : ('.count($submissions).")\n";
                            if (count($submissions) > 0) {
                                $this->saveModuleDescription($module, $modulePath);
                                $this->saveAssignMetadata($module['instance'], $course['id'], $modulePath);
                                $submission = $this->getRandomAssignSubmission($module['instance'], $submissions);
                                $this->saveSubmission($submission, $modulePath);
                                echo "\t\tDépôt de l'utilisateur (".$submission['lastattempt']['submission']['userid'].')'."\n";
                            }
                            else {
                                echo "\t\tPas de submission pour ".$module['name']."\n";
                            }
                        }
                    }
                    else if ($module['modname'] == 'folder') {
                        $this->saveFolder($module, $modulePath);
                    }
                    else if ($module['modname'] == 'label') {
                        $this->saveLabel($module, $modulePath);
                    }
                    else if ($module['modname'] == 'resource') {
                        $this->saveResource($module, $modulePath);
                    }
                    else {
                        echo "Module ".$module['modname']." non géré\n";
                    }
                    $moduleNumber++;
                }
                $sectionNumber++;
            }
        }
    }

    /**
     * Le cours a-t-il du contenu de devoir
     * 
     * @param array $courseContents tableau des sections d'un cours
     * @return bool true si un module de type d'examen est trouvé, false sinon
     */
    public function courseHasExam($courseContents) {
        foreach ($courseContents as $courseContent) {
            foreach ($courseContent['modules'] as $module) {
                if (in_array($module['modname'], self::$MODULES_EXAMEN)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function saveModuleDescription($module, $directory) {
        if ((! empty($module['description'])) && (strlen(trim($module['description'])) > 0)) {
            archive_fileutils::ensureDirectory($directory);
            return archive_fileutils::writeFile($directory, self::$DESCRIPTION_FILENAME, $module['description'], true);
        }
        return false;
    }

    /**
     * Pour un quiz donné avec les utilisateurs donnés, récupère un attempt existant au hasard
     */
    public function getRandomQuizAttempt($module, $users) {
        $positiveResultsCount = 0;
        $results = [];
        foreach ($users as $user) {
            $ubg = $this->amq->get_user_best_grade($module['instance'], $user['id']);
            if (isset($ubg['errorcode'])) {
                echo "\t\t".$ubg['exception']."/".$ubg['errorcode']."/".$ubg['message']."\n";
            }
            else {
                if (($ubg['hasgrade']) && ($ubg['grade'] > self::$GRADE_MINIMUM)) {
                    $positiveResultsCount++;
                    $results[$ubg['grade']] = $user['id'];
                }
                if ($positiveResultsCount > self::$LIMIT_POSITIVE_GRADES) {
                    echo "\t\t".self::$LIMIT_POSITIVE_GRADES. ' trouvés après '.$positiveResultsCount." tentatives.\n";
                    break;
                }
            }
        }
        if (count($results) == 0) {
            echo "\t\tErreur dans le parcours du quiz (".$module['instance']."), pas de résultat.\n";
            return null;
        }
        $randomUser = array_pop($results);
        $attempts = $this->amq->get_user_attempts($module['instance'], $randomUser);
        $attempt = array_pop($attempts['attempts']);
        $attempt = $this->amq->get_attempt_review($attempt['id']);
        $html = '<h1>Note globale : '.round($attempt['grade'], 2).'</h1>';
        foreach ($attempt['questions'] as $question) {
            $html .= $question['html']."\n";
        }
        return $html;
    }

    /**
     * Récupère au hasard une soumission de devoir noté
     * 
     * @param archive_mod_assign $this->ama le gestionnaire de mod_assign
     * @param int $assignId l'identifiant de l'assignment courant
     * @param array $submissions les soumissions déjà récupérées via get_submissions
     */
    public function getRandomAssignSubmission($assignid, $submissions) {
        shuffle($submissions); // afin d'avoir un peu d'aléatoire

        foreach ($submissions as $submission) {
            if ($submission['gradingstatus'] != 'notgraded') {
                $subStatus = $this->ama->get_submission_status($assignid, $submission['userid']);
                if (isset($subStatus['feedback'])) {
                    if (intval($subStatus['feedback']['grade']['grade']) > self::$GRADE_MINIMUM) {
                        return $subStatus;
                    }
                }   
            }
        }
        // aïe, il n'y a pas eu de notes, oups, donc là on prend au pif sans note
        echo "\t\tPas de dépôt avec note ! Renvoi d'un document au hasard.\n";
        $submission = array_pop($submissions);
        $subStatus = $this->ama->get_submission_status($assignid, $submission['userid']);
        // très rares cas, le ws de dessus ne renvoie rien, on reconstruit avec les infos qu'on a
        // mais on garde l'utilisation de son retour pour le cas général déjà codé et fonctionnel
        if (! array_key_exists('lastattempt', $subStatus)) {
            $subStatus['lastattempt'] = [];
            $subStatus['lastattempt']['submission'] = $submission;
            $subStatus['lastattempt']['gradingstatus'] = $submission['gradingstatus'];
            if (array_key_exists('feedback', $submission)) {
                $subStatus['feedback'] = $submission['feedback'];   
            }
        }
        return $subStatus;
        
    }

    public function saveAssignMetadata($assignid, $courseid, $directory) {
        // c'est un peu contre-productif mais d'autres fonction pour avoir les détails avec le sujet que cette fonction API
        $assignments = $this->ama->get_assignments_from_courseid($courseid);
        $assignments = $assignments['courses'][0]['assignments'];
        foreach ($assignments as $assignment) {
            if ($assignment['id'] == $assignid) {
                // l'intro contenant souvent les explications
                if (strlen($assignment['intro']) > 0) {
                    archive_fileutils::writeFile($directory, self::$ASSIGN_INTRO_FILENAME, $assignment['intro'], true);
                }
                // les documents attachés, parfois le sujet
                foreach ($assignment['introattachments'] as $attachment) {
                    archive_fileutils::writeFile($directory, $attachment['filename'], $this->moodle_rest->getFile($attachment['fileurl']), true);
                }
                break;
            }
        }
    }

    /**
     * Enregistre les détails de la remise de devoir dans le répertoire de sauvegarde, au bon endroit
     * 
     * @param archive_mod_assign $this->ama le gestionnaire des dépôts de devoir
     * @param array $submission le devoir
     * @param string $directory le répertoire où sauvegarder
     * @return void
     */
    public function saveSubmission($submission, $directory) {
        foreach ($submission['lastattempt']['submission']['plugins'] as $plugin) {
            if (in_array($plugin['type'], ['file', 'onlinetext'])) {
                foreach ($plugin['fileareas'] as $filearea) {
                    if (in_array($filearea['area'], ['submission_files', 'submissions_onlinetext'])) {
                        $iFile = 1;
                        foreach ($filearea['files'] as $file) {
                            $extension = archive_fileutils::getExtension($file['filename']);
                            archive_fileutils::writeFile($directory, self::$ASSIGN_SUBMISSION_FILENAME.'_'.$iFile.'.'.$extension, $this->moodle_rest->getFile($file['fileurl']), true);
                            $iFile++;
                        }
                    }
                }
            }
            $note = 'Pas de note, juste des dépôts pour ce devoir.';
            if ($submission['lastattempt']['gradingstatus'] != 'notgraded') {
                $note = $submission['feedback']['gradefordisplay'];
            }
            archive_fileutils::writeFile($directory, self::$GRADE_FILENAME, $note, true);
        }
    }

    /**
     * Sauvegarde d'un module folder dans le répertoire donné
     */
    public function saveFolder($module, $modulepath) {
        archive_fileutils::ensureDirectory($modulepath);
        archive_fileutils::writeFile($modulepath, self::$DESCRIPTION_FILENAME, $module['description'], true);
        foreach ($module['contents'] as $folderContent) {
            if ($folderContent['type'] == 'file') {
                archive_fileutils::writeFile($modulepath, $folderContent['filename'], $this->moodle_rest->getFile($folderContent['fileurl']), true);
            }
        }
    }

    public function saveLabel($module, $modulepath) {
        archive_fileutils::ensureDirectory($modulepath);
        archive_fileutils::writeFile($modulepath, self::$DESCRIPTION_FILENAME, $module['description'], true);
    }

    public function saveResource($module, $modulepath) {
        archive_fileutils::ensureDirectory($modulepath);
        foreach ($module['contents'] as $content) {
            if ($content['type'] == 'file') {
                archive_fileutils::writeFile($modulepath, $content['filename'], $this->moodle_rest->getFile($content['fileurl']), true);
            }
        }
    }

    /**
     * Calcule le chemin de répertoire de l'archive pour un cours donné
     * @param object $course le cours
     * 
     * @return string le chemin absolu calculé
     */
    public function getCoursePath($course) {
        $path = ARCHIVE_DIRNAME.DIRECTORY_SEPARATOR;
        $arCategories = preg_split('/\//', $this->allCategories[$course['categoryid']]['path'], -1 , PREG_SPLIT_NO_EMPTY);
        foreach ($arCategories as $idCat) {
            $path .= $this->allCategories[$idCat]['name'].DIRECTORY_SEPARATOR;
        }
        return $path.$course['fullname'].DIRECTORY_SEPARATOR;

    }
}

?>
