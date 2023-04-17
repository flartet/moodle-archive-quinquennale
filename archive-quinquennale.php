#!/bin/env php

<?php

define('CLI_SCRIPT', true);
define('wstoken', '662eebfcb372a285a93a90e4bed8a80a');

$MOODLE_PATH = "/var/www/moodle-exams-git/";
require_once($MOODLE_PATH."config.php");
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->dirroot .'/mod/quiz/lib.php');
require_once($CFG->dirroot .'/mod/assign/lib.php');
require_once($CFG->dirroot .'/mod/assign/externallib.php');

$ac = new ArchiveExams();
$ac->run();

class ArchiveExams {
    public static $ARCHIVE_DIRNAME = "archive-quinquennale";

    public static $EXCLUDED_CATEGORIES = [1, 889];
    public static $EXCLUDED_COURSES = [1];
    public static $EXCLUDED_MODULES = ['label', 'forum', 'resource'];
    public static $UMASK_DIRECTORIES = 770;
    public static $UMASK_FILES = 660;

    public static $BULK_LOAD = 2;

    // récupéré sur la doc : https://docs.moodle.org/dev/index.php?title=Overview_of_the_Moodle_question_engine&modqueued=1
    public static $SQL_QUIZ_ATTEMPT_DETAIL = 
        "SELECT qasd.id,quiza.sumgrades as \"Note globale\",
        qa.maxmark as \"Note max à la question\",
        qa.minfraction,
        qa.flagged,
        qas.sequencenumber,
        case when qas.state = 'gradedright' then 'Juste'
            when qas.state = 'gradedwrong' then 'Faux'
            when qas.state = 'gradedpartial' then 'Partiellement juste'
        end as \"Validité réponse\",
        qas.fraction as \"Note à la question\",
        timestamptz 'epoch' + qas.timecreated * interval '1 second' as \"Date réponse\",
        -- or FROM_UNIXTIME(qas.timecreated) if you are on MySQL.
        qasd.value,
        qa.questionsummary as \"Question: réponses\",
        qa.rightanswer as \"Bonne réponse\",
        qa.responsesummary as \"Réponse étudiant\"
        FROM mdl_quiz_attempts quiza
        JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid
        JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id
        JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id
        LEFT JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid = qas.id
        WHERE quiza.id = :quizattemptid
        and qasd.name = '-finish'
        ORDER BY quiza.userid, quiza.attempt, qa.slot, qas.sequencenumber, qasd.name";

    public static $GRADES_HEAD = 0.25;

    public function run() {
        global $DB, $CFG;

        // chargement des types de modules
        $modulesById = $DB->get_records('modules', null, 'name', 'id,name');
        $categoriesById = $DB->get_records('course_categories', null, 'name', 'id,name,path');

        $currentPath = '.';

        $current_load = 0;
        $nbCourses = 0;
        do {
            $courses = $DB->get_records('course', null, 'id', '*', $current_load, self::$BULK_LOAD);
            $nbCourses = count($courses);
            while ($course = array_shift($courses)) {
                // on écarte les exclusions explicites
                if ((! in_array($course->category, self::$EXCLUDED_CATEGORIES)) && (! in_array($course->id, self::$EXCLUDED_COURSES))) {
                    $currentPath = $this->getPath($categoriesById, $course);
                    echo $currentPath."\n";
                    $modules = $DB->get_records('course_modules', ['course' => $course->id]);
                    foreach ($modules as $module) {
                        if (in_array($module->name, self::$EXCLUDED_MODULES)) {
                            continue;
                        }
                        // $context = $DB->get_records('context', ['instanceid' => $module->instance, 'contextlevel' => CONTEXT_MODULE]);
                        $modName = $modulesById[$module->module]->name;
                        if ($modName == 'quiz') {
                            $quiz = $DB->get_records('quiz', ['id' => $module->instance]);
                            $quiz = array_shift($quiz);
                            $quizAttempt = $this->quizExtractOneAttempt($quiz);
                            $csv = $this->arrayOfObjectsToCSV($quizAttempt);
                            echo "Création de $currentPath ...\n";
                            mkdir($currentPath, self::$UMASK_DIRECTORIES, true);
                            $filename = $quiz->name.'.csv';
                            echo "\tÉcriture de $filename ...\n";
                            file_put_contents($currentPath.$filename, $csv);
                            chmod($currentPath.$filename, self::$UMASK_FILES);
                        }
                        elseif ($modName == 'assign') {
                            $assign = $DB->get_records('assign', ['id' => $module->instance]);
                            $assign = array_shift($assign);
                            $assignGrades = assign_get_user_grades($assign);
                            $selectedAssignGrade = $this->getRandomGoodGrade($assignGrades,true);
                            // var_dump($assign->id, $selectedAssignGrade, $assign);die;
                            // var_dump(assign_get_file_areas($course, $module, ))
                            // $asf = new assign_submission_file($assign, 'files');
                            $submissions = mod_assign_external::get_submissions([$assign->id]);
                            var_dump($submissions);die;
                        }
                        else {
                            echo "$modName non traité\n";
                        }
                    }
                }
            }
            $current_load += self::$BULK_LOAD;
        } while ($nbCourses > 0);
    }

