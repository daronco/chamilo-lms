<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\CourseBundle\Component\CourseCopy;

/**
 * Some functions to write a course-object to a zip-file and to read a course-
 * object from such a zip-file.
 * @author Bart Mollet <bart.mollet@hogent.be>
 * @package chamilo.backup
 *
 * @todo Use archive-folder of Chamilo?
 */
class CourseArchiver
{
    /**
     * Delete old temp-dirs
     */
    public static function clean_backup_dir()
    {
        $dir = api_get_path(SYS_ARCHIVE_PATH);
        if ($handle = @ opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != ".." &&
                    strpos($file, 'CourseArchiver_') === 0 &&
                    is_dir($dir . '/' . $file)
                ) {
                    rmdirr($dir . '/' . $file);
                }
            }
            closedir($handle);
        }
    }

    /**
     * Write a course and all its resources to a zip-file.
     * @return string A pointer to the zip-file
     */
    public static function write_course($course)
    {
        $perm_dirs = api_get_permissions_for_new_directories();

        CourseArchiver::clean_backup_dir();

        // Create a temp directory
        $tmp_dir_name = 'CourseArchiver_' . api_get_unique_id();
        $backup_dir = api_get_path(SYS_ARCHIVE_PATH) . $tmp_dir_name . '/';

        // All course-information will be stored in course_info.dat
        $course_info_file = $backup_dir . 'course_info.dat';
        $zip_dir = api_get_path(SYS_ARCHIVE_PATH);
        $user = api_get_user_info();
        $date = new \DateTime(api_get_local_time());
        $zip_file = $user['user_id'] . '_' . $course->code . '_' . $date->format('Ymd-His') . '.zip';
        $php_errormsg = '';
        $res = @mkdir($backup_dir, $perm_dirs);
        if ($res === false) {
            //TODO set and handle an error message telling the user to review the permissions on the archive directory
            error_log(__FILE__ . ' line ' . __LINE__ . ': ' . (ini_get('track_errors') != false ? $php_errormsg : 'error not recorded because track_errors is off in your php.ini') . ' - This error, occuring because your archive directory will not let this script write data into it, will prevent courses backups to be created', 0);
        }
        // Write the course-object to the file
        $fp = @fopen($course_info_file, 'w');
        if ($fp === false) {
            error_log(__FILE__ . ' line ' . __LINE__ . ': ' . (ini_get('track_errors') != false ? $php_errormsg : 'error not recorded because track_errors is off in your php.ini'), 0);
        }

        $res = @fwrite($fp, base64_encode(serialize($course)));
        if ($res === false) {
            error_log(__FILE__ . ' line ' . __LINE__ . ': ' . (ini_get('track_errors') != false ? $php_errormsg : 'error not recorded because track_errors is off in your php.ini'), 0);
        }

        $res = @fclose($fp);
        if ($res === false) {
            error_log(__FILE__ . ' line ' . __LINE__ . ': ' . (ini_get('track_errors') != false ? $php_errormsg : 'error not recorded because track_errors is off in your php.ini'), 0);
        }

        // Copy all documents to the temp-dir
        if (isset($course->resources[RESOURCE_DOCUMENT]) && is_array($course->resources[RESOURCE_DOCUMENT])) {
            foreach ($course->resources[RESOURCE_DOCUMENT] as $document) {
                if ($document->file_type == DOCUMENT) {
                    $doc_dir = $backup_dir . $document->path;
                    @mkdir(dirname($doc_dir), $perm_dirs, true);
                    if (file_exists($course->path . $document->path)) {
                        copy($course->path . $document->path, $doc_dir);
                    }
                } else {
                    @mkdir($backup_dir . $document->path, $perm_dirs, true);
                }
            }
        }

        // Copy all scorm documents to the temp-dir
        if (isset($course->resources[RESOURCE_SCORM]) && is_array($course->resources[RESOURCE_SCORM])) {
            foreach ($course->resources[RESOURCE_SCORM] as $document) {
                $doc_dir = dirname($backup_dir . $document->path);
                @mkdir($doc_dir, $perm_dirs, true);
                copyDirTo($course->path . $document->path, $doc_dir, false);
            }
        }

        // Copy calendar attachments.

        if (isset($course->resources[RESOURCE_EVENT]) && is_array($course->resources[RESOURCE_EVENT])) {
            $doc_dir = dirname($backup_dir . '/upload/calendar/');
            @mkdir($doc_dir, $perm_dirs, true);
            copyDirTo($course->path . 'upload/calendar/', $doc_dir, false);
        }

        // Copy Learning path author image.
        if (isset($course->resources[RESOURCE_LEARNPATH]) && is_array($course->resources[RESOURCE_LEARNPATH])) {
            $doc_dir = dirname($backup_dir . '/upload/learning_path/');
            @mkdir($doc_dir, $perm_dirs, true);
            copyDirTo($course->path . 'upload/learning_path/', $doc_dir, false);
        }

        // Copy announcements attachments.
        if (isset($course->resources[RESOURCE_ANNOUNCEMENT]) && is_array($course->resources[RESOURCE_ANNOUNCEMENT])) {
            $doc_dir = dirname($backup_dir . '/upload/announcements/');
            @mkdir($doc_dir, $perm_dirs, true);
            copyDirTo($course->path . 'upload/announcements/', $doc_dir, false);
        }

        // Copy work folders (only folders)
        if (isset($course->resources[RESOURCE_WORK]) && is_array($course->resources[RESOURCE_WORK])) {
            $doc_dir = dirname($backup_dir . '/upload/work/');
            @mkdir($doc_dir, $perm_dirs, true);
            // @todo: adjust to only create subdirs, but not copy files
            copyDirTo($course->path . 'upload/work/', $doc_dir, false);
        }

        // Zip the course-contents
        $zip = new \PclZip($zip_dir . $zip_file);
        $zip->create($zip_dir . $tmp_dir_name, PCLZIP_OPT_REMOVE_PATH, $zip_dir . $tmp_dir_name . '/');
        //$zip->deleteByIndex(0);
        // Remove the temp-dir.
        rmdirr($backup_dir);
        return '' . $zip_file;
    }

    /**
     * @param int $user_id
     * @return array
     */
    public static function get_available_backups($user_id = null)
    {
        $backup_files = array();
        $dirname = api_get_path(SYS_ARCHIVE_PATH) . '';
        if ($dir = opendir($dirname)) {
            while (($file = readdir($dir)) !== false) {
                $file_parts = explode('_', $file);
                if (count($file_parts) == 3) {
                    $owner_id = $file_parts[0];
                    $course_code = $file_parts[1];
                    $file_parts = explode('.', $file_parts[2]);
                    $date = $file_parts[0];
                    $ext = isset($file_parts[1]) ? $file_parts[1] : null;
                    if ($ext == 'zip' && ($user_id != null && $owner_id == $user_id || $user_id == null)) {
                        $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' . substr($date, 9, 2) . ':' . substr($date, 11, 2) . ':' . substr($date, 13, 2);
                        $backup_files[] = array(
                            'file' => $file,
                            'date' => $date,
                            'course_code' => $course_code
                        );
                    }
                }
            }
            closedir($dir);
        }

        return $backup_files;
    }

    /**
     * @param array $file
     * @return bool|string
     */
    public static function import_uploaded_file($file)
    {
        $new_filename = uniqid('') . '.zip';
        $new_dir = api_get_path(SYS_ARCHIVE_PATH);
        if (is_dir($new_dir) && is_writable($new_dir)) {
            move_uploaded_file($file, api_get_path(SYS_ARCHIVE_PATH).$new_filename);

            return $new_filename;
        }

        return false;
    }

    /**
     * Read a course-object from a zip-file
     * @param string $filename
     * @param boolean $delete Delete the file after reading the course?
     *
     * @return course The course
     * @todo Check if the archive is a correct Chamilo-export
     */
    public static function read_course($filename, $delete = false)
    {
        CourseArchiver::clean_backup_dir();
        // Create a temp directory
        $tmp_dir_name = 'CourseArchiver_' . uniqid('');
        $unzip_dir = api_get_path(SYS_ARCHIVE_PATH) . '' . $tmp_dir_name;
        @mkdir($unzip_dir, api_get_permissions_for_new_directories(), true);
        @copy(api_get_path(SYS_ARCHIVE_PATH) . '' . $filename, $unzip_dir . '/backup.zip');
        // unzip the archive
        $zip = new \PclZip($unzip_dir . '/backup.zip');
        @chdir($unzip_dir);
        $zip->extract(PCLZIP_OPT_TEMP_FILE_ON);
        // remove the archive-file
        if ($delete) {
            @unlink(api_get_path(SYS_ARCHIVE_PATH) . '' . $filename);
        }

        // read the course
        if (!is_file('course_info.dat')) {
            return new Course();
        }

        $fp = @fopen('course_info.dat', "r");
        $contents = @fread($fp, filesize('course_info.dat'));
        @fclose($fp);

        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Course', 'Course');

        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Announcement', 'Announcement');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Attendance', 'Attendance');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\CalendarEvent', 'CalendarEvent');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\CourseCopyLearnpath', 'CourseCopyLearnpath');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\CourseCopyTestcategory', 'CourseCopyTestcategory');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\CourseDescription', 'CourseDescription');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\CourseSession', 'CourseSession');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Document', 'Document');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Forum', 'Forum');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\ForumCategory', 'ForumCategory');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\ForumPost', 'ForumPost');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\ForumTopic', 'ForumTopic');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Glossary', 'Glossary');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\GradeBookBackup', 'GradeBookBackup');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Link', 'Link');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\LinkCategory', 'LinkCategory');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Quiz', 'Quiz');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\QuizQuestion', 'QuizQuestion');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\QuizQuestionOption', 'QuizQuestionOption');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\ScormDocument', 'ScormDocument');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Survey', 'Survey');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\SurveyInvitation', 'SurveyInvitation');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\SurveyQuestion', 'SurveyQuestion');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Thematic', 'Thematic');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\ToolIntro', 'ToolIntro');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Wiki', 'Wiki');
        class_alias('Chamilo\CourseBundle\Component\CourseCopy\Resources\Work', 'Work');

        $course = unserialize(base64_decode($contents));

        if (!in_array(
            get_class($course), ['Course', 'Chamilo\CourseBundle\Component\CourseCopy\Course'])
        ) {
            return new Course();
        }

        $course->backup_path = $unzip_dir;

        return $course;
    }
}
