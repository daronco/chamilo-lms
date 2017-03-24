<?php
/* For licensing terms, see /license.txt */

/**
 * Class Annotation
 * Allow instanciate an object of type HotSpot extending the class question
 * @author Angel Fernando Quiroz Campos <angel.quiroz@beeznest.com>
 * @package chamilo.
 */
class Annotation extends Question
{
    public static $typePicture = 'annotation.png';
    public static $explanationLangVar = 'Annotation';

    /**
     * Annotation constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = ANNOTATION;
    }

    public function display()
    {
    }

    /**
     * @param FormValidator $form
     * @param int $fck_config
     */
    public function createForm(&$form, $fck_config = 0)
    {
        parent::createForm($form, $fck_config);

        if (isset($_GET['editQuestion'])) {
            $form->addButtonUpdate(get_lang('ModifyExercise'), 'submitQuestion');

            return;
        }

        $form->addElement(
            'file',
            'imageUpload',
            array(
                Display::img(
                    Display::return_icon('annotation.png', null, null, ICON_SIZE_BIG, false, true)
                ),
                get_lang('UploadJpgPicture'),
            )
        );

        $form->addButtonSave(get_lang('GoToQuestion'), 'submitQuestion');
        $form->addRule('imageUpload', get_lang('OnlyImagesAllowed'), 'filetype', array('jpg', 'jpeg', 'png', 'gif'));
        $form->addRule('imageUpload', get_lang('NoImage'), 'uploadedfile');
    }

    /**
     * @param FormValidator $form
     * @param null $objExercise
     * @return null|false
     */
    public function processCreation($form, $objExercise = null)
    {
        $fileInfo = $form->getSubmitValue('imageUpload');
        $courseInfo = api_get_course_info();

        parent::processCreation($form, $objExercise);

        if (!empty($fileInfo['tmp_name'])) {
            $this->uploadPicture($fileInfo['tmp_name'], $fileInfo['name']);

            $documentPath = api_get_path(SYS_COURSE_PATH).$courseInfo['path'].'/document';
            $picturePath = $documentPath.'/images';

            // fixed width ang height
            if (!file_exists($picturePath.'/'.$this->picture)) {
                return false;
            }

            $this->resizePicture('width', 800);
            $this->save();

            return true;
        }
    }

    /**
     * @param FormValidator $form
     */
    function createAnswersForm($form)
    {
        // nothing
    }

    /**
     * @param FormValidator $form
     */
    function processAnswersCreation($form)
    {
        // nothing
    }
}