    public function quizExtractOneAttempt($quiz) {
        global $DB;
        // le quiz a des notes
        if (quiz_has_grades($quiz)) {
            $quizGrades = quiz_get_user_grades($quiz);
            $quizSelectedGrade = $this->getRandomGoodGrade($quizGrades);
            $userAttempt = array_shift(quiz_get_user_attempts($quiz->id, $quizSelectedGrade->userid));
            $attemptDetails = $DB->get_records_sql(self::$SQL_QUIZ_ATTEMPT_DETAIL, ['quizattemptid' => $userAttempt->id]);
            return $attemptDetails;
        }
        return null;
    }

    public function getAssignRandom($assign) {
        global $DB;
        $assignGrades = assign_get_user_grades($assign);
        if (count($assignGrades) > 0) {
            $assignGrades = assign_get_user_grades($assign);
            $arAssignGrades = [];
            foreach ($assignGrades as $ag) {
                $arAssignGrades[$ag->rawgrade] = $ag;
            }
            ksort($arAsssignGrades);
            $userAttempt = array_shift(quiz_get_user_attempts($quiz->id, $quizSelectedGrade->userid));
            $attemptDetails = $DB->get_records_sql(self::$SQL_QUIZ_ATTEMPT_DETAIL, ['quizattemptid' => $userAttempt->id]);
            return $attemptDetails;
        }
        return null;
    }

    public function arrayOfObjectsToCSV($arObjs) {
        $csv = '"'.join('","',array_keys((array)array_slice($arObjs, 1)[0])).'"';
        foreach ($arObjs as $obj) {
            $obj = (array)$obj;
            $csv .= "\n";
            array_walk($obj, function(&$value) use (&$csv) {
                $csv .= '"'.str_replace('"', '""', $value).'",';
            });
            $csv = chop($csv, ',');
        }
        return $csv;
    }

    public function getPath($categories, $course) {
        global $CFG;

        $path = $CFG->dataroot.DIRECTORY_SEPARATOR.self::$ARCHIVE_DIRNAME.DIRECTORY_SEPARATOR;
        $arCategories = preg_split('/\//', $categories[$course->category]->path, -1 , PREG_SPLIT_NO_EMPTY);
        foreach ($arCategories as $idCat) {
            $path .= $categories[$idCat]->name.DIRECTORY_SEPARATOR;
        }
        return $path.$course->fullname.DIRECTORY_SEPARATOR;
    }

    public function getRandomGoodGrade($moduleGrades) {
        $arModuleGrades = [];
        foreach ($moduleGrades as $moduleGrade) {
            $arModuleGrades[$moduleGrade->rawgrade] = $moduleGrade;
        }
        ksort($arModuleGrades);
        $arSelectedGrades = array_slice($arModuleGrades, floor(count($arModuleGrades)*(1-self::$GRADES_HEAD)));
        shuffle($arSelectedGrades);
        return array_pop($arSelectedGrades);
    }
}

?>